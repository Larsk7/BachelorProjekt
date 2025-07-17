<?php

function wvz_st(
    array $dataLehrDisDt,
    array $dataWissDisDt,
    array $dataStudierende,
    array $dataPromovierende,
    array $dataSonsDisDt
): array {
    $union_result_raw = [];

    // --- 1. Union Branch: mitarbeitende_pbv_distinct_filtered_lehr_distinct_dt ---
    foreach ($dataLehrDisDt as $row) {
        $union_result_raw[] = [
            'pid' => $row['person_id'] ?? null,
            'wgp' => 1, // Fixwert
            'pas' => $row['passiv'] ?? null,
            'fna' => $row['firstname'] ?? null,
            'sna' => $row['surname'] ?? null,
            'abt' => $row['abteilung'] ?? null,
            'fsc' => null, // Fixwert
            'mnr' => null, // Fixwert
        ];
    }
    error_log("(6_wvz_st): LehrDisDt hinzugefügt. Aktuelle Zeilen: " . count($union_result_raw));

    // --- 2. Union Branch: mitarbeitende_pbv_distinct_filtered_wiss_distinct_dt ---
    foreach ($dataWissDisDt as $row) {
        $union_result_raw[] = [
            'pid' => $row['person_id'] ?? null,
            'wgp' => 2, // Fixwert
            'pas' => $row['passiv'] ?? null,
            'fna' => $row['firstname'] ?? null,
            'sna' => $row['surname'] ?? null,
            'abt' => $row['abteilung'] ?? null,
            'fsc' => null, // Fixwert
            'mnr' => null, // Fixwert
        ];
    }
    error_log("(6_wvz_st): WissDisDt hinzugefügt. Aktuelle Zeilen: " . count($union_result_raw));

    // --- 3. Union Branch: studierende ---
    foreach ($dataStudierende as $row) {
        $union_result_raw[] = [
            'pid' => $row['personid'] ?? null, // Spaltenname im Input ist 'personid'
            'wgp' => 3, // Fixwert
            'pas' => 1, // Fixwert
            'fna' => $row['firstname'] ?? null,
            'sna' => $row['surname'] ?? null,
            'abt' => $row['abteilung'] ?? null,
            'fsc' => $row['fachschaft'] ?? null,
            'mnr' => $row['registrationnumber'] ?? null, // Spaltenname im Input ist 'registrationnumber'
        ];
    }
    error_log("(6_wvz_st): Studierende hinzugefügt. Aktuelle Zeilen: " . count($union_result_raw));

    // --- 4. Union Branch: promovierende ---
    foreach ($dataPromovierende as $row) {
        $union_result_raw[] = [
            'pid' => $row['personid'] ?? null, // Spaltenname im Input ist 'personid'
            'wgp' => 4, // Fixwert
            'pas' => 1, // Fixwert
            'fna' => $row['firstname'] ?? null,
            'sna' => $row['surname'] ?? null,
            'abt' => $row['abteilung'] ?? null,
            'fsc' => $row['fachschaft'] ?? null,
            'mnr' => $row['registrationnumber'] ?? null, // Spaltenname im Input ist 'registrationnumber'
        ];
    }
    error_log("(6_wvz_st): Promovierende hinzugefügt. Aktuelle Zeilen: " . count($union_result_raw));

    // --- 5. Union Branch: mitarbeitende_pbv_distinct_filtered_sons_distinct_dt ---
    foreach ($dataSonsDisDt as $row) {
        $union_result_raw[] = [
            'pid' => $row['person_id'] ?? null,
            'wgp' => 5, // Fixwert
            'pas' => $row['passiv'] ?? null,
            'fna' => $row['firstname'] ?? null,
            'sna' => $row['surname'] ?? null,
            'abt' => $row['abteilung'] ?? null,
            'fsc' => null, // Fixwert
            'mnr' => null, // Fixwert
        ];
    }
    error_log("(6_wvz_st): SonsDisDt hinzugefügt. Aktuelle Zeilen: " . count($union_result_raw));


    // UNION's DISTINCT
    $distinct_union_result = [];
    $seen_union_rows = [];
    foreach ($union_result_raw as $row) {
        ksort($row);
        $row_string = json_encode($row);
        if (!isset($seen_union_rows[$row_string])) {
            $distinct_union_result[] = $row;
            $seen_union_rows[$row_string] = true;
        }
    }
    error_log("(6_wvz_st): Nach UNION DISTINCT. Finale Zeilen: " . count($distinct_union_result));


    // GROUP BY 
    $grouped_verzeichnis = [];
    foreach ($distinct_union_result as $row) {
        // Group-By-Schlüssel (alle Spalten der GROUP BY Klausel)
        $group_key = implode('||', [
            (string)($row['pid'] ?? ''),
            (string)($row['mnr'] ?? ''),
            (string)($row['wgp'] ?? ''),
            (string)($row['pas'] ?? ''),
            (string)($row['fna'] ?? ''),
            (string)($row['sna'] ?? ''),
            (string)($row['abt'] ?? ''),
            (string)($row['fsc'] ?? '')
        ]);

        if (!isset($grouped_verzeichnis[$group_key])) {
            $grouped_verzeichnis[$group_key] = $row;
        }
    }
    $final_verzeichnis_data = array_values($grouped_verzeichnis);
    error_log("Phase 13: Nach GROUP BY. Finale Zeilen: " . count($final_verzeichnis_data));


    // ORDER BY
    usort($final_verzeichnis_data, function($a, $b) {
        // verzeichnis.wgp ASC
        $cmp_wgp = ($a['wgp'] ?? PHP_INT_MAX) <=> ($b['wgp'] ?? PHP_INT_MAX);
        if ($cmp_wgp !== 0) return $cmp_wgp;

        // verzeichnis.sna ASC
        $cmp_sna = ($a['sna'] ?? '') <=> ($b['sna'] ?? '');
        if ($cmp_sna !== 0) return $cmp_sna;

        // verzeichnis.fna ASC
        $cmp_fna = ($a['fna'] ?? '') <=> ($b['fna'] ?? '');
        return $cmp_fna;
    });
    error_log("Phase 13: Nach ORDER BY. Finale Zeilen: " . count($final_verzeichnis_data));

    // SELECT
    $final_output_list = [];
    foreach ($final_verzeichnis_data as $row) {
        $final_output_list[] = [
            'personid' => $row['pid'],
            'matrikelnr' => $row['mnr'],
            'wählendengruppe' => $row['wgp'],
            'passiv' => $row['pas'],
            'vorname' => $row['fna'],
            'nachname' => $row['sna'],
            'abteilung' => $row['abt'],
            'fachschaft' => $row['fsc']
        ];
    }


    return $final_output_list;
}