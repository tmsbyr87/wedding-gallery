=== Wedding Gallery ===
Contributors: tmsbyr87
Tags: gallery, wedding, uploads, photos, videos
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Token-protected wedding guest photo/video uploads with encrypted-at-rest storage and admin media management.

== Description ==
Wedding Gallery provides a protected wedding media workflow for guests and admins:

* Token-protected guest upload link
* QR-code support for phone-friendly access
* Allowed file type validation for images and videos
* Size limit enforcement with runtime-safe clamping
* Encrypted storage in `wp-content/uploads/wedding-gallery/`
* Admin media tab with diagnostics, preview, download, and delete actions

== Installation ==
1. Upload the `wedding-gallery` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Create an upload page and add shortcode `[wedding_gallery_upload]`.
4. Open **Wedding Gallery** in admin and set the upload page URL.
5. Share the generated protected guest link or QR code.

== Frequently Asked Questions ==
= How do guests access uploads? =
Use the tokenized guest link (or QR code) generated in the plugin admin page.

= Where are files stored? =
Encrypted media files are stored in `wp-content/uploads/wedding-gallery/`.

= Can I delete files on uninstall? =
Yes. Enable **Cleanup On Uninstall** in plugin settings before uninstalling.

== Changelog ==
= 1.0.0 =
* WordPress.org compliance cleanup, strict token access enforcement, and release metadata finalization.

= 0.3.0 =
* Pilot release with tokenized guest uploads, local QR generation, encrypted storage, mobile-first UX, and diagnostics.

== Upgrade Notice ==
= 1.0.0 =
Production-ready release with compliance cleanup and hardened token access controls.
