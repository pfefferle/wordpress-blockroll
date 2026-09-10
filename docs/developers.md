# Developer documentation

Notes for people who build on the plugin or work on it. Everything a user needs is in the [readme](../readme.md).

## How it works

The links live in the block attributes, so the whole blogroll travels with the post
content. Each link holds `url`, `name`, `description`, `feedUrl`, `photo`, an `xfn`
array, and the date it was added.

Every entry is marked up as an [h-card](https://microformats.org/wiki/h-card) with
[XFN](https://gmpg.org/xfn/) relationships on the link, in an
[XOXO](https://microformats.org/wiki/xoxo) list:

    <ul class="blockroll-list xoxo blogroll">
      <li class="h-card">
        <img class="u-photo" src="…" alt="" loading="lazy">
        <a class="u-url p-name" rel="friend met noopener" href="https://example.com/">Example</a>
        <p class="p-note">A blog about examples</p>
        <a class="u-feed" rel="alternate noopener" type="application/rss+xml" href="…/feed/">feed</a>
      </li>
    </ul>

OPML is a plain query var rather than a rewrite rule, so nothing has to be flushed, and
when the plugin is disabled the URL falls back to the page instead of leaving subscribed
readers with a 404:

* `{page}/?opml` is the OPML of one blogroll page.
* `{page}/?opml&group={anchor}` is the OPML of one block on that page, picked
  by its HTML anchor. `group` is a query var of its own rather than a value of
  `opml`, so `{page}.opml?group={anchor}` works as well. An anchor no block
  has falls back to the whole page.
* `/?opml` is a directory that lists those per-page OPMLs as `<outline type="include">`,
  so a reader references them instead of keeping a copy.
* `/.well-known/recommendations.opml` is the same directory under a well-known address.
  That path has no page behind it, so it does get a rewrite rule, flushed on activation.

A page with more than one blogroll groups them, one `<outline>` per block with the
links nested inside it. The group name is the one WordPress keeps when a block is
renamed in the editor, `metadata.name`, so the block needs no title of its own. The
block also has a "Name" field in the sidebar that writes the same value. A blogroll
that was never named falls back to "Blogroll", and a page with a single blogroll stays
a flat list.

Pages with a blogroll advertise their own OPML with `<link rel="blogroll">`, following
[Dave Winer's proposal](https://danq.me/2024/05/03/23615/), and the front page repeats
those links. A page with several blogrolls adds one pair of links per anchored block,
pointing at its group OPML and at `{page}#{anchor}`. The block supports the HTML anchor,
and renders it as the `id` of its wrapper. Feeds carry the same information as `<source:blogroll>`. The directory
address is never advertised, since it is a list of OPMLs rather than a blogroll.

The anchor is generated from the block name, the way the Heading block derives its
anchor from the heading text: the editor slugs the name and makes it unique against
every other anchor on the page, appending `-1`, `-2` if needed. An anchor set by hand
under Advanced is left alone. Pages saved before anchors existed are migrated lazily:
on the first singular view or OPML request, `Anchors::migrate()` writes the missing
anchors into the blogroll block comments only, with the same rules, directly into
`post_content` so there is no revision, no modified date and no save hook for a
change nobody made.

Two REST routes back the editor, because a browser cannot fetch other people's sites
itself:

* `POST blockroll/v1/discover` takes a URL and returns feed, name, description and photo.
* `POST blockroll/v1/import` takes an OPML file, paste, or URL and returns links.

Which pages have a blogroll is kept in a private taxonomy, updated on save. The link
data still lives in the block, the taxonomy is only an index.

## Development

```bash
composer install
npm install

npm run build         # compile the block assets (build/ is committed)
npm run dev           # watch mode
npm run env-start     # wp-env on ports 8833/8834
composer test:wp-env  # PHPUnit inside wp-env
npm run test:unit     # Jest
npm run lint:js
npm run lint:css
composer lint         # PHP CodeSniffer (WPCS)
```

### File layout

```
wordpress-blockroll/
├── blockroll.php              # plugin bootstrap
├── includes/
│   ├── class-discovery.php    # extracts feed, name, description, photo from HTML
│   ├── class-import.php       # OPML parsing
│   ├── class-links.php        # link normalizing and sorting
│   ├── class-opml.php         # opml output + head discovery links
│   ├── class-index.php        # private taxonomy, kept in sync on save
│   ├── class-xfn.php          # XFN vocabulary and rel helper
│   └── rest/
│       ├── class-discovery-controller.php   # POST blockroll/v1/discover
│       └── class-import-controller.php      # POST blockroll/v1/import
├── templates/
│   ├── opml.php               # OPML of one blogroll page
│   └── opml-directory.php     # directory of all blogroll pages
├── src/blogroll/
│   ├── block.json             # block metadata and attributes
│   ├── render.php             # PHP frontend rendering
│   ├── index.js               # registerBlockType
│   ├── edit.js                # editor UI
│   ├── components/            # link form, import modal, XFN control
│   ├── __tests__/             # Jest tests
│   ├── editor.scss
│   └── style.scss
├── build/                     # compiled assets (committed)
├── tests/                     # PHPUnit tests and fixtures
├── package.json
├── composer.json
├── readme.md
└── docs/developers.md
```
