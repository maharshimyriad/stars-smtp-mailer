=== Stars SMTP Mailer ===
Contributors: aditya.dugar
Author: Myriad Solutionz
Author URI: https://myriadsolutionz.com
Plugin URI: https://myriadsolutionz.com/stars-smtp-mailer
Tags: smtp mailer,mail log,wp smtp,php mail alternative,smtp plugin
Requires at least: 5.8 or higher
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 2.3
Version: 2.3
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html


== Description ==

Every email your WordPress website sends is important — whether it’s a contact form message, password reset, order update, or newsletter. These emails help you stay connected with your users, build trust, and keep your business running smoothly.

But by default, WordPress uses a basic PHP mail function — and that often leads to problems. Emails may get blocked by your hosting server, flagged as spam, or never arrive at all. You may not even know anything went wrong.

That’s where our SMTP plugin makes a difference.

It replaces the unreliable default method with SMTP (Simple Mail Transfer Protocol) — the same trusted system used by major email services like Gmail, Outlook, SendGrid, Mailgun, and more. It sends your messages through real, authenticated mail servers for secure, consistent delivery — every time.


== New in Version 2.3 ==

🔒 Stronger Password Encryption:
SMTP passwords are now encrypted using a unique per-site key derived from your WordPress security salts — replacing the old hardcoded key. A random IV is generated for every encryption operation (AES-256-CBC). Existing passwords are automatically migrated on first load with no action required.

📊 Brand-New Dashboard:
The plugin now has a fully redesigned Dashboard page with:
- Stat cards showing today's emails, 7-day accepted/failed counts, and success rate
- An interactive bar chart (Chart.js) for email activity over the last 7 days
- A "Recent Emails" table showing the last 5 log entries at a glance
- An active SMTP account panel with quick Edit and Send Test links
- An Overview sidebar showing account count, log usage (with a warning at 180/200), and plugin version
- Quick Actions panel for one-click access to Add Account, Send Test Email, View Logs, and Export CSV

📥 Export & Clear Logs:
Email logs can now be exported as a CSV file directly from the Email Log page or the Dashboard. A nonce-protected "Clear Logs" button lets you wipe all log entries with one click. A confirmation notice is shown after clearing.

🔍 Advanced Log Filtering:
Filter email logs by status (Accepted / Failed) and type (Test / General) using new dropdown selectors on the Email Log page.

❗ Log Cap Warning:
A dashboard notice and admin-bar warning appear when the email log reaches 180 of 200 entries, prompting you to export or clear old data before entries are auto-pruned.

🛡️ SSRF Protection:
SMTP hostname validation now blocks Server-Side Request Forgery attempts by rejecting loopback, private (RFC-1918), link-local, and reserved IP ranges before any connection is made.

🖥️ WordPress Dashboard Widget:
A new widget on the main WordPress Dashboard displays a 7-day sent/failed bar chart, today's email count, and the currently active SMTP account — at a glance without leaving the dashboard.

🔐 Security Hardening:
- AJAX actions now require the `manage_options` capability and verify CSRF nonces
- Account add/edit form processing moved to `admin_init` to prevent headers-already-sent warnings and enable proper redirect after save
- Unauthenticated access to the notify/subscribe endpoint removed; rate limiting (one request per user per hour) added
- All form inputs consistently sanitized and validated

📬 Weekly Email Reports (introduced in 2.2.1, refined in 2.3):
Your site automatically sends a visual weekly summary of email activity to the site admin — includes total emails sent, last week's count, and percentage change.


== What is SMTP and Why You Need It ==

SMTP (Simple Mail Transfer Protocol) is the global standard for sending emails. It’s how major providers like Gmail, Yahoo, Outlook, and others securely deliver billions of emails every day.

In contrast, WordPress’s default PHP mail is unverified and often blocked. SMTP ensures your messages come from a verified email server, improving both trust and deliverability.

With SMTP, you get:
- Higher email delivery success rates
- Fewer emails lost or sent to spam
- Improved domain reputation
- Consistent, professional communication

If your WordPress site sends any type of email — SMTP is essential.

== How the Plugin Works ==

Our plugin makes advanced email delivery simple — even for non-technical users.
A step-by-step Setup Wizard guides you through connecting your site to the email provider of your choice. Setup takes just minutes — no coding or server configuration needed.

**Supported Email Providers:**
- Gmail / G Suite
- Outlook / Office 365
- Zoho Mail
- SendGrid
- Mailgun
- Amazon SES
- Custom SMTP servers

== Real-Time Email Logs & Tracking ==

No more guessing if your email was sent. With detailed logs and tracking, you can:
- View sent, failed, and queued emails
- See time-stamped delivery data
- Filter logs by form, date, recipient, or status

This ensures complete visibility and control over your site’s email activity.

== Built for Security and Reliability ==

Your email reputation is critical. That’s why this plugin includes built-in features to protect your site and ensure emails are trusted.

**Security Features:**
- SPF, DKIM, and DMARC authentication
- Bounce alerts and instant failure detection

Whether you run a personal blog or a large eCommerce platform, this plugin is built to scale with you.

== Multilingual Support ==

Now available in 6 additional languages, so you can manage your WordPress email setup in your preferred language.

**Supported Languages:**
- Spanish
- French
- Arabic
- Portuguese
- Russian
- German

== Why Choose This Plugin? ==

- Beginner-friendly, no coding needed
- Fast, lightweight, and performance-optimized
- Works with all major SMTP providers
- Designed for blogs, business websites, and high-volume eCommerce stores

== Key Features Summary ==

- Send emails using Gmail, Outlook, SendGrid, Mailgun, Zoho Mail, Amazon SES, and others
- Easy step-by-step setup wizard
- Real-time email logs with status tags, filtering by status and type, CSV export, and one-click clear
- Brand-new Dashboard with stat cards, 7-day bar chart, recent emails table, and quick actions
- WordPress Dashboard widget showing live email stats and active account
- Log cap warning when approaching the 200-entry limit
- Stronger AES-256-CBC encryption with per-site unique keys and random IVs for stored SMTP passwords
- SSRF protection blocking private/reserved IP ranges on SMTP host validation
- Built-in support for SPF, DKIM, and DMARC authentication
- Weekly email reports with sent/failed stats delivered to the admin inbox
- Multilingual support — available in Spanish, French, Arabic, Portuguese, Russian, and German
- Secure and reliable delivery across all WordPress sites
- Scalable for any size website — from small blogs to enterprise platforms

Make sure your WordPress emails get delivered — not lost or ignored.
Install the SMTP plugin today for reliable, secure, and professional email delivery.


== Installation ==
= Plugin =
1. Copy the 'StarsSMTPMailer' folder into your plugins folder
2. Activate the plugin via the Plugins admin page

== Screenshots ==
1. Add new SMTP account (/plugin-directory/screenshots/screenshot-1.png)
3. Email Logs (/plugin-directory/screenshots/screenshot-3.png)
4. Send test email (/plugin-directory/screenshots/screenshot-4.png)
2. Manage SMTP account (/plugin-directory/screenshots/screenshot-2.png)



== Frequently Asked Questions ==

= Why are my WordPress emails not being delivered or ending up in spam? =
WordPress uses PHP mail by default, which many hosts block or mark as spam. Our SMTP plugin routes your emails through trusted providers like Gmail, Outlook, or SendGrid, ensuring they are authenticated and delivered directly to inboxes.

= Do I need technical knowledge to set up this plugin? =
No. The plugin includes a guided Setup Wizard that walks you through each step. Most users can configure it in just a few minutes — no coding or email server experience required.

= What email providers can I use with this plugin? =
You can connect your site to all major email services including Gmail, Outlook, Office 365, Zoho Mail, SendGrid, Mailgun, Amazon SES, and even custom SMTP servers.

= Can I track and manage the emails my site sends? =
Yes! The plugin offers advanced email logs where you can view sent, failed, or queued emails. You can filter, search, and even resend failed messages — all from your WordPress dashboard.

= Is the plugin available in other languages? =
Yes. The plugin is now available in Spanish, French, Arabic, Portuguese, Russian, and German — so you can manage settings and view messages in your preferred language.



== Changelog ==
= 2.3 =
* Redesigned Dashboard page with stat cards, 7-day bar chart (Chart.js), recent emails table, active account panel, overview sidebar, and quick actions.
* New WordPress Dashboard widget showing 7-day sent/failed chart and active account.
* CSV export for email logs — available from both the Email Log page and the Dashboard.
* One-click "Clear Logs" button with nonce protection and post-clear confirmation notice.
* Advanced log filtering by status (Accepted / Failed) and type (Test / General).
* Log cap warning notice when log entries reach 180 / 200.
* Stronger password encryption: per-site unique key (derived from WordPress salts) + random IV per value (AES-256-CBC). Automatic migration of legacy passwords on first load.
* SSRF protection: blocks loopback, RFC-1918 private, link-local, and reserved IP ranges on SMTP host validation.
* Account add/edit form processing moved to admin_init for reliable redirects and no headers-already-sent errors.
* AJAX handlers secured with capability checks and CSRF nonce verification.
* Unauthenticated subscribe endpoint removed; per-user hourly rate limiting added.
* Error details modal in Email Log with plain-English and technical tabs.
= 2.2.1 =
* Added weekly email report feature — visual summary sent to admin inbox every week.
* Introduced Pro version with pre-subscription option.
= 2.2.0 =
* Added support for multiple languages: Spanish, French, Arabic, Portuguese, Russian, and German.
* Fixes and performance improvements.
= 2.1.6 =
* Fixes and performance improvement.
= 2.0 =
* Fixes and performance improvement.
= 1.6 =
* Fixes and performance improvement.
= 1.5 =
* Bug fixes and performance improvement.
= 1.4 =
* Added functionality to keep records of attachments in email log.
= 1.3 =
* Updated WordPress version compatibility.
* Bug fixes and performance improvement.
= 1.2 =
* Bug fixes and performance improvement.
= 1.1 =
* Added screen option for pagination.
* Added option to add attachments in test email.
* Saving test email attachments in server.
= 1.0 =
* Initial Release.

== Upgrade Notice ==

= 2.3 =
Major release. New Dashboard with charts and stats, CSV export, log filtering, log cap warnings, stronger encryption with automatic password migration, SSRF protection, and multiple security hardening improvements. Upgrade recommended for all users.

= 2.2.1 =
Added weekly email update feature. Introduced Pro version with pre-subscription option.

= 2.2.0 =
Added multilingual support for 6 languages. Recommended for expanded language accessibility and overall performance improvements.