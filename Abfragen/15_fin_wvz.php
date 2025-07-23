<?php

function fin_wvz(array $inputData): array {
    $pre_filtered_data = [];
    // Werte für die NOT IN Klausel definieren (typo 'Sontige' beachtet)
    $excluded_groups = ["Hochschullehrende", "Sonstige Mitarbeitende", "Sontige Mitarbeitende"];

    // --- GROUP BY (wirkt wie DISTINCT auf alle SELECT-Spalten) ---
    // Da alle SELECT-Spalten auch im GROUP BY stehen, sammeln wir einfach eindeutige Zeilen.
    $unique_data_pre_having = [];
    foreach ($inputData as $row) {
        $group_key_parts = [];
        // Erzeuge einen Schlüssel aus allen SELECT-Spalten für GROUP BY (DISTINCT)
        // Die Reihenfolge der Spalten im group_key muss konsistent sein.
        // Die Access GROUP BY Klausel ist hier: personid, ecumnr, matrikelnr, vorname, nachname, username,
        // wählendengruppe, passiv, fakultät, fachschaft, enrollmentdate, disenrollment_date,
        // (wählendengruppe, nachname sind redundant in Access, hier nur einmal nutzen)
        
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
        $group_key_parts[] = (string)($row['enrollmentdate'] ?? '');
        $group_key_parts[] = (string)($row['disenrollment_date'] ?? '');
        // Die duplizierten Spalten in Access GROUP BY (wählendengruppe, nachname) werden nicht erneut hinzugefügt.

        $group_key = implode('||', $group_key_parts);

        if (!isset($unique_data_pre_having[$group_key])) {
            $unique_data_pre_having[$group_key] = $row;
        }
    }
    $filtered_data_pre_having = array_values($unique_data_pre_having);
    error_log("Phase 24: Daten nach GROUP BY (DISTINCT) verarbeitet. Anzahl Zeilen: " . count($filtered_data_pre_having));


    // --- HAVING-Klausel anwenden ---
    // (((wählendenverzeichnis.wählendengruppe) Not In ("Hochschullehrende","Sonstige Mitarbeitende","Sontige Mitarbeitende")))
    $final_filtered_data = [];
    foreach ($filtered_data_pre_having as $row) {
        $waehlendengruppe = $row['wählendengruppe'] ?? null;

        $cond_group_not_in_excluded = !in_array($waehlendengruppe, $excluded_groups);

        if ($cond_group_not_in_excluded) {
            $final_filtered_data[] = $row;
        }
    }
    error_log("Phase 24: Daten nach HAVING-Klausel gefiltert. Anzahl Zeilen: " . count($final_filtered_data));


    // --- ORDER BY Klausel ---
    // wählendenverzeichnis.wählendengruppe, wählendenverzeichnis.nachname, wählendenverzeichnis.vorname
    usort($final_filtered_data, function($a, $b) {
        $cmp_wgp = ($a['wählendengruppe'] ?? '') <=> ($b['wählendengruppe'] ?? '');
        if ($cmp_wgp !== 0) return $cmp_wgp;

        $cmp_nachname = ($a['nachname'] ?? '') <=> ($b['nachname'] ?? '');
        if ($cmp_nachname !== 0) return $cmp_nachname;

        $cmp_vorname = ($a['vorname'] ?? '') <=> ($b['vorname'] ?? '');
        return $cmp_vorname;
    });
    error_log("Phase 24: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($final_filtered_data));

    return $final_filtered_data;
}