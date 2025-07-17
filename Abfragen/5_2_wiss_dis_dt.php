<?php

function WissDisDt(array $inputData): array {
    $groupedIntermediateData = [];

    foreach ($inputData as $row) {
        // GROUP BY Schlüssel
        $group_key = implode('||', [
            (string)($row['person_id'] ?? ''),
            (string)($row['registrationnumber_final'] ?? ''), 
            (string)($row['firstname'] ?? ''),
            (string)($row['surname'] ?? ''),
            (string)($row['personalnr'] ?? ''),
            (string)($row['student'] ?? '')
        ]);

        if (!isset($groupedIntermediateData[$group_key])) {
            $groupedIntermediateData[$group_key] = [
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
            $groupedIntermediateData[$group_key]['proz'] += (float)($row['proz'] ?? 0);
            if (($row['proz'] ?? 0) > 25) {
                $groupedIntermediateData[$group_key]['count_proz_gt_25'] += 1;
            }
        }
    }

    // --- HAVING-Klausel anwenden und 'passiv'-Spalte berechnen ---
    $finalAggregatedOutput = [];
    foreach ($groupedIntermediateData as $row) {
        $sum_proz_gt_25 = $row['count_proz_gt_25']; 

        if ($sum_proz_gt_25 > 0) { 
            $passiv_value = null;
            if (($row['proz'] ?? 0) >= 50 || ($row['pbu_art'] ?? '') == '78') { 
                $passiv_value = 1;
            }

            $finalAggregatedOutput[] = [
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

    error_log("Nach Phase 9 (Aggregation, HAVING & Passiv-Berechnung). Anzahl Zeilen: " . count($finalAggregatedOutput));
    return $finalAggregatedOutput;
}