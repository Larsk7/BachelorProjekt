<?php

function wvz_st_f_ecum_2020(
    array $dataWvzStFEcum,
    array $dbConfigPortal,
    array $hilfDegreeDistinctData,
    string $stichtag // Der Stichtag im Format YYYY-MM-DD
): array {
    // --- DB-Zugriff für mannheim.wahlen2 ---
    $mannheim_wahlen2_data = [];
    $pdoPortal = null;
    try {
        $dsnPortal = "pgsql:host={$dbConfigPortal['host']};port={$dbConfigPortal['port']};dbname={$dbConfigPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfigPortal['user'], $dbConfigPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Wählen Sie HIER ALLE Spalten aus, die in der nachfolgenden PHP-Logik verwendet werden!
        // Benötigt: id, username, enrollmentdate, disenrollment_date
        $stmtWahlen2 = $pdoPortal->query("SELECT id, username, enrollmentdate, disenrollment_date FROM mannheim.wahlen2;");
        $mannheim_wahlen2_data = $stmtWahlen2->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Portal DB Error (mannheim.wahlen2 in Phase 18): " . $e->getMessage());
        throw $e; // Werfe die Exception weiter
    } finally {
        $pdoPortal = null;
    }
    error_log("Phase 18: mannheim.wahlen2 Rohdaten geladen. Anzahl Zeilen: " . count($mannheim_wahlen2_data));


    // --- Vorbereitung der Lookup-Maps ---
    // Lookup für mannheim_wahlen2, keyed by 'id'
    $mannheim_wahlen2_map = create_lookup_map($mannheim_wahlen2_data, 'id');
    error_log("Phase 18: mannheim.wahlen2 Lookup (keyed by 'id') erstellt mit " . count($mannheim_wahlen2_map) . " Einträgen.");

    // Lookup für hilf_degree_distinct, keyed by 'id'
    $hilf_degree_distinct_map = create_lookup_map($hilfDegreeDistinctData, 'id');
    error_log("Phase 18: hilf_degree_distinct Lookup (keyed by 'id') erstellt mit " . count($hilf_degree_distinct_map) . " Einträgen.");


    // --- Durchführung der LEFT JOINs ---
    $joined_data_pre_group = [];
    foreach ($dataWvzStFEcum as $wvz_row) {
        $personid = $wvz_row['personid'] ?? null;

        // LEFT JOIN mannheim_wahlen2 ON wählendenverzeichnis_stichtag_form_ecum.personid = mannheim_wahlen2.id
        $mw2_info = $mannheim_wahlen2_map[$personid] ?? null;

        // LEFT JOIN hilf_degree_distinct ON wählendenverzeichnis_stichtag_form_ecum.personid = hilf_degree_distinct.id
        $hdd_info = $hilf_degree_distinct_map[$personid] ?? null;

        $joined_row = [
            'personid' => $wvz_row['personid'] ?? null,
            'ecumnr' => $wvz_row['ecumnr'] ?? null,
            'matrikelnr' => $wvz_row['matrikelnr'] ?? null,
            'vorname' => $wvz_row['vorname'] ?? null,
            'nachname' => $wvz_row['nachname'] ?? null,
            'wählendengruppe' => $wvz_row['wählendengruppe'] ?? null,
            'passiv' => $wvz_row['passiv'] ?? null,
            'fakultät' => $wvz_row['fakultät'] ?? null,
            'fachschaft' => $wvz_row['fachschaft'] ?? null,
            'LetzterWertvoncourse_of_study_longtext' => $hdd_info['course_of_study_longtext_last'] ?? null,
            'username' => $mw2_info['username'] ?? null,
            'enrollmentdate' => $mw2_info['enrollmentdate'] ?? null,
            'disenrollment_date' => $mw2_info['disenrollment_date'] ?? null,
        ];
        $joined_data_pre_group[] = $joined_row;
    }
    error_log("Phase 18: Daten nach LEFT JOINs vorbereitet. Anzahl Zeilen: " . count($joined_data_pre_group));


    // --- GROUP BY (wirkt hier wie DISTINCT, da alle SELECT-Spalten gruppiert werden) ---
    $grouped_data = [];
    foreach ($joined_data_pre_group as $row) {
        // Erstelle einen eindeutigen Schlüssel aus allen GROUP BY Spalten
        $group_key_parts = [];
        foreach ($row as $key => $value) {
            // Spezielle Behandlung für den 'LetzterWertvoncourse_of_study_longtext' Schlüssel
            // (da dieser im SQL-Query einen Alias hat)
            if ($key === 'LetzterWertvoncourse_of_study_longtext') {
                $group_key_parts[] = (string)($row['LetzterWertvoncourse_of_study_longtext'] ?? '');
            } else {
                $group_key_parts[] = (string)($row[$key] ?? '');
            }
        }
        $group_key = implode('||', $group_key_parts);

        if (!isset($grouped_data[$group_key])) {
            $grouped_data[$group_key] = $row;
        }
    }
    $filtered_data_pre_having = array_values($grouped_data);
    error_log("Phase 18: Daten nach GROUP BY (DISTINCT) verarbeitet. Anzahl Zeilen: " . count($filtered_data_pre_having));


    // --- HAVING-Klausel anwenden ---
    $final_filtered_data = [];
    foreach ($filtered_data_pre_having as $row) {
        $enrollmentdate = $row['enrollmentdate'];
        $disenrollment_date = $row['disenrollment_date'];

        // Access-Datum-Vergleich: [Forms]![Stichtag]![stichtag]
        // PHP-Datum-Vergleich: $stichtag
        
        $condition1 = (
            (!is_null($enrollmentdate) && $enrollmentdate <= $stichtag) &&
            (is_null($disenrollment_date) || (!is_null($disenrollment_date) && $disenrollment_date >= $stichtag))
        );

        $condition2 = (
            is_null($enrollmentdate) && is_null($disenrollment_date)
        );

        $condition3 = (
            (!is_null($enrollmentdate) && $enrollmentdate <= $stichtag) &&
            (!is_null($disenrollment_date) && $disenrollment_date <= $stichtag)
        );

        if ($condition1 || $condition2 || $condition3) {
            $final_filtered_data[] = $row;
        }
    }
    error_log("Phase 18: Daten nach HAVING-Klausel gefiltert. Anzahl Zeilen: " . count($final_filtered_data));


    // --- ORDER BY wählendenverzeichnis_stichtag_form_ecum.nachname ---
    usort($final_filtered_data, function($a, $b) {
        $nachnameA = $a['nachname'] ?? '';
        $nachnameB = $b['nachname'] ?? '';
        return $nachnameA <=> $nachnameB; // ASC
    });
    error_log("Phase 18: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($final_filtered_data));

    return $final_filtered_data;
}