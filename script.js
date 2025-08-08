// Globale Variable, um die Daten des Wählerverzeichnisses zu speichern
let voterData = [];

// Event Listener für Begrenzung auf Gremienwahl
document.addEventListener("DOMContentLoaded", function() {
    const selectWahl = document.getElementById("wahl");
    const defaultOptionValue = "gremienwahl";

    selectWahl.addEventListener("change", function(event) {
        if (this.value !== defaultOptionValue) {
            // Meldung anzeigen
            alert("Diese Option ist noch nicht verfügbar.");

            // Die Auswahl auf die erste Option zurücksetzen
            this.value = defaultOptionValue;
        }
    });
});
// Event Listener für die WVZ-Erstellung
document.getElementById('voterForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const stichtag = document.getElementById('stichtag').value;
    const wahl = document.getElementById('wahl').value;
    const resultsDiv = document.getElementById('voterResults');
    const loadingOverlay = document.getElementById('loadingOverlayErstellen');

    // Zeige das Lade-Overlay an
    loadingOverlay.style.display = 'flex';
    
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
        // Lade-Overlay ausblenden
        loadingOverlay.style.display = 'none';

        // Buttons wieder aktivieren
        document.getElementById('createButton').disabled = false;
        document.getElementById('downloadButton').disabled = false;

        if (data.error) {
            resultsDiv.innerHTML = '<p style="color: red;">Fehler: ' + data.error + '</p>';
            voterData = []; // Daten leeren bei Fehler
            return;
        }

        // Speichern der geladenen Daten in der globalen Variable (und Anzahl Datensätze)
        voterData = data.wvz; 
        const rowCount = data.rowCount;

        const stichtag = document.getElementById('stichtag').value;
        const wahl = document.getElementById('wahl').value;

        // Funktion zur Umwandlung des Datumsformats YYYY-MM-DD zu DD.MM.YYYY für die Anzeige
        const formatStichtag = (dateString) => {
            const [year, month, day] = dateString.split('-');
            return `${day}.${month}.${year}`;
        };

        // NEU: Zuordnung der Spaltennamen für die Anzeige
        const columnNameMap = {
            'personid': 'Person_ID',
            'ecumnr': 'Ecum_Nr',
            'matrikelnr': 'Matrikelnummer',
            'vorname': 'Vorname',
            'nachname': 'Nachname',
            'wählendengruppe': 'Wählendengruppe',
            'fakultät': 'Fakultät',
            'fachschaft': 'Fachschaft',
            'LetzterWertvoncourse_of_study_longtext': 'Course of Study',
            'username': 'Username',
            'enrollmentdate': 'Einschreibungsdatum',
            'disenrollment_date': 'Abmeldungsdatum' 
        };

        // Darstellung der JSON 
        if (voterData.length > 0) {
            
            // Wandle den Wert der Wahl (z.B. "gremienwahl") in einen lesbaren Text um
            const wahlText = {
                "gremienwahl": "Gremienwahl",
                "studierendenwahl": "Studierendenwahl",
                "personalratswahl": "Personalratswahl"
            }[wahl] || wahl; // Fallback, falls der Wert nicht in der Liste ist

            let tableHtml = `<h2>Wählerverzeichnis ${wahlText} (${formatStichtag(stichtag)}) - ${rowCount} Datensätze</h2><table><thead><tr>`;
            
            const columnKeys = Object.keys(voterData[0]); // Speichere die Schlüssel, um Konsistenz zu gewährleisten
            
            // Erstelle Header mit den umbenannten Spaltennamen
            columnKeys.forEach(key => {
                const headerText = columnNameMap[key] || key; // Nutze den neuen Namen, wenn vorhanden, sonst den Originalnamen
                tableHtml += `<th>${headerText}</th>`;
            });
            tableHtml += '</tr></thead><tbody>';

            // Fülle die Datenzeilen
            voterData.forEach(row => {
                tableHtml += '<tr>';
                // Iteriere über die gespeicherten Schlüssel, um die Zellen in der richtigen Reihenfolge zu erstellen
                columnKeys.forEach(key => {
                    let cellValue = row[key];
                    // Behandlung von null oder undefined, um "null" oder "undefined" nicht direkt anzuzeigen
                    if (cellValue === null || typeof cellValue === 'undefined') {
                        cellValue = ''; // Leerer String für leere Zellen
                    }
                    tableHtml += `<td>${cellValue}</td>`;
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
        // Lade-Overlay ausblenden
        loadingOverlay.style.display = 'none';

        // Buttons wieder aktivieren
        document.getElementById('createButton').disabled = false;
        document.getElementById('downloadButton').disabled = false;

        console.error('Fetch error:', error);
        resultsDiv.innerHTML = '<p style="color: red;">Ein unerwarteter Fehler ist aufgetreten: ' + error.message + '</p>';
        voterData = []; // Daten leeren bei Fehler
    });
});

// Event Listener für den Excel-Download
document.getElementById('downloadButton').addEventListener('click', function(event) {
    event.preventDefault();

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
    
    // Pop-up anzeigen
    const loadingOverlay = document.getElementById('loadingOverlayDownload');
    loadingOverlay.style.display = 'flex';

    // Button deaktivieren
    document.getElementById('downloadButton').disabled = true;

    const downloadFormData = new FormData();
    downloadFormData.append('stichtag', stichtag);
    downloadFormData.append('wahl', wahl);
    downloadFormData.append('voter_data_json', JSON.stringify(voterData)); 

    fetch('Abfragen/download_excel.php', {
        method: 'POST',
        body: downloadFormData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error('Fehler beim Herunterladen der Excel-Datei: ' + text);
            });
        }
        return response.blob();
    })
    .then(blob => {
        // Pop-up ausblenden
        loadingOverlay.style.display = 'none';
        // Button wieder aktivieren
        document.getElementById('downloadButton').disabled = false;

        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Waehlerverzeichnis_${wahl}_${stichtag}.xlsx`; 
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
    })
    .catch(error => {
        // Pop-up ausblenden
        loadingOverlay.style.display = 'none';
        // Button wieder aktivieren
        document.getElementById('downloadButton').disabled = false;

        console.error('Download error:', error);
        alert('Ein Fehler ist beim Herunterladen aufgetreten: ' + error.message);
    });
});