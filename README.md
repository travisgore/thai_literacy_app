# Thai Literacy App (WordPress Plugin)

This plugin now uses a **custom post type** so each app instance is content-managed in WordPress.

## What you get

- Post type: **Thai Literacy Games** (`tla_literacy_game`)
- Game options per post: Syllable Decoder, Tone Calculator, Grapheme Snap, Vowel Assembler
- UTF-8 Thai-safe CSV template download + bulk CSV upload
- Starter content preloaded as published game posts on plugin activation
- Front-end app with shortcode support and per-post data loading

## Install

1. Copy `thai-literacy-app/` into `wp-content/plugins/`.
2. Activate **Thai Literacy App**.
3. Go to **Thai Literacy Games** in the admin menu.
4. Edit any starter game post, or create your own.

## Content management

Each Thai Literacy Game post includes meta fields for:

- Primary game mode
- App title
- Default mode
- IPA toggle
- Lesson flow
- Content JSON

## CSV bulk tools

Go to **Settings → Thai Literacy App**:

- **Download CSV Template** (UTF-8 BOM for Thai characters)
- **Upload CSV** to bulk create/update `tla_literacy_game` posts

Template columns:

- `post_id` (optional; set to update existing)
- `post_title`
- `post_status`
- `game_mode`
- `app_title`
- `show_ipa` (`1` or `0`)
- `default_mode`
- `lesson_flow_json` (JSON array)
- `content_json` (JSON object)

## Shortcode

- `[thai_literacy_app]` → on a single Thai Literacy Game post, auto-loads that post’s content.
- `[thai_literacy_app post_id="123"]` → load a specific game post anywhere.

## Notes

- Attempt logs are stored in `{wp_prefix}_tla_attempts`.
- REST config endpoint supports `post_id` query parameter.
