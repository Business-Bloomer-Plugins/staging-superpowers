=== Staging Superpowers ===
Contributors: businessbloomer
Tags: staging, emails, development, testing, woocommerce
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make a staging copy of your site safe to test on: blocks emails and outside services, freezes scheduled tasks, keeps search engines and AI bots out.

== Description ==

**Live stays safe. Every time.**

Your staging copy has your real customers, your real payment keys and your real automations. One test can email thousands of people or charge a real card. Staging Superpowers stops all of it, the moment you activate it.

= What it blocks =

* **Emails:** every email to real people is blocked, even through SMTP plugins. Or forward them all to yourself.
* **Outside services:** Stripe, PayPal, Mailchimp, Klaviyo, Zapier, CRMs and 50+ more can't be reached.
* **Automations:** WP-Cron and scheduled actions are frozen, so nothing runs twice.
* **Search engines and AI bots:** noindex, robots.txt, no sitemaps, and known crawlers refused.
* **Analytics:** test visits stay out of Google Analytics and Meta reports.
* **Page caching:** off, so you always see your latest change.

= What it adds =

* **Staging look:** a red STAGING badge and a status bar showing every protection.
* **Changelog:** every change you make on staging, so you know what to redo on live.
* **Troubleshooting:** switch plugins off and on in bulk. A fatal error is undone automatically.
* **Visitors:** let everyone in, or show logged-out visitors a "We'll be right back" message.
* **Emails log:** every email it stopped or forwarded, with recipient, subject and sender plugin. Never the message body.
* **Requests log:** every outgoing request it blocked, with the plugin that tried.

= On WooCommerce stores =

* Payment methods are swapped for a Staging Test Gateway, so no real card is charged.
* Subscriptions copied from live are locked, so live renewals keep working.
* Webhooks are paused.

= Safe to keep on your live site =

It only switches itself on where the address looks like staging (staging., dev., .local, WP Engine, Kinsta, Cloudways and more) or WP_ENVIRONMENT_TYPE is staging, development or local. On your live site it stays off. Every clone of your site already has it, and protects itself straight away. Push staging back to live and it switches itself off again.

Nothing in your database is changed by the protections. Deactivate it and the site behaves as before.

= Staging Superpowers Pro =

[Staging Superpowers Pro](https://www.businessbloomer.com/plugins/staging-superpowers-pro/) adds tools for working on the copy: anonymize customers and staff before handing the site to an agency, send visitors to your live site, compare pages with live side by side, find staging links on live, see and block every outgoing request, and WooCommerce test data and switches.

== External services ==

This plugin does not connect to any external service and does not send data anywhere.

The web addresses listed in its settings are services the plugin blocks. The default list is: api.stripe.com, api.paypal.com, api-m.paypal.com, api.braintreegateway.com, connect.squareup.com, api.mollie.com, api.authorize.net, api.mailchimp.com, a.klaviyo.com, api.brevo.com, api.sendinblue.com, connect.mailerlite.com, api.omnisend.com, api.kit.com, api.convertkit.com, api.hubapi.com, ssapi.shipstation.com, api.taxjar.com, rest.avatax.com, graph.facebook.com, google-analytics.com, api.twilio.com, slack.com, hooks.zapier.com, make.com, integromat.com, api-us1.com, api.createsend.com, api.getdrip.com, api.sendgrid.com, mailgun.net, api.postmarkapp.com, sheets.googleapis.com, api.sparkpost.com, mandrillapp.com, bridge.mailpoet.com, api.mailjet.com, api.resend.com, api.mailersend.com, api.smtp2go.com, api.elasticemail.com, api.twitter.com, api.linkedin.com, developer.blog2social.com, blog2social-wordpress-api.adenion.de, onesignal.com, api.pushengage.com, rpc.pingomatic.com, api.cloudflare.com, api.automatorplugin.com, api.shortpixel.com and smushpro.wpmudev.com. When "Connected services" is on, the plugin stops the staging site from contacting them, so a staging copy cannot reach your live accounts. The plugin never sends requests to them itself.

== Installation ==

1. Install and activate Staging Superpowers, on your live site or on staging.
2. On a staging address it switches itself on: you'll see a red STAGING badge. Anywhere else it stays off, or click "This is a staging site: turn on" in the notice.
3. Everything is on by default. Settings are at Tools > Staging Superpowers.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. It works on any WordPress site. The payment, subscription and webhook protections turn on by themselves when WooCommerce is active.

= Can I keep it active on my live site? =

Yes. It stays off there, and switches on by itself in every staging copy. That way you never forget to install it on a new clone.

= Does it work with my host's staging? =

Yes, with any staging copy: made by your host, a staging plugin or by hand. If yours isn't recognized, click the button in the notice once.

= What happens when I push staging to live? =

It notices the address changed and switches itself off, so your live site keeps working normally.

= Does it stop bots that ignore robots.txt? =

Known crawlers and AI bots, Bytespider included, are refused whatever robots.txt says. For full lockdown, also password-protect staging at your host.

= Some background tasks are not running =

That's the automations freeze. Untick "Scheduled actions" or "WP-Cron" in Tools > Staging Superpowers, or run single tasks by hand.

= Does blocking services stop every request? =

It blocks requests made through the WordPress HTTP API, which almost every plugin uses. A plugin with its own connection code, such as WP Offload Media, can't be blocked: switch it off on staging.

== Screenshots ==

1. The status bar shows every protection at a glance, with links to manage each one.
2. The protection settings, with a plain-English explanation for each one.
3. On a WooCommerce store, the Staging Test Gateway is the only payment method at checkout, so no real money moves.
4. Troubleshooting: every plugin with its status, its version on this site and its latest release. Disable or enable one, or several at once; a fatal error is undone straight away.
5. The changelog: a list of everything you changed on staging, to redo on your live site.
6. The message visitors can be shown instead of the staging site.

== Changelog ==

= 1.2.0 =
* New: Emails log. Every email the staging site tried to send is listed with its recipients, subject, the plugin that sent it, what happened (blocked or forwarded) and a count. Message bodies, headers and attachments are never kept, and CC and BCC addresses are only counted.
* New: Requests log. Every request to a blocked service is listed with the address, the plugin that made it and a count. Only the method, host and path are kept, never what was sent.
* The status bar shows how many emails and requests were stopped, with a link to each log.
* Shorter, clearer plugin description, and new FAQ answers.
* It's safe to keep the plugin active on your live site: it stays off there and switches on by itself in every staging copy.

= 1.1.2 =
* Fix: automatic background updates of plugins and themes are now recorded in the changelog, like updates made from the Plugins screen.
* Fix: saving the settings no longer records "Blocked services changed" when the list did not change.

= 1.1.1 =
* Settings moved to Tools > Staging Superpowers, in a clearer order: staging look, search engines and caching first, then visitors, emails, WooCommerce, then automations and connected services. Old links to Settings > Staging Superpowers still work.
* New: Crawlers and AI bots (on by default). robots.txt asks every crawler to stay away, the WordPress sitemaps are off, and known search and AI crawlers are refused.
* Visitors now have two choices: everyone sees the site (the new default), or logged-out visitors see a message with your own heading and text. The message page never mentions staging. The redirect to the live site and the visitor STAGING bar were removed, together with the live site address setting.
* Troubleshooting is now a table: one plugin or theme per row, active first, with its status, the version on this site (red when a newer version is out, green when up to date) and the latest release with its date and changelog. Disable or enable each plugin from its row, or several at once with Disable selected and Enable selected. Premium plugins show the version their own updater reports, and add-ons can supply release details with the sspw_plugin_release_info filter.
* The changelog now records the old and the new version when a plugin or theme is updated.
* Troubleshooting: WooCommerce can be disabled like any other plugin. Plugins that need it are disabled with it, and the message says so. After every change the site checks itself, and a fatal error undoes the change and names the plugin or theme that caused it.
* Troubleshooting actions write one changelog entry each, however many plugins they change. The status bar no longer shows disabled plugins or a switched theme.
* The changelog is now always kept and shown on its own page, also on WooCommerce stores (it used to go to the WooCommerce logs). It keeps the latest 1,000 changes.
* The page heading now includes the tagline, and every section has a clearer heading and description.
* When the admin bar wraps onto two rows on narrow screens, the status bar moves down instead of covering it.
* The Plugin check list was removed from the settings page; the protections it described still work.
* A short list of what Staging Superpowers Pro adds, above the Save button.

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
