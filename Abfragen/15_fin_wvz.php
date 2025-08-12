<?php

function fin_wvz(array $wvzData): array {
    // Werte für die NOT IN Klausel definieren (typo 'Sontige' beachtet)
    $excluded_groups = ["Hochschullehrende", "Sonstige Mitarbeitende", "Sontige Mitarbeitende"];

    // --- GROUP BY (wirkt wie DISTINCT auf alle SELECT-Spalten) ---
    $uniqueData = [];
    foreach ($wvzData as $row) {
        $groupKeyParts = [];
        // Erzeuge einen Schlüssel aus allen SELECT-Spalten für GROUP BY (DISTINCT)
        
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
        $groupKeyParts[] = (string)($row['enrollmentdate'] ?? '');
        $groupKeyParts[] = (string)($row['disenrollment_date'] ?? '');
        // Die duplizierten Spalten in Access GROUP BY (wählendengruppe, nachname) werden nicht erneut hinzugefügt.

        $groupKey = implode('||', $groupKeyParts);

        if (!isset($uniqueData[$groupKey])) {
            $uniqueData[$groupKey] = $row;
        }
    }
    $filteredData = array_values($uniqueData);
    error_log("Phase 24: Daten nach GROUP BY (DISTINCT) verarbeitet. Anzahl Zeilen: " . count($filteredData));


    // --- HAVING-Klausel anwenden ---
    // (((wählendenverzeichnis.wählendengruppe) Not In ("Hochschullehrende","Sonstige Mitarbeitende","Sontige Mitarbeitende")))
    $resultData = [];
    foreach ($filteredData as $row) {
        $waehlendengruppe = $row['wählendengruppe'] ?? null;

        $condGroupNotInExc = !in_array($waehlendengruppe, $excluded_groups);

        if ($condGroupNotInExc) {
            $resultData[] = $row;
        }
    }
    error_log("Phase 24: Daten nach HAVING-Klausel gefiltert. Anzahl Zeilen: " . count($resultData));


    // --- ORDER BY Klausel ---
    // wählendenverzeichnis.wählendengruppe, wählendenverzeichnis.nachname, wählendenverzeichnis.vorname
    usort($resultData, function($a, $b) {
        $cmp_wgp = ($a['wählendengruppe'] ?? '') <=> ($b['wählendengruppe'] ?? '');
        if ($cmp_wgp !== 0) return $cmp_wgp;

        $cmp_nachname = ($a['nachname'] ?? '') <=> ($b['nachname'] ?? '');
        if ($cmp_nachname !== 0) return $cmp_nachname;

        $cmp_vorname = ($a['vorname'] ?? '') <=> ($b['vorname'] ?? '');
        return $cmp_vorname;
    });
    error_log("Phase 24: Finale Daten nach ORDER BY sortiert. Finale Zeilen: " . count($resultData));
    
    return $resultData;
}