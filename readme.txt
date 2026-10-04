=== Staging Superpowers ===
Contributors: businessbloomer
Tags: staging, emails, development, testing, woocommerce
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make a staging copy of your site safe to test on: blocks emails and outside services, freezes scheduled tasks, keeps search engines and AI bots out.

== Description ==

**Live stays safe. Every time.**

A staging copy of a live WordPress site is risky. It has the real email addresses of your users, the same connections to your email marketing, CRM and payment accounts, and the same scheduled tasks, all ready to fire the moment someone tests a form or WP-Cron runs.

Install it on your staging site. When the site looks like staging (a staging. or dev. address, a .local site, a hosting company's staging domain such as WP Engine, Kinsta or Cloudways, or WP_ENVIRONMENT_TYPE set to staging), it turns itself on the moment you activate it. Anywhere else it waits for you to confirm with one click, so installing it on your live site by mistake changes nothing.

It works on any WordPress site. On a WooCommerce store it also hides your payment methods, adds a test gateway, pauses webhooks and locks subscriptions copied from the live store. See "WooCommerce stores" below.

**At a glance**

* Stops every email to real people, including those sent through SMTP plugins.
* Blocks payment, email marketing, CRM, shipping, tax, social and analytics services, so a test never reaches your live accounts.
* Freezes WP-Cron and scheduled actions, so renewals, follow-ups and syncs do not run twice.
* Keeps search engines and AI bots out.
* Marks the site with a red STAGING badge and a status bar showing every protection.
* Records what you change, so you know what to redo on the live site.
* Finds the plugin that breaks your site, and undoes a change that causes a fatal error.
* Switches itself off if the database ends up on your live site.

The protections never change your settings in the database, and they are all on by default. The details are below.

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

A few plugins, such as WP Offload Media, Imagify and WP Search with Algolia, talk to their services with their own code, which cannot be blocked. Switch them off or change their settings on staging.

**Automations**

* Freezes WP-Cron, so scheduled tasks copied from the live site (digests, syncs, backups to the cloud, imports) do not run a second time. Nothing is deleted: everything runs again once you turn it off.
* Freezes the Action Scheduler queue too, when your site has it (WooCommerce and many other plugins use it), so renewals, follow-up emails, automation workflows and sync jobs do not run. The status bar shows how many are waiting.
* You can still run any single scheduled action by hand from its admin screen, or a single WP-Cron task with WP-CLI (wp cron event run).

**Staging look**

* A red STAGING badge in the admin bar, on the dashboard and on the front end. Your admin bar keeps its usual colors.
* A status bar under it shows at a glance which protections are on (✓) or off (✗), each linking to the screen where you manage it, plus a shortcut to all settings.
* A reminder on post, page, product, coupon, menu and store settings screens, so changes meant for the live site are not made here by mistake.
* "[STAGING]" prefix on admin page titles, so browser tabs are easy to tell apart.

**Search engines, crawlers and AI bots**

* Adds noindex, nofollow to every page, whatever is set in Settings > Reading.
* robots.txt asks every crawler to stay away, and the WordPress sitemaps are turned off.
* Known search and AI crawlers that come anyway (Googlebot, Bingbot, GPTBot, ClaudeBot, PerplexityBot, CCBot, Bytespider and others) are refused. Your team, logged-in users and the site's own background tasks are never blocked.

**Page caching**

* Turns off page caching, so you always see your latest changes. Works with WP Rocket, W3 Total Cache, LiteSpeed Cache, WP Super Cache, Cache Enabler, SiteGround, Breeze, Hummingbird, WP-Optimize and host caches that respect no-cache headers. Existing saved pages are emptied once. Cache plugin settings are not changed.

**Visitors**

* By default everyone can see the staging site, so you can test it logged out too.
* Or show logged-out visitors (and accounts that cannot edit the site, like customers) a plain message instead, with your own heading and text. It never mentions staging, so customers are not confused. Your team logs in at /wp-login.php as usual, and browsers your team has logged in with go to the login page instead of the message.

**Changelog**

Copying a staging database over your live site wipes everything added on the live site since the copy was made: comments, sign-ups, form entries, orders. So instead, the plugin records everything you change on staging, as a list of what to redo on the live site:

* Settings of this plugin (and of WooCommerce), with the old and new value, for example: Settings > Products: "Enable AJAX add to cart buttons on archives" changed from on to off.
* Theme switched, plugins switched on or off, plugin, theme and WordPress updates.
* Posts, pages, products, coupons, categories, tags and menus created, edited or deleted.

Find it at Tools > Staging Superpowers > Changelog, on every site, with or without WooCommerce. The latest 1,000 changes are kept.

**Troubleshooting**

Find out if a problem is caused by another plugin or by the theme, from Tools > Staging Superpowers > Troubleshooting.

* Every plugin on one row, active ones first: its status, the version on this site (red when a newer version is out, green when up to date) and its latest release with how long ago it came out, plus a link to its changelog. A plugin with a recent release is often the cause.
* Premium plugins with their own updater show the newest version their updater reports.
* Disable or Enable any plugin from its row, or tick several (or select all) and use Disable selected or Enable selected, above or below the table. WooCommerce can be disabled like any other plugin. Staging Superpowers is always on.
* Plugins that need a plugin you disable (their "Requires Plugins" header, or a WooCommerce.com extension when WooCommerce goes off) are disabled with it, and enabling a plugin also enables what it needs. The message afterwards says what else changed and why.
* After every change, the site loads itself in the background, logged out and as you. If that shows a fatal error, the change is undone straight away and the plugin or theme that caused it is named, so the staging site never ends up broken.
* Enable everything you disabled here again with one click.
* Every theme on one row with the same details, a "Use this theme" button and a button to go back to the theme you started with.
* Each action is one entry in the changelog, however many plugins it changed.
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
* When visitors are shown the message instead of the site, they cannot use the cart and checkout API either, so nobody places orders that go nowhere.
* The changelog also records WooCommerce settings and the cart or checkout switching between blocks and classic.

Settings are at Tools > Staging Superpowers.

**Staging Superpowers Pro**

Staging Superpowers blocks what could hurt your live site. [Staging Superpowers Pro](https://www.businessbloomer.com/plugins/staging-superpowers-pro/) adds tools for working on the copy:

* Anonymize users, customers, form entries and staff, and remove secret keys, before you hand the site to a developer or agency.
* Send logged-out visitors to the same page on your live site.
* Compare any page with your live site, side by side.
* Find staging links, images and files still used on your live site.
* On WooCommerce stores: generate and delete test orders, products and customers, scramble revenue, log in as a customer, switch WooCommerce versions and HPOS.

== External services ==

This plugin does not connect to any external service and does not send data anywhere.

The web addresses listed in its settings are services the plugin blocks. The default list is: api.stripe.com, api.paypal.com, api-m.paypal.com, api.braintreegateway.com, connect.squareup.com, api.mollie.com, api.authorize.net, api.mailchimp.com, a.klaviyo.com, api.brevo.com, api.sendinblue.com, connect.mailerlite.com, api.omnisend.com, api.kit.com, api.convertkit.com, api.hubapi.com, ssapi.shipstation.com, api.taxjar.com, rest.avatax.com, graph.facebook.com, google-analytics.com, api.twilio.com, slack.com, hooks.zapier.com, make.com, integromat.com, api-us1.com, api.createsend.com, api.getdrip.com, api.sendgrid.com, mailgun.net, api.postmarkapp.com, sheets.googleapis.com, api.sparkpost.com, mandrillapp.com, bridge.mailpoet.com, api.mailjet.com, api.resend.com, api.mailersend.com, api.smtp2go.com, api.elasticemail.com, api.twitter.com, api.linkedin.com, developer.blog2social.com, blog2social-wordpress-api.adenion.de, onesignal.com, api.pushengage.com, rpc.pingomatic.com, api.cloudflare.com, api.automatorplugin.com, api.shortpixel.com and smushpro.wpmudev.com. When "Connected services" is on, the plugin stops the staging site from contacting them, so a staging copy cannot reach your live accounts. The plugin never sends requests to them itself.

== Installation ==

1. On your staging site, go to Plugins > Add New Plugin, search for "Staging Superpowers" and click Install Now, then Activate.
2. If the site looks like staging, the plugin turns itself on and a red STAGING badge appears in the admin bar. If not, click "This is a staging site: turn on" in the notice at the top of the screen.
3. Go to Tools > Staging Superpowers and review the settings. Every protection is on by default.

Do not install it on your live site. If you do by mistake, it stays off unless someone confirms the site is a copy.

== Frequently Asked Questions ==

= Who is it for? =

Store owners, developers and agencies who test changes on a staging copy before making them on the live site: plugin and theme updates, new features, redesigns, checkout changes.

= Does it work with my host's staging sites? =

Yes. It works with any staging copy, whether your host made it (WP Engine, Kinsta, SiteGround, Cloudways and others), a staging plugin made it, or you copied the site by hand. Most hosting staging addresses are recognized, so it turns itself on. If yours is not, click the button in the notice once.

= Will it break or change my staging site? =

No. The protections work through WordPress filters while the plugin is active: payment methods, webhooks, scheduled tasks and settings stay exactly as they were in the database. Deactivate it and the site behaves as before. Only Troubleshooting changes things, and only when you ask it to: it disables and enables the plugins and themes you pick, and can enable them all again with one click.

= What happens when I push staging to my live site? =

The plugin notices it is on a different address and switches itself off, so your live site keeps sending emails, taking payments and running its scheduled tasks. Even so, think twice before pushing a staging database over a live one: it wipes everything added on the live site since the copy was made. The changelog lists what you changed, so you can redo it instead.

= Do I need WooCommerce? =

No. Every protection except payments, webhooks and the subscription lock works on any WordPress site. Those three turn on by themselves when WooCommerce is active.

= Will it turn itself on if I install it on my live site? =

No. It only turns itself on when the site address or environment clearly says staging. On any other address it shows a notice and waits for an admin to confirm, with a warning, that the site is a copy.

= Visitors see a message instead of my staging site =

Visitors are set to "Show a message instead". Log in at your staging address followed by /wp-login.php and you will see the staging site as normal. To let everyone see it, set Visitors to "Let everyone see the site" in Tools > Staging Superpowers.

= I cloned my staging site to a new URL and the plugin says it is not turned on =

That is the deploy guard. If the new address looks like staging, the plugin turns itself back on by itself. Otherwise click "This is a staging site: turn on for ..." in the admin notice.

= I cloned my site by hand and the plugin says the site is the live site =

A hand-made copy keeps the live site's wp-config.php, which may say the site is in production. If you are sure the site is a copy, click "This is a copy, not my live site" in the admin notice and confirm.

= Does blocking connected services stop every request a plugin makes? =

It blocks requests made through the WordPress HTTP API, which is what almost every WordPress plugin uses. A plugin that bypasses it with its own cURL calls will not be blocked.

= Some background tasks are not running =

That is the automations freeze. WooCommerce and other plugins use scheduled tasks for their own housekeeping too, for example analytics imports and order table syncs. Untick "Scheduled actions" or "WP-Cron" in Tools > Staging Superpowers if you need them, or run the ones you need by hand.

= Can the Staging Test Gateway be used on my live store? =

No. It only exists while the plugin is turned on for the current URL, and the deploy guard switches the plugin off on any other URL.

= What does Staging Superpowers Pro add? =

Tools for working on the copy: anonymizing personal data before you hand the site over, sending visitors to your live site, comparing pages with live, finding staging links on live, and WooCommerce test data and switches. See [Staging Superpowers Pro](https://www.businessbloomer.com/plugins/staging-superpowers-pro/).

== Screenshots ==

1. The status bar shows every protection at a glance, with links to manage each one.
2. The protection settings, with a plain-English explanation for each one.
3. On a WooCommerce store, the Staging Test Gateway is the only payment method at checkout, so no real money moves.
4. Troubleshooting: every plugin with its status, its version on this site and its latest release. Disable or enable one, or several at once; a fatal error is undone straight away.
5. The changelog: a list of everything you changed on staging, to redo on your live site.
6. The message visitors can be shown instead of the staging site.

== Changelog ==

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
