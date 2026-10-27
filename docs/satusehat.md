# Integrasi SATUSEHAT — KlinikDent.id

## Arsitektur

1. Kunjungan berstatus `Done` memicu `VisitObserver` → `IntegrationService::queueSatuSehat`.
2. Job `SyncSatuSehatEncounter` (unique per visit, retry/backoff) menjalankan `SatuSehatSync::run`.
3. Alur: resolve Patient/Practitioner/Location → Encounter `in-progress` → Condition/Procedure/Observation (+ resource kondisional) → gate → Encounter `finished` hanya jika resource wajib sukses.
4. Mapping ID lokal↔remote di tabel `satusehat_resources` (`tenant_id` + `local_key` unik).
5. Kredensial per cabang di `branch_satusehat_settings` (`client_secret` terenkripsi).

## Status kunjungan (`visits.satusehat_status`)

| Status | Arti |
|---|---|
| `pending` | Belum dikirim |
| `queued` | Dalam antrian / sedang diproses / menunggu retry sementara |
| `partial` | Sebagian resource berhasil; Encounter belum `finished` atau resource wajib gagal |
| `synced` | Encounter `finished` + seluruh resource wajib skenario sukses |
| `failed` | Gagal keras (konfigurasi, auth, validasi permanen, atau retry habis) |

HTTP 2xx saja tidak otomatis berarti playbook lengkap.

## Waktu Encounter

- `visits.arrived_at` — awal pelayanan (diisi saat visit dibuat; fallback `created_at`).
- `visits.completed_at` — saat status menjadi `Done`.
- SQL DBA: `docs/sql/2026-10-16-visits-satusehat-timestamps.sql`.

## Resource yang dikirim (kondisional)

| Resource | Syarat |
|---|---|
| Patient / Practitioner | NIK + lookup/register MPI |
| Organization | Konfigurasi cabang (tidak auto-create) |
| Location | Pemetaan cabang/ruang |
| Encounter | Selalu untuk kunjungan Done yang siap sync |
| Condition | Ada diagnosis ICD-10 |
| Procedure | Ada tindakan + ICD-9; edukasi jika instruksi tercatat |
| Observation | Vital, exam gigi, odontogram, DMF; OHIS jika DI+CI lengkap; ABO/Rh/hamil jika terisi |
| AllergyIntolerance | `present` + zat pemicu, atau `none_confirmed`. `not_asked` dan `unknown` tidak dikirim. `code.coding` wajib (Rule 10078) dan system ATC `http://www.whocc.no/atc` ditolak. Obat memakai KFA atau SNOMED, makanan/lingkungan memakai SNOMED. Nama zat tetap di `code.text`. Kategori kosong pada data lama dikirim sebagai `medication` |
| MedicationStatement | `medication_status=present` + detail teks |
| Medication / MedicationRequest | Resep sent/fulfilled + item siap (obat jadi: `kfa_code`; racikan: bahan ber-KFA + tipe EP) |
| MedicationDispense | Hanya prescription `fulfilled` |
| ServiceRequest | Ada Referral dengan tujuan/alasan; `code.coding` SNOMED `103697008` (rujukan gigi) |

### Medication.extension MedicationType

Wajib. Extension URL tetap `https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType`, tetapi `coding.system` harus
`http://terminology.kemkes.go.id/CodeSystem/medication-type` (tanpa `s` — server staging/production menolak `https://` pada path ini).

Mapping dari kolom `prescription_items.medication_type`:

| Data lokal | Kode | Arti |
|---|---|---|
| Obat jadi (`kind=ready`) | `NC` | Non-compound |
| Racikan bagi sama rata | `EP` | Divide into equal parts (jumlah bungkus) |
| Racikan d.t.d | `SD` | Give of such doses (`strength_value`/`strength_unit` per bahan) |

SQL/migration: `docs/sql/2026-10-16-prescription-medication-type.sql`.

Master obat (`/management/medicines`) menyimpan `strength_value` / `strength_unit` sebagai default kekuatan zat aktif; terisi otomatis ke bahan racikan SD. SQL: `docs/sql/2026-10-16-medicine-strength-fields.sql`.

### MedicationRequest.dispenseRequest.quantity

Wajib `SimpleQuantity` lengkap (`value`, `unit`, `system`, `code`). Tanpa `system` server menolak dengan `Invalid coding system: (RuleNumber: 10348)`.

`system` = `http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm`. Mapping satuan lokal: tablet→`TAB`, kapsul→`CAP`, botol→`BOT`, tube→`TUBE`, ampul→`AMP`, sachet/bungkus (racikan)→`PKG`.

### ServiceRequest untuk rujukan

Local key `servicerequest:referral:{id}` berasal dari tabel `referrals`. Payload wajib punya `code.coding` (bukan hanya `text`).
| ClinicalImpression | Hanya jika `plan_instructions.prognosis` terisi |
| CarePlan | Ada tujuan perawatan dan/atau instruksi. Kategori SNOMED `736271009`. Periode memakai `control_date` atau `followup_date` bila terisi |
| Composition | Resume medis rawat jalan (LOINC `88645-7`) setelah resource klinis sukses. Section hanya merujuk resource yang benar-benar terkirim |

Tidak dikirim: Specimen, ImagingStudy, DiagnosticReport (tidak ada sumber lokal klinis). AllergyIntolerance tidak dikirim bila status belum ditanyakan atau tidak diketahui.

## Logging & privasi

`PayloadRedactor` menyamarkan NIK, nama, alamat, telepon, token, dan secret. Body exchange di `integration_logs` selalu di-redaksi.

## Troubleshooting

1. Restart `php artisan queue:work` setelah deploy.
2. Status partial → buka log detail, perbaiki data, kirim ulang manual.
3. Practitioner tidak ditemukan → lengkapi NIK dokter yang terdaftar di SATUSEHAT.
4. Obat tidak terkirim → isi `medicines.kfa_code`; pastikan resep status sent/fulfilled; cek notes/failures untuk MedicationType / `dispenseRequest.quantity` (system+code).
5. ServiceRequest gagal / `Element not found: ServiceRequest.code` → pastikan rujukan punya tujuan/alasan; kirim ulang setelah deploy perbaikan coding.
6. OHIS tidak terkirim → isi DI dan CI sekaligus di pemeriksaan.

## Batasan

Integrasi mengikuti Playbook Rawat Jalan Gigi SATUSEHAT. Klaim compliance penuh hanya setelah verifikasi sandbox dan review klinis/terminologi.
