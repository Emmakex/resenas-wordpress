=== Gestión de Reseñas ===
Contributors: eduardoyauri
Tags: reviews, ratings, feedback, customer service, multi-location
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0-rc4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect customer ratings and feedback, recover dissatisfied customers, and manage one or multiple business locations directly in WordPress.

== Description ==

Gestión de Reseñas adds a simple post-service feedback and service-recovery flow to your WordPress site.

Create a page, insert the `[resenas_wordpress]` shortcode, publish it, and share that page URL with customers. A common workflow is to send the link through WhatsApp after a purchase, service, booking, appointment, or intervention. The same page can also be shared by email, SMS, QR code, your website, or another channel.

The plugin is local-first: its core workflow runs inside your WordPress installation and does not require a Kairoseth account or subscription.

= Service recovery workflow =

When a customer selects a low rating, the plugin can ask what happened, collect a reason and comment, and notify the responsible team so the business can follow up and resolve the issue.

After the internal feedback is submitted, the customer can still choose to share their experience on Google through a secondary action when a Google review URL is configured.

For higher ratings, the Google review link can remain the primary next action.

= Main features =

* 1-to-5 star customer ratings.
* Internal feedback and service recovery workflow.
* Optional Google review links.
* Single-location and multi-location support.
* Separate Google review URL and notification email per location.
* External references using `?ref=`.
* Location selection using `?sede=`.
* Operational context such as service, order, agent, and source.
* Custom logo, colors, copy, and feedback reasons.
* Email notifications for ratings that require follow-up.
* Keyboard-accessible rating controls and visible focus states.
* Responsive interface designed for mobile traffic, including WhatsApp flows.
* Customer data stored locally in WordPress.

Official product page: https://kairoseth.com/products/resenas-wordpress

== How it works ==

1. Create a WordPress page, for example **Reviews**.
2. Insert `[resenas_wordpress]` into that page.
3. Publish the page. Its URL could be `https://example.com/reviews/`.
4. Configure your business, Google review URL, appearance, notifications, and optional locations.
5. Send the published page URL to the customer, for example through WhatsApp.
6. Optionally add a reference such as `?ref=ORDER-123`.
7. For multiple locations, add the location identifier, for example `?sede=downtown&ref=ORDER-123`.

The plugin does not create a separate public review URL. The URL you share is the WordPress page where the shortcode is placed.

== Installation ==

1. Upload the plugin ZIP from **Plugins > Add Plugin > Upload Plugin** or install it from the WordPress Plugin Directory when available.
2. Activate **Gestión de Reseñas**.
3. Open the Gestión de Reseñas menu in WordPress.
4. Configure your business and optional locations.
5. Create a page and insert `[resenas_wordpress]`.
6. Publish the page and share its URL with customers.

== Compatibility and migration ==

Gestión de Reseñas preserves the option keys, locations data, database table, and shortcodes used by the validated legacy package. This allows existing installations to move to the WordPress.org Store identity without rebuilding their configuration or losing stored feedback.

When the Store package is activated, an active legacy plugin whose main file is `resenas-wordpress.php` is deactivated to prevent duplicate hooks and endpoints. Existing data is not deleted.

The source language is English. A validated Spanish catalog is bundled for manual ZIP installs, while WordPress.org language packs take priority when available.

== Frequently Asked Questions ==

= Is Gestión de Reseñas free? =

Yes. The core plugin runs locally in WordPress and does not require a Kairoseth subscription.

= Do I need WhatsApp? =

No. WhatsApp is a common and recommended channel, but the published WordPress page URL can be shared through email, SMS, QR codes, your website, or another channel.

= Does the plugin publish a review automatically? =

No. The plugin records the customer rating and feedback inside WordPress. A Google review page is opened only when the customer chooses to use the configured Google action.

= What URL should I send to customers? =

Send the URL of the WordPress page where `[resenas_wordpress]` is inserted. For example: `https://example.com/reviews/`.

= Does the existing shortcode still work? =

Yes. `[resenas_wordpress]` remains the primary shortcode and the legacy shortcode is retained for compatibility.

= I installed an earlier version manually. Will I lose my data? =

No. The Store package keeps the same option keys, locations data, database table, shortcodes, and feedback records used by the validated legacy version. When the new Store package is activated, it deactivates an active legacy `resenas-wordpress.php` plugin file to avoid duplicate hooks while preserving its existing data.

After confirming Gestión de Reseñas works correctly, the old plugin folder can be removed from the Plugins screen. Do not use a destructive data-removal process for the legacy package during migration.

== Privacy ==

Gestión de Reseñas stores its operational data in the site's own WordPress database.

New records in the Store Candidate do not store the visitor IP address, user-agent string, or full page URL. The plugin keeps the information required for the feedback workflow, such as rating, feedback, location, optional reference, service/order context, agent, and source when supplied.

The plugin does not send customer feedback to Kairoseth. A configured Google review URL is opened only when the customer chooses that action.

== Screenshots ==

1. Public rating screen optimized for mobile.
2. Service-recovery feedback screen for low ratings.
3. Dashboard with activity metrics and How it works guidance.
4. Location management for businesses with one or multiple locations.
5. Appearance, copy, and notification settings.

== Changelog ==

= 1.2.0 =
* RC4 production preview aligns the real frontend and admin UI with the final premium visual direction.
* Added admin feedback pagination and visual star/status presentation.
* Premium responsive frontend and improved accessibility.
* Accessible 1-to-5 rating control with keyboard support.
* Service-recovery flow for ratings that require follow-up.
* Secondary Google review action remains available after internal feedback.
* Premium WordPress-native admin experience.
* Settings grouped into General, Appearance, Copy, and Notifications.
* English source strings and bundled Spanish translation for manual installs.
* Data minimization for new records: IP address, user-agent, and full page URL are no longer stored.
* Store package migration preserves legacy settings, locations, feedback, and shortcodes.

= 1.1.3 =
* Validated production baseline with complete low-rating feedback cycle.
