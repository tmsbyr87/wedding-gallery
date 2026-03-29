=== Guest Upload Vault ===
Contributors: tmsbyr
Tags: guest upload, event upload, private upload, qr upload, media collection
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect guest photos and videos through a protected upload link or QR code.

== Description ==

Guest Upload Vault helps you collect photos and videos from guests through a protected upload page in WordPress.

Share a secure upload link or a QR code, and guests can send photos and videos directly from their phone. Uploaded files are stored encrypted, and only admins can review and download them.

Unlike classic gallery plugins, Guest Upload Vault is not built for displaying public image galleries. It is built for secure guest media collection.

Perfect for:
- weddings
- birthdays
- anniversaries
- private parties
- company events
- reunions
- school events
- club and community gatherings

= Why Guest Upload Vault? =

- Protected guest upload page
- QR-code ready for fast mobile access
- Upload from camera, gallery, or files
- Supports photos and videos
- Encrypted storage for better privacy
- Admin-only access to uploaded media
- Mobile-friendly upload flow
- Multiple file upload support
- Health checks and admin diagnostics
- Multilingual support for German, English, French, Italian, and Spanish

= Languages =

Guest Upload Vault is designed for multilingual use and currently supports:
- German (de_DE)
- English (en_US)
- French (fr_FR)
- Italian (it_IT)
- Spanish (es_ES)

The plugin uses standard WordPress translation support and follows the site language where translations are available.

= What this plugin does =

Guest Upload Vault creates a secure upload flow for guest-generated media.

Admins can:
- set an upload page
- generate or regenerate a protected guest upload link
- use a QR code for quick guest access
- review uploaded files in WordPress admin
- download uploaded media securely

Guests can:
- scan a QR code or open a protected link
- upload photos and videos from their phone
- use camera, photo library, gallery, or files depending on device/browser support

= What this plugin is not =

Guest Upload Vault is not a front-end photo gallery plugin.
It does not focus on public gallery layouts, sliders, masonry displays, or image presentation.
Its purpose is secure media collection from guests.

= Privacy and security =

- Guest uploads are protected by token-based access
- Uploaded media is stored encrypted at rest
- Admin downloads are access-controlled
- Metadata integrity checks help detect tampering
- No external QR-code service is required

= Shared hosting note =

Guest Upload Vault is designed to work on typical WordPress hosting environments.
Maximum upload size still depends on your server and PHP limits such as:
- upload_max_filesize
- post_max_size
- memory_limit

The plugin helps clamp upload settings to safer runtime limits.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it through WordPress.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Create a page for guest uploads.
4. Place this shortcode on the page:

`[guest_upload_vault]`

5. Open the plugin settings in WordPress admin.
6. Set the upload page URL.
7. Save settings.
8. Copy the protected guest upload link or use the generated QR code.

== Frequently Asked Questions ==

= Is this a wedding gallery plugin? =

No. Guest Upload Vault is not a classic image gallery plugin.
It is a secure upload and media collection plugin for guest content.

= Can I use it for events other than weddings? =

Yes. It works well for weddings, birthdays, private events, business events, reunions, and similar occasions.

= Can guests upload from iPhone and Android? =

Yes. Guests can upload from supported mobile browsers using camera, gallery, photo library, or files, depending on device and browser behavior.

= Are uploads protected? =

Yes. The upload page is protected by a guest token link, uploaded files are stored encrypted, and only admins can access downloads.

= Does the plugin use an external QR code service? =

No. QR code generation is handled locally in the plugin.

= Can I regenerate the guest upload link? =

Yes. Regenerating the token invalidates previously shared guest links and QR codes.

= What happens on uninstall? =

The plugin includes a cleanup-on-uninstall option.
By default, uploaded files are kept unless explicit cleanup is enabled.

= Which languages are supported? =

Currently supported languages include German, English, French, Italian, and Spanish.
The plugin follows the WordPress site language when matching translations are available.

== Screenshots ==

1. Admin settings page with protected guest link and QR code
2. Mobile-friendly guest upload form
3. Admin uploaded files overview with health status
4. Secure guest upload flow on smartphone

== Changelog ==

= 1.0.0 =
* Initial public release
* Protected guest upload page via shortcode
* Token-based guest upload links
* Local QR-code generation in WordPress admin
* Mobile-friendly photo and video upload flow
* Encrypted media storage
* Admin-only file list and secure download
* Metadata integrity checks
* Diagnostics and file health states
* Translation support for German, English, French, Italian, and Spanish

== Upgrade Notice ==

= 1.0.0 =
Initial public release of Guest Upload Vault.
