<?php

function WissDisDt(array $dataPhase5_1wiss): array {
    $groupedData = [];

    foreach ($dataPhase5_1wiss as $row) {
        // GROUP BY Schlüssel
        $group_key = implode('||', [
            (string)($row['person_id'] ?? ''),
            (string)($row['registrationnumber_final'] ?? ''), 
            (string)($row['firstname'] ?? ''),
            (string)($row['surname'] ?? ''),
            (string)($row['personalnr'] ?? ''),
            (string)($row['student'] ?? '')
        ]);

        if (!isset($groupedData[$group_key])) {
            $groupedData[$group_key] = [
                'person_id' => $row['person_id'] ?? null,
                'registrationnumber' => $row['registrationnumber_final'] ?? null,
                'firstname' => $row['firstname'] ?? null,
                'surname' => $row['surname'] ?? null,
                'personalnr' => $row['personalnr'] ?? null,

                'institut' => $row['institut'] ?? null,    
                'abteilung' => $row['abteilungs_name'] ?? null, 
                'pbu_art' => $row['pbu_art'] ?? null,       
                'proz' => (float)($row['proz'] ?? 0),        
                'count_proz_gt_25' => (($row['proz'] ?? 0) > 25 ? 1 : 0) 
            ];
        } else {
            $groupedData[$group_key]['proz'] += (float)($row['proz'] ?? 0);
            if (($row['proz'] ?? 0) > 25) {
                $groupedData[$group_key]['count_proz_gt_25'] += 1;
            }
        }
    }

    // --- HAVING-Klausel anwenden und 'passiv'-Spalte berechnen ---
    $resultData = [];
    foreach ($groupedData as $row) {
        $sum_proz_gt_25 = $row['count_proz_gt_25']; 

        if ($sum_proz_gt_25 > 0) { 
            $passiv_value = null;
            if (($row['proz'] ?? 0) >= 50 || ($row['pbu_art'] ?? '') == '78') { 
                $passiv_value = 1;
            }

            // Select der Spalten für die finale Ausgabe
            $resultData[] = [
                'person_id' => $row['person_id'],
                'registrationnumber' => $row['registrationnumber'],
                'firstname' => $row['firstname'],
                'surname' => $row['surname'],
                'personalnr' => $row['personalnr'],
                'institut' => $row['institut'],
                'abteilung' => $row['abteilung'],
                'passiv' => $passiv_value
            ];
        }
    }

    error_log("Nach Phase 9 (Aggregation, HAVING & Passiv-Berechnung). Anzahl Zeilen: " . count($resultData));
    return $resultData;
}