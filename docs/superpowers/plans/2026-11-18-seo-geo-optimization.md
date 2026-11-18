# SEO & GEO KlinikDent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline) or subagent-driven-development. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Naikkan kesiapan SEO/GEO KlinikDent.id lewat konten siap terbit, halaman entitas, polish meta/sitemap, dan dokumentasi evaluasi — tanpa merusak fitur operasional.

**Architecture:** Perkuat fondasi existing (`SeoMeta`, CMS, hub, sitemap). Gelombang C: konten → GEO → polish → docs. Tidak ada migrasi DB baru.

**Tech Stack:** Laravel 12, Blade + jQuery, CmsPost/SeoSetting, MarketingCatalog, HtmlSanitizer.

## Global Constraints

- Jangan ubah care/kasir/pasien/WhatsApp runtime.
- Jangan klaim ranking, rating, sertifikasi, atau statistik fiktif.
- Jangan jalankan Pest/PHPUnit otomatis; verifikasi manual.
- Jangan commit/deploy kecuali diminta user.
- Respons & UI marketing tetap branding KlinikDent.

---

### Task 1: Blokir publish berisi placeholder editorial

**Files:**
- Create: `app/Support/Cms/EditorialGuard.php`
- Modify: `app/Support/Cms/SeoAnalyzer.php`
- Modify: `app/Http/Requests/Cms/StoreCmsPostRequest.php`
- Modify: `app/Support/Cms/PostService.php` (jika status published diset di service)

**Interfaces:**
- Produces: `EditorialGuard::forbiddenPhrases(): array`, `EditorialGuard::findIn(string $html): ?string`

- [ ] **Step 1:** Buat `EditorialGuard` dengan frasa: `Draft ini belum dipublikasikan`, `Slug target`, `Konten klinis wajib ditinjau` (opsional sebagai warn saja), instruksi internal seeder.
- [ ] **Step 2:** `SeoAnalyzer` tambah level `error` jika frasa terlarang ditemukan.
- [ ] **Step 3:** `StoreCmsPostRequest::withValidator` — jika status published/scheduled, gagalkan bila `EditorialGuard::findIn(body)`.
- [ ] **Step 4:** Smoke: coba publish lewat tinker/simulasi validation message.

---

### Task 2: Tulis ulang body 10 artikel di seeder

**Files:**
- Modify: `database/seeders/CmsContentSeeder.php`
- Create (opsional): `database/seeders/Concerns/CmsArticleBodies.php`

**Interfaces:**
- Produces: `bodyFor(string $slug): string` unik per slug, answer-first, H2/H3, internal link, tanpa placeholder.

- [ ] **Step 1:** Ganti `body()` generik menjadi konten spesifik per slug.
- [ ] **Step 2:** Artikel non-klinis set `status=published` + `published_at=now()`; artikel clinical tetap draft ATAU published dengan disclaimer edukatif (bukan “draft belum dipublikasikan”).
- [ ] **Step 3:** `php artisan db:seed --class=CmsContentSeeder` lalu cek strip placeholder dari body.
- [ ] **Step 4:** Pastikan sitemap lokal hanya URL published.

---

### Task 3: Halaman `/tentang-klinikdent`

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Marketing/LandingController.php`
- Create: `resources/views/marketing/pages/tentang-klinikdent.blade.php`
- Modify: `app/Support/Marketing/SitemapBuilder.php`
- Modify: `resources/views/marketing/partials/header.blade.php`, `footer.blade.php`
- Modify: `LandingController::llmsTxt`

- [ ] **Step 1:** Route `GET /tentang-klinikdent` → `LandingController@tentang`.
- [ ] **Step 2:** View AboutPage + meta + FAQ singkat fakta produk.
- [ ] **Step 3:** Tambah ke sitemap, nav, footer, llms.txt.
- [ ] **Step 4:** Smoke URL 200 + canonical benar.

---

### Task 4: Polish homepage meta, FAQ, hub answer-first

**Files:**
- Modify: `resources/views/marketing/landing.blade.php`
- Modify: `app/Support/Marketing/MarketingCatalog.php` (FAQ + page ledes)
- Modify: `resources/views/marketing/pages/*.blade.php` (answer-first bila belum)

- [ ] **Step 1:** Title/description sesuai target copy master prompt.
- [ ] **Step 2:** FAQ tambah pertanyaan harga/trial/SATUSEHAT/multi-cabang sesuai fakta.
- [ ] **Step 3:** Hub pages: satu paragraf jawaban langsung di bawah H1.
- [ ] **Step 4:** Schema Offer dari paket aktif bila harga valid.

---

### Task 5: Performa ringan marketing

**Files:**
- Modify: `resources/views/marketing/landing.blade.php` / partial gambar
- Modify: `public/js/marketing.js` hanya jika perlu lazy

- [ ] **Step 1:** `loading="lazy"` + width/height pada gambar non-hero.
- [ ] **Step 2:** Jangan klaim Lighthouse lulus tanpa ukur.

---

### Task 6: Dokumentasi & evaluasi

**Files:**
- Modify: `docs/cms-seo-blog.md`
- Create: `docs/seo-geo-evaluation-2026-11-18.md`
- Modify: `docs/cms-seo-patch-files.txt`

- [ ] **Step 1:** Changelog gelombang C + checklist deploy/rollback.
- [ ] **Step 2:** Rubrik skor before/after + residual risks.
- [ ] **Step 3:** Instruksi ZIP patch (exclude `.env`).

---

## Execution

Inline di session ini, mulai Task 1 → 6. Checkpoint setelah Task 2 dan Task 3.
