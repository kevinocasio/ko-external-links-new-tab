=== KO External Links New Tab ===
Contributors: kevinocasio
Tags: external links, new tab, open in new tab, target blank, outbound links
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically opens outbound external links in a new browser tab with secure rel="noopener noreferrer" attributes.

== Description ==

When visitors click an external link on your website, you don't want them leaving your site completely. Forcing external outbound links to open in a new browser tab keeps your site open in their background and lowers your bounce rate.

Manually checking the "Open in new tab" box on every single link inside the editor is tedious and easy to forget.

KO External Links New Tab handles this automatically. It scans your post content and widget text as pages render, detecting external links that point outside your domain and adding `target="_blank"` with secure `rel="noopener noreferrer"` attributes. Internal links to your own pages stay untouched.

= Features =

* **Automatic Outbound Link Detection:** Detects and opens external links in a new browser tab.
* **Internal Link Protection:** Keeps internal links to your own pages opening in the same tab.
* **Security Hardened:** Automatically adds `rel="noopener noreferrer"` to prevent tab hijacking.
* **Zero Front-End Assets:** Pure PHP execution with 0 bytes of extra CSS or JavaScript.
* **Instant Dashboard Switch:** Turn the feature on or off in one click directly from the KO Plugins dashboard.

== Installation ==

1. Upload the `ko-external-links-new-tab` folder to your `/wp-content/plugins/` directory, or install it directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **KO Plugins** in your sidebar to toggle your settings.

== Frequently Asked Questions ==

= Does this modify my database content? =
No. The plugin parses links dynamically during page display. It doesn't alter your saved post HTML in the database.

= Will this slow down my website? =
No. It uses a single fast PHP regex filter and loads zero external scripts or styles.

== Changelog ==

= 1.0.0 =
* Initial public release.
