# CMS, SEO & Blog KlinikDent

## Ringkasan

Modul blog/CMS platform untuk situs marketing `klinikdent.id` (tanpa `tenant_id`). Admin berada di menu SuperAdmin SaaS: **SEO & Content**.

## Instalasi staging

1. `php artisan migrate`
2. `php artisan db:seed --class=RolePermissionSeeder`
3. `php artisan db:seed --class=MenuSeeder`
4. `php artisan db:seed --class=CmsContentSeeder`
5. Pastikan `php artisan storage:link` jika belum (upload media).

## Routes publik

| Path | Nama |
|------|------|
| `/` | `home` |
| `/tentang-klinikdent` | `marketing.tentang` |
| `/odontogram-digital` | `marketing.odontogram` |
| `/rekam-medis-gigi` | `marketing.rekam-medis` |
| `/blog` | `blog.index` |
| `/blog/{slug}` | `blog.show` |
| `/blog/kategori/{slug}` | `blog.category` |
| `/blog/tag/{slug}` | `blog.tag` |
| `/blog/penulis/{slug}` | `blog.author` |
| `/blog/cari` | `blog.search` |
| `/blog/rss.xml` | `blog.rss` |
| `/llms.txt` | `llms` |
| `/sitemap.xml` | `sitemap` (termasuk artikel published + tentang) |
| `/robots.txt` | `robots` |

## Gelombang C (2026-11-18)

- Artikel seed ditulis ulang (tanpa placeholder editorial); publish diblokir jika body mengandung frasa terlarang (`EditorialGuard`).
- Halaman entitas `/tentang-klinikdent`.
- Evaluasi rubrik: `docs/seo-geo-evaluation-2026-11-18.md`
- Spec/plan: `docs/superpowers/specs/2026-11-18-seo-geo-optimization-design.md`, `docs/superpowers/plans/2026-11-18-seo-geo-optimization.md`

## Routes admin (`/cms/*`)

Butuh permission `cms.view` / `cms.manage` / `seo.manage` + akses SuperAdmin SaaS.

## Tabel baru

`seo_settings`, `seo_redirects`, `cms_authors`, `cms_categories`, `cms_tags`, `cms_media`, `cms_posts`, `cms_post_tag`, `cms_post_revisions`, `cms_content_plans`, `cms_keywords`, `cms_analytics_events`

## Keamanan

- Draft/schedule/private tidak masuk sitemap atau listing publik.
- Body HTML disanitasi allowlist (`HtmlSanitizer`).
- Redirect dicegah open-redirect (hanya path internal atau host app).
- Tidak mengakses data pasien/klinis.

## Checklist deploy

- [ ] Migrate + seed permission/menu/cms
- [ ] Smoke: `/`, `/blog`, `/sitemap.xml`, `/robots.txt`, `/llms.txt`
- [ ] Login SuperAdmin → menu SEO & Content
- [ ] Preview draft tidak muncul di `/blog` tanpa auth
- [ ] Regresi: login, dashboard SaaS, demo/starter-kit

## Rollback

1. Hapus menu/permission CMS dari seed + clear cache menu
2. `php artisan migrate:rollback --step=1` (migration `2026_11_18_100000_create_cms_and_seo_tables`)
3. Revert file patch terkait marketing/CMS

## PATCH file utama

- `app/Support/Marketing/*`, `app/Support/Cms/*`
- `app/Models/Cms*`, `SeoSetting`, `SeoRedirect`
- `app/Http/Controllers/Marketing/BlogController.php`, `LandingController.php`
- `app/Http/Controllers/Cms/*`
- `resources/views/marketing/**`, `resources/views/cms/**`
- `routes/web.php`, `bootstrap/app.php`
- `database/migrations/2026_11_18_100000_create_cms_and_seo_tables.php`
- `database/seeders/CmsContentSeeder.php`
