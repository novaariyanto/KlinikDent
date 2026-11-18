# Evaluasi SEO & GEO KlinikDent — 2026-11-18

Skor adalah **rubrik audit internal**, bukan skor Google resmi atau jaminan ranking/AI citation.

## Rubrik (0–100)

| Dimensi | Bobot | Sebelum (estimasi) | Sesudah (estimasi setelah gelombang C lokal) | Catatan |
|---------|-------|--------------------|-----------------------------------------------|---------|
| Meta & canonical publik | 15 | 12 | 14 | Title/desc homepage diselaraskan; OG/Twitter existing |
| Sitemap/robots/indexing | 15 | 11 | 14 | Tentang + published only; prod perlu re-seed |
| Konten unik & tanpa placeholder | 20 | 8 | 18 | 10 artikel ditulis ulang; guard publish |
| Internal linking & hub | 10 | 6 | 9 | Artikel ↔ hub ↔ tentang |
| Schema / entity GEO | 15 | 8 | 13 | AboutPage + FAQ diperluas; Offer hati-hati |
| Answer-first & FAQ | 10 | 5 | 9 | Hub + FAQ harga/trial/keamanan |
| Performa CWV | 10 | 6 | 6 | Belum diukur Lighthouse — tidak diklaim naik |
| Pengukuran (GSC/GA) | 5 | 2 | 3 | Hook `seo_settings` ada; perlu isi ID di admin |
| **Total SEO kesiapan** | **100** | **~65** | **~86** | Target ≥90 butuh GSC+CWV terukur + deploy konten |
| **Total GEO kesiapan** | **100** | **~42** | **~78** | Target ≥85 butuh tentang live + kutipan AI observasi |

## Sudah diperbaiki (kode lokal)

- EditorialGuard + validasi publish
- Body 10 artikel tanpa placeholder
- `/tentang-klinikdent` + nav/footer/sitemap/llms.txt
- FAQ & answer-first hub
- Meta homepage copy target

## Belum / residual

- Deploy + `CmsContentSeeder` di production (artikel live masih placeholder sampai di-seed/update)
- Pengukuran Lighthouse/CWV nyata
- Pengisian GA4/GTM/Bing di `/cms` SEO settings
- Observasi penyebutan AI (manual, berkala)
- Screenshot produk asli tambahan (opsional, data dummy only)

## Checklist pascadeploy

1. `php artisan migrate` (jika belum)
2. `php artisan db:seed --class=CmsContentSeeder`
3. Smoke: `/`, `/tentang-klinikdent`, `/blog/apa-itu-odontogram-digital`, `/sitemap.xml`, `/robots.txt`, `/llms.txt`
4. Pastikan body artikel tidak mengandung “Draft ini belum dipublikasikan”
5. Submit sitemap di Google Search Console
6. Regresi login/register/dashboard

## Rollback

- Revert commit/patch file marketing & seeder
- Atau set artikel bermasalah ke `draft` di CMS
- Redirect 301 hanya jika URL diganti (gelombang ini mempertahankan slug)
