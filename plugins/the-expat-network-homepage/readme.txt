=== The Expat Network Homepage ===
Contributors: theexpatnetwork
Tags: homepage, lead capture, candidate form, partner form, india germany, talent, innovation
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A privacy-conscious India–Germany network homepage and native lead-capture system for The Expat Network.

== Description ==

The Expat Network Homepage renders theexpatnetwork.org as an India–Germany talent, innovation and opportunity network through the shortcode:

[ten_homepage]

The plugin owns the candidate and partner forms. Submissions are stored as private, administrator-only WordPress lead records.

The plugin includes:

* Responsive static homepage sections
* Candidate registration and partner enquiry forms
* Server-side validation, allowed-value checks, and length limits
* WordPress nonce protection
* Honeypot, mandatory minimum-completion time, rate limiting, and duplicate protection
* Short-lived server-side restoration of sanitized form values after recoverable errors
* Field-level errors and accessible error summaries
* Private, non-public WordPress lead records
* Administrator and submitter email-status recording
* Automated retention deadlines and daily cleanup
* Configurable legal and thank-you pages
* No external form processor, analytics, file uploads, accounts, dashboards, or job board

== Installation ==

1. Upload and activate the plugin ZIP.
2. Go to Settings > Expat Network Homepage.
3. Confirm the notification email address.
4. Select the Privacy Policy and Imprint pages.
5. Optionally select separate candidate and partner thank-you pages.
6. Add [ten_homepage] to the WordPress page used as the static homepage.
7. Assign that page under Settings > Reading.
8. Disable the theme's separate Content Title for that page so the plugin's hero remains the only H1.
9. Configure authenticated SMTP separately and test admin and submitter emails.

Existing settings, the ten_lead post type, and previously submitted leads are preserved on update. Version 1.2.0 adds missing retention metadata to older leads automatically.

== Lead storage and retention ==

Submissions appear under Expat Network Leads. The custom post type is private, unavailable through the REST API, and restricted to administrators.

Retention deadlines are applied automatically:

* Candidate with optional future-opportunity permission: 365 days
* Candidate without that permission: 60 days
* Partner enquiry: 365 days

A daily WordPress Cron job permanently deletes expired records so personal data is not retained in WordPress Trash beyond the configured deadline. The expiry date is sortable, and the expiry and email acceptance statuses are visible in the lead list and lead detail screen.

WordPress Cron runs only when the site receives traffic. The site operator should verify that scheduled events run reliably on the hosting environment.

== Privacy and security ==

The candidate form does not request CVs, identity documents, residence permits, financial information, health information, or other sensitive documents.

The plugin does not save raw IP addresses or browser user-agent data in lead records. A keyed, salted HMAC of the connecting IP is used only inside short-lived WordPress transients for abuse throttling. Email-based and duplicate-submission keys are also HMAC-derived and expire automatically.

When validation fails, sanitized form values may be stored in a random-token WordPress transient for up to 15 minutes so the visitor does not lose their work. Personal data is never placed in the redirect URL; only the random state token is used.

The site operator remains responsible for:

* Maintaining an accurate Privacy Policy and Imprint consistent with the implemented retention periods
* Restricting WordPress administrator access and securing backups
* Configuring SMTP securely and reviewing delivery logs
* Maintaining WordPress, theme, and plugin updates
* Processing access, correction, withdrawal, and deletion requests
* Obtaining clear permission before sharing candidate information externally

== Email delivery ==

The plugin uses WordPress wp_mail() and records whether WordPress accepted or rejected each admin and submitter send request. A successful wp_mail() result does not prove inbox delivery.

Use authenticated SMTP and verify SPF, DKIM, and DMARC. The submitter's email is used only as Reply-To for the internal notification, not as the From address.

== Caching ==

The shortcode page is marked non-cacheable so public WordPress form nonces remain fresh. The plugin also calls LiteSpeed Cache's documented no-cache hook when available. Verify that any additional host or CDN cache respects this behavior.

== Uninstall ==

Uninstall removes this plugin's settings, data-version option, and scheduled cleanup event. Private lead records are intentionally retained to prevent accidental loss. Delete those records manually before uninstalling when required.

== Changelog ==


= 1.3.0 =
* Repositioned the homepage as the India–Germany Talent, Innovation & Opportunity Network.
* Reordered the homepage around How the Network Works, Opportunities & Talent, Entrepreneurship & Innovation, Partners, Founder Trust, registration forms, expectations, and FAQ.
* Removed the old trust strip and generic final CTA.
* Added a contextual handoff to theexpatnetwork.de for practical Germany tools.
* Expanded candidate interests to include entrepreneurship, technology, research collaboration, and early-career connections.
* Updated partner enquiry categories for employers, recruiters, startups, research bodies, industry/ecosystem organizations, and service partners.
* Added explicit independent/non-government positioning and SETU-safe affiliation boundaries without naming external initiatives.
* Added restrained section-reveal motion with prefers-reduced-motion support.
* Refreshed the visual system with navy, teal, sage, sand, and warm accent tones while preserving the secure native form backend.

= 1.2.0 =
* Enforced mandatory four-second minimum completion time; missing or future timestamps now fail closed.
* Replaced unsalted transient identifiers with keyed SHA-256 HMAC keys.
* Added separate high-tolerance attempt throttling, successful IP throttling, email throttling, and ten-minute duplicate-submission protection.
* Added 32 KB request-size and server-side field-length limits.
* Added field-level validation, accessible error summaries, and short-lived server-side restoration of sanitized values after recoverable errors.
* Added candidate retention deadlines of 60 or 365 days based on optional future-contact permission, and a 365-day partner retention period.
* Added daily permanent expiry cleanup, sortable retention dates, and automatic migration of existing leads.
* Added admin-visible retention expiry and wp_mail() acceptance statuses.
* Fixed pathway required semantics, placeholder contrast, and keyboard focus styling.
* Added conditional Other handling to the partner opportunity type.
* Refined homepage and form copy, including Students & Early Careers and clearer required/optional privacy wording.
* Preserved the shortcode, settings option, lead post type, and existing leads.

= 1.1.0 =
* Replaced Fluent Forms dependencies with native candidate and partner forms.
* Added secure admin-post submission handling, nonce checks, sanitization, and validation.
* Added private administrator-only lead storage.
* Added email notifications and submitter confirmations.
* Added configurable candidate and partner thank-you pages.

= 1.0.0 =
* Initial shortcode-based homepage release using Fluent Forms embeds.
