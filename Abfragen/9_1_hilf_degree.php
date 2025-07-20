<?php
function hilf_degree(array $dbConfigPortal): array {
    $wahl2_data = []; // Rohdaten von mannheim.wahlen2

    // --- DB-Zugriff für mannheim.wahlen2 ---
    $pdoPortal = null;
    try {
        $dsnPortal = "pgsql:host={$dbConfigPortal['host']};port={$dbConfigPortal['port']};dbname={$dbConfigPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfigPortal['user'], $dbConfigPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Wählen Sie HIER ALLE Spalten aus, die in der nachfolgenden PHP-Logik verwendet werden!
        // Also alle SELECT-Spalten, alle JOIN-ON-Spalten, alle WHERE-Spalten.
        $stmtWahlen2 = $pdoPortal->query("SELECT
            id, registrationnumber, studynumber, subjectnumber, degree_program_progress_startdate, course_of_study_longtext
            FROM mannheim.wahlen2;"); // Keine LIMIT-Klausel, da Filterung in PHP erfolgt
        $wahl2_data = $stmtWahlen2->fetchAll(PDO::FETCH_ASSOC);

    } finally {
        $pdoPortal = null;
    }
    error_log("(hilf_degree) mannheim.wahlen2 Rohdaten geladen. Anzahl Zeilen: " . count($wahl2_data));

    $groupedAndFilteredData = [];
    foreach ($wahl2_data as $row) {
        // --- GROUP BY Schlüssel ---
        // Alle Spalten im SELECT müssen im GROUP BY sein
        $group_key = implode('||', [
            (string)($row['id'] ?? ''),
            (string)($row['registrationnumber'] ?? ''),
            (string)($row['studynumber'] ?? ''),
            (string)($row['subjectnumber'] ?? ''),
            (string)($row['degree_program_progress_startdate'] ?? ''),
            (string)($row['course_of_study_longtext'] ?? '')
        ]);

        if (!isset($groupedAndFilteredData[$group_key])) {
            // Dies ist die erste Zeile für diese Gruppe, kopiere sie
            $groupedAndFilteredData[$group_key] = $row;
            // Keine Aggregationen, nur durchreichen
        }
        // Wenn der Schlüssel bereits existiert, wird die Zeile übersprungen (da GROUP BY ohne Aggregate wie DISTINCT wirkt)
    }
    error_log("Phase 16: Daten nach GROUP BY gruppiert. Anzahl Gruppen: " . count($groupedAndFilteredData));


    // --- HAVING Klausel anwenden ---
    $filteredByHavingData = [];
    foreach ($groupedAndFilteredData as $row) {
        $studynumber = (int)($row['studynumber'] ?? 0);
        $subjectnumber = (int)($row['subjectnumber'] ?? 0);

        // HAVING (((mannheim_wahlen2.studynumber)=1) AND ((mannheim_wahlen2.subjectnumber)=1))
        if ($studynumber == 1 && $subjectnumber == 1) {
            $filteredByHavingData[] = $row;
        }
    }
    error_log("Phase 16: Daten nach HAVING gefiltert. Anzahl Zeilen: " . count($filteredByHavingData));


    // --- ORDER BY Klausel ---
    usort($filteredByHavingData, function($a, $b) {
        // degree_program_progress_startdate DESC
        // Achten Sie auf NULLs: NULL sollte hier als "kleinster" Wert behandelt werden, um am Ende zu erscheinen.
        $dateA = $a['degree_program_progress_startdate'] ?? null;
        $dateB = $b['degree_program_progress_startdate'] ?? null;

        if (is_null($dateA) && is_null($dateB)) return 0;
        if (is_null($dateA)) return 1; // A ist NULL, kommt nach B (DESC)
        if (is_null($dateB)) return -1; // B ist NULL, kommt nach A (DESC)

        // Für Datum-Strings: direkter Vergleich funktioniert für YYYY-MM-DD
        return $dateB <=> $dateA; // DESCending
    });
    error_log("Phase 16: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($filteredByHavingData));

    return $filteredByHavingData;
}