<?php

function SonsDisDt(array $inputData): array {
    $groupedIntermediateData = [];

    foreach ($inputData as $row) {
        // GROUP BY Schlüssel (wie in der Access-Abfrage angegeben)
        $group_key = implode('||', [
            (string)($row['person_id'] ?? ''),
            (string)($row['registrationnumber_final'] ?? ''), // Berechneter Wert aus Phase 2
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

                // Aggregierte Werte 
                'institut' => $row['institut'] ?? null,     
                'abteilung' => $row['abteilungs_name'] ?? null, 
                'sum_proz' => (float)($row['proz'] ?? 0),         
                'count_proz_gt_25' => (($row['proz'] ?? 0) > 25 ? 1 : 0) 
            ];
        } else {
            $groupedIntermediateData[$group_key]['sum_proz'] += (float)($row['proz'] ?? 0);
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
            if (($row['sum_proz'] ?? 0) >= 50) { 
                $passiv_value = 1;
            }

            // Select der Spalten für die finale Ausgabe
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

    error_log("Nach Phase 10 (Sons(dis_dt) Aggregation, HAVING & Passiv-Berechnung). Anzahl Zeilen: " . count($finalAggregatedOutput));
    return $finalAggregatedOutput;
}