<?php

global $sva4_inst_lookup_map;
$sva4_inst_lookup_map = [];

function php_DateAddDay(?string $dateString, int $days): ?string {
    if (empty($dateString)) {
        return null;
    }
    try {
        $date = new DateTime($dateString);
        $date->modify("$days day"); 
        return $date->format('Y-m-d');
    } catch (Exception $e) {
        error_log("Fehler in php_DateAddDay für Datum '$dateString' ($days Tage): " . $e->getMessage());
        return null;
    }
}
function php_FullMonthDiff(?string $date1String, ?string $date2String): ?int {
    if (is_null($date1String)) {
        return null;
    }
    if (is_null($date2String)) {
        return 9999; 
    }
    try {
        $dateA = new DateTime($date1String);
        $dateB = new DateTime($date2String);

        $yearDiff = (int)$dateB->format('Y') - (int)$dateA->format('Y');
        $monthDiff = (int)$dateB->format('m') - (int)$dateA->format('m');
        $dayDiff = (int)$dateB->format('d') - (int)$dateA->format('d');

        $fullMonths = $yearDiff * 12 + $monthDiff;

        if ($dayDiff < 0) {
            $fullMonths--;
        }
        
        return $fullMonths;

    } catch (Exception $e) {
        error_log("Fehler in php_FullMonthDiff für '$date1String', '$date2String': " . $e->getMessage());
        return null;
    }
}
function php_MultiplePbvsOver6Months(int $persnr, int $pbvnr, string $stichtag, array $all_pbv_data, array $all_pbl_data): int {
    $multiplePbvsOver6Months = 0;

    $stichtag_dt = new DateTime($stichtag);

    $dateStart_dt = clone $stichtag_dt;
    $dateStart_dt->modify('-12 months');
    $dateStart_str = $dateStart_dt->format('Y-m-d');

    $dateEnd_dt = clone $stichtag_dt;
    $dateEnd_dt->modify('+12 months');
    $dateEnd_str = $dateEnd_dt->format('Y-m-d');
    
    $zeitraumStart = $dateStart_str;
    $zeitraumEnd = $dateEnd_str;

    $target_adbz = null;
    foreach ($all_pbv_data as $pbv_row) {
        if (($pbv_row['pbv_pgd_join_id'] ?? null) == $persnr && ($pbv_row['pbv_nr'] ?? null) == $pbvnr && ($pbv_row['pbv_status'] ?? null) == 0) {
            foreach ($all_pbl_data as $pbl_row) {
                if (($pbl_row['pbl_pgd_join_id'] ?? null) == ($pbv_row['pbv_pgd_join_id'] ?? null) && ($pbl_row['pbl_pbv_nr'] ?? null) == ($pbv_row['pbv_nr'] ?? null)) {
                    if (($pbl_row['pbl_status'] ?? null) == 0 && ($pbv_row['pbv_von'] ?? '') <= $stichtag && (is_null($pbv_row['pbv_bis']) || ($pbv_row['pbv_bis'] ?? '') >= $stichtag) && ($pbl_row['pbl_von'] ?? '') <= $stichtag && (is_null($pbl_row['pbl_bis']) || ($pbl_row['pbl_bis'] ?? '') >= $stichtag)) {
                        $target_adbz = $pbl_row['pbl_adt_bez'] ?? null;
                        break 2; 
                    }
                }
            }
        }
    }

    if (is_null($target_adbz)) {
        return $multiplePbvsOver6Months; 
    }
    $relevant_records = [];
    foreach ($all_pbv_data as $pbv_row) {
        if (($pbv_row['pbv_pgd_join_id'] ?? null) == $persnr && ($pbv_row['pbv_status'] ?? null) == 0) {
            foreach ($all_pbl_data as $pbl_row) {
                if (($pbl_row['pbl_pgd_join_id'] ?? null) == ($pbv_row['pbv_pgd_join_id'] ?? null) && ($pbl_row['pbl_pbv_nr'] ?? null) == ($pbv_row['pbv_nr'] ?? null)) {
                    if (($pbl_row['pbl_adt_bez'] ?? null) == $target_adbz && ($pbl_row['pbl_status'] ?? null) == 0) {
                        $pbv_von = $pbv_row['pbv_von'] ?? null;
                        $pbv_bis = $pbv_row['pbv_bis'] ?? null;
                        $pbl_von = $pbl_row['pbl_von'] ?? null;
                        $pbl_bis = $pbl_row['pbl_bis'] ?? null;

                        $pbv_date_ok = ( ( $pbv_von < $zeitraumStart && (is_null($pbv_bis) || $pbv_bis >= $zeitraumStart) ) || ( $pbv_von >= $zeitraumStart && $pbv_von <= $zeitraumEnd ) );
                        $pbl_date_ok = ( ( $pbl_von < $zeitraumStart && (is_null($pbl_bis) || $pbl_bis >= $zeitraumStart) ) || ( $pbl_von >= $zeitraumStart && $pbl_von <= $zeitraumEnd ) );

                        if ($pbv_date_ok && $pbl_date_ok) {
                            $relevant_records[] = $pbv_row; 
                            break;
                        }
                    }
                }
            }
        }
    }

    usort($relevant_records, function($a, $b) {
        $cmp_von = ($a['pbv_von'] ?? '') <=> ($b['pbv_von'] ?? '');
        if ($cmp_von !== 0) return $cmp_von;
        
        // KORREKTUR: $bis_a muss aus $a['pbv_bis'] kommen
        $bis_a = $a['pbv_bis'] ?? '9999-12-31'; 
        $bis_b = $b['pbv_bis'] ?? '9999-12-31';
        return $bis_a <=> $bis_b;
    });

    $arbeitStart = null;
    $arbeitEnd = null;

    if (!empty($relevant_records)) {
        foreach ($relevant_records as $r_row) {
            $r_pbv_von = $r_row['pbv_von'] ?? null;
            $r_pbv_bis = $r_row['pbv_bis'] ?? null;

            if (!is_null($r_pbv_von) && ($r_pbv_von <= $stichtag) && (is_null($r_pbv_bis) || ($r_pbv_bis >= $stichtag))) {
                $arbeitStart = $r_pbv_von;
                $arbeitEnd = $r_pbv_bis;
                break; 
            }
        }

        if (!is_null($arbeitStart)) { 
            $again = true;
            while ($again) {
                $again = false;
                foreach ($relevant_records as $r_row) {
                    $r_pbv_von = $r_row['pbv_von'] ?? null;
                    $r_pbv_bis = $r_row['pbv_bis'] ?? null;

                    if (!is_null($r_pbv_bis) && php_DateAddDay($r_pbv_bis, -1) == $arbeitStart) {
                        $arbeitStart = $r_pbv_von;
                        $again = true;
                    } 
                    
                    elseif (!is_null($r_pbv_von) && php_DateAddDay($r_pbv_von, -1) == $arbeitEnd) {
                        $arbeitEnd = $r_pbv_bis; 
                        $again = true;
                    }
                }
            }
        }
    }

    if (!is_null($arbeitStart)) {
        $end_date_for_diff = (is_null($arbeitEnd) || $arbeitEnd === 0) ? null : $arbeitEnd; 
        return php_FullMonthDiff($arbeitStart, php_DateAddDay($end_date_for_diff, 1));
    }

    return $multiplePbvsOver6Months; 
}
function php_MultiplePbusOver6Months(int $persnr, int $pbvnr, string $stichtag, array $all_pbu_data): int {
    $multiplePbusOver6Months = 0; 

    $stichtag_dt = new DateTime($stichtag);

    $dateStart_dt = clone $stichtag_dt;
    $dateStart_dt->modify('-12 months');
    $dateStart_str = $dateStart_dt->format('Y-m-d');

    $dateEnd_dt = clone $stichtag_dt;
    $dateEnd_dt->modify('+12 months');
    $dateEnd_str = $dateEnd_dt->format('Y-m-d');

    $zeitraumStart = $dateStart_str;
    $zeitraumEnd = $dateEnd_str;

    $relevant_records = [];
    foreach ($all_pbu_data as $pbu_row) {
        if (($pbu_row['pbu_pgd_join_id'] ?? null) == $persnr && ($pbu_row['pbu_status'] ?? null) == 0) {
            $pbu_von = $pbu_row['pbu_von'] ?? null;
            $pbu_bis = $pbu_row['pbu_bis'] ?? null;

            $pbu_date_ok = ( ( $pbu_von < $zeitraumStart && (is_null($pbu_bis) || $pbu_bis >= $zeitraumStart) ) || ( $pbu_von >= $zeitraumStart && $pbu_von <= $zeitraumEnd ) );
            
            if ($pbu_date_ok) {
                $relevant_records[] = $pbu_row;
            }
        }
    }

    usort($relevant_records, function($a, $b) {
        $cmp_von = ($a['pbu_von'] ?? '') <=> ($b['pbu_von'] ?? '');
        if ($cmp_von !== 0) return $cmp_von;
        $bis_a = $a['pbu_bis'] ?? '9999-12-31'; 
        $bis_b = $b['pbu_bis'] ?? '9999-12-31';
        return $bis_a <=> $bis_b;
    });

    $urlaubStart = null;
    $urlaubEnd = null;

    if (!empty($relevant_records)) {
        foreach ($relevant_records as $r_row) {
            $r_pbu_von = $r_row['pbu_von'] ?? null;
            $r_pbu_bis = $r_row['pbu_bis'] ?? null;

            if (!is_null($r_pbu_von) && ($r_pbu_von <= $stichtag) && (is_null($r_pbu_bis) || ($r_pbu_bis >= $stichtag))) {
                $urlaubStart = $r_pbu_von;
                $urlaubEnd = $r_pbu_bis;
                break; 
            }
        }

        if (!is_null($urlaubStart)) { 
            $again = true;
            while ($again) {
                $again = false;
                foreach ($relevant_records as $r_row) {
                    $r_pbu_von = $r_row['pbu_von'] ?? null;
                    $r_pbu_bis = $r_row['pbu_bis'] ?? null;

                    if (!is_null($r_pbu_bis) && php_DateAddDay($r_pbu_bis, -1) == $urlaubStart) {
                        $urlaubStart = $r_pbu_von;
                        $again = true;
                    } 
                    
                    elseif (!is_null($r_pbu_von) && php_DateAddDay($r_pbu_von, -1) == $urlaubEnd) {
                        $urlaubEnd = $r_pbu_bis;
                        $again = true;
                    }
                }
            }
        }
    }

    // return php_FullMonthDiff($urlaubStart, php_DateAddDay($urlaubEnd, 1)); 
    $result = php_FullMonthDiff($urlaubStart, php_DateAddDay($urlaubEnd, 1));
    return $result ?? 0;

}
function php_GetParentInst($instNr): mixed {
    global $sva4_inst_lookup_map;

    if (is_null($instNr) || $instNr === '') {
        return null;
    }

    if (empty($sva4_inst_lookup_map)) {
        error_log("WARNUNG: php_GetParentInst: \$sva4_inst_lookup_map ist leer. inst_nr: $instNr. Stellen Sie sicher, dass sva4.inst in fetch_voter_data.php vorgeladen wurde.");
        return null;
    }

    if (isset($sva4_inst_lookup_map[$instNr])) {
        return $sva4_inst_lookup_map[$instNr]['uebinst_nr'] ?? null;
    }

    return null;
}
function php_GetParentAbt($instNr, $recursionDepth = 0): mixed {
    if ($recursionDepth > 100) { 
        error_log("WARNUNG: php_GetParentAbt: Maximale Rekursionstiefe ($recursionDepth) erreicht für Instanz $instNr.");
        return null;
    }

    if (is_null($instNr) || $instNr === '') {
        return null;
    }

    $parentFak = php_GetParentInst($instNr);

    if (is_null($parentFak)) {
        return null;
    }

    $relevantAbteilungen = [
        "1010000000", "1010100000", "1010200000", "1020000000",
        "1030100000", "1030200000", "1040000000", "1050000000", "1060000000"
    ];

    if (in_array($parentFak, $relevantAbteilungen)) {
        return $parentFak;
    } else {
        return php_GetParentAbt($parentFak, $recursionDepth + 1);
    }
}

