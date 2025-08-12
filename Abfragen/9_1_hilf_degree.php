<?php
function hilf_degree(array $dbConfPortal): array {
    $wahl2_data = []; // Rohdaten von mannheim.wahlen2

    // --- DB-Zugriff für mannheim.wahlen2 ---
    $pdoPortal = null;
    try {
        $dsnPortal = "pgsql:host={$dbConfPortal['host']};port={$dbConfPortal['port']};dbname={$dbConfPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfPortal['user'], $dbConfPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Alle benötigten Spalten der Quelldaten auswählen
        $stmtWahlen2 = $pdoPortal->query("SELECT
            id, registrationnumber, studynumber, subjectnumber, degree_program_progress_startdate, course_of_study_longtext
            FROM mannheim.wahlen2;"); // Keine LIMIT-Klausel, da Filterung in PHP erfolgt
        $wahl2_data = $stmtWahlen2->fetchAll(PDO::FETCH_ASSOC);

    } finally {
        $pdoPortal = null;
    }
    error_log("(hilf_degree) mannheim.wahlen2 Rohdaten geladen. Anzahl Zeilen: " . count($wahl2_data));

    $groupedData = [];
    foreach ($wahl2_data as $row) {
        // --- GROUP BY Schlüssel ---
        $group_key = implode('||', [
            (string)($row['id'] ?? ''),
            (string)($row['registrationnumber'] ?? ''),
            (string)($row['studynumber'] ?? ''),
            (string)($row['subjectnumber'] ?? ''),
            (string)($row['degree_program_progress_startdate'] ?? ''),
            (string)($row['course_of_study_longtext'] ?? '')
        ]);

        if (!isset($groupedData[$group_key])) {
            $groupedData[$group_key] = $row;
        }
    }
    error_log("Phase 16: Daten nach GROUP BY gruppiert. Anzahl Gruppen: " . count($groupedData));

    // --- HAVING Klausel anwenden ---
    $resultData = [];
    foreach ($groupedData as $row) {
        $studyNr = (int)($row['studynumber'] ?? 0);
        $subjectNr = (int)($row['subjectnumber'] ?? 0);

        // HAVING (((mannheim_wahlen2.studynumber)=1) AND ((mannheim_wahlen2.subjectnumber)=1))
        if ($studyNr == 1 && $subjectNr == 1) {
            $resultData[] = $row;
        }
    }
    error_log("Phase 16: Daten nach HAVING gefiltert. Anzahl Zeilen: " . count($resultData));


    // --- ORDER BY Klausel ---
    usort($resultData, function($a, $b) {
        // degree_program_progress_startdate DESC
        $dateA = $a['degree_program_progress_startdate'] ?? null;
        $dateB = $b['degree_program_progress_startdate'] ?? null;

        if (is_null($dateA) && is_null($dateB)) return 0;
        if (is_null($dateA)) return 1; // A ist NULL, kommt nach B (DESC)
        if (is_null($dateB)) return -1; // B ist NULL, kommt nach A (DESC)

        // Für Datum-Strings: direkter Vergleich funktioniert für YYYY-MM-DD
        return $dateB <=> $dateA; // DESCending
    });
    error_log("Phase 16: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($resultData));

    return $resultData;
}