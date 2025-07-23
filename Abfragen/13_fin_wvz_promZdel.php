<?php

function fin_wvz_promZdel(array $wahlenverzeichnisData, array $promoprobAkadMitarbeiterData): array {
    $personids_to_delete_set = [];

    // --- Schritt A: Liste der zu löschenden personids vorbereiten (entspricht dem Subselect im IN) ---
    // SELECT personid FROM Finish_Waehlerverzeichnis_promoprob_akademischMitarbeiter
    foreach ($promoprobAkadMitarbeiterData as $row) {
        $personid = $row['personid'] ?? null;
        if ($personid !== null) {
            $personids_to_delete_set[(string)$personid] = true; // Verwende Hash-Map für effizienten Lookup
        }
    }
    error_log("Phase 22: Set der zu 'löschenden' Person IDs erstellt mit " . count($personids_to_delete_set) . " Einträgen.");


    $filteredWahlenverzeichnis = []; // Hier werden die nicht-gelöschten Zeilen gesammelt

    // --- Schritt B: Array filtern (entspricht der DELETE ... WHERE ...) ---
    // Wir iterieren über die Originaldaten und kopieren nur die Zeilen, die NICHT gelöscht werden sollen.
    foreach ($wahlenverzeichnisData as $row) {
        $personid = (string)($row['personid'] ?? null);
        $wählendengruppe = $row['wählendengruppe'] ?? null;

        // Bedingungen für die "Löschung" nach der WHERE-Klausel:
        // 1. wählendenverzeichnis.personid in (select personid from Finish_Waehlerverzeichnis_promoprob_akademischMitarbeiter)
        $cond_in_delete_list = isset($personids_to_delete_set[$personid]);

        // 2. and wählendenverzeichnis.wählendengruppe="Promovierende"
        $cond_group_is_promovierende = ($wählendengruppe === "Promovierende");

        // Wenn BEIDE Bedingungen WAHR sind, soll die Zeile "gelöscht" werden (d.h. nicht in $filteredWahlenverzeichnis kopiert).
        // Also kopieren wir die Zeile, wenn die Gesamtbedingung FALSCH ist.
        if (!($cond_in_delete_list && $cond_group_is_promovierende)) {
            $filteredWahlenverzeichnis[] = $row; // Zeile wird beibehalten
        } else {
            error_log("Phase 22: Zeile 'gelöscht' (nicht kopiert): Person ID " . $personid . ", Gruppe " . $wählendengruppe);
        }
    }
    error_log("Phase 22: Finale Zeilen nach 'DELETE'-Filterung: " . count($filteredWahlenverzeichnis));

    return $filteredWahlenverzeichnis;
}