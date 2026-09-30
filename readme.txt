=== Staging Superpowers ===
Contributors: businessbloomer
Tags: staging, emails, development, testing, woocommerce
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make a staging copy of your site safe to test on: blocks emails and outside services, freezes scheduled tasks, sends visitors to your live site.

== Description ==

A staging copy of a live WordPress site is risky. It has the real email addresses of your users, the same connections to your email marketing, CRM and payment accounts, and the same scheduled tasks, all ready to fire the moment someone tests a form or WP-Cron runs.

Install it on your staging site. When the site looks like staging (a staging. or dev. address, a .local site, a hosting company's staging domain such as WP Engine, Kinsta or Cloudways, or WP_ENVIRONMENT_TYPE set to staging), it turns itself on the moment you activate it. Anywhere else it waits for you to confirm with one click, so installing it on your live site by mistake changes nothing.

It works on any WordPress site. On a WooCommerce store it also hides your payment methods, adds a test gateway, pauses webhooks and locks subscriptions copied from the live store. See "WooCommerce stores" below.

**Emails**

* Blocks every outgoing email by default.
* Or forward them all to one address instead. The original recipient is added to the subject line, for example "[STAGING to jane@example.com] Your password was reset". Until you enter a forwarding address, emails stay blocked.
* CC and BCC headers are removed, so nobody gets a copy by accident.
* Works for every email sent through WordPress, including SMTP plugins like WP Mail SMTP, FluentSMTP and Post SMTP: the block runs before any mailer.
* Checks for plugins that replace the WordPress email function (some Mailgun, SendGrid, SparkPost and Mandrill versions do) and warns you in the status bar. Their sending services are blocked too, and anything that still reaches the WordPress mailer is readdressed to nowhere.

**Connected services**

* Blocks outgoing requests to a list of hosts you control. The default list covers common payment, email marketing, shipping, tax and tracking APIs (Stripe, PayPal, Square, Mollie, Mailchimp, Klaviyo, ShipStation, Avalara, Facebook and more), plus the services automation workflows send to (Twilio SMS, Slack, Zapier, Make, ActiveCampaign, SendGrid, Mailgun, Postmark, Google Sheets and more).
* Also blocked by default: social sharing and push notification services (X, LinkedIn, Blog2Social, OneSignal, PushEngage), the Cloudflare API, the Uncanny Automator API, Google Analytics (including GA4 server events) and the ShortPixel and Smush image APIs.
* Blocking payment APIs also stops a refund from an admin screen reaching the real payment account.
* Stops pingbacks, trackbacks and update-service pings (Ping-O-Matic) when you publish, so other sites are not told about test posts.

**Popular plugins**

Some plugins reach live services in their own way, so they get their own switch while the site is protected:

* Jetpack is put in safe mode, so it stops syncing to WordPress.com and Jetpack Social does not share new posts.
* UpdraftPlus scheduled backups do not run, so they cannot upload to or prune your live backup storage. Backups you start by hand still work.
* Easy Digital Downloads and GiveWP run in test mode, and Paid Memberships Pro uses its sandbox gateway environment.
* Analytics are off (you can turn this off): Site Kit by Google does not add its Analytics, Tag Manager, Ads and AdSense tags, MonsterInsights tracking is skipped, GTM4WP leaves out its container, and the Meta pixel's Conversions API sends nothing.

The Plugin check on the settings page lists the active plugins it knows, with "Covered" or "Check" and what to do. For example, WP Offload Media, Imagify and WP Search with Algolia talk to their services with their own code, which cannot be blocked, so it tells you to switch them off or change their settings on staging.

**Automations**

* Freezes WP-Cron, so scheduled tasks copied from the live site (digests, syncs, backups to the cloud, imports) do not run a second time. Nothing is deleted: everything runs again once you turn it off.
* Freezes the Action Scheduler queue too, when your site has it (WooCommerce and many other plugins use it), so renewals, follow-up emails, automation workflows and sync jobs do not run. The status bar shows how many are waiting.
* You can still run any single scheduled action by hand from its admin screen, or a single WP-Cron task with WP-CLI (wp cron event run).

**Visitors**

* Logged-out visitors and accounts that cannot edit the site (customers, members, subscribers) who find the staging copy through Google or an old link are sent to the same page on your live site.
* You, and anyone who can edit the site, see the staging copy as normal once logged in. The login page is never redirected. Browsers you have logged in with are remembered, so when your login expires you land on the staging login page, not on the live site.
* Your live site address is filled in for you when it can be detected from your site data (you check it and save).
* Prefer something else? Show visitors a "this is a staging site" page, or let them browse with a STAGING bar on every page. Search engines are told not to index anything either way.

**Staging look**

* Orange admin bar with a STAGING badge, on the dashboard and on the front end.
* A reminder on post, page, product, coupon, menu and store settings screens, so changes meant for the live site are not made here by mistake. Add your live site address and it links to the same screen on the live site.
* A status bar under it shows at a glance which protections are on (✓) or off (✗), each linking to the screen where you manage it, plus a shortcut to all settings.
* "[STAGING]" prefix on admin page titles, so browser tabs are easy to tell apart.
* Adds noindex, nofollow to every page.
* Turns off page caching, so you always see your latest changes and visitors are never shown a saved page instead of being redirected. Works with WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache, Cache Enabler, SiteGround, Breeze, Hummingbird, WP-Optimize and host caches that respect no-cache headers. Existing saved pages are emptied once. Cache plugin settings are not changed.

**Changelog**

Copying a staging database over your live site wipes everything added on the live site since the copy was made: comments, sign-ups, form entries, orders. So instead, the plugin records everything you change on staging, as a list of what to redo on the live site:

* Settings of this plugin (and of WooCommerce), with the old and new value, for example: Settings > Products: "Enable AJAX add to cart buttons on archives" changed from on to off.
* Theme switched, plugins switched on or off, plugin, theme and WordPress updates.
* Posts, pages, products, coupons, categories, tags and menus created, edited or deleted.

Find it at Settings > Staging Superpowers > Changelog. On a WooCommerce store it is kept in the WooCommerce logs and follows their retention setting (30 days by default). Otherwise the latest 500 entries are kept.

**Troubleshooting**

Find out if a problem is caused by another plugin or by the theme, from Settings > Staging Superpowers > Troubleshooting.

* Switch off every plugin except the ones you choose. Plugins that a kept plugin needs stay on automatically, and so does WooCommerce.
* Switch the same plugins back on with one click, or switch on every installed plugin.
* Switch to the parent theme or to a default theme, then back to your theme.
* While plugins or the theme are switched, the status bar shows a reminder so nothing is forgotten.
* Only available while the plugin is on for the current URL, so a live site can never be switched off from here.

**Deploy guard**

The plugin remembers the exact URL it was turned on for. If the database ends up on a different URL, for example when staging is pushed to the live site, every feature switches off by itself and an admin notice explains why. Your live site keeps sending emails and running its scheduled tasks even if the plugin comes along by mistake. If the new URL also looks like staging (say you refreshed staging into staging2.), the plugin turns itself back on.

If the site's configuration says it is the live site (WP_ENVIRONMENT_TYPE set to "production"), the plugin also stays off until an admin confirms the site is a copy. That confirmation only applies to the current URL, so the deploy guard still protects the live site.

Nothing is changed in the database: gateway settings, webhook statuses and scheduled tasks are left exactly as they were. Deactivate the plugin and the site behaves as before.

**WooCommerce stores**

When WooCommerce is active, these protections are added on top, all on by default:

* Payments: hides every payment method at checkout, so live Stripe, PayPal or WooPayments keys are never used, and adds a Staging Test Gateway instead. Choose whether test payments succeed, go on hold or fail. Refunds are simulated too. Works with the classic checkout and the Checkout block.
* Subscriptions: locks subscriptions copied from the live store, their orders and the customers who own them, so nobody can delete them on staging. Deleting them could make your payment plugin remove the customer's saved card at Stripe (or PayPal, Square...), which would stop renewals on the live store. Subscriptions you create on staging for testing are not locked.
* Webhooks: stops every WooCommerce webhook from being delivered, so your ERP, fulfillment or accounting tools never receive staging orders.
* Visitors cannot use the cart and checkout API, so nobody places orders that go nowhere.
* The changelog also records WooCommerce settings and the cart or checkout switching between blocks and classic.

Settings are at Settings > Staging Superpowers.

== External services ==

This plugin does not connect to any external service and does not send data anywhere.

The web addresses listed in its settings are services the plugin blocks. The default list is: api.stripe.com, api.paypal.com, api-m.paypal.com, api.braintreegateway.com, connect.squareup.com, api.mollie.com, api.authorize.net, api.mailchimp.com, a.klaviyo.com, api.brevo.com, api.sendinblue.com, connect.mailerlite.com, api.omnisend.com, api.kit.com, api.convertkit.com, api.hubapi.com, ssapi.shipstation.com, api.taxjar.com, rest.avatax.com, graph.facebook.com, google-analytics.com, api.twilio.com, slack.com, hooks.zapier.com, make.com, integromat.com, api-us1.com, api.createsend.com, api.getdrip.com, api.sendgrid.com, mailgun.net, api.postmarkapp.com, sheets.googleapis.com, api.sparkpost.com, mandrillapp.com, bridge.mailpoet.com, api.mailjet.com, api.resend.com, api.mailersend.com, api.smtp2go.com, api.elasticemail.com, api.twitter.com, api.linkedin.com, developer.blog2social.com, blog2social-wordpress-api.adenion.de, onesignal.com, api.pushengage.com, rpc.pingomatic.com, api.cloudflare.com, api.automatorplugin.com, api.shortpixel.com and smushpro.wpmudev.com. When "Connected services" is on, the plugin stops the staging site from contacting them, so a staging copy cannot reach your live accounts. The plugin never sends requests to them itself.

== Installation ==

1. On your staging site, go to Plugins > Add New, search for "Staging Superpowers" and click Install Now, then Activate.
2. If the site looks like staging, the plugin turns itself on and the admin bar turns orange. If not, click "This is a staging site: turn on" in the notice at the top of the screen.
3. Go to Settings > Staging Superpowers, check your live site address, and review the settings. Every protection is on by default.

Do not install it on your live site. If you do by mistake, it stays off unless someone confirms the site is a copy.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. Every protection except payments, webhooks and the subscription lock works on any WordPress site. Those three turn on by themselves when WooCommerce is active.

= Will it turn itself on if I install it on my live site? =

No. It only turns itself on when the site address or environment clearly says staging. On any other address it shows a notice and waits for an admin to confirm, with a warning, that the site is a copy.

= I visit my staging site and end up on my live site =

That is the visitor protection: logged-out visitors are sent to the live site so nobody uses staging by mistake. Log in at your staging address followed by /wp-login.php (that page is never redirected) and you will see the staging site as normal. After that, your browser is remembered and goes to the login page instead.

= I cloned my staging site to a new URL and the plugin says it is not turned on =

That is the deploy guard. If the new address looks like staging, the plugin turns itself back on by itself. Otherwise click "This is a staging site: turn on for ..." in the admin notice.

= I cloned my site by hand and the plugin says the site is the live site =

A hand-made copy keeps the live site's wp-config.php, which may say the site is in production. If you are sure the site is a copy, click "This is a copy, not my live site" in the admin notice and confirm.

= Does blocking connected services stop every request a plugin makes? =

It blocks requests made through the WordPress HTTP API, which is what almost every WordPress plugin uses. A plugin that bypasses it with its own cURL calls will not be blocked.

= Some background tasks are not running =

That is the automations freeze. WooCommerce and other plugins use scheduled tasks for their own housekeeping too, for example analytics imports and order table syncs. Untick "Scheduled actions" or "WP-Cron" in Settings > Staging Superpowers if you need them, or run the ones you need by hand.

= Can the Staging Test Gateway be used on my live store? =

No. It only exists while the plugin is turned on for the current URL, and the deploy guard switches the plugin off on any other URL.

== Screenshots ==

1. The status bar shows every protection at a glance, with links to manage each one.
2. Settings, with a plain-English explanation for every protection.
3. On a WooCommerce store, the Staging Test Gateway is the only payment method at checkout, so no real money moves.
4. Troubleshooting: switch plugins and the theme off, then back on with one click.
5. The changelog: a list of what to redo on your live site.
6. What visitors see if you choose the "this is a staging site" page instead of sending them to the live site.

== Changelog ==

= 1.1.0 =
* New name: Staging Superpowers. It now works on any WordPress site, and its WooCommerce protections turn on by themselves when WooCommerce is active.
* Settings moved to Settings > Staging Superpowers.
* New: Plugin check lists active plugins that talk to live services, with Covered or Check and what to do. It replaces the email plugin list.
* New: Jetpack safe mode, no scheduled UpdraftPlus backups, test mode for Easy Digital Downloads, GiveWP and Paid Memberships Pro, and no pingbacks, trackbacks or update-service pings.
* New: "Stop analytics on staging" setting (on by default) for Site Kit by Google, MonsterInsights, GTM4WP and the Meta pixel's Conversions API.
* New: block social sharing, push notification, Cloudflare, Uncanny Automator, Ping-O-Matic, ShortPixel and Smush services by default, and every Google Analytics address.
* Without WooCommerce, the changelog keeps its latest 500 entries and shows them on its own page.
* New: email check. Warns when a plugin replaces the WordPress email function, lists active email plugins and whether they are covered, blocks more email sending services, and readdresses anything that still reaches the WordPress mailer.
* New: lock subscriptions copied from the live store (and their orders and customers) against deletion and edits, so a staging clean-up cannot remove saved cards the live store needs for renewals.
* New: freeze WP-Cron tasks as well as scheduled actions, so no automation runs by itself on staging.
* New: block Twilio, Slack, Zapier, Make, ActiveCampaign, Campaign Monitor, Drip, SendGrid, Mailgun, Postmark and Google Sheets by default.
* Status bar: one "Automations" entry, warnings shown first.

= 1.0.0 =
* First release.
