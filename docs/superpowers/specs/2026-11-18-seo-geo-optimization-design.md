# Design: Optimasi SEO & GEO KlinikDent.id

**Tanggal:** 2026-11-18  
**Status:** Disetujui (opsi gelombang C)  
**Situs:** https://klinikdent.id/

## Ringkasan

Meningkatkan kesiapan SEO (~65→≥90) dan GEO (~42→≥85) sebagai **rubrik audit internal**, dengan memanfaatkan fondasi CMS/SEO yang sudah ada. Tidak merusak fitur operasional klinik, tidak mengarang klaim, tidak deploy otomatis ke production.

## Konteks existing

Sudah ada: `SeoMeta`, layout meta/OG/Twitter, hub pages, sitemap/robots/llms.txt, schema JSON-LD, CMS admin, redirect SEO, 10 artikel seed (sebagian published dengan body placeholder).

## Masalah nyata

1. Artikel live masih memuat teks editorial (“Draft ini belum dipublikasikan”, “Slug target”).
2. Belum ada halaman entitas `/tentang-klinikdent`.
3. Title/description/FAQ/harga perlu diselaraskan dengan fakta paket (Trial vs Pro tanpa trial).
4. Deliverable docs/ZIP/evaluasi rubrik belum lengkap.
5. Performa (CWV) belum diukur — optimasi ringan saja, tanpa klaim skor.

## Pendekatan

Gelombang berurutan di atas fondasi existing (bukan rewrite marketing).

### Gelombang 1 — Konten kritis

- Tulis ulang body artikel (pertahankan slug).
- `CmsContentSeeder` updateOrCreate konten siap terbit (atau draft bersih tanpa placeholder).
- Blokir publish jika body mengandung frasa terlarang.
- `SeoAnalyzer` flag placeholder + internal link.
- Internal link ke hub + CTA registrasi yang akurat.

### Gelombang 2 — GEO / entitas

- Route + view `/tentang-klinikdent` (AboutPage schema).
- Nav/footer/sitemap/llms.txt.
- Answer-first + FAQ faktual di hub/homepage.
- Schema Offer hanya dari harga `SaasPackage` valid; tanpa rating palsu.

### Gelombang 3 — Polish teknis

- Selaraskan meta homepage dengan copy target.
- Harden sitemap (published only + tentang + lastmod).
- Lazy-load gambar non-kritis marketing; alt/dimensi.
- Tidak menyentuh care/kasir/pasien/WhatsApp runtime.

### Gelombang 4 — Deliverable

- Update `docs/cms-seo-blog.md` + daftar file patch.
- Rubrik skor before/after.
- Checklist GSC/GA via `seo_settings`.
- Instruksi ZIP tanpa `.env`/kredensial.

## Batasan

- Tidak mengubah skema DB kecuali terbukti perlu (target: tanpa migrasi baru).
- Tidak ada data pasien di konten/schema.
- Tidak ada deploy production otomatis.
- Klaim fitur hanya yang terverifikasi di produk.

## File terdampak (perkiraan)

- `database/seeders/CmsContentSeeder.php`
- `app/Support/Cms/SeoAnalyzer.php`, `PostService.php`, `StoreCmsPostRequest.php`
- `app/Http/Controllers/Marketing/LandingController.php`
- `app/Support/Marketing/SitemapBuilder.php`, `MarketingCatalog.php`
- `resources/views/marketing/**` (landing, pages, tentang, header/footer, partials)
- `routes/web.php`
- `docs/cms-seo-blog.md`, `docs/superpowers/plans/*`

## Kriteria sukses

- Tidak ada placeholder editorial di artikel published.
- `/tentang-klinikdent` live di sitemap & nav.
- Meta/canonical/schema konsisten; draft tetap noindex & di luar sitemap.
- Docs + rubrik evaluasi tersedia.
- Regresi: login, register, dashboard, CMS posts list tidak rusak.
