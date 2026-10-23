=== Kairoseth Reviews ===
Contributors: eduardoyauri
Tags: reviews, ratings, feedback, customer service, multi-location
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0-rc3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect customer ratings and feedback, manage service recovery, and support one or multiple business locations directly in WordPress.

== Description ==

Kairoseth Reviews adds a simple post-service feedback flow to your WordPress site.

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
2. Activate **Kairoseth Reviews**.
3. Open the Kairoseth Reviews menu in WordPress.
4. Configure your business and optional locations.
5. Create a page and insert `[resenas_wordpress]`.
6. Publish the page and share its URL with customers.

== Frequently Asked Questions ==

= Is Kairoseth Reviews free? =

Yes. The core plugin runs locally in WordPress and does not require a Kairoseth subscription.

= Do I need WhatsApp? =

No. WhatsApp is a recommended workflow, but the same page URL can be shared through email, SMS, QR codes, your website, or another channel.

= Does it support multiple locations? =

Yes. Each location can have its own identifier, Google review URL, and notification email.

= Which URL do I send to customers? =

Send the URL of the WordPress page where you placed `[resenas_wordpress]`, for example `https://example.com/reviews/`.

= Can I keep my existing shortcode from an earlier version? =

Yes. `[resenas_wordpress]` remains the primary shortcode and the legacy shortcode is retained for compatibility.

= I installed an earlier Reseñas WordPress package manually. Will I lose my data? =

No. Kairoseth Reviews keeps the same option keys, locations data, database table, shortcodes, and feedback records used by the validated legacy version. When the new Store package is activated, it deactivates an active legacy `resenas-wordpress.php` plugin file to avoid duplicate hooks while preserving its existing data.

After confirming Kairoseth Reviews works correctly, the old plugin folder can be removed from the Plugins screen. Do not use a destructive data-removal process for the legacy package during migration.

== Privacy ==

Kairoseth Reviews stores its operational data in the site's own WordPress database.

New records in the Store Candidate do not store the visitor IP address, user-agent string, or full page URL. The plugin keeps the information required for the feedback workflow, such as rating, feedback, location, optional reference, service/order context, agent, and source when supplied.

The plugin does not send customer feedback to Kairoseth. A configured Google review URL is opened only when the customer chooses that action.

Site owners are responsible for informing users about their own data-processing practices and for applying any retention requirements that apply to their business.

== Screenshots ==

1. Premium mobile-friendly customer rating screen with accessible 1-to-5 star controls.
2. Service recovery screen for collecting the reason and customer comment after a low rating.
3. WordPress dashboard with feedback metrics and the page → shortcode → URL → WhatsApp workflow.
4. Multi-location management with separate Google review links and notification emails.
5. Grouped settings for General, Appearance, Copy, and Notifications.

== Changelog ==

= 1.2.0 =
* Premium responsive customer experience.
* Improved keyboard and screen-reader accessibility.
* Service recovery workflow for ratings that require follow-up.
* Secondary Google sharing option after low-rating feedback.
* Redesigned WordPress-native administration screens.
* Grouped settings: General, Appearance, Copy, and Notifications.
* English source language with a validated Spanish translation catalog.
* Reduced collection of unnecessary technical visitor data.
* Safe migration path from manually installed legacy packages.
* WordPress.org Store Candidate hardening.

= 1.1.3 =
* Validated functional core.
* Single-location and multi-location support.
* Complete rating and feedback cycle.
* Support for references and source context.

== Upgrade Notice ==

= 1.2.0 =
Adds the premium accessible interface and service recovery workflow while preserving the existing database table, options, locations, shortcodes, and customer feedback data. An active legacy `resenas-wordpress.php` package is deactivated automatically when the Store package is activated.
