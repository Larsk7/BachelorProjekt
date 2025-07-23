<?php

function fin_wvz_promprob_akad(array $dataWvzStFEcum2020, array $finWvzPromprob): array {
    // --- Vorbereitung Lookup für Finish_Waehlerverzeichnis_PromoProb ---
    $fin_wvz_promprob_map = [];
    foreach ($finWvzPromprob as $row) {
        $personid = $row['personid'] ?? null;
        if ($personid !== null) {
            $fin_wvz_promprob_map[$personid] = $row; // Annahme: personid ist eindeutig in diesem Ergebnis
        }
    }
    error_log("Phase 21: Finish_Waehlerverzeichnis_PromoProb Lookup erstellt mit " . count($fin_wvz_promprob_map) . " Einträgen.");


    $joined_data_pre_group = [];
    foreach ($dataWvzStFEcum2020 as $wv_row) { // 'wv' ist der Alias
        $personid = $wv_row['personid'] ?? null;

        // LEFT JOIN Finish_Waehlerverzeichnis_PromoProb AS fwp ON wählendenverzeichnis.personid = Finish_Waehlerverzeichnis_PromoProb.personid
        $fwp_info = $fin_wvz_promprob_map[$personid] ?? null;

        $joined_row = [
            'personid' => $wv_row['personid'] ?? null,
            'ecumnr' => $wv_row['ecumnr'] ?? null,
            'matrikelnr' => $wv_row['matrikelnr'] ?? null,
            'vorname' => $wv_row['vorname'] ?? null,
            'nachname' => $wv_row['nachname'] ?? null,
            'username' => $wv_row['username'] ?? null,
            'wählendengruppe' => $wv_row['wählendengruppe'] ?? null,
            'passiv' => $wv_row['passiv'] ?? null,
            'fakultät' => $wv_row['fakultät'] ?? null,
            'fachschaft' => $wv_row['fachschaft'] ?? null,
            'LetzterWertvoncourse_of_study_longtext' => $wv_row['LetzterWertvoncourse_of_study_longtext'] ?? null,
            'enrollmentdate' => $wv_row['enrollmentdate'] ?? null,
            'disenrollment_date' => $wv_row['disenrollment_date'] ?? null,

            // Spalten aus dem JOIN:
            'fwp_Anzahlvonwählendengruppe' => $fwp_info['Anzahlvonwählendengruppe'] ?? null, // From Finish_Waehlerverzeichnis_PromoProb
        ];
        $joined_data_pre_group[] = $joined_row;
    }
    error_log("Phase 21: Daten nach LEFT JOIN vorbereitet. Anzahl Zeilen: " . count($joined_data_pre_group));


    // --- GROUP BY (wirkt wie DISTINCT auf alle SELECT-Spalten) ---
    // Der GROUP BY-Schlüssel muss alle Spalten des SELECT-Teils enthalten.
    $grouped_data = [];
    foreach ($joined_data_pre_group as $row) {
        $group_key_parts = [];
        // Erzeuge einen Schlüssel aus allen relevanten Spalten für GROUP BY
        // Die Reihenfolge muss konsistent sein.
        // Alle im SELECT genannten Spalten, plus die aus der HAVING und ORDER BY genutzten
        $group_key_parts[] = (string)($row['personid'] ?? '');
        $group_key_parts[] = (string)($row['ecumnr'] ?? '');
        $group_key_parts[] = (string)($row['matrikelnr'] ?? '');
        $group_key_parts[] = (string)($row['vorname'] ?? '');
        $group_key_parts[] = (string)($row['nachname'] ?? '');
        $group_key_parts[] = (string)($row['username'] ?? '');
        $group_key_parts[] = (string)($row['wählendengruppe'] ?? '');
        $group_key_parts[] = (string)($row['passiv'] ?? '');
        $group_key_parts[] = (string)($row['fakultät'] ?? '');
        $group_key_parts[] = (string)($row['fachschaft'] ?? '');
        $group_key_parts[] = (string)($row['LetzterWertvoncourse_of_study_longtext'] ?? '');
        $group_key_parts[] = (string)($row['enrollmentdate'] ?? '');
        $group_key_parts[] = (string)($row['disenrollment_date'] ?? '');
        $group_key_parts[] = (string)($row['fwp_Anzahlvonwählendengruppe'] ?? ''); // Auch im GROUP BY
        // wählendenverzeichnis.wählendengruppe und wählendenverzeichnis.nachname sind dupliziert, nur einmal verwenden
        // wählendenverzeichnis.vorname ist nicht im GROUP BY der Access Query, aber im ORDER BY.

        $group_key = implode('||', $group_key_parts);

        if (!isset($grouped_data[$group_key])) {
            $grouped_data[$group_key] = $row; // Die erste Zeile für diese Gruppe behalten
        }
    }
    $filtered_data_pre_having = array_values($grouped_data);
    error_log("Phase 21: Daten nach GROUP BY (DISTINCT) verarbeitet. Anzahl Zeilen: " . count($filtered_data_pre_having));


    // --- HAVING-Klausel anwenden ---
    // (((Finish_Waehlerverzeichnis_PromoProb.Anzahlvonwählendengruppe)>1) AND ((wählendenverzeichnis.wählendengruppe) In ("Promovierende")))
    $final_filtered_data = [];
    foreach ($filtered_data_pre_having as $row) {
        $anzahl_wgp = $row['fwp_Anzahlvonwählendengruppe'] ?? null;
        $wgp_name = $row['wählendengruppe'] ?? null;

        $cond_anzahl = (!is_null($anzahl_wgp) && $anzahl_wgp > 1);
        $cond_wgp_promovierende = ($wgp_name === "Promovierende"); // Vergleich direkt String, da es nur ein Wert ist

        if ($cond_anzahl && $cond_wgp_promovierende) {
            $final_filtered_data[] = $row;
        }
    }
    error_log("Phase 21: Daten nach HAVING-Klausel gefiltert. Anzahl Zeilen: " . count($final_filtered_data));


    // --- ORDER BY wählendenverzeichnis.wählendengruppe, wählendenverzeichnis.nachname, wählendenverzeichnis.vorname ---
    usort($final_filtered_data, function($a, $b) {
        $cmp_wgp = ($a['wählendengruppe'] ?? '') <=> ($b['wählendengruppe'] ?? '');
        if ($cmp_wgp !== 0) return $cmp_wgp;

        $cmp_nachname = ($a['nachname'] ?? '') <=> ($b['nachname'] ?? '');
        if ($cmp_nachname !== 0) return $cmp_nachname;

        $cmp_vorname = ($a['vorname'] ?? '') <=> ($b['vorname'] ?? '');
        return $cmp_vorname;
    });
    error_log("Phase 21: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($final_filtered_data));

    return $final_filtered_data;
}