<?php

function wvz_st_f_ecum(
    array $wvzData,
    array $dbConfPortal,
    array $infoWähGrTable
): array {
    // --- DB-Zugriff für mannheim.wahlen  ---
    $wahl_data = []; // Rohdaten von mannheim.wahlen
    $pdoPortal = null;
    try {
        $dsnPortal = "pgsql:host={$dbConfPortal['host']};port={$dbConfPortal['port']};dbname={$dbConfPortal['dbname']}";
        $pdoPortal = new PDO($dsnPortal, $dbConfPortal['user'], $dbConfPortal['password']);
        $pdoPortal->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Alle benötigten Spalten der Quelldaten auswählen
        $stmtWahlen = $pdoPortal->query("SELECT id, person_id, cardnumber, sequencenumber, lockdate FROM mannheim.wahlen;");
        $wahl_data = $stmtWahlen->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Portal DB Error (mannheim.wahlen): " . $e->getMessage());
        throw $e;
    } finally {
        $pdoPortal = null;
    }
    error_log("mannheim.wahlen Rohdaten geladen. Anzahl Zeilen: " . count($wahl_data));

    // --- Vorbereitung der Lookup-Maps für den Join mit 'mw' ---
    $maWahlMap = []; // Für (SELECT * FROM mannheim_wahlen WHERE lockdate Is Null) as mw
    foreach ($wahl_data as $mw_row) { // Nutzt jetzt $wahl_data
        if ($mw_row['lockdate'] === null) {
            // Join-Bedingung ist wv.personid = mw.id
            $mw_id = $mw_row['person_id'] ?? null;
            if ($mw_id !== null) {
                // Berücksichtigt ORDER BY sequencenumber DESC für First()
                if (!isset($maWahlMap[$mw_id]) ||
                    ($mw_row['sequencenumber'] ?? -PHP_INT_MAX) > ($maWahlMap[$mw_id]['sequencenumber'] ?? -PHP_INT_MAX)) {
                    $maWahlMap[$mw_id] = $mw_row;
                }
            }
        }
    }
    error_log("Phase 15: Mannheim Wahlen Lookup (keyed by 'id') erstellt mit " . count($maWahlMap) . " Einträgen.");

    // Lookup für info_wählendengruppen, keyed by 'description'
    $wählGrDescMap = create_lookup_map($infoWähGrTable, 'description');
    error_log("Phase 15: Wählendengruppen (Description) Lookup erstellt mit " . count($wählGrDescMap) . " Einträgen.");

    // --- Bau des inneren Subqueries 'l' ---
    $lRawData = [];
    foreach ($wvzData as $wv_row) { // wv ist $finalOutputData von Phase 14
        $personidFromWv = $wv_row['personid'] ?? null;
        
        // LEFT JOIN mit 'mw' (mannheim_wahlen) ON wv.personid = mw.id
        $mwInfo = $maWahlMap[$personidFromWv] ?? null;

        // LEFT JOIN info_wählendengruppen AS wg ON wv.wählendengruppe = wg.description
        $wgDescrFromWv = $wv_row['wählendengruppe'] ?? null;
        $wgInfo = $wählGrDescMap[$wgDescrFromWv] ?? null;

        // Projektion der Spalten für den Subquery 'l'
        $lRow = [
            'personid' => $personidFromWv,
            'cardnumber' => $mwInfo['cardnumber'] ?? null,
            'sequencenumber' => $mwInfo['sequencenumber'] ?? null,
            'matrikelnr' => $wv_row['matrikelnr'] ?? null,
            'wählendengruppe' => $wv_row['wählendengruppe'] ?? null,
            'nr' => $wgInfo['nr'] ?? null, // wg.nr
            'passiv' => $wv_row['passiv'] ?? null,
            'vorname' => $wv_row['vorname'] ?? null,
            'nachname' => $wv_row['nachname'] ?? null,
            'fakultät' => $wv_row['fakultät'] ?? null,
            'fachschaft' => $wv_row['fachschaft'] ?? null,
        ];
        $lRawData[] = $lRow;
    }
    error_log("Phase 15: Inner Subquery 'l' Rohdaten erstellt. Anzahl Zeilen: " . count($lRawData));


    // --- Sortierung für First() Aggregation (ORDER BY sequencenumber DESC) ---
    usort($lRawData, function($a, $b) {
        $seq_a = $a['sequencenumber'] ?? -PHP_INT_MAX;
        $seq_b = $b['sequencenumber'] ?? -PHP_INT_MAX;
        return $seq_b <=> $seq_a;
    });
    error_log("Phase 15: Inner Subquery 'l' Daten nach sequencenumber DESC sortiert.");


    // --- GROUP BY l.personid, l.wählendengruppe (und Aggregation First(), &) ---
    $groupedData = [];
    foreach ($lRawData as $lRow) {
        $group_key = implode('||', [
            (string)($lRow['personid'] ?? ''),
            (string)($lRow['wählendengruppe'] ?? '')
        ]);

        if (!isset($groupedData[$group_key])) {
            $groupedData[$group_key] = [
                'personid' => $lRow['personid'] ?? null,
                'cardnumber_first' => $lRow['cardnumber'] ?? null,
                'sequencenumber_first' => $lRow['sequencenumber'] ?? null,
                'matrikelnr' => $lRow['matrikelnr'] ?? null,
                'wählendengruppe' => $lRow['wählendengruppe'] ?? null,
                'nr_first' => $lRow['nr'] ?? null,
                'passiv_first' => $lRow['passiv'] ?? null,
                'vorname_first' => $lRow['vorname'] ?? null,
                'nachname_first' => $lRow['nachname'] ?? null,
                'fakultät_first' => $lRow['fakultät'] ?? null,
                'fachschaft_first' => $lRow['fachschaft'] ?? null,
            ];
        }
    }
    error_log("Phase 15: Daten nach GROUP BY aggregiert. Anzahl Gruppen: " . count($groupedData));


    // --- Final SELECT-Liste und Ecumnr-Berechnung ---
    $resultData = [];
    foreach ($groupedData as $l_agg_row) {
        $ecumnr_val = null;
        if (!is_null($l_agg_row['cardnumber_first']) && !is_null($l_agg_row['sequencenumber_first'])) {
            $ecumnr_val = (string)$l_agg_row['cardnumber_first'] . (string)$l_agg_row['sequencenumber_first'];
        }

        $resultData[] = [
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
    usort($resultData, function($a, $b) {
        $cmp_nr = ($a['sort_nr'] ?? PHP_INT_MAX) <=> ($b['sort_nr'] ?? PHP_INT_MAX);
        if ($cmp_nr !== 0) return $cmp_nr;

        $cmp_nachname = ($a['sort_nachname'] ?? '') <=> ($b['sort_nachname'] ?? '');
        if ($cmp_nachname !== 0) return $cmp_nachname;

        $cmp_vorname = ($a['sort_vorname'] ?? '') <=> ($b['sort_vorname'] ?? '');
        if ($cmp_vorname !== 0) return $cmp_vorname;

        $cmp_personid = ($a['sort_personid'] ?? PHP_INT_MAX) <=> ($b['sort_personid'] ?? PHP_INT_MAX);
        return $cmp_personid;
    });
    error_log("Phase 15: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($resultData));

    return $resultData;
}