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
| AllergyIntolerance | `allergy_status=present` (+detail) atau `none_confirmed` |
| MedicationStatement | `medication_status=present` + detail teks |
| Medication / MedicationRequest | Resep sent/fulfilled + item punya `kfa_code` |
| MedicationDispense | Hanya prescription `fulfilled` |
| ServiceRequest | Ada Referral dengan tujuan/alasan |
| ClinicalImpression | Hanya jika `plan_instructions.prognosis` terisi |

Tidak dikirim: Composition, Specimen, ImagingStudy, DiagnosticReport (tidak relevan / tidak ada sumber lokal klinis).

## Logging & privasi

`PayloadRedactor` menyamarkan NIK, nama, alamat, telepon, token, dan secret. Body exchange di `integration_logs` selalu di-redaksi.

## Troubleshooting

1. Restart `php artisan queue:work` setelah deploy.
2. Status partial → buka log detail, perbaiki data, kirim ulang manual.
3. Practitioner tidak ditemukan → lengkapi NIK dokter yang terdaftar di SATUSEHAT.
4. Obat tidak terkirim → isi `medicines.kfa_code`.
5. OHIS tidak terkirim → isi DI dan CI sekaligus di pemeriksaan.

## Batasan

Integrasi mengikuti Playbook Rawat Jalan Gigi SATUSEHAT. Klaim compliance penuh hanya setelah verifikasi sandbox dan review klinis/terminologi.
