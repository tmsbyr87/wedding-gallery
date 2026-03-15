# Wedding Gallery Plugin (Pilot 0.3.0)

WordPress plugin for collecting guest wedding photos/videos through a protected upload link (QR-ready), with encrypted media storage and admin-only download.

## Pilot Scope

- Guest upload page via shortcode: `[wedding_gallery_upload]`
- Token-protected guest access link (`wg_token`)
- Local QR code generation in WP admin (no external QR service)
- Mobile-first guest upload UI (multi-file + progress feedback)
- Encrypted media blob storage in `wp-content/uploads/wedding-gallery/`
- Metadata integrity checks and tamper detection
- Admin file list, health states, diagnostics, and admin-only download
- WordPress i18n support (`wedding-gallery` text domain) with German (`de_DE`) translations

## Setup (Pilot)

1. Copy folder `wedding_gallery/` into `wp-content/plugins/`.
2. Activate **Wedding Gallery** in WordPress plugins.
3. Create a page for guest uploads and place shortcode:
   - `[wedding_gallery_upload]`
4. Open **Wedding Gallery** in WP admin.
5. Set **Upload Page URL** to the page from step 3.
6. Save settings.
7. Copy the generated **Guest Upload Link** or use the generated QR code.

## Version

- Current pilot target: `0.3.0`

## Guest Token + QR Workflow

1. Admin sets `Upload Page URL`.
2. Plugin builds protected URL by appending `wg_token`.
3. Admin shares either:
   - the protected URL directly, or
   - QR code from admin page.
4. Guest opens link/QR on iPhone/Android and uploads files.

Important:
- Regenerating the token invalidates previous guest links/QR codes.
- Anyone with a valid token link can upload (intended for invited guests).

## Backup and Restore (Critical)

Back up and restore these together:

- WordPress database (contains plugin settings, including encryption key material)
- `wp-content/uploads/wedding-gallery/` directory (encrypted blobs + metadata files)

If only one side is restored (DB only or uploads only), files may become undecryptable.

## Cleanup on Uninstall Behavior

Plugin setting: **Cleanup On Uninstall**

- Unchecked (default safe behavior): uninstall keeps files on disk.
- Checked: uninstall attempts to permanently remove `uploads/wedding-gallery/` contents and then deletes plugin option `wg_settings`.

Use checked cleanup only when permanent deletion is explicitly desired.

## Shared Hosting Notes / Known Limitations

- Direct URL blocking files (`.htaccess`, `web.config`) may vary by host/server stack.
- Primary protection is encrypted-at-rest storage + admin-gated download.
- Large file handling is bounded by runtime limits (`upload_max_filesize`, `post_max_size`, `memory_limit`), and plugin clamps configured max upload accordingly.
- Admin download currently decrypts full file in PHP memory (non-streaming).
- Existing legacy files/metadata may appear with warning statuses in admin.

## Release Packaging Notes

For a production plugin zip, ship the plugin directory `wedding_gallery/` only.

Do not ship repository noise / handoff artifacts:

- `.git/`, `.gitignore`, `.gitattributes`
- root-level docs (`README.md`, `CHANGELOG.md`, `docs/`)
- `.DS_Store`
- `wedding_gallery/languages/wedding-gallery.pot` (optional source template, not required at runtime)

Language files in release:

- `wedding_gallery/languages/wedding-gallery-de_DE.mo` should be shipped (runtime translations).
- `wedding_gallery/languages/wedding-gallery-de_DE.po` can also be shipped (useful source/editable translation).
- `wedding_gallery/languages/wedding-gallery.pot` may be excluded.
- Important: if building release zips from Git, make sure `.mo/.po` files are committed so they are actually present in the archive.

## Plugin Icon/Banner Assets

Placeholder-ready structure is available in `/assets` for WordPress.org-style plugin presentation files.
See:

- `assets/README.md` for expected filenames, sizes, and placement.

## Pilot Handoff Checklist

1. Confirm upload page + shortcode works on mobile.
2. Confirm tokenized link opens upload page.
3. Confirm QR scan opens upload page on iOS + Android.
4. Upload test image + mp4 and verify admin download.
5. Confirm backup job includes DB + `uploads/wedding-gallery/`.
6. Decide and set uninstall cleanup preference.
