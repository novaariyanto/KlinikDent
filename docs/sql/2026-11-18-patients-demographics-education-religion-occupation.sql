-- Demografi pasien tambahan (standar data / rekam medis + form pendaftaran).
-- Setara migration: database/migrations/2026_11_18_150900_add_patient_demographics_education_religion_occupation.php
-- Review DBA sebelum prod.

ALTER TABLE patients
    ADD COLUMN education VARCHAR(40) NULL AFTER family_card_number,
    ADD COLUMN religion VARCHAR(20) NULL AFTER education,
    ADD COLUMN occupation VARCHAR(100) NULL AFTER religion;
