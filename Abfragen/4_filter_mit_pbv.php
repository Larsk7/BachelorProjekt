<?php
function filterMitPbv(array $dataPhase3): array {
    $resultData = [];

    // Arrays für 'IN'-Bedingungen festlegen
    $pbuArtIn = ['38', '40', '41', '42', '64', '74', '78', '84', '86', '93', '94', '96', '98'];
    $pbvArtNotIn = ['0060', '0099'];

    // Select und Where
    foreach ($dataPhase3 as $row) {
        $pbv_monate = (int)($row['pbv_monate'] ?? 0); 
        $pbv_sum = (int)($row['pbv_sum'] ?? 0);       

        $pbu_von = $row['pbu_von'] ?? null;       
        $pbu_art = (string)($row['pbu_art'] ?? '');  
        $pbu_monate = (int)($row['pbu_monate'] ?? 0);
        $pbu_sum_val = (int)($row['pbu_sum'] ?? 0);  

        $pbv_art = (string)($row['pbv_art'] ?? '');   

        $cond1 = ($pbv_monate >= 6 || $pbv_sum >= 6);

        $cond2_A = is_null($pbu_von);
        $cond2_B = in_array($pbu_art, $pbuArtIn);
        $cond2_C = ($pbu_monate < 6 && (is_null($row['pbu_sum']) || $pbu_sum_val < 6)); // is_null($row['pbu_sum']) prüft den Originalwert
        $cond2 = ($cond2_A || $cond2_B || $cond2_C);

        $cond3 = !in_array($pbv_art, $pbvArtNotIn);

        if ($cond1 && $cond2 && $cond3) {
            $resultData[] = $row; 
        }
    }
    error_log("Nach Phase 4 (Filterung). Anzahl Zeilen: " . count($resultData));
    return $resultData;
}