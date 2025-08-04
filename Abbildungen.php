<?php

$pdo = new PDO(
    "pgsql:host=localhost;port=5432;dbname=meine_datenbank",
    "benutzername",
    "passwort"
);

$stmt = $pdo->prepare(
    "SELECT id, vorname, nachmane 
            FROM nutzer 
            WHERE geburtstag <= TO_DATE('01.01.2004', 'DD.MM.YYYY')
            ORDER BY nachname");
$stmt->execute();
$result = $stmt->fetchAll(
    PDO::FETCH_ASSOC);

//------------------------------------------------
/* +++ Orchester +++ (aus fetch) */
// --- 11 --- liefert fin_wvz_promprob
$fin_wvz_promprob = fin_wvz_promprob($dataWvzStFEcum2020);

// --- 12 --- liefert fin_wvz_promprob_akad
$fin_wvz_promprob_akad = fin_wvz_promprob_akad(
    $dataWvzStFEcum2020, 
    $fin_wvz_promprob);

// --- 13 --- liefert fin_wvz_promZdel
$fin_wvz_promZdel = fin_wvz_promZdel(
    $dataWvzStFEcum2020, 
    $fin_wvz_promprob_akad);

// -- 14 --- liefert FachschaftLückenFürPromovierendeFüllen
$FSLueckFuerPromFuell = updateFakultaetPromovierende($fin_wvz_promZdel);

// --- 15 --- liefert Fin_WVZ
$finalesWaehlerverzeichnis = fin_wvz($FSLueckFuerPromFuell);


/* +++ Left-Join +++ (aus 7_)*/
// --- LEFT JOIN info_fachschaften AS fs ON wv.fachschaft = fs.name ---
        $fs_name_from_wv = (string)($current_row['fachschaft'] ?? null);
        $fs_info = $fachschaften_map[$fs_name_from_wv] ?? null;
        if ($fs_info) {
            $current_row['fachschaft_description'] = $fs_info['description'] ?? null;
        } else {
            $current_row['fachschaft_description'] = null;
        }

/* +++ Select +++ (aus 6_) */
// SELECT
    $final_output_list = [];
    foreach ($final_verzeichnis_data as $row) {
        $final_output_list[] = [
            'personid' => $row['pid'],
            'matrikelnr' => $row['mnr'],
            'wählendengruppe' => $row['wgp'],
            'passiv' => $row['pas'],
            'vorname' => $row['fna'],
            'nachname' => $row['sna'],
            'abteilung' => $row['abt'],
            'fachschaft' => $row['fsc']
        ];
    }
/* +++ WHERE +++ (aus 5_1_lehr)*/

$adbz_in_values = ['2882', '6720', '6730', '0570'];

    // --- Filterung (WHERE-Klausel) ---
    foreach ($data as $row) {
        $adbz = (string)($row['adbz'] ?? ''); 

        if (in_array($adbz, $adbz_in_values)) {
            $filteredData[] = $row;
        }
    }