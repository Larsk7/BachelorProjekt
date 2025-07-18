<?php
function wvz_st_f(
    array $wahlenverzeichnisStichtagData,
    array $infoWaehlendengruppenTable,
    array $infoAbteilungenTable, // Diese hat nun die Spalte 'fakultät'
    array $infoFakultaetenTable,
    array $infoFachschaftenTable
): array {
    // --- Lookup-Maps für Info-Tabellen erstellen ---
    $waehlendengruppen_map = create_lookup_map($infoWaehlendengruppenTable, 'nr'); // Join auf 'nr'
    $abteilungen_map = create_lookup_map($infoAbteilungenTable, 'name'); // Join auf 'name'
    $fakultaeten_map = create_lookup_map($infoFakultaetenTable, 'name'); // Join auf 'name'
    $fachschaften_map = create_lookup_map($infoFachschaftenTable, 'name'); // Join auf 'name'
    error_log("Phase 14: Info-Tabellen Lookups erstellt.");

    $finalDisplayData = [];

    foreach ($wahlenverzeichnisStichtagData as $wv_row) { // 'wv' ist der Alias aus der Access-Abfrage
        $current_row = $wv_row; // Daten aus dem Wählerverzeichnis

        // --- LEFT JOIN info_wählendengruppen AS wg ON wv.wählendengruppe = wg.nr ---
        $wgp_nr_from_wv = (string)($current_row['wählendengruppe'] ?? null); // Aus der 'wgp' Spalte von Phase 13
        $wg_info = $waehlendengruppen_map[$wgp_nr_from_wv] ?? null;
        if ($wg_info) {
            $current_row['wählendengruppe_description'] = $wg_info['description'] ?? null;
            $current_row['wählendengruppe_nr_for_sort'] = $wg_info['nr'] ?? null; // Holen für die Sortierung
        } else {
            $current_row['wählendengruppe_description'] = null;
            $current_row['wählendengruppe_nr_for_sort'] = null;
        }

        // --- LEFT JOIN info_abteilungen AS abt ON wv.abteilung = abt.name ---
        $abt_name_from_wv = (string)($current_row['abteilung'] ?? null);
        $abt_info = $abteilungen_map[$abt_name_from_wv] ?? null;
        if ($abt_info) {
            $current_row['abteilungs_info_name'] = $abt_info['name'] ?? null; // Abteilungs-Name aus Lookup
            // NEU: Holen Sie den 'fakultät'-Wert aus der TBL_INFO_ABTEILUNGEN
            $current_row['abteilungs_info_fakultaet_join_key'] = $abt_info['fakultät'] ?? null; // Das ist der Schlüssel für den Join mit TBL_INFO_FAKULTÄTEN
        } else {
            $current_row['abteilungs_info_name'] = null;
            $current_row['abteilungs_info_fakultaet_join_key'] = null;
        }

        // --- LEFT JOIN info_fakultäten AS fak ON abt.fakultät = fak.name ---
        // Jetzt verwenden wir den neuen Schlüssel aus der abteilungs_info_fakultaet_join_key
        $fakultaet_key_from_abt = (string)($current_row['abteilungs_info_fakultaet_join_key'] ?? null);
        $fak_info = $fakultaeten_map[$fakultaet_key_from_abt] ?? null;
        if ($fak_info) {
            $current_row['fakultaet_description'] = $fak_info['description'] ?? null;
        } else {
            $current_row['fakultaet_description'] = null;
        }

        // --- LEFT JOIN info_fachschaften AS fs ON wv.fachschaft = fs.name ---
        $fs_name_from_wv = (string)($current_row['fachschaft'] ?? null);
        $fs_info = $fachschaften_map[$fs_name_from_wv] ?? null;
        if ($fs_info) {
            $current_row['fachschaft_description'] = $fs_info['description'] ?? null;
        } else {
            $current_row['fachschaft_description'] = null;
        }

        // --- Final SELECT-Liste und Spaltenprojektion ---
        $finalDisplayData[] = [
            'personid' => $current_row['personid'] ?? null,
            'matrikelnr' => $current_row['matrikelnr'] ?? null,
            'wählendengruppe' => $current_row['wählendengruppe_description'] ?? null, // Beschreibung von wg
            'passiv' => $current_row['passiv'] ?? null,
            'vorname' => $current_row['vorname'] ?? null,
            'nachname' => $current_row['nachname'] ?? null,
            //'abteilung' => $current_row['abteilungs_info_name'] ?? null, // Name der Abteilung aus Lookup
            'fachschaft' => $current_row['fachschaft_description'] ?? null, // Beschreibung der Fachschaft aus Lookup
            'fakultät' => $current_row['fakultaet_description'] ?? null, // Beschreibung der Fakultät
            
            // Sortierhilfen (werden nicht im JSON ausgegeben, wenn Object.keys(data[0]) verwendet wird)
            //'sort_wgp_nr' => $current_row['wählendengruppe_nr_for_sort'] ?? null,
            //'sort_nachname' => $current_row['nachname'] ?? null,
            //'sort_vorname' => $current_row['vorname'] ?? null,
            //'sort_personid' => $current_row['personid'] ?? null,
        ];
    }
    error_log("Phase 14: Nach Joins mit Info-Tabellen. Anzahl Zeilen: " . count($finalDisplayData));


    // --- ORDER BY Klausel ---
    usort($finalDisplayData, function($a, $b) {
        // wg.nr ASC (nutzt den extrahierten sort_wgp_nr)
        $cmp_wgp_nr = ($a['sort_wgp_nr'] ?? PHP_INT_MAX) <=> ($b['sort_wgp_nr'] ?? PHP_INT_MAX); // NULL als größte behandeln für ASC
        if ($cmp_wgp_nr !== 0) return $cmp_wgp_nr;

        // wv.nachname ASC
        $cmp_nachname = ($a['sort_nachname'] ?? '') <=> ($b['sort_nachname'] ?? '');
        if ($cmp_nachname !== 0) return $cmp_nachname;

        // wv.vorname ASC
        $cmp_vorname = ($a['sort_vorname'] ?? '') <=> ($b['sort_vorname'] ?? '');
        if ($cmp_vorname !== 0) return $cmp_vorname;

        // wv.personid ASC
        $cmp_personid = ($a['sort_personid'] ?? PHP_INT_MAX) <=> ($b['sort_personid'] ?? PHP_INT_MAX); // Für numerische IDs
        return $cmp_personid;
    });
    error_log("Phase 14: Nach Sortierung. Finale Zeilen: " . count($finalDisplayData));


    return $finalDisplayData;
}