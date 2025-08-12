<?php

function fin_wvz_promprob_akad(array $wvzData, array $dataPhase11): array {
    // --- Vorbereitung Lookup für Finish_Waehlerverzeichnis_PromoProb ---
    $wvz_map = [];
    foreach ($dataPhase11 as $row) {
        $personid = $row['personid'] ?? null;
        if ($personid !== null) {
            $wvz_map[$personid] = $row; // Annahme: personid ist eindeutig in diesem Ergebnis
        }
    }
    error_log("Phase 21: Finish_Waehlerverzeichnis_PromoProb Lookup erstellt mit " . count($wvz_map) . " Einträgen.");


    $joinedData = [];
    foreach ($wvzData as $wv_row) { // 'wv' ist der Alias
        $personid = $wv_row['personid'] ?? null;

        // LEFT JOIN Finish_Waehlerverzeichnis_PromoProb AS fwp ON wählendenverzeichnis.personid = Finish_Waehlerverzeichnis_PromoProb.personid
        $fwpInfo = $wvz_map[$personid] ?? null;

        $joinedRow = [
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
            'fwp_Anzahlvonwählendengruppe' => $fwpInfo['Anzahlvonwählendengruppe'] ?? null, // From Finish_Waehlerverzeichnis_PromoProb
        ];
        $joinedData[] = $joinedRow;
    }
    error_log("Phase 21: Daten nach LEFT JOIN vorbereitet. Anzahl Zeilen: " . count($joinedData));


    // --- GROUP BY (wirkt wie DISTINCT auf alle SELECT-Spalten) ---
    $groupedData = [];
    foreach ($joinedData as $row) {
        $groupKeyParts = [];
        // Erzeuge einen Schlüssel aus allen relevanten Spalten für GROUP BY
        // Alle im SELECT genannten Spalten, plus die aus der HAVING und ORDER BY genutzten
        $groupKeyParts[] = (string)($row['personid'] ?? '');
        $groupKeyParts[] = (string)($row['ecumnr'] ?? '');
        $groupKeyParts[] = (string)($row['matrikelnr'] ?? '');
        $groupKeyParts[] = (string)($row['vorname'] ?? '');
        $groupKeyParts[] = (string)($row['nachname'] ?? '');
        $groupKeyParts[] = (string)($row['username'] ?? '');
        $groupKeyParts[] = (string)($row['wählendengruppe'] ?? '');
        $groupKeyParts[] = (string)($row['passiv'] ?? '');
        $groupKeyParts[] = (string)($row['fakultät'] ?? '');
        $groupKeyParts[] = (string)($row['fachschaft'] ?? '');
        $groupKeyParts[] = (string)($row['LetzterWertvoncourse_of_study_longtext'] ?? '');
        $groupKeyParts[] = (string)($row['enrollmentdate'] ?? '');
        $groupKeyParts[] = (string)($row['disenrollment_date'] ?? '');
        $groupKeyParts[] = (string)($row['fwp_Anzahlvonwählendengruppe'] ?? ''); 

        $groupKey = implode('||', $groupKeyParts);

        if (!isset($groupedData[$groupKey])) {
            $groupedData[$groupKey] = $row; // Die erste Zeile für diese Gruppe behalten
        }
    }
    $filteredData = array_values($groupedData);
    error_log("Phase 21: Daten nach GROUP BY (DISTINCT) verarbeitet. Anzahl Zeilen: " . count($filteredData));


    // --- HAVING-Klausel anwenden ---
    // (((Finish_Waehlerverzeichnis_PromoProb.Anzahlvonwählendengruppe)>1) AND ((wählendenverzeichnis.wählendengruppe) In ("Promovierende")))
    $resultData = [];
    foreach ($filteredData as $row) {
        $anzahlWgp = $row['fwp_Anzahlvonwählendengruppe'] ?? null;
        $wgpName = $row['wählendengruppe'] ?? null;

        $condAnzahl = (!is_null($anzahlWgp) && $anzahlWgp > 1);
        $condWgpPromovierende = ($wgpName === "Promovierende"); // Vergleich direkt String, da es nur ein Wert ist

        if ($condAnzahl && $condWgpPromovierende) {
            $resultData[] = $row;
        }
    }
    error_log("Phase 21: Daten nach HAVING-Klausel gefiltert. Anzahl Zeilen: " . count($resultData));


    // --- ORDER BY wählendenverzeichnis.wählendengruppe, wählendenverzeichnis.nachname, wählendenverzeichnis.vorname ---
    usort($resultData, function($a, $b) {
        $cmp_wgp = ($a['wählendengruppe'] ?? '') <=> ($b['wählendengruppe'] ?? '');
        if ($cmp_wgp !== 0) return $cmp_wgp;

        $cmp_nachname = ($a['nachname'] ?? '') <=> ($b['nachname'] ?? '');
        if ($cmp_nachname !== 0) return $cmp_nachname;

        $cmp_vorname = ($a['vorname'] ?? '') <=> ($b['vorname'] ?? '');
        return $cmp_vorname;
    });
    error_log("Phase 21: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($resultData));

    return $resultData;
}