# Guest Upload Vault Plugin (1.0.0)

WordPress plugin for collecting guest event photos/videos through a protected upload link (QR-ready), with encrypted media storage and admin-only download.

## Pilot Scope

- Guest upload page via shortcode: `[guest_upload_vault]`
- Token-protected guest access link (`guest_upload_vault_token`)
- Local QR code generation in WP admin (no external QR service)
- Mobile-first guest upload UI (multi-file + progress feedback)
- Encrypted media blob storage in `wp-content/uploads/guest-upload-vault/`
- Metadata integrity checks and tamper detection
- Admin file list, health states, diagnostics, and admin-only download
- WordPress i18n support (`guest-upload-vault` text domain) with runtime translations for `de_DE`, `fr_FR`, `it_IT`, and `es_ES`

## Setup (Pilot)

1. Copy folder `guest-upload-vault/` into `wp-content/plugins/`.
2. Activate **Guest Upload Vault** in WordPress plugins.
3. Create a page for guest uploads and place shortcode:
   - `[guest_upload_vault]`
4. Open **Guest Upload Vault** in WP admin.
5. Set **Upload Page URL** to the page from step 3.
6. Save settings.
7. Copy the generated **Guest Upload Link** or use the generated QR code.

## Version

- Current release target: `1.0.0`

## Localized Pilot Handoff Docs

- French: `docs/PILOT_HANDOFF.fr_FR.md`
- Italian: `docs/PILOT_HANDOFF.it_IT.md`
- Spanish: `docs/PILOT_HANDOFF.es_ES.md`
- Translation ownership and review gate: `docs/TRANSLATION_OWNERS.md`

## Guest Token + QR Workflow

1. Admin sets `Upload Page URL`.
2. Plugin builds protected URL by appending `guest_upload_vault_token`.
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
- `wp-content/uploads/guest-upload-vault/` directory (encrypted blobs + metadata files)

If only one side is restored (DB only or uploads only), files may become undecryptable.

## Cleanup on Uninstall Behavior

Plugin setting: **Cleanup On Uninstall**

- Unchecked (default safe behavior): uninstall keeps files on disk.
- Checked: uninstall attempts to permanently remove `uploads/guest-upload-vault/` contents and then deletes plugin option `guest_upload_vault_settings`.

Use checked cleanup only when permanent deletion is explicitly desired.

## Shared Hosting Notes / Known Limitations

- Direct URL blocking files (`.htaccess`, `web.config`) may vary by host/server stack.
- Primary protection is encrypted-at-rest storage + admin-gated download.
- Large file handling is bounded by runtime limits (`upload_max_filesize`, `post_max_size`, `memory_limit`), and plugin clamps configured max upload accordingly.
- Admin download currently decrypts full file in PHP memory (non-streaming).
- Existing legacy files/metadata may appear with warning statuses in admin.

## Release Packaging Notes

For a production plugin zip, ship the plugin directory `guest-upload-vault/` only.

Do not ship repository noise / handoff artifacts:

- `.git/`, `.gitignore`, `.gitattributes`
- root-level docs (`README.md`, `CHANGELOG.md`, `docs/`)
- `.DS_Store`
- `guest-upload-vault/languages/guest-upload-vault.pot` (optional source template, not required at runtime)

Language files in release:

- Ship runtime files for all supported locales (`de_DE`, `fr_FR`, `it_IT`, `es_ES`) as `guest-upload-vault/languages/guest-upload-vault-<locale>.mo`.
- Keep matching `.po` files in Git for maintainability/review.
- `guest-upload-vault/languages/guest-upload-vault.pot` may be excluded from release ZIP.
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
5. Confirm backup job includes DB + `uploads/guest-upload-vault/`.
6. Decide and set uninstall cleanup preference.
