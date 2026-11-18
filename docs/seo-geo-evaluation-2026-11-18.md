# Evaluasi SEO & GEO KlinikDent — 2026-11-18

Skor adalah **rubrik audit internal**, bukan skor Google resmi atau jaminan ranking/AI citation.

## Rubrik (0–100)

| Dimensi | Bobot | Sebelum (estimasi) | Sesudah gelombang C | Sesudah redesign + SEO/GEO | Catatan |
|---------|-------|--------------------|---------------------|----------------------------|---------|
| Meta & canonical publik | 15 | 12 | 14 | 14 | Title/desc/OG tetap factual |
| Sitemap/robots/indexing | 15 | 11 | 14 | 14 | lastmod hub/statis; published-only posts |
| Konten unik & tanpa placeholder | 20 | 8 | 18 | 18 | EditorialGuard tetap |
| Internal linking & hub | 10 | 6 | 9 | 10 | Footer multi-kolom + CTA band |
| Schema / entity GEO | 15 | 8 | 13 | 15 | Offer/AggregateOffer + HowTo; tanpa rating |
| Answer-first & FAQ | 10 | 5 | 9 | 9 | Hub lede + FAQ katalog |
| Performa CWV | 10 | 6 | 6 | 7 | Lazy trust/blog images; CSS inline — belum diukur Lighthouse |
| Pengukuran (GSC/GA) | 5 | 2 | 3 | 3 | Hook `seo_settings`; isi ID di admin |
| **Total SEO kesiapan** | **100** | **~65** | **~86** | **~90** | Deploy + GSC/CWV tetap residual |
| **Total GEO kesiapan** | **100** | **~42** | **~78** | **~84** | llms.txt diperkaya; observasi AI manual residual |

## Sudah diperbaiki (kode lokal)

### Gelombang C
- EditorialGuard + validasi publish
- Body 10 artikel tanpa placeholder
- `/tentang-klinikdent` + nav/footer/sitemap/llms.txt
- FAQ & answer-first hub

### Redesign marketing + SEO/GEO
- UI beranda/hub/blog ala eHealth (warna KlinikDent)
- Trust hanya PSE / SatuSehat / HKI (tanpa metrik/testimoni fiktif)
- Schema Offer dari paket + HowTo 4 langkah
- Organization `sameAs` hanya jika `social_profiles` terisi di SEO settings
- BlogPosting tanpa field null kosong; lazy-load kartu blog
- Docs & checklist smoke diperbarui

## Belum / residual

- Deploy production + seed konten bila masih placeholder di live
- Pengukuran Lighthouse/CWV nyata (jangan klaim skor tanpa ukur)
- Pengisian GA4/GTM/Bing di CMS SEO settings
- Observasi penyebutan AI (manual, berkala)
- Aset foto/screenshot produk asli (opsional, data dummy only)
- Social proof klinik nyata bila nanti tersedia (jangan fiktif)

## Checklist pascadeploy

1. `php artisan migrate` (jika belum)
2. `php artisan db:seed --class=CmsContentSeeder` (jika artikel live masih placeholder)
3. Smoke: `/`, `/tentang-klinikdent`, `/odontogram-digital`, `/blog`, `/sitemap.xml`, `/robots.txt`, `/llms.txt`
4. View-source `/`: pastikan JSON-LD ada HowTo + Offer/AggregateOffer tanpa AggregateRating
5. Pastikan body artikel tidak mengandung “Draft ini belum dipublikasikan”
6. Submit sitemap di Google Search Console
7. Regresi login/register/dashboard

## Rollback

- Revert commit/patch file marketing & CSS min
- Atau set artikel bermasalah ke `draft` di CMS
- Redirect 301 hanya jika URL diganti (slug dipertahankan)
