<?php
function processStudierende(
    array $dbConfigPortal,
    string $stichtag,
    array $infoLehrbereicheTable, // NEU: Diese Konstante muss in Info_Tabellen.php existieren
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
    error_log("(Studierende) mannheim.wahlen2 Rohdaten geladen. Anzahl Zeilen: " . count($wahl2_data));


    // --- Lookup-Maps für Info-Tabellen erstellen ---
    $lehrbereiche_map = create_lookup_map($infoLehrbereicheTable, 'name'); // Annahme: 'name' ist der Join-Schlüssel
    $fachschaften_map = create_lookup_map($infoFachschaftenTable, 'name'); // Annahme: 'name' ist der Join-Schlüssel
    $abteilungen_map = create_lookup_map($infoAbteilungenTable, 'Kennung'); // Annahme: 'Kennung' ist der Join-Schlüssel
    error_log("Info-Tabellen Lookups erstellt.");


    $joinedData = [];
    $seenCombinations = []; // Für DISTINCT

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


        // --- WHERE-Klausel anwenden ---
        $registrationnumber = $current_row['registrationnumber'] ?? null;
        $enrollmentdate = $current_row['enrollmentdate'] ?? null;
        $disenrollment_date = $current_row['disenrollment_date'] ?? null;
        $studynumber = $current_row['studynumber'] ?? null;
        $subjectnumber = $current_row['subjectnumber'] ?? null;
        $studystatus = $current_row['studystatus'] ?? null;
        $degree_uniquename = $current_row['degree_uniquename'] ?? null;
        $degree_program_progress_startdate = $current_row['degree_program_progress_startdate'] ?? null;
        $degree_program_progress_enddate = $current_row['degree_program_progress_enddate'] ?? null;

        // Bedingungen der WHERE-Klausel
        $cond_reg_num = ($registrationnumber > 1000);
        $cond_enroll_date = ($enrollmentdate <= $stichtag);
        $cond_disenroll_date = (is_null($disenrollment_date) || $disenrollment_date >= $stichtag);
        $cond_study_subject_num = ($studynumber == 1 && $subjectnumber == 1);
        $cond_studystatus = in_array($studystatus, ['E','N','R','B']);
        $cond_degree_uniquename = in_array($degree_uniquename, ['82','88','83','89','87','08','25','95','24','11','18','02']);
        $cond_degree_progress_start = ($degree_program_progress_startdate <= $stichtag);
        $cond_degree_progress_end = ($degree_program_progress_enddate >= $stichtag);

        if ($cond_reg_num && $cond_enroll_date && $cond_disenroll_date &&
            $cond_study_subject_num && $cond_studystatus && $cond_degree_uniquename 
           //&& $cond_degree_progress_start// && $cond_degree_progress_end)
            // Auskommentiert da alle Werte dieser 2 Attribute in Mannheim.Wahlen2 'Null' sind 
            // (In DBeaver und im TS)
        )
        {
            // --- SELECT DISTINCT und Spaltenprojektion ---
            // Stelle sicher, dass die Reihenfolge und Namen der Spalten hier mit dem Access SELECT übereinstimmen
            $final_row_output = [
                'personid' => $current_row['id'] ?? null, // AS personid
                'personalnr' => $current_row['personalnr'] ?? null,
                'firstname' => $current_row['firstname'] ?? null,
                'surname' => $current_row['surname'] ?? null,
                'registrationnumber' => $current_row['registrationnumber'] ?? null,
                'enrollmentdate' => $current_row['enrollmentdate'] ?? null,
                'disenrollment_date' => $current_row['disenrollment_date'] ?? null,
                'orgunit_defaulttxt' => $current_row['orgunit_defaulttxt'] ?? null,
                'orgunit_uniquename' => $current_row['orgunit_uniquename'] ?? null,
                'abteilung' => $current_row['info_abteilungen_name'] ?? null, // Von info_abteilungen
                'fachschaft' => $current_row['info_fachschaften_name'] ?? null, // Von info_fachschaften
                // Die folgenden sind aus der Access-Query, du musst entscheiden, ob du sie brauchst oder ob sie Duplikate sind
                // 'orgunit_uniquename_dup' => $current_row['orgunit_uniquename'] ?? null, // Duplikat
                'info_lehrbereiche_name' => $current_row['info_lehrbereiche_name'] ?? null, // Name von info_lehrbereiche
                'info_lehrbereiche_fachschaft' => $current_row['info_lehrbereiche_fachschaft'] ?? null, // Fachschaft von info_lehrbereiche
                // 'info_fachschaften_name_dup' => $current_row['info_fachschaften_name'] ?? null, // Duplikat
                'teachingunit_uniquename' => $current_row['teachingunit_uniquename'] ?? null,
                'info_abteilungen_kennung' => $current_row['info_abteilungen_kennung'] ?? null, // Kennung von info_abteilungen
            ];

            // DISTINCT-Logik auf die finalen ausgewählten Spalten anwenden
            // Wie zuvor: Eine JSON-Repräsentation der Zeile als Schlüssel verwenden.
            ksort($final_row_output); // Für konsistente JSON-Strings
            $row_string = json_encode($final_row_output);

            if (!isset($seenCombinations[$row_string])) {
                $joinedData[] = $final_row_output;
                $seenCombinations[$row_string] = true;
            }
        }
    }
    error_log("Phase 11 (Studierende) Ergebnis. Anzahl Zeilen: " . count($joinedData));
    return $joinedData;
}