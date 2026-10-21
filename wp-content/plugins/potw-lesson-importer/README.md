# POTW Lesson Importer

Copies posts from the old site (`peopleofthewordonline.wordpress.com`) into the **Lesson** post type, including every PDF / Word file. Admin screen: **Lessons → Import from old site**.

## What maps where

| Old post | Lesson |
|---|---|
| Title (e.g. "Acts 6:1-15") | Title **and** `lesson_verse` (verse only set when the title starts with a Bible book + chapter) |
| Publish date | Post date, `lesson_start_date`; `lesson_end_date` = start + 7 days |
| File links labelled *Homework* / *Worksheet* | `homework_file` rows (name, year, PDF, Word) |
| All other file links (Lecture, Devotion, calendars, bios…) | `lesson_file` rows (name, year, PDF, Word) |
| Other content (e.g. video buttons) | Post content |
| Bible book from the verse (fallback: old categories) | Category |

Word + PDF of the same document (same person + year) are paired into one row. Name/year are read from labels like `POW Lesson #6 – Acts 6:1-15 – Lecture (Word) – Ellen Wong (2026)`; missing year falls back to the post's year. Devotions and other documents use a descriptive name, e.g. "AM Devotion – Dan Leppo".

Files land in the Media Library in the same `uploads/YYYY/MM/` folder as on the old site, attached to the lesson.

## Re-running

* **Sync new & changed** – imports posts that are new, were edited on the old site (by `modified` date), or had a file error last time.
* **Re-import everything** – rewrites all lessons. Files already downloaded are reused (keyed by old upload path); a file re-uploaded on the old site gets a new URL, so it is downloaded fresh.
* Per lesson: **Re-import** row action in the Lessons list, or the button in the "Imported from old site" box on the edit screen.
* **Lock** checkbox on a lesson = importer never touches it.
* Never overwritten: Memory Verse, Podcast, Video, featured image, tags, slug (after creation).
* Optional daily background sync (Settings on the importer page).
* WP-CLI: `wp potw-lessons sync [--all] [--id=<old id>]`, `wp potw-lessons preview <old id>`, `wp potw-lessons status`.

If the mapping rules change, bump `POTW_LI_Importer::MAPPING_VERSION`; the next sync re-imports every lesson made with the older version.

## Tracking meta

Lesson: `_potw_source_id`, `_potw_source_modified_gmt`, `_potw_source_hash`, `_potw_source_link`, `_potw_import_version`, `_potw_imported_at`, `_potw_import_lock`.
Attachment: `_potw_source_key` (e.g. `2026/10/file.pdf`), `_potw_source_url`.
