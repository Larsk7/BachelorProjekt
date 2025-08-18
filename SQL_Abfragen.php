<?php
const SQL_SVA = "
WITH hilf_pfi_stichtag AS (
    SELECT *
    FROM sva4.pfi AS pfi
    WHERE (((pfi.pfi_von) <= :stichtag) AND (pfi.pfi_bis IS NULL OR (pfi.pfi_bis) >= :stichtag)) AND pfi_status = 0
),
hilf_pfi_stichtag_max AS (
    SELECT DISTINCT pfi.*
    FROM hilf_pfi_stichtag AS pfi LEFT JOIN hilf_pfi_stichtag AS pfi2 ON (pfi.pfi_pbv_nr = pfi2.pfi_pbv_nr) AND
        (pfi.pfi_pgd_join_id = pfi2.pfi_pgd_join_id AND pfi.pfi_prozent < pfi2.pfi_prozent AND pfi.pfi_status = pfi2.pfi_status)
    WHERE pfi2.pfi_serial IS NULL AND pfi.pfi_status = 0
),
hilf_pfi_stichtag_max_prio AS (
    SELECT DISTINCT pfi.*
    FROM hilf_pfi_stichtag_max AS pfi LEFT JOIN hilf_pfi_stichtag_max AS pfi2 ON (pfi.pfi_pbv_nr = pfi2.pfi_pbv_nr) 
        AND (pfi.pfi_pgd_join_id = pfi2.pfi_pgd_join_id AND pfi.pfi_von > pfi2.pfi_von AND pfi.pfi_status = pfi2.pfi_status)
    WHERE pfi2.pfi_serial IS NULL
),
hilf_pfi_stichtag_max_prio_sva AS (
    SELECT DISTINCT 
        pbv.pbv_nr, pbv.pbv_von, pbv.pbv_bis, pbv.pbv_art, 
        pbu.pbu_von, pbu.pbu_bis, pbu.pbu_art,           
        paz.paz_tz_proz AS proz,                           
        pbl.pbl_adt_bez AS adbz,                            
        prio.poz_institut AS institut,
        SUBSTRING(prio.poz_institut FROM 1 FOR 3) AS bereich_kennung,
        pbv.pbv_pgd_join_id AS hisrm_join_key_id 
                                            
    FROM hilf_pfi_stichtag_max_prio AS prio
        INNER JOIN sva4.pbv AS pbv
            ON (prio.pfi_pbv_nr = pbv.pbv_nr) AND (prio.pfi_pgd_join_id = pbv.pbv_pgd_join_id)
        INNER JOIN sva4.pbl AS pbl
            ON (pbv.pbv_pgd_join_id = pbl.pbl_pgd_join_id) AND (pbv.pbv_nr = pbl.pbl_pbv_nr)
        INNER JOIN sva4.paz AS paz
            ON (paz.paz_pbv_nr = pbv.pbv_nr) AND (paz.paz_pgd_join_id = pbv.pbv_pgd_join_id)
        LEFT JOIN sva4.pbu AS pbu
            ON (pbv.pbv_pgd_join_id = pbu.pbu_pgd_join_id) AND (pbv.pbv_nr = pbu.pbu_pbv_nr)

    WHERE ((pbv.pbv_von)::date <= :stichtag::date AND (pbv.pbv_bis IS NULL OR (pbv.pbv_bis)::date >= :stichtag::date))
        AND (pbv.pbv_status = 0)
        AND (paz.paz_status = 0)
        AND ((paz.paz_von)::date <= :stichtag::date AND (paz.paz_bis IS NULL OR (paz.paz_bis)::date >= :stichtag::date))
        AND (pbl.pbl_status = 0)
        AND ((pbl.pbl_von)::date <= :stichtag::date AND (pbl.pbl_bis IS NULL OR (pbl.pbl_bis)::date >= :stichtag::date))
        AND (prio.pfi_status = 0)
        AND ((prio.pfi_von)::date <= :stichtag::date AND (prio.pfi_bis IS NULL OR (prio.pfi_bis)::date >= :stichtag::date))
)
SELECT * FROM hilf_pfi_stichtag_max_prio_sva;         
";

const SQL_SVA2 = "
WITH hilf_pfi_stichtag AS (
    SELECT *
    FROM sva4.pfi AS pfi
    WHERE (((pfi.pfi_von) <= :stichtag) 
        AND (pfi.pfi_bis IS NULL OR (pfi.pfi_bis) >= :stichtag)) AND pfi_status = 0
),
hilf_pfi_stichtag_max AS (
    SELECT DISTINCT pfi.*
    FROM hilf_pfi_stichtag AS pfi LEFT JOIN hilf_pfi_stichtag AS pfi2 ON (pfi.pfi_pbv_nr = pfi2.pfi_pbv_nr) AND
        (pfi.pfi_pgd_join_id = pfi2.pfi_pgd_join_id AND pfi.pfi_prozent < pfi2.pfi_prozent AND pfi.pfi_status = pfi2.pfi_status)
    WHERE pfi2.pfi_serial IS NULL AND pfi.pfi_status = 0
),
hilf_pfi_stichtag_max_prio AS (
    SELECT DISTINCT pfi.*
    FROM hilf_pfi_stichtag_max AS pfi LEFT JOIN hilf_pfi_stichtag_max AS pfi2 ON (pfi.pfi_pbv_nr = pfi2.pfi_pbv_nr) 
        AND (pfi.pfi_pgd_join_id = pfi2.pfi_pgd_join_id AND pfi.pfi_von > pfi2.pfi_von AND pfi.pfi_status = pfi2.pfi_status)
    WHERE pfi2.pfi_serial IS NULL
)
SELECT * FROM hilf_pfi_stichtag_max_prio;          
";

const SQL_PORTAL = "
WITH mannheim_wahlen2_casted AS (
    SELECT
        CASE
            WHEN mw2.personalnr IS NULL THEN NULL
            ELSE mw2.personalnr::integer 
        END AS personalnr,
        mw2.id,mw2.firstname,mw2.surname,mw2.birthdate,
        mw2.registrationnumber,mw2.enrollmentdate,mw2.universitysemester,mw2.k_studystatus_id,
        mw2.parental_leave_from,mw2.parental_leave_to,mw2.term_type_id,mw2.term_year,mw2.studynumber,
        mw2.subjectnumber,mw2.degree_program_progress_startdate,mw2.degree_program_progress_enddate,
        mw2.degree_uniquename,mw2.subject_lid,mw2.studystatus,mw2.studystatus_defaulttxt,
        mw2.course_of_study_lid,mw2.course_of_study_degree_lid,mw2.course_of_study_subject_lid,
        mw2.course_of_study_longtext,mw2.course_of_study_uniquename,mw2.course_of_study_orgunit_lid,
        mw2.orgunit_defaulttxt,mw2.orgunit_uniquename,mw2.teachingunit_defaulttxt,mw2.teachingunit_uniquename
    FROM mannheim.wahlen2 AS mw2
),
mannheim_wahlen_personen AS (
    SELECT *
    FROM (
        SELECT DISTINCT mannheim_wahlen2_casted.id AS person_id, mannheim_wahlen2_casted.registrationnumber, mannheim_wahlen2_casted.firstname, 
            mannheim_wahlen2_casted.surname, mannheim_wahlen2_casted.personalnr, 
            CASE
                WHEN mw2.id IS NULL THEN NULL
                ELSE 1
            END AS student
        FROM mannheim_wahlen2_casted
        LEFT JOIN (
            SELECT * FROM mannheim_wahlen2_casted AS filter 
            WHERE filter.degree_program_progress_startdate <= :stichtag::date  
                AND filter.degree_program_progress_enddate >= :stichtag::date  
        ) AS mw2 ON mannheim_wahlen2_casted.id = mw2.id
    ) AS personen
    WHERE personalnr IS NOT NULL OR student = 1 
)
SELECT * FROM mannheim_wahlen_personen; 
";