<?php

function wvz_st_f_ecum_2020(
    array $wvzData,
    array $dbConfPortal,
    array $dataPhase9_2,
    string $stichtag // Der Stichtag im Format YYYY-MM-DD
): array {
    // --- DB-Zugriff für mannheim.wahlen2 ---
    $wahl2_data = [];
    $pdoPortal = null;
    try {
        $dsnPortal = "pgsql:host={$dbConfPortal['host']};port={$dbConfPortal['port']};dbname={$dbConfPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfPortal['user'], $dbConfPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Wählen Sie HIER ALLE Spalten aus, die in der nachfolgenden PHP-Logik verwendet werden!
        // Benötigt: id, username, enrollmentdate, disenrollment_date
        $stmtWahlen2 = $pdoPortal->query("SELECT id, username, enrollmentdate, disenrollment_date FROM mannheim.wahlen2;");
        $wahl2_data = $stmtWahlen2->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Portal DB Error (mannheim.wahlen2 in Phase 18): " . $e->getMessage());
        throw $e; // Werfe die Exception weiter
    } finally {
        $pdoPortal = null;
    }
    error_log("Phase 18: mannheim.wahlen2 Rohdaten geladen. Anzahl Zeilen: " . count($wahl2_data));


    // --- Vorbereitung der Lookup-Maps ---
    // Lookup für mannheim_wahlen2, keyed by 'id'
    $wahl2_map = create_lookup_map($wahl2_data, 'id');
    error_log("Phase 18: mannheim.wahlen2 Lookup (keyed by 'id') erstellt mit " . count($wahl2_map) . " Einträgen.");

    // Lookup für hilf_degree_distinct, keyed by 'id'
    $hilfDegree_map = create_lookup_map($dataPhase9_2, 'id');
    error_log(message: "Phase 18: hilf_degree_distinct Lookup (keyed by 'id') erstellt mit " . count($hilfDegree_map) . " Einträgen.");


    // --- Durchführung der LEFT JOINs ---
    $joinedData = [];
    foreach ($wvzData as $wvz_row) {
        $personid = $wvz_row['personid'] ?? null;

        // LEFT JOIN mannheim_wahlen2 ON wählendenverzeichnis_stichtag_form_ecum.personid = mannheim_wahlen2.id
        $mw2Info = $wahl2_map[$personid] ?? null;

        // LEFT JOIN hilf_degree_distinct ON wählendenverzeichnis_stichtag_form_ecum.personid = hilf_degree_distinct.id
        $hddInfo = $hilfDegree_map[$personid] ?? null;

        $joinedRow = [
            'personid' => $wvz_row['personid'] ?? null,
            'ecumnr' => $wvz_row['ecumnr'] ?? null,
            'matrikelnr' => $wvz_row['matrikelnr'] ?? null,
            'vorname' => $wvz_row['vorname'] ?? null,
            'nachname' => $wvz_row['nachname'] ?? null,
            'wählendengruppe' => $wvz_row['wählendengruppe'] ?? null,
            'passiv' => $wvz_row['passiv'] ?? null,
            'fakultät' => $wvz_row['fakultät'] ?? null,
            'fachschaft' => $wvz_row['fachschaft'] ?? null,
            'LetzterWertvoncourse_of_study_longtext' => $hddInfo['course_of_study_longtext_last'] ?? null,
            'username' => $mw2Info['username'] ?? null,
            'enrollmentdate' => $mw2Info['enrollmentdate'] ?? null,
            'disenrollment_date' => $mw2Info['disenrollment_date'] ?? null,
        ];
        $joinedData[] = $joinedRow;
    }
    error_log("Phase 18: Daten nach LEFT JOINs vorbereitet. Anzahl Zeilen: " . count($joinedData));


    // --- GROUP BY (wirkt hier wie DISTINCT, da alle SELECT-Spalten gruppiert werden) ---
    $groupedData = [];
    foreach ($joinedData as $row) {
        // Erstelle einen eindeutigen Schlüssel aus allen GROUP BY Spalten
        $groupKeyParts = [];
        foreach ($row as $key => $value) {
            // Spezielle Behandlung für den 'LetzterWertvoncourse_of_study_longtext' Schlüssel
            if ($key === 'LetzterWertvoncourse_of_study_longtext') {
                $groupKeyParts[] = (string)($row['LetzterWertvoncourse_of_study_longtext'] ?? '');
            } else {
                $groupKeyParts[] = (string)($row[$key] ?? '');
            }
        }
        $groupKey = implode('||', $groupKeyParts);

        if (!isset($groupedData[$groupKey])) {
            $groupedData[$groupKey] = $row;
        }
    }
    $filteredData = array_values($groupedData);
    error_log("Phase 18: Daten nach GROUP BY (DISTINCT) verarbeitet. Anzahl Zeilen: " . count($filteredData));


    // --- HAVING-Klausel anwenden ---
    $resultData = [];
    foreach ($filteredData as $row) {
        $enrollDate = $row['enrollmentdate'];
        $disenrollDate = $row['disenrollment_date'];
        
        $cond1 = (
            (!is_null($enrollDate) && $enrollDate <= $stichtag) &&
            (is_null($disenrollDate) || (!is_null($disenrollDate) && $disenrollDate >= $stichtag))
        );

        $cond2 = (
            is_null($enrollDate) && is_null($disenrollDate)
        );

        $cond3 = (
            (!is_null($enrollDate) && $enrollDate <= $stichtag) &&
            (!is_null($disenrollDate) && $disenrollDate <= $stichtag)
        );

        if ($cond1 || $cond2 || $cond3) {
            $resultData[] = $row;
        }
    }
    error_log("Phase 18: Daten nach HAVING-Klausel gefiltert. Anzahl Zeilen: " . count($resultData));


    // --- ORDER BY wählendenverzeichnis_stichtag_form_ecum.nachname ---
    usort($resultData, function($a, $b) {
        $nachnameA = $a['nachname'] ?? '';
        $nachnameB = $b['nachname'] ?? '';
        return $nachnameA <=> $nachnameB; // ASC
    });
    error_log("Phase 18: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($resultData));

    return $resultData;
}