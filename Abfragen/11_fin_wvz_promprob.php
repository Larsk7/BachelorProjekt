<?php

function fin_wvz_promprob(array $inputData): array {
    $pre_filtered_data = [];
    $target_groups = ["Promovierende", "Akademische Mitarbeitende"];

    // --- WHERE-Klausel (Filterung vor Gruppierung) ---
    // WHERE (((wählendenverzeichnis.wählendengruppe) In ("Promovierende","Akademische Mitarbeitende")))
    foreach ($inputData as $row) {
        $waehlendengruppe = $row['wählendengruppe'] ?? null;
        if (in_array($waehlendengruppe, $target_groups)) {
            $pre_filtered_data[] = $row;
        }
    }
    error_log("Phase 20: Daten nach WHERE-Filterung. Anzahl Zeilen: " . count($pre_filtered_data));


    // --- GROUP BY wählendenverzeichnis.personid ---
    // und Count(wählendenverzeichnis.wählendengruppe) AS Anzahlvonwählendengruppe
    $grouped_counts = [];
    foreach ($pre_filtered_data as $row) {
        $personid = $row['personid'] ?? null;
        if ($personid !== null) {
            if (!isset($grouped_counts[$personid])) {
                // Erste Instanz für diese personid
                $grouped_counts[$personid] = [
                    'personid' => $personid,
                    'Anzahlvonwählendengruppe' => 0 // Initialisiere Zähler
                ];
            }
            $grouped_counts[$personid]['Anzahlvonwählendengruppe']++; // Inkrementiere Zähler
        }
    }
    error_log("Phase 20: Daten nach GROUP BY aggregiert. Anzahl Gruppen: " . count($grouped_counts));


    // --- HAVING-Klausel anwenden ---
    // HAVING (((Count(wählendenverzeichnis.wählendengruppe))>1));
    $final_filtered_groups = [];
    foreach ($grouped_counts as $person_data) {
        if (($person_data['Anzahlvonwählendengruppe'] ?? 0) > 1) {
            $final_filtered_groups[] = $person_data;
        }
    }
    error_log("Phase 20: Finale Daten nach HAVING gefiltert. Anzahl Zeilen: " . count($final_filtered_groups));

    return $final_filtered_groups;
}