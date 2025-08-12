<?php
function wvz_st_f(
    array $wvzData,
    array $infoWählGrTable,
    array $infoAbtTable, 
    array $infoFakTable,
    array $infoFachTable
): array {
    // --- Lookup-Maps für Info-Tabellen erstellen ---
    $wählGr_map = create_lookup_map($infoWählGrTable, 'nr'); // Join auf 'nr'
    $abt_map = create_lookup_map($infoAbtTable, 'name'); // Join auf 'name'
    $fak_map = create_lookup_map($infoFakTable, 'name'); // Join auf 'name'
    $fach_map = create_lookup_map($infoFachTable, 'name'); // Join auf 'name'
    error_log("Phase 14: Info-Tabellen Lookups erstellt.");

    $resultData = [];

    foreach ($wvzData as $wv_row) { // 'wv' ist der Alias aus der Access-Abfrage
        $current_row = $wv_row; // Daten aus dem Wählerverzeichnis

        // --- LEFT JOIN info_wählendengruppen AS wg ON wv.wählendengruppe = wg.nr ---
        $wgNrFromWv = (string)($current_row['wählendengruppe'] ?? null); // Aus der 'wgp' Spalte von Phase 13
        $wgInfo = $wählGr_map[$wgNrFromWv] ?? null;
        if ($wgInfo) {
            $current_row['wählendengruppe_description'] = $wgInfo['description'] ?? null;
            $current_row['wählendengruppe_nr_for_sort'] = $wgInfo['nr'] ?? null; // Holen für die Sortierung
        } else {
            $current_row['wählendengruppe_description'] = null;
            $current_row['wählendengruppe_nr_for_sort'] = null;
        }

        // --- LEFT JOIN info_abteilungen AS abt ON wv.abteilung = abt.name ---
        $abtNameFromWv = (string)($current_row['abteilung'] ?? null);
        $abtInfo = $abt_map[$abtNameFromWv] ?? null;
        if ($abtInfo) {
            $current_row['abteilungs_info_name'] = $abtInfo['name'] ?? null; // Abteilungs-Name aus Lookup
            $current_row['abteilungs_info_fakultaet_join_key'] = $abtInfo['fakultät'] ?? null; // Das ist der Schlüssel für den Join mit TBL_INFO_FAKULTÄTEN
        } else {
            $current_row['abteilungs_info_name'] = null;
            $current_row['abteilungs_info_fakultaet_join_key'] = null;
        }

        // --- LEFT JOIN info_fakultäten AS fak ON abt.fakultät = fak.name ---
        $fakKeyFromAbt = (string)($current_row['abteilungs_info_fakultaet_join_key'] ?? null);
        $fakInfo = $fak_map[$fakKeyFromAbt] ?? null;
        if ($fakInfo) {
            $current_row['fakultaet_description'] = $fakInfo['description'] ?? null;
        } else {
            $current_row['fakultaet_description'] = null;
        }

        // --- LEFT JOIN info_fachschaften AS fs ON wv.fachschaft = fs.name ---
        $fsNameFromWv = (string)($current_row['fachschaft'] ?? null);
        $fsInfo = $fach_map[$fsNameFromWv] ?? null;
        if ($fsInfo) {
            $current_row['fachschaft_description'] = $fsInfo['description'] ?? null;
        } else {
            $current_row['fachschaft_description'] = null;
        }

        // --- Final SELECT-Liste und Spaltenprojektion ---
        $resultData[] = [
            'personid' => $current_row['personid'] ?? null,
            'matrikelnr' => $current_row['matrikelnr'] ?? null,
            'wählendengruppe' => $current_row['wählendengruppe_description'] ?? null, // Beschreibung von wg
            'passiv' => $current_row['passiv'] ?? null,
            'vorname' => $current_row['vorname'] ?? null,
            'nachname' => $current_row['nachname'] ?? null,
            'abteilung' => $current_row['abteilungs_info_name'] ?? null, // Name der Abteilung aus Lookup
            'fachschaft' => $current_row['fachschaft_description'] ?? null, // Beschreibung der Fachschaft aus Lookup
            'fakultät' => $current_row['fakultaet_description'] ?? null, // Beschreibung der Fakultät
        ];
    }
    error_log("Phase 14: Nach Joins mit Info-Tabellen. Anzahl Zeilen: " . count($resultData));

    // --- ORDER BY Klausel ---
    usort($resultData, function($a, $b) {
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
    error_log("Phase 14: Nach Sortierung. Finale Zeilen: " . count($resultData));

    return $resultData;
}