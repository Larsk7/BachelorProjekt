<?php

function processPromovierende(
    array $dbConfigPortal,
    string $stichtag,
    array $infoLehrbereicheTable,
    array $infoFachschaftenTable,
    array $infoAbteilungenTable
): array {

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
            id, personalnr, firstname, surname, registrationnumber, enrollmentdate,
            disenrollment_date, orgunit_defaulttxt, orgunit_uniquename,
            teachingunit_uniquename, studynumber, subjectnumber, studystatus,
            degree_uniquename, degree_program_progress_startdate, degree_program_progress_enddate
            FROM mannheim.wahlen2;"); // Keine LIMIT-Klausel, da Filterung in PHP erfolgt
        $wahl2_data = $stmtWahlen2->fetchAll(PDO::FETCH_ASSOC);

    } finally {
        $pdoPortal = null;
    }
    error_log("(Promovierende) mannheim.wahlen2 Rohdaten geladen. Anzahl Zeilen: " . count($wahl2_data));

    // --- Lookup-Maps für Info-Tabellen erstellen ---
    $lehrbereiche_map = create_lookup_map($infoLehrbereicheTable, 'name');
    $fachschaften_map = create_lookup_map($infoFachschaftenTable, 'name');
    $abteilungen_map = create_lookup_map($infoAbteilungenTable, 'Kennung');
    error_log("Info-Tabellen Lookups erstellt für Phase 12.");

    $joinedFilteredData = [];
    $seenCombinations = []; // Für DISTINCT (auf alle ausgewählten Spalten)

    foreach ($wahl2_data as $row_mw2) {
        $current_row = $row_mw2; // Start mit Daten von mannheim_wahlen2

        // --- LEFT JOIN info_lehrbereiche ON mannheim_wahlen2.teachingunit_uniquename = info_lehrbereiche.name ---
        $teachingunit_uniquename = $current_row['teachingunit_uniquename'] ?? null;
        $lehrbereich_info = $lehrbereiche_map[$teachingunit_uniquename] ?? null;
        if ($lehrbereich_info) {
            $current_row['info_lehrbereiche_name'] = $lehrbereich_info['name'] ?? null;
            $current_row['info_lehrbereiche_fachschaft'] = $lehrbereich_info['fachschaft'] ?? null;
        } else {
            $current_row['info_lehrbereiche_name'] = null;
            $current_row['info_lehrbereiche_fachschaft'] = null;
        }

        // --- LEFT JOIN info_fachschaften ON info_lehrbereiche.fachschaft = info_fachschaften.name ---
        $lehrbereiche_fachschaft = $current_row['info_lehrbereiche_fachschaft'] ?? null; // Aus dem vorherigen Join
        $fachschaft_info = $fachschaften_map[$lehrbereiche_fachschaft] ?? null;
        if ($fachschaft_info) {
            $current_row['info_fachschaften_name'] = $fachschaft_info['name'] ?? null;
        } else {
            $current_row['info_fachschaften_name'] = null;
        }

        // --- LEFT JOIN info_abteilungen ON Mid(mannheim_wahlen2.orgunit_uniquename,1, 3) = info_abteilungen.Kennung ---
        $orgunit_uniquename = $current_row['orgunit_uniquename'] ?? '';
        $orgunit_prefix = substr($orgunit_uniquename, 0, 3); // Access Mid(string, 1, 3)
        $abteilung_info = $abteilungen_map[$orgunit_prefix] ?? null;
        if ($abteilung_info) {
            $current_row['info_abteilungen_name'] = $abteilung_info['name'] ?? null;
            $current_row['info_abteilungen_kennung'] = $abteilung_info['Kennung'] ?? null;
        } else {
            $current_row['info_abteilungen_name'] = null;
            $current_row['info_abteilungen_kennung'] = null;
        }


        // --- WHERE-Klausel anwenden (Angepasste Bedingungen) ---
        $degree_program_progress_startdate = $current_row['degree_program_progress_startdate'] ?? null;
        $degree_program_progress_enddate = $current_row['degree_program_progress_enddate'] ?? null;
        $studynumber = $current_row['studynumber'] ?? null;
        $subjectnumber = $current_row['subjectnumber'] ?? null;
        $degree_uniquename = $current_row['degree_uniquename'] ?? null;
        $studystatus = $current_row['studystatus'] ?? null;

        // Bedingungen der WHERE-Klausel (NULL-Behandlung wie im originalen Access SQL, d.h. NULLs fallen raus bei "<=" und ">=")
        $cond_degree_progress_start = ($degree_program_progress_startdate && $stichtag && new DateTime($degree_program_progress_startdate) <= new DateTime($stichtag));
        $cond_degree_progress_end = ($degree_program_progress_enddate && $stichtag && new DateTime($degree_program_progress_enddate) >= new DateTime($stichtag));
        $cond_study_subject_num = ($studynumber == 1 && $subjectnumber == 1);
        $cond_degree_uniquename = ($degree_uniquename == '06'); // Hier direkt == '06'
        $cond_studystatus = in_array($studystatus, ['E','N','R','B']);
        
        if ($cond_degree_progress_start 
            && $cond_degree_progress_end 
            && $cond_study_subject_num 
            && $cond_degree_uniquename 
            && $cond_studystatus
            )
        {
            // --- SELECT DISTINCT und Spaltenprojektion ---
            $final_row_output = [
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
            ksort($final_row_output); // Für konsistente JSON-Strings
            $row_string = json_encode($final_row_output);

            if (!isset($seenCombinations[$row_string])) {
                $joinedFilteredData[] = $final_row_output;
                $seenCombinations[$row_string] = true;
            }
        }
    }
    error_log("Phase 12 (Personalratswahl) Ergebnis. Anzahl Zeilen: " . count($joinedFilteredData));
    return $joinedFilteredData;
}