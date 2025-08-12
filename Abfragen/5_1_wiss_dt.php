<?php

function filterWissDt(array $dataPhase4): array {
    $resultData = [];

    // Array für 'IN'-Bedingung
    $adbzIN = ['0060','0070','0072','0080','0090','0100','3472','3710','7090',];

    // --- Filterung (WHERE-Klausel) ---
    foreach ($dataPhase4 as $row) {
        $adbz = (string)($row['adbz'] ?? ''); 

        if (in_array($adbz, $adbzIN)) {
            $resultData[] = $row;
        }
    }
    error_log("Nach Phase 5 (Filterung Wiss). Anzahl Zeilen: " . count($resultData));

    // --- Sortierung (ORDER BY-Klausel) ---
    usort($resultData, function($a, $b) {
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
    error_log("Nach Phase 5 (Sortierung Wiss). Finale Zeilen: " . count($resultData));

    return $resultData;
}