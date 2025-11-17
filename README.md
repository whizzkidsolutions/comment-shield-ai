# Comment Shield AI — Perspective Spam Guard

[![WordPress](https://img.shields.io/badge/WordPress-Plugin-blue.svg)](#)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](#)
[![License](https://img.shields.io/badge/License-GPLv2-orange.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Automate WordPress comment moderation with the **Google Perspective API**.  
Mark comments automatically as *approved* or *spam* based on toxicity scores.

## ✨ Features

- Automatic AI moderation via Perspective
- Cron-based scanning (hourly by default)
- Toxicity score per comment shown in the Comments overview
- Meta box on “Edit Comment” to override AI decisions
- Fully configurable thresholds
- Safe fallback (no score → no automated status change)
- Optional error logging to `error_log()` for production diagnostics
- No extra database tables
- Localization-ready (English default, Spanish and other languages via translation files)

## 📦 Installation

1. Download the plugin folder `comment-shield-ai`.
2. Upload it to `wp-content/plugins/` or symlink via your local dev environment.
3. Activate the plugin in the WordPress Dashboard.
4. Go to **Settings → Comment Shield AI** and enter your API key.

## ⚙️ Settings

- **Perspective API Key**
- **Spam threshold** (score ≥ X → spam)
- **Auto-approve threshold** (score ≤ Y → approved)
- **Batch size per cron run**
- **Languages** for Perspective (e.g. `en, nl, es`)
- **Enable error logging** (on/off)

## 🧩 How it works

1. New comments arrive as “Pending” (or hold).
2. WP-Cron runs periodically and processes a batch of pending comments.
3. Each comment is scored via Perspective.
4. The response determines the status:
   - Score ≥ spam threshold → **spam**
   - Score ≤ auto-approve threshold → **approved**
   - Everything in between stays in moderation

## 🧪 Debugging

On errors (timeouts, API failures, malformed responses) the plugin can log to:

```text
error_log()
```

Most hosting environments expose this via:
`/wp-content/debug.log`, `php-error.log`, or a server log viewer.

## 🌍 Localization

The plugin ships with:

- English source strings (default)
- A `.pot` file for translators
- A starter Spanish translation file (`es_ES`)

Use standard WordPress tooling (e.g. `wp i18n make-pot`, Poedit, or Loco Translate) to extend or customize translations.

## 🧹 Uninstall

When uninstalling the plugin:

- All plugin settings are removed.
- All stored toxicity scores are removed from comment meta.

## 📄 License

GPL v2 or later.
