-- Master ICD e-klaim. Isi data lewat seeder, bukan INSERT di file ini.
-- php artisan db:seed --class=IcdCatalogSeeder
-- Sumber: public/icd/[PUBLIC] ICD-10 e-klaim.xlsx dan [PUBLIC] ICD-9CM e-klaim.xlsx

CREATE TABLE icd10_codes (
    code VARCHAR(32) NOT NULL,
    description TEXT NOT NULL,
    version VARCHAR(32) NULL,
    PRIMARY KEY (code)
);

CREATE TABLE icd9_codes (
    code VARCHAR(32) NOT NULL,
    description TEXT NOT NULL,
    version VARCHAR(32) NULL,
    PRIMARY KEY (code)
);
