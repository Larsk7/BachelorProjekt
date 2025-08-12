<?php

function processPromovierende(
    array $dbConfPortal,
    string $stichtag,
    array $infoLehrTable,
    array $infoFachTable,
    array $infoAbtTable
): array {

    // Rohdaten von mannheim.wahlen2
    $wahl2_data = []; 

    // --- DB-Zugriff für mannheim.wahlen2 ---
    $pdoPortal = null;
    try {
        $dsnPortal = "pgsql:host={$dbConfPortal['host']};port={$dbConfPortal['port']};dbname={$dbConfPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfPortal['user'], $dbConfPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Alle benötigten Spalten der Quelldaten auswählen
        $stmtWahlen2 = $pdoPortal->query("SELECT
            id, personalnr, firstname, surname, registrationnumber, enrollmentdate,
            disenrollment_date, orgunit_defaulttxt, orgunit_uniquename,
            teachingunit_uniquename, studynumber, subjectnumber, studystatus,
            degree_uniquename, degree_program_progress_startdate, degree_program_progress_enddate
            FROM mannheim.wahlen2;"); 
        $wahl2_data = $stmtWahlen2->fetchAll(PDO::FETCH_ASSOC);

    } finally {
        $pdoPortal = null;
    }
    error_log("(Promovierende) mannheim.wahlen2 Rohdaten geladen. Anzahl Zeilen: " . count($wahl2_data));

    // --- Lookup-Maps für Info-Tabellen erstellen ---
    $lehr_map = create_lookup_map($infoLehrTable, 'name');
    $fach_map = create_lookup_map($infoFachTable, 'name');
    $abt_map = create_lookup_map($infoAbtTable, 'Kennung');
    error_log("Info-Tabellen Lookups erstellt für Phase 12.");

    $resultData = [];
    $seenCombs = []; // Für DISTINCT (auf alle ausgewählten Spalten)

    foreach ($wahl2_data as $row_mw2) {
        $current_row = $row_mw2; // Start mit Daten von mannheim_wahlen2

        // --- LEFT JOIN info_lehrbereiche ON mannheim_wahlen2.teachingunit_uniquename = info_lehrbereiche.name ---
        $teachingunit = $current_row['teachingunit_uniquename'] ?? null;
        $lehr_info = $lehr_map[$teachingunit] ?? null;
        if ($lehr_info) {
            $current_row['info_lehrbereiche_name'] = $lehr_info['name'] ?? null;
            $current_row['info_lehrbereiche_fachschaft'] = $lehr_info['fachschaft'] ?? null;
        } else {
            $current_row['info_lehrbereiche_name'] = null;
            $current_row['info_lehrbereiche_fachschaft'] = null;
        }

        // --- LEFT JOIN info_fachschaften ON info_lehrbereiche.fachschaft = info_fachschaften.name ---
        $lehr_fach = $current_row['info_lehrbereiche_fachschaft'] ?? null; // Aus dem vorherigen Join
        $fach_info = $fach_map[$lehr_fach] ?? null;
        if ($fach_info) {
            $current_row['info_fachschaften_name'] = $fach_info['name'] ?? null;
        } else {
            $current_row['info_fachschaften_name'] = null;
        }

        // --- LEFT JOIN info_abteilungen ON Mid(mannheim_wahlen2.orgunit_uniquename,1, 3) = info_abteilungen.Kennung ---
        $orgunit = $current_row['orgunit_uniquename'] ?? '';
        $orgunit_prefix = substr($orgunit, 0, 3); // Access Mid(string, 1, 3)
        $abt_info = $abt_map[$orgunit_prefix] ?? null;
        if ($abt_info) {
            $current_row['info_abteilungen_name'] = $abt_info['name'] ?? null;
            $current_row['info_abteilungen_kennung'] = $abt_info['Kennung'] ?? null;
        } else {
            $current_row['info_abteilungen_name'] = null;
            $current_row['info_abteilungen_kennung'] = null;
        }


        // --- WHERE-Klausel anwenden (Angepasste Bedingungen) ---
        $degProgProgressStart = $current_row['degree_program_progress_startdate'] ?? null;
        $degProgProgressEnd = $current_row['degree_program_progress_enddate'] ?? null;
        $studynr = $current_row['studynumber'] ?? null;
        $subjectnr = $current_row['subjectnumber'] ?? null;
        $degree = $current_row['degree_uniquename'] ?? null;
        $studystatus = $current_row['studystatus'] ?? null;

        // Bedingungen der WHERE-Klausel (NULL-Behandlung wie im originalen Access SQL, d.h. NULLs fallen raus bei "<=" und ">=")
        $condDegProgStart = ($degProgProgressStart && $stichtag && new DateTime($degProgProgressStart) <= new DateTime($stichtag));
        $condDegProgEnd = ($degProgProgressEnd && $stichtag && new DateTime($degProgProgressEnd) >= new DateTime($stichtag));
        $condStudySubjectNr = ($studynr == 1 && $subjectnr == 1);
        $condDeg = ($degree == '06'); // Hier direkt == '06'
        $condStudystatus = in_array($studystatus, ['E','N','R','B']);
        
        if ($condDegProgStart && $condDegProgEnd && $condStudySubjectNr && $condDeg && $condStudystatus)
        {
            // --- SELECT DISTINCT und Spaltenprojektion ---
            $finalRows = [
                'personid' => $current_row['id'] ?? null,
                'personalnr' => $current_row['personalnr'] ?? null,
                'firstname' => $current_row['firstname'] ?? null,
                'surname' => $current_row['surname'] ?? null,
                'registrationnumber' => $current_row['registrationnumber'] ?? null,
                'orgunit_defaulttxt' => $current_row['orgunit_defaulttxt'] ?? null,
                'orgunit_uniquename' => $current_row['orgunit_uniquename'] ?? null,
                'abteilung' => $current_row['info_abteilungen_name'] ?? null,
                'fachschaft' => $current_row['info_fachschaften_name'] ?? null,
                'info_abteilungen_kennung' => $current_row['info_abteilungen_kennung'] ?? null, // 'Kennung' aus info_abteilungen
            ];

            // DISTINCT-Logik auf die finalen ausgewählten Spalten anwenden
            // Eine JSON-Repräsentation der Zeile als Schlüssel verwenden.
            ksort($finalRows); // Für konsistente JSON-Strings
            $row_string = json_encode($finalRows);

            if (!isset($seenCombs[$row_string])) {
                $resultData[] = $finalRows;
                $seenCombs[$row_string] = true;
            }
        }
    }
    error_log("Phase 12 (Personalratswahl) Ergebnis. Anzahl Zeilen: " . count($resultData));
    return $resultData;
}