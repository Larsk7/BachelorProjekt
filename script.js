document.getElementById('voterForm').addEventListener('submit', function(event) {
    event.preventDefault();

    const stichtag = document.getElementById('stichtag').value;
    const wahl = document.getElementById('wahl').value;
    const resultsDiv = document.getElementById('voterResults');

    resultsDiv.innerHTML = '<p>Lade Wählerverzeichnis...</p>'; 

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
        if (data.error) {
            resultsDiv.innerHTML = '<p style="color: red;">Fehler: ' + data.error + '</p>';
            return;
        }

        // Darstellung der JSON
        if (data.length > 0) {
            let tableHtml = '<h2>Wählerverzeichnis</h2><table><thead><tr>';
            
            Object.keys(data[0]).forEach(key => {
                tableHtml += `<th>${key}</th>`;
            });
            tableHtml += '</tr></thead><tbody>';

            data.forEach(row => {
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
        console.error('Fetch error:', error);
        resultsDiv.innerHTML = '<p style="color: red;">Ein unerwarteter Fehler ist aufgetreten: ' + error.message + '</p>';
    });
});