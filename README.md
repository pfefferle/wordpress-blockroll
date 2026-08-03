# Blockroll

A blogroll block for WordPress. You add a list of sites you read or people you know,
and it renders them as [h-cards](https://microformats.org/wiki/h-card) with
[XFN](https://gmpg.org/xfn/) relationships, lets visitors sort and page through them,
and publishes the whole thing as an OPML feed so other people can subscribe to your
list.

The name is a bad pun: a Gutenberg *block* that renders a *blogroll*.

## What it does

- **One block, `blockroll/blogroll`.** No options page, no global config. Everything
  lives in the block.
- **Add a link, get the rest for free.** Paste a URL and it tries to find the site's
  feed, name, description, and avatar for you. You can edit any of it afterwards.
- **XFN and h-cards.** Each entry is marked up as an h-card, and the relationships you
  pick (friend, met, colleague, and so on) are written as `rel` values on the link.
- **Sorting and paging on the frontend.** Both are plain links with query parameters,
  rendered and handled entirely in PHP. There is no JavaScript on the frontend. You
  can turn the visitor-facing sorting off per block.
- **OPML out.** The block exposes its links as OPML at `{page}/?opml`. The site
  root, `/?opml`, is a directory that lists all of those per-page OPMLs.
- **Blogroll discovery.** Pages with a blogroll advertise their own OPML in the head
  with `<link rel="blogroll">`, following
  [Dave Winer's proposal](https://danq.me/2024/05/03/23615/), and the front page
  advertises them too, so readers can find the blogroll from the homepage. The
  site-root directory is never advertised this way, since it is a list of OPMLs rather
  than a blogroll.
- **OPML in.** You can seed a block by importing an OPML file, for example the export
  from your feed reader.

## How it works

### The block stores everything itself

There is no settings page and no database table. All the link data is saved in the
block's attributes, inside the post content:

```
<!-- wp:blockroll/blogroll {"links":[{"url":"…","feedUrl":"…","xfn":["friend","met"]}]} -->
```

Each link holds `url`, `name`, `description`, `feedUrl`, `photo`, an `xfn` array, and
the date it was added. The block also keeps a default sort order, a page size, whether
to show site icons, and whether visitors may re-sort the list.

### Auto-fill and import happen on the server

A browser cannot fetch other people's sites directly, so the editor asks the plugin to
do it. There are two REST routes:

- `POST blockroll/v1/discover` takes a URL, fetches it, and pulls out the feed (from
  `<link rel="alternate">`), the name and description (from an h-card, then the feed,
  then the page title), and an avatar (from an h-card photo, then the favicon).
- `POST blockroll/v1/import` takes an OPML file, paste, or URL and turns its
  `<outline>` entries into links. Fetching extra details for each imported link is
  opt-in, so importing a 200-feed OPML does not fire 200 requests unless you ask it to.

### The frontend is PHP only

The block is rendered by `render.php`. Every link becomes an h-card list item with the
XFN relationships as `rel`:

```html
<li class="h-card">
  <img class="u-photo" src="…" alt="" loading="lazy">
  <a class="u-url p-name" rel="friend met noopener" target="_blank" href="https://example.com/">Example</a>
  <p class="p-note">A blog about examples</p>
  <a href="feed:…/feed/" title="Subscribe">…</a>
  <a class="u-feed" rel="alternate noopener" type="application/rss+xml" href="…/feed/">feed</a>
</li>
```

The feed icon links with the `feed:` protocol, so a click lands in the visitor's feed
reader; the "feed" text links to the plain URL. The chosen relationships are also shown
as a small list under each entry.

Sorting and paging are links with the query parameters `blockroll-sort` and
`blockroll-page`, so each click is a normal page load and only the current page of
links is in the HTML. Sort options only show up when they make sense: "Newest first"
needs links with dates, and the manual order is only offered (as "Default") when it is
the block's own default. There is no frontend JavaScript at all.

### OPML and discovery

The plugin registers an `opml` query var: append `?opml` to a page and you get the
OPML for that page's blogroll. A plain query var needs no rewrite rules, and when the
plugin is disabled the URL falls back to the page itself instead of a 404.

At the site root, `/?opml` emits a directory OPML that lists each per-page OPML with
`<outline type="link">`, so a reader gets one entry per blogroll page rather than one
big merged list. An OPML request for a page without a blogroll gets the theme's
regular 404 page.

To find those pages cheaply, the plugin keeps a private taxonomy up to date on save: a
post gets the term when it contains the block and loses it when it does not. The link
data still lives in the block, the taxonomy is only an index.

Pages that have a blogroll add the discovery link to their head, pointing at their own
OPML, and the front page repeats those links. The root directory URL never appears in
a head:

```html
<link rel="blogroll" type="text/xml" href="https://example.com/links/?opml"
      title="Example's blogroll">
```

## File layout

```
wordpress-blockroll/
├── blockroll.php              # plugin bootstrap
├── includes/
│   ├── class-discovery.php    # extracts feed, name, description, photo from HTML
│   ├── class-import.php       # OPML parsing
│   ├── class-links.php        # link normalizing and sorting
│   ├── class-opml.php         # opml feed + head discovery links
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
│   ├── editor.scss
│   └── style.scss
├── build/                     # compiled assets (committed)
├── tests/                     # PHPUnit and Jest tests
├── package.json
├── composer.json
└── README.md
```

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

## Status

Early.

## License

GPL-2.0-or-later.
