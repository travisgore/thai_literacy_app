# Thai Literacy App (WordPress Plugin)

This repository provides a configurable WordPress plugin for Thai reading instruction with:

- Interactive learner modes (syllable decoder, tone calculator, grapheme snap, vowel assembler)
- Configurable curriculum and data via admin settings JSON
- REST-based attempt tracking for SRS analytics

## Install

1. Copy `thai-literacy-app/` into `wp-content/plugins/`.
2. Activate **Thai Literacy App** in WordPress.
3. Configure settings under **Settings → Thai Literacy App**.
4. Add shortcode `[thai_literacy_app]` to a page.

## Content flexibility

The plugin ships with starter data but does **not** require hard-coded content. Replace the **Content JSON** setting with your own data model items:

- `graphemes`
- `syllables`
- `vowel_families`
- `tone_rules`
- `games`

## Notes

- Tone is computed from a decision table driven by class + mark + syllable type.
- Attempts are stored in `{wp_prefix}_tla_attempts`.
- This MVP is optimized for reading/decoding workflows.
