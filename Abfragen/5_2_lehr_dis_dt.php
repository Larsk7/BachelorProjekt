<?php

function aggregateAndFilterLehrDisDt(array $inputData): array {
    $groupedIntermediateData = [];

    foreach ($inputData as $row) {
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
                'student' => $row['student'] ?? null,
                
                'institut' => $row['institut'] ?? null,         
                'abteilung' => $row['abteilungs_name'] ?? null, 
                'pbu_art' => $row['pbu_art'] ?? null,           
                'proz' => (float)($row['proz'] ?? 0)           
            ];
        } else {
            $groupedIntermediateData[$group_key]['proz'] += (float)($row['proz'] ?? 0);
        }
    }

    // --- HAVING-Klausel anwenden ---
    $filteredAggregatedData = [];
    foreach ($groupedIntermediateData as $row) {
        $sum_proz = (float)($row['proz'] ?? 0); 
        $pbu_art_first = (string)($row['pbu_art'] ?? ''); 

        if ($sum_proz > 25 || $pbu_art_first == '78') {
            $filteredAggregatedData[] = [
                'person_id' => $row['person_id'],
                'registrationnumber' => $row['registrationnumber'],
                'firstname' => $row['firstname'],
                'surname' => $row['surname'],
                'personalnr' => $row['personalnr'],
                'institut' => $row['institut'],
                'abteilung' => $row['abteilung'],
                'pbu_art' => $pbu_art_first,
                'proz' => $sum_proz
            ];
        }
    }

    error_log("Nach Phase 6 (Aggregation und HAVING Lehr). Anzahl Zeilen: " . count($filteredAggregatedData));
    return $filteredAggregatedData;
}

function SelectLehrDisDt(array $data): array {
    $finalOutput = [];

    foreach ($data as $p) { 
        $passiv_value = null;
        if (($p['proz'] ?? 0) >= 50 || ($p['pbu_art'] ?? '') == '78') {
            $passiv_value = 1;
        }

        $finalOutput[] = [
            'person_id' => $p['person_id'] ?? null,
            'registrationnumber' => $p['registrationnumber'] ?? null,
            'firstname' => $p['firstname'] ?? null,
            'surname' => $p['surname'] ?? null,
            'personalnr' => $p['personalnr'] ?? null,
            'institut' => $p['institut'] ?? null,
            'abteilung' => $p['abteilung'] ?? null,
            'passiv' => $passiv_value 
        ];
    }
    error_log("Nach Phase 7 (Finale Transformation). Anzahl Zeilen: " . count($finalOutput));
    return $finalOutput;
}