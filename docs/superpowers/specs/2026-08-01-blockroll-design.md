# Blockroll — Design Spec

**Date:** 2026-08-01
**Status:** Approved
**Slug / dir:** `wordpress-blockroll` · **Namespace / text-domain:** `blockroll`

## Summary

A single dynamic Gutenberg block, `blockroll/blogroll`, that renders a blogroll of
sites as XFN-annotated [h-cards](https://microformats.org/wiki/h-card), with
visitor-facing sort + paging, an auto-generated OPML feed, OPML import to seed the
block, and **zero admin settings**. Editor is JS/React; frontend is PHP-rendered with
a small [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)
view script for sort/paging.

## Goals

1. Blogroll block with per-link **XFN** relationships and **h-card** microformats markup.
2. Visitor-facing **sorting** and **paging** on the frontend.
3. Implement the [blogroll discovery standard](https://danq.me/2024/05/03/23615/)
   (Dave Winer's `<link rel="blogroll">`) pointing at an OPML file.
4. Generate **OPML**, ideally reachable at `{page}/feed/opml` and `/feed/opml`.
5. **No settings** — no wp-admin options page; the block is self-contained.
6. **Auto-fill from a URL** — given only a link, discover its feed, name, description,
   and avatar.
7. Store all data in **block attributes** (in `post_content`).
8. **OPML importer** to initialize the block from an existing reader export.
9. **JS for editor, PHP for frontend.**

## Non-goals (YAGNI)

- No admin/options settings page.
- No custom DB tables or post meta.
- No scheduled/background feed refresh — enrichment is on-demand.
- No comment/feed reading; Blockroll only *publishes* and *imports* OPML.

## Data model — block attributes only

All state lives in the block's attributes, serialized into `post_content`.

```jsonc
{
  "links": [
    {
      "url":         "https://example.com/",        // htmlUrl (required)
      "name":        "Example",                       // p-name
      "description": "A blog about examples",         // p-note
      "feedUrl":     "https://example.com/feed/",     // xmlUrl (rel=alternate)
      "photo":       "https://example.com/avatar.jpg",// u-photo (avatar/favicon)
      "xfn":         ["friend", "met"],               // XFN rel tokens
      "added":       "2026-08-01"                      // ISO date, for sort=added
    }
  ],
  "sortBy":      "name",   // "name" | "added" | "manual"  (default frontend sort)
  "perPage":     0,         // 0 = no paging; N = N links per page
  "showAvatars": true
}
```

- `links` order is authoritative for `sortBy: "manual"`.
- No other storage. The OPML endpoints parse blocks out of `post_content`.

## Components

### 1. Editor (JS/React) — `src/edit.js`

- Empty-state placeholder offers two actions: **Add link** and **Import OPML**.
- Link list manager: paste a URL → auto-discovery fills name/description/feedUrl/photo
  → any field is editable. Drag to reorder (drives `manual` sort).
- **XFN control** per link: grouped toggles following the
  [XFN spec](https://gmpg.org/xfn/) — friendship (`friend`/`acquaintance`/`contact`),
  physical (`met`), professional (`co-worker`/`colleague`), geographical
  (`co-resident`/`neighbor`), family (`child`/`parent`/`sibling`/`spouse`/`kin`),
  romantic, and identity (`me`). Mutually-exclusive groups enforced (e.g. only one
  friendship token).
- Minimal inspector controls (block-level, not a wp-admin page): default `sortBy`,
  `perPage`, `showAvatars`. This is consistent with "no settings" = no options page.

### 2. Auto-discovery — REST route `POST blockroll/v1/discover`

CORS makes client-side fetching of arbitrary sites impossible, so discovery runs
server-side. Editor calls the route with `{ url }`; PHP fetches via the WP HTTP API
and extracts, in priority order:

| Field       | Source priority                                                        |
|-------------|------------------------------------------------------------------------|
| `feedUrl`   | `<link rel="alternate" type="application/rss+xml \| atom+xml">`         |
| `name`      | h-card `p-name` → feed `<title>` → HTML `<title>`                       |
| `description` | h-card `p-note` → feed description → `<meta name="description">`      |
| `photo`     | h-card `u-photo` → `<link rel="icon">`/favicon                          |

Returns a normalized link object. A per-link **re-fetch** button re-runs discovery.
Nothing is ever fetched on the frontend.

### 3. OPML importer — REST route `POST blockroll/v1/import`

Accepts a file upload, pasted OPML text, or a URL to an OPML file. PHP parses
`<outline>` elements:

| OPML attr     | Link field    |
|---------------|---------------|
| `text`/`title`| `name`        |
| `xmlUrl`      | `feedUrl`     |
| `htmlUrl`     | `url`         |
| `description` | `description` |

Returns a `links` array to populate the block. **Enrichment is opt-in**: a
"fetch details for imported links" checkbox; when off, no per-link HTTP requests are
made (so importing a 200-feed OPML is instant). When on, each link is passed through
the discovery route to fill `photo`/missing `name`.

### 4. Frontend — `render.php` (dynamic render callback)

Renders every link as an h-card `<li>` with XFN `rel` on the anchor. The full list is
always present in the DOM — microformats-parseable and functional with JS disabled.

```html
<ul class="wp-block-blockroll-blogroll">
  <li class="h-card">
    <img class="u-photo" src="…" alt="" loading="lazy">
    <a class="u-url p-name" rel="friend met" href="https://example.com/">Example</a>
    <p class="p-note">A blog about examples</p>
    <a class="u-feed" rel="alternate" type="application/rss+xml" href="…/feed/">feed</a>
  </li>
  …
</ul>
```

### 5. Frontend interactivity — `src/view.js` (Interactivity API)

A small store handles **sort** (name / added / manual) and **paging** client-side over
the already-rendered list — no reload. First page shown; remaining pages hidden via
CSS when JS is active (progressive enhancement — no JS ⇒ full list visible, unpaged).

### 6. Blogroll index — private taxonomy `blockroll_has`

To find posts that contain a blogroll without scanning `post_content`, a **private
taxonomy** (`public => false`, `show_ui => false`, `rewrite => false`) is registered
against the block's post types. On `save_post`, `has_block( 'blockroll/blogroll', $post )`
decides whether the post gets the term or has it removed. This is an index only; the
link data still lives in block attributes. The taxonomy join is indexed, so the root
directory is a cheap `get_posts()` term query at any scale. A public `/blogrolls/`
archive can be exposed later by flipping the taxonomy public — not now (YAGNI).

### 7. OPML output + discovery link — `includes/class-opml.php`

- `add_feed( 'opml', … )` registers the feed handler.
  - `{page}/feed/opml` (singular) → parse that post's blockroll block(s) → a full OPML
    of that page's links.
  - `/feed/opml` (site root) → a **directory OPML that lists the per-page OPMLs**, one
    `<outline type="link" url="{page}feed/opml">` per post carrying the `blockroll_has`
    term. It does not inline every link; readers that don't follow the links still see
    a clean list of blogroll pages. Built from the taxonomy query above.
- On `wp_head` for singular views containing the block, emit the discovery link:
  ```html
  <link rel="blogroll" type="text/xml" href="{page}feed/opml" title="…'s blogroll">
  ```
  Only these pages get the `rel="blogroll"` link. The root `/feed/opml` still exists as
  an endpoint, but it is a directory of OPMLs rather than a blogroll, so it is **not**
  advertised with `rel="blogroll"` anywhere.

## File layout

```
wordpress-blockroll/
├── blockroll.php              # plugin bootstrap: register block, feed, REST, hooks
├── block.json                 # block metadata + attributes
├── render.php                 # PHP frontend render callback
├── includes/
│   ├── class-discovery.php    # REST: /discover  (feed + h-card + favicon extraction)
│   ├── class-import.php       # REST: /import    (OPML parse)
│   ├── class-opml.php         # add_feed handler (page OPML + root directory OPML)
│   ├── class-index.php        # blockroll_has taxonomy, maintained on save_post
│   └── class-xfn.php          # XFN token vocabulary + rel rendering helper
├── src/
│   ├── index.js               # registerBlockType
│   ├── edit.js                # editor UI (link manager, XFN, import)
│   ├── view.js                # Interactivity API: sort + paging
│   ├── editor.scss
│   └── style.scss
├── build/                     # compiled assets (@wordpress/scripts)
├── package.json
├── composer.json
└── README.md
```

## Error handling

- Discovery/import fetch failures return a structured REST error; the editor shows an
  inline notice and still lets the user save the link with whatever fields exist.
- Malformed OPML → 422 with a human-readable message; nothing is imported.
- URLs are validated/escaped (`esc_url_raw`) before any HTTP request; feed and photo
  URLs re-escaped on output. XFN tokens whitelisted against the known vocabulary.
- OPML endpoints output correct `Content-Type: text/xml` and escape all values.

## Testing

- **PHP (PHPUnit):** OPML generation from block attributes (per-page + aggregate),
  OPML import parsing (well-formed, malformed, missing attrs), discovery extraction
  against fixture HTML (h-card present / feed-only / bare `<title>`), XFN whitelist,
  `wp_head` discovery-link output, root directory OPML lists exactly the tagged posts,
  `blockroll_has` term added/removed as the block is added/removed on save.
- **JS (Jest):** attribute reducers, XFN group exclusivity, view.js sort/paging store.
- **Manual:** editor add/import/reorder; frontend sort/page with and without JS;
  validate output h-cards with a microformats parser and OPML with an OPML validator.

## Open defaults (chosen, changeable)

- `perPage` default `0` (no paging until set).
- Root `/feed/opml` is a directory of per-page OPMLs, built from the `blockroll_has`
  taxonomy (indexed). A public `/blogrolls/` archive is possible later by making the
  taxonomy public; deferred.
