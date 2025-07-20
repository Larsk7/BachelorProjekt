<?php

function wvz_st_f_ecum(
    array $wahlenverzeichnisStichtagData,
    array $dbConfigPortal,
    array $infoWaehlendengruppenTable
): array {// --- DB-Zugriff für mannheim.wahlen (wie vom Benutzer spezifiziert) ---
    $wahl_data = []; // Rohdaten von mannheim.wahlen
    $pdoPortal = null;
    try {
        $dsnPortal = "pgsql:host={$dbConfigPortal['host']};port={$dbConfigPortal['port']};dbname={$dbConfigPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfigPortal['user'], $dbConfigPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Wählen Sie HIER ALLE Spalten aus, die in der nachfolgenden PHP-Logik verwendet werden!
        // Also alle SELECT-Spalten, alle JOIN-ON-Spalten, alle WHERE-Spalten.
        // Die Access-Query verwendet: id, person_id, cardnumber, sequencenumber, lockdate
        $stmtWahlen = $pdoPortal->query("SELECT id, person_id, cardnumber, sequencenumber, lockdate FROM mannheim.wahlen;");
        $wahl_data = $stmtWahlen->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Portal DB Error (mannheim.wahlen): " . $e->getMessage());
        // Hier sollte eine Fehlerbehandlung erfolgen, ggf. Exception werfen oder leeres Array zurückgeben
        throw $e; // Werfe die Exception weiter, damit sie im Orchestrator abgefangen wird
    } finally {
        $pdoPortal = null;
    }
    error_log("mannheim.wahlen Rohdaten geladen. Anzahl Zeilen: " . count($wahl_data));


    // --- Vorbereitung der Lookup-Maps für den Join mit 'mw' ---
    $mannheim_wahlen_filtered_map = []; // Für (SELECT * FROM mannheim_wahlen WHERE lockdate Is Null) as mw
    foreach ($wahl_data as $mw_row) { // Nutzt jetzt $wahl_data
        if (is_null($mw_row['lockdate'])) {
            // Join-Bedingung ist wv.personid = mw.id
            $mw_id = $mw_row['id'] ?? null;
            if ($mw_id !== null) {
                // Berücksichtigt ORDER BY sequencenumber DESC für First()
                if (!isset($mannheim_wahlen_filtered_map[$mw_id]) ||
                    ($mw_row['sequencenumber'] ?? -PHP_INT_MAX) > ($mannheim_wahlen_filtered_map[$mw_id]['sequencenumber'] ?? -PHP_INT_MAX)) {
                    $mannheim_wahlen_filtered_map[$mw_id] = $mw_row;
                }
            }
        }
    }
    error_log("Phase 15: Mannheim Wahlen Lookup (keyed by 'id') erstellt mit " . count($mannheim_wahlen_filtered_map) . " Einträgen.");


    // Lookup für info_wählendengruppen, keyed by 'description'
    $waehlendengruppen_desc_map = create_lookup_map($infoWaehlendengruppenTable, 'description');
    error_log("Phase 15: Wählendengruppen (Description) Lookup erstellt mit " . count($waehlendengruppen_desc_map) . " Einträgen.");


    // --- Bau des inneren Subqueries 'l' ---
    $l_raw_data = [];
    foreach ($wahlenverzeichnisStichtagData as $wv_row) { // wv ist $finalOutputData von Phase 14
        $personid_from_wv = $wv_row['personid'] ?? null;
        
        // LEFT JOIN mit 'mw' (mannheim_wahlen) ON wv.personid = mw.id
        $mw_info = $mannheim_wahlen_filtered_map[$personid_from_wv] ?? null;

        // LEFT JOIN info_wählendengruppen AS wg ON wv.wählendengruppe = wg.description
        $wgp_description_from_wv = $wv_row['wählendengruppe'] ?? null;
        $wg_info = $waehlendengruppen_desc_map[$wgp_description_from_wv] ?? null;

        // Projektion der Spalten für den Subquery 'l'
        $l_row = [
            'personid' => $personid_from_wv,
            'cardnumber' => $mw_info['cardnumber'] ?? null,
            'sequencenumber' => $mw_info['sequencenumber'] ?? null,
            'matrikelnr' => $wv_row['matrikelnr'] ?? null,
            'wählendengruppe' => $wv_row['wählendengruppe'] ?? null,
            'nr' => $wg_info['nr'] ?? null, // wg.nr
            'passiv' => $wv_row['passiv'] ?? null,
            'vorname' => $wv_row['vorname'] ?? null,
            'nachname' => $wv_row['nachname'] ?? null,
            'fakultät' => $wv_row['fakultät'] ?? null,
            'fachschaft' => $wv_row['fachschaft'] ?? null,
        ];
        $l_raw_data[] = $l_row;
    }
    error_log("Phase 15: Inner Subquery 'l' Rohdaten erstellt. Anzahl Zeilen: " . count($l_raw_data));


    // --- Sortierung für First() Aggregation (ORDER BY sequencenumber DESC) ---
    usort($l_raw_data, function($a, $b) {
        $seq_a = $a['sequencenumber'] ?? -PHP_INT_MAX;
        $seq_b = $b['sequencenumber'] ?? -PHP_INT_MAX;
        return $seq_b <=> $seq_a;
    });
    error_log("Phase 15: Inner Subquery 'l' Daten nach sequencenumber DESC sortiert.");


    // --- GROUP BY l.personid, l.wählendengruppe (und Aggregation First(), &) ---
    $grouped_l_data = [];
    foreach ($l_raw_data as $l_row) {
        $group_key = implode('||', [
            (string)($l_row['personid'] ?? ''),
            (string)($l_row['wählendengruppe'] ?? '')
        ]);

        if (!isset($grouped_l_data[$group_key])) {
            $grouped_l_data[$group_key] = [
                'personid' => $l_row['personid'] ?? null,
                'cardnumber_first' => $l_row['cardnumber'] ?? null,
                'sequencenumber_first' => $l_row['sequencenumber'] ?? null,
                'matrikelnr' => $l_row['matrikelnr'] ?? null,
                'wählendengruppe' => $l_row['wählendengruppe'] ?? null,
                'nr_first' => $l_row['nr'] ?? null,
                'passiv_first' => $l_row['passiv'] ?? null,
                'vorname_first' => $l_row['vorname'] ?? null,
                'nachname_first' => $l_row['nachname'] ?? null,
                'fakultät_first' => $l_row['fakultät'] ?? null,
                'fachschaft_first' => $l_row['fachschaft'] ?? null,
            ];
        }
    }
    error_log("Phase 15: Daten nach GROUP BY aggregiert. Anzahl Gruppen: " . count($grouped_l_data));


    // --- Final SELECT-Liste und Ecumnr-Berechnung ---
    $final_output_pre_sort = [];
    foreach ($grouped_l_data as $l_agg_row) {
        $ecumnr_val = null;
        if (!is_null($l_agg_row['cardnumber_first']) && !is_null($l_agg_row['sequencenumber_first'])) {
            $ecumnr_val = (string)$l_agg_row['cardnumber_first'] . (string)$l_agg_row['sequencenumber_first'];
        }

        $final_output_pre_sort[] = [
            'personid' => $l_agg_row['personid'] ?? null,
            'ecumnr' => $ecumnr_val,
            'matrikelnr' => $l_agg_row['matrikelnr'] ?? null,
            'wählendengruppe' => $l_agg_row['wählendengruppe'] ?? null,
            'passiv' => $l_agg_row['passiv_first'] ?? null,
            'vorname' => $l_agg_row['vorname_first'] ?? null,
            'nachname' => $l_agg_row['nachname_first'] ?? null,
            'fakultät' => $l_agg_row['fakultät_first'] ?? null,
            'fachschaft' => $l_agg_row['fachschaft_first'] ?? null,
            
            // Sortierhilfen
            //'sort_nr' => $l_agg_row['nr_first'] ?? PHP_INT_MAX,
            //'sort_nachname' => $l_agg_row['nachname_first'] ?? '',
            //'sort_vorname' => $l_agg_row['vorname_first'] ?? '',
            //'sort_personid' => $l_agg_row['personid'] ?? PHP_INT_MAX,
        ];
    }


    // --- Outer ORDER BY ---
    usort($final_output_pre_sort, function($a, $b) {
        $cmp_nr = ($a['sort_nr'] ?? PHP_INT_MAX) <=> ($b['sort_nr'] ?? PHP_INT_MAX);
        if ($cmp_nr !== 0) return $cmp_nr;

        $cmp_nachname = ($a['sort_nachname'] ?? '') <=> ($b['sort_nachname'] ?? '');
        if ($cmp_nachname !== 0) return $cmp_nachname;

        $cmp_vorname = ($a['sort_vorname'] ?? '') <=> ($b['sort_vorname'] ?? '');
        if ($cmp_vorname !== 0) return $cmp_vorname;

        $cmp_personid = ($a['sort_personid'] ?? PHP_INT_MAX) <=> ($b['sort_personid'] ?? PHP_INT_MAX);
        return $cmp_personid;
    });
    error_log("Phase 15: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($final_output_pre_sort));

    return $final_output_pre_sort;
}