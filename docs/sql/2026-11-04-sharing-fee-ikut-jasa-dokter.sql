-- Sharing fee mengikuti komponen jasa dokter pada tarif.
-- BHP dan jasa klinik tidak dibagi.
-- Aturan persentase lama (dari total tarif) diganti mengikuti jasa dokter 100%.
-- Aturan nominal tetap tidak diubah.

UPDATE sharing_fee_rules
SET
    calc_type = 'doctor_fee',
    rate = 100,
    material_deduction_type = 'none',
    material_deduction_value = 0
WHERE calc_type = 'percentage';
