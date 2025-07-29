// Globale Variable, um die Daten des Wählerverzeichnisses zu speichern
let voterData = [];

document.getElementById('voterForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const stichtag = document.getElementById('stichtag').value;
    const wahl = document.getElementById('wahl').value;
    const resultsDiv = document.getElementById('voterResults');

    resultsDiv.innerHTML = '<p>Lade Wählerverzeichnis...</p>'; 
    // Button deaktivieren, um Mehrfachklicks während des Ladens zu verhindern
    document.getElementById('createButton').disabled = true;
    document.getElementById('downloadButton').disabled = true;


    const formData = new FormData();
    formData.append('stichtag', stichtag);
    formData.append('wahl', wahl);

    fetch('Abfragen/fetch_data.php', { 
        method: 'POST', 
        body: formData 
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.statusText + ' (' + response.status + ')');
        }
        return response.json(); 
    })
    .then(data => {
        // Buttons wieder aktivieren
        document.getElementById('createButton').disabled = false;
        document.getElementById('downloadButton').disabled = false;

        if (data.error) {
            resultsDiv.innerHTML = '<p style="color: red;">Fehler: ' + data.error + '</p>';
            voterData = []; // Daten leeren bei Fehler
            return;
        }

        // Speichern der geladenen Daten in der globalen Variable
        voterData = data; 

        // Darstellung der JSON (bleibt unverändert)
        if (voterData.length > 0) {
            let tableHtml = '<h2>Wählerverzeichnis</h2><table><thead><tr>';
            
            Object.keys(voterData[0]).forEach(key => {
                tableHtml += `<th>${key}</th>`;
            });
            tableHtml += '</tr></thead><tbody>';

            voterData.forEach(row => {
                tableHtml += '<tr>';
                Object.values(row).forEach(value => {
                    tableHtml += `<td>${value}</td>`;
                });
                tableHtml += '</tr>';
            });
            tableHtml += '</tbody></table>';
            resultsDiv.innerHTML = tableHtml;
        } else {
            resultsDiv.innerHTML = '<p>Keine Wähler für die ausgewählten Kriterien gefunden.</p>';
        }
    })
    .catch(error => {
        // Buttons wieder aktivieren
        document.getElementById('createButton').disabled = false;
        document.getElementById('downloadButton').disabled = false;

        console.error('Fetch error:', error);
        resultsDiv.innerHTML = '<p style="color: red;">Ein unerwarteter Fehler ist aufgetreten: ' + error.message + '</p>';
        voterData = []; // Daten leeren bei Fehler
    });
});


// Neuer Event Listener für den Download-Button
document.getElementById('downloadButton').addEventListener('click', function(event) {
    event.preventDefault();

    // Überprüfen, ob Daten vorhanden sind
    if (voterData.length === 0) {
        alert('Bitte generieren Sie zuerst das Wählerverzeichnis mit "Erstellen", bevor Sie es herunterladen.');
        return;
    }

    const stichtag = document.getElementById('stichtag').value;
    const wahl = document.getElementById('wahl').value;

    if (!stichtag || !wahl) {
        alert('Stichtag oder Wahl fehlen im Formular. Bitte überprüfen Sie die Eingaben.');
        return;
    }

    // Erstelle ein FormData-Objekt, um die Daten per POST zu senden
    const downloadFormData = new FormData();
    downloadFormData.append('stichtag', stichtag); // Weiterhin Stichtag und Wahl senden
    downloadFormData.append('wahl', wahl);
    // Hänge das gesamte Wählerverzeichnis als JSON-String an
    downloadFormData.append('voter_data_json', JSON.stringify(voterData)); 

    // Sende die Daten per POST an die download_excel.php
    fetch('Abfragen/download_excel.php', {
        method: 'POST',
        body: downloadFormData
    })
    .then(response => {
        // Prüfen, ob der Server eine Datei zurückgibt
        if (!response.ok) {
            // Wenn der Server einen Fehler-Header (z.B. 500) oder keinen Blob sendet
            return response.text().then(text => { // Versuche, den Fehlertext zu lesen
                throw new Error('Fehler beim Herunterladen der Excel-Datei: ' + text);
            });
        }
        return response.blob(); // Erwarte eine Binärdatei (Blob)
    })
    .then(blob => {
        // Erstelle eine temporäre URL für den Blob
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        // Setze den Dateinamen für den Download
        a.download = `Waehlerverzeichnis_${wahl}_${stichtag}.xlsx`; 
        document.body.appendChild(a);
        a.click(); // Simuliere einen Klick, um den Download zu starten
        a.remove(); // Entferne das temporäre Element
        window.URL.revokeObjectURL(url); // Gib den Speicher frei
    })
    .catch(error => {
        console.error('Download error:', error);
        alert('Ein Fehler ist beim Herunterladen aufgetreten: ' + error.message);
    });
});