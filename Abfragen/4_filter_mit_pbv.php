<?php
function filterMitPbv(array $data): array {
    $filteredData = [];

    $pbu_art_in_values = ['38', '40', '41', '42', '64', '74', '78', '84', '86', '93', '94', '96', '98'];
    $pbv_art_not_in_values = ['0060', '0099'];

    foreach ($data as $row) {
        $pbv_monate = (int)($row['pbv_monate'] ?? 0); 
        $pbv_sum = (int)($row['pbv_sum'] ?? 0);       

        $pbu_von = $row['pbu_von'] ?? null;       
        $pbu_art = (string)($row['pbu_art'] ?? '');  
        $pbu_monate = (int)($row['pbu_monate'] ?? 0);
        $pbu_sum_val = (int)($row['pbu_sum'] ?? 0);  

        $pbv_art = (string)($row['pbv_art'] ?? '');   

        $condition1 = ($pbv_monate >= 6 || $pbv_sum >= 6);

        $condition2_partA = is_null($pbu_von);
        $condition2_partB = in_array($pbu_art, $pbu_art_in_values);
        $condition2_partC = ($pbu_monate < 6 && (is_null($row['pbu_sum']) || $pbu_sum_val < 6)); // is_null($row['pbu_sum']) prüft den Originalwert
        $condition2 = ($condition2_partA || $condition2_partB || $condition2_partC);

        $condition3 = !in_array($pbv_art, $pbv_art_not_in_values);

        if ($condition1 && $condition2 && $condition3) {
            $filteredData[] = $row; 
        }
    }
    error_log("Nach Phase 4 (Filterung). Anzahl Zeilen: " . count($filteredData));
    return $filteredData;
}