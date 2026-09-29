=== Staging Superpowers for WooCommerce ===
Contributors: businessbloomer
Tags: woocommerce, staging, emails, payment gateway, development
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make a WooCommerce staging copy safe to test on. Redirects emails, swaps payment gateways for a test gateway, pauses webhooks and more.

== Description ==

A staging copy of a live WooCommerce store is dangerous. It has real customer emails, live payment keys, active webhooks and pending subscription renewals, all ready to fire the moment someone places a test order or WP-Cron runs.

Install this plugin on the staging site and it is safe as soon as you activate it.

**Emails**

* Blocks every outgoing email by default.
* Or forward them all to one address instead. The original recipient is added to the subject line, for example "[STAGING to jane@example.com] Your order is complete". Until you enter a forwarding address, emails stay blocked.
* CC and BCC headers are removed, so nobody gets a copy by accident.
* Works for WooCommerce emails and any other email sent through WordPress.

**Payments**

* Hides every payment gateway at checkout, so live Stripe, PayPal or WooPayments keys are never used.
* Adds a Staging Test Gateway instead. Choose whether test payments succeed, go on hold or fail. Refunds are simulated too.
* Works with the classic checkout and the Checkout block.

**Webhooks**

* Stops every WooCommerce webhook from being delivered, so your ERP, fulfillment or accounting tools never receive staging orders.

**HTTP firewall**

* Blocks outgoing requests to a list of hosts you control. The default list covers common payment, email marketing, shipping, tax and tracking APIs (Stripe, PayPal, Square, Mollie, Mailchimp, Klaviyo, ShipStation, Avalara, Facebook and more).
* Blocking payment APIs also stops a refund from the order screen reaching the real payment account.

**Scheduled actions**

* Freezes the Action Scheduler queue, so subscription renewals, follow-up emails and sync jobs copied from the live site do not run. The status bar shows how many are waiting.
* You can still run any single action by hand from Tools > Scheduled Actions.

**Visitors**

* Your live store address is filled in for you when it can be detected from your store data (you check it and save).
* Customers who find the staging copy (through Google or an old link) see a "This is a staging site" page with a button to your live store, instead of a shop where they could place orders that go nowhere.
* The page tells search engines the site is temporarily unavailable, and the sitemap, feeds and the checkout API are blocked too.
* Store managers and editors see the full site after logging in.
* Or let visitors browse, with a STAGING bar on every page.

**Staging look**

* Orange admin bar with a STAGING badge, on the dashboard and on the front end.
* A reminder on product, page, coupon, menu and WooCommerce settings screens, so changes meant for the live store are not made here by mistake. Add your live store address and it links to the same screen on the live store.
* A status bar under it shows at a glance which protections are on (✓) or off (✗), each linking to the screen where you manage it, plus a shortcut to all settings.
* "[STAGING]" prefix on admin page titles, so browser tabs are easy to tell apart.
* Adds noindex, nofollow to every page.

**Changelog**

Copying a staging database over your live store wipes every order placed since the copy was made. So instead, the plugin writes everything you change on staging to the WooCommerce logs, as a list of what to redo on the live store:

* WooCommerce settings, with the old and new value, for example: Settings > Products: "Enable AJAX add to cart buttons on archives" changed from on to off.
* Theme switched, plugins switched on or off, plugin, theme and WordPress updates.
* Cart or checkout switched between blocks and classic.
* Products, pages, posts, coupons, categories, tags and menus created, edited or deleted.

Find it at WooCommerce > Settings > Staging > Changelog. Entries follow the WooCommerce log retention setting (30 days by default).

**Troubleshooting**

Find out if a problem is caused by another plugin or by the theme, from WooCommerce > Settings > Staging > Troubleshooting.

* Switch off every plugin except WooCommerce and the ones you choose. Plugins that a kept plugin needs stay on automatically.
* Switch the same plugins back on with one click, or switch on every installed plugin.
* Switch to the parent theme or to a default theme, then back to your theme.
* While plugins or the theme are switched, the status bar shows a reminder so nothing is forgotten.
* Only available while the plugin is on for the current URL, so a live store can never be switched off from here.

**Deploy guard**

The plugin remembers the exact URL it was turned on for. If the database ends up on a different URL, for example when staging is pushed to the live site, every feature switches off by itself and an admin notice explains why. Your live store keeps sending emails and taking payments even if the plugin comes along by mistake.

If the site's configuration says it is the live store (WP_ENVIRONMENT_TYPE set to "production"), the plugin also stays off until an admin confirms the site is a copy. That confirmation only applies to the current URL, so the deploy guard still protects the live store.

Nothing is changed in the database: gateway settings, webhook statuses and scheduled actions are left exactly as they were. Deactivate the plugin and the store behaves as before.

Settings are at WooCommerce > Settings > Staging.

== Frequently Asked Questions ==

= I cloned my staging site to a new URL and the plugin says it is paused =

That is the deploy guard. Click "This is a staging site: turn on for ..." in the admin notice to turn it on for the new URL.

= I cloned my store by hand and the plugin says the site is the live store =

A hand-made copy keeps the live store's wp-config.php, which may say the site is in production. If you are sure the site is a copy, click "This is a copy, not my live store" in the admin notice and confirm.

= Does the HTTP firewall block every request a plugin makes? =

It blocks requests made through the WordPress HTTP API, which is what almost every WordPress plugin uses. A plugin that bypasses it with its own cURL calls will not be blocked.

= Some WooCommerce background tasks are not running =

WooCommerce uses the Action Scheduler for its own background jobs too, for example analytics imports and order table syncs. Untick "Scheduled actions" in WooCommerce > Settings > Staging if you need them, or run the ones you need by hand from Tools > Scheduled Actions.

= Can the Staging Test Gateway be used on my live store? =

No. It only exists while the plugin is turned on for the current URL, and the deploy guard switches the plugin off on any other URL.

== Changelog ==

= 1.0.0 =
* First release.
