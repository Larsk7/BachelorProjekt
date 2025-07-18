<?php

function wvz_st_f_ecum(
    array $wahlenverzeichnisStichtagData,
    array $dbConfigPortal,
    array $infoWaehlendengruppenTable
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


    // Erstellen eines Lookups für info_wählendengruppen, keyed by 'description'
    // (Da der Join auf wv.wählendengruppe = wg.description stattfindet)
    $waehlendengruppen_desc_map = create_lookup_map($infoWaehlendengruppenTable, 'description');
    error_log("Phase 15: Wählendengruppen (Description) Lookup erstellt mit " . count($waehlendengruppen_desc_map) . " Einträgen.");


    // --- Bau des inneren Subqueries 'l' ---
    $l_raw_data = [];
    foreach ($wahlenverzeichnisStichtagData as $wv_row) { // wv ist $finalOutputData von Phase 14
        $personid = $wv_row['personid'] ?? null;
        
        // LEFT JOIN (SELECT * FROM mannheim_wahlen WHERE lockdate Is Null) as mw ON wv.personid = mw.person_id
        $mw_info = $wahl2_data[$personid] ?? null;

        // LEFT JOIN info_wählendengruppen AS wg ON wv.wählendengruppe = wg.description
        $wgp_description_from_wv = $wv_row['wählendengruppe'] ?? null; // Beschreibung wie 'Hochschullehrende'
        $wg_info = $waehlendengruppen_desc_map[$wgp_description_from_wv] ?? null;

        // Projektion der Spalten für den Subquery 'l'
        $l_row = [
            'personid' => $personid,
            'cardnumber' => $mw_info['cardnumber'] ?? null,
            'sequencenumber' => $mw_info['sequencenumber'] ?? null,
            'matrikelnr' => $wv_row['matrikelnr'] ?? null,
            'wählendengruppe' => $wv_row['wählendengruppe'] ?? null, // Die Beschreibung
            'nr' => $wg_info['nr'] ?? null, // wg.nr für das äußere ORDER BY
            'passiv' => $wv_row['passiv'] ?? null,
            'vorname' => $wv_row['vorname'] ?? null,
            'nachname' => $wv_row['nachname'] ?? null,
            'fakultät' => $wv_row['fakultät'] ?? null,
            'fachschaft' => $wv_row['fachschaft'] ?? null,
        ];
        $l_raw_data[] = $l_row;
    }
    error_log("Phase 15: Inner Subquery 'l' Rohdaten erstellt. Anzahl Zeilen: " . count($l_raw_data));

    // --- GROUP BY l.personid, l.wählendengruppe (und Aggregation First(), &) ---
    $grouped_l_data = [];
    foreach ($l_raw_data as $l_row) {
        $group_key = implode('||', [
            (string)($l_row['personid'] ?? ''),
            (string)($l_row['wählendengruppe'] ?? '') // Gruppiert nach Beschreibung
        ]);

        if (!isset($grouped_l_data[$group_key])) {
            // Erste Zeile für diese Gruppe, speichere die First() Werte
            $grouped_l_data[$group_key] = [
                'personid' => $l_row['personid'] ?? null,
                'cardnumber_first' => $l_row['cardnumber'] ?? null,
                'sequencenumber_first' => $l_row['sequencenumber'] ?? null,
                'matrikelnr' => $l_row['matrikelnr'] ?? null,
                'wählendengruppe' => $l_row['wählendengruppe'] ?? null,
                'nr_first' => $l_row['nr'] ?? null, // wg.nr
                'passiv_first' => $l_row['passiv'] ?? null,
                'vorname_first' => $l_row['vorname'] ?? null,
                'nachname_first' => $l_row['nachname'] ?? null,
                'fakultät_first' => $l_row['fakultät'] ?? null,
                'fachschaft_first' => $l_row['fachschaft'] ?? null,
            ];
        }
        // Weitere Zeilen für diese Gruppe ändern die First() Werte nicht
    }
    error_log("Phase 15: Daten nach GROUP BY aggregiert. Anzahl Gruppen: " . count($grouped_l_data));


    // --- Final SELECT-Liste und Ecumnr-Berechnung ---
    $final_output_pre_sort = [];
    foreach ($grouped_l_data as $l_agg_row) {
        // ecumnr: First(l.cardnumber) & First(l.sequencenumber)
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
            
            // Sortierhilfen (werden nicht im finalen JSON gezeigt, nur für usort)
            //'sort_nr' => $l_agg_row['nr_first'] ?? PHP_INT_MAX, // Für ORDER BY First(l.nr)
            //'sort_nachname' => $l_agg_row['nachname_first'] ?? '',
            //'sort_vorname' => $l_agg_row['vorname_first'] ?? '',
            //'sort_personid' => $l_agg_row['personid'] ?? PHP_INT_MAX,
        ];
    }


    // --- Outer ORDER BY ---
    usort($final_output_pre_sort, function($a, $b) {
        // First(l.nr) ASC
        $cmp_nr = ($a['sort_nr'] ?? PHP_INT_MAX) <=> ($b['sort_nr'] ?? PHP_INT_MAX);
        if ($cmp_nr !== 0) return $cmp_nr;

        // First(l.nachname) ASC
        $cmp_nachname = ($a['sort_nachname'] ?? '') <=> ($b['sort_nachname'] ?? '');
        if ($cmp_nachname !== 0) return $cmp_nachname;

        // First(l.vorname) ASC
        $cmp_vorname = ($a['sort_vorname'] ?? '') <=> ($b['sort_vorname'] ?? '');
        if ($cmp_vorname !== 0) return $cmp_vorname;

        // First(l.personid) ASC
        $cmp_personid = ($a['sort_personid'] ?? PHP_INT_MAX) <=> ($b['sort_personid'] ?? PHP_INT_MAX);
        return $cmp_personid;
    });
    error_log("Phase 15: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($final_output_pre_sort));

    return $final_output_pre_sort;
}