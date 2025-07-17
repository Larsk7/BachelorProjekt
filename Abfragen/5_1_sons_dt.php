<?php

function filterSonsDt(array $data): array {
    $filteredData = [];

    $adbz_excluded_values = [
        '2882', '6720', '6730', '0570',        
        '0060', '0070', '0072', '0080', '0090', '0100', '3472', '3710', '7090', 
        '2770', '5040', '3460',               
        '4940', '4950', '4955', '4960', '4970', '4980', '4990', '5000', '5010', '5020', '5030' 
    ];

    $pbv_art_excluded_values = [
        '0070', '0071', '0081', '0082', '0083', 
        '0099'                                 
    ];

    // --- Filterung (WHERE-Klausel) ---
   foreach ($data as $row) {
        $adbz = (string)($row['adbz'] ?? '');
        $pbv_art = (string)($row['pbv_art'] ?? '');

        $condition_adbz_ok = !in_array($adbz, $adbz_excluded_values);
        $condition_pbv_art_ok = !in_array($pbv_art, $pbv_art_excluded_values);

        if ($condition_adbz_ok && $condition_pbv_art_ok) {
            $filteredData[] = $row;
        }
    }
    error_log("Nach Filterung (Sons-Dt A). Anzahl Zeilen: " . count($filteredData));


    // --- Sortierung (ORDER BY-Klausel) ---
    usort($filteredData, function($a, $b) {
        // pbv_monate DESC
        $cmp_pbv_monate = ($b['pbv_monate'] ?? -PHP_INT_MAX) <=> ($a['pbv_monate'] ?? -PHP_INT_MAX);
        if ($cmp_pbv_monate !== 0) return $cmp_pbv_monate;

        // pbv_von ASC
        $cmp_pbv_von = ($a['pbv_von'] ?? '') <=> ($b['pbv_von'] ?? ''); 
        if ($cmp_pbv_von !== 0) return $cmp_pbv_von;

        // pbv_sum DESC
        $cmp_pbv_sum = ($b['pbv_sum'] ?? -PHP_INT_MAX) <=> ($a['pbv_sum'] ?? -PHP_INT_MAX); 
        return $cmp_pbv_sum;
    });
    error_log("Nach Phase 5 (Sortierung Wiss). Finale Zeilen: " . count($filteredData));

    return $filteredData;
}