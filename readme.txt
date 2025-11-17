=== Comment Shield AI – Perspective Spam Guard ===
Contributors: yourname
Tags: comments, spam, moderation, ai, perspective, toxicity
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automate comment moderation with the Google Perspective API. Mark comments as approved or spam based on a toxicity score.

== Description ==

- Scan comments via WP-Cron (hourly by default).
- Store a toxicity score per comment.
- Auto-approve comments below a configurable score.
- Mark comments as spam above a configurable score.
- New column in the Comments list to display scores.
- Meta box on the "Edit Comment" screen to manually override the AI decision.
- Optional error logging to PHP's error_log().

== Installation ==

1. Upload the `comment-shield-ai` folder to `/wp-content/plugins/`.
2. Activate the plugin via the "Plugins" menu.
3. Go to **Settings → Comment Shield AI** and enter your Perspective API key.

== Frequently Asked Questions ==

= Does this plugin delete or alter comment content? =

No. It only changes comment status (approved / spam) and stores a toxicity score as comment meta.

= Does it work with non-English comments? =

Yes. By default the plugin sends the languages `en, nl, es` to the Perspective API. You can change this in the settings.

== Changelog ==

= 1.0.0 =
* Initial release.
