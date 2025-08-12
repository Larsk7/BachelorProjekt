<?php

function fin_wvz_promprob(array $dataPhase9): array {
    $data = [];
    $groups = ["Promovierende", "Akademische Mitarbeitende"];

    // --- WHERE-Klausel (Filterung vor Gruppierung) ---
    // WHERE (((wählendenverzeichnis.wählendengruppe) In ("Promovierende","Akademische Mitarbeitende")))
    foreach ($dataPhase9 as $row) {
        $wählGr = $row['wählendengruppe'] ?? null;
        if (in_array($wählGr, $groups)) {
            $data[] = $row;
        }
    }
    error_log("Phase 20: Daten nach WHERE-Filterung. Anzahl Zeilen: " . count($data));

    // --- GROUP BY wählendenverzeichnis.personid ---
    // und Count(wählendenverzeichnis.wählendengruppe) AS Anzahlvonwählendengruppe
    $groupedCounts = [];
    foreach ($data as $row) {
        $personid = $row['personid'] ?? null;
        if ($personid !== null) {
            if (!isset($groupedCounts[$personid])) {
                // Erste Instanz für diese personid
                $groupedCounts[$personid] = [
                    'personid' => $personid,
                    'Anzahlvonwählendengruppe' => 0 // Initialisiere Zähler
                ];
            }
            $groupedCounts[$personid]['Anzahlvonwählendengruppe']++; // Inkrementiere Zähler
        }
    }
    error_log("Phase 20: Daten nach GROUP BY aggregiert. Anzahl Gruppen: " . count($groupedCounts));


    // --- HAVING-Klausel anwenden ---
    // HAVING (((Count(wählendenverzeichnis.wählendengruppe))>1));
    $resultData = [];
    foreach ($groupedCounts as $person_data) {
        if (($person_data['Anzahlvonwählendengruppe'] ?? 0) > 1) {
            $resultData[] = $person_data;
        }
    }
    error_log("Phase 20: Finale Daten nach HAVING gefiltert. Anzahl Zeilen: " . count($resultData));

    return $resultData;
}