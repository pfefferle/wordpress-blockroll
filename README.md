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
- **Sorting and paging on the frontend.** The list is rendered in PHP, and a small
  script sorts and pages it in place without a reload. With JavaScript off you still
  get the full list, just unsorted and unpaged.
- **OPML out.** The block exposes its links as OPML at `{page}/feed/opml`. The site
  root, `/feed/opml`, is a directory that lists all of those per-page OPMLs.
- **Blogroll discovery.** Pages with a blogroll advertise their own OPML in the head
  with `<link rel="blogroll">`, following
  [Dave Winer's proposal](https://danq.me/2024/05/03/23615/), so readers can find it
  automatically. The site-root directory is not advertised this way, since it is a list
  of OPMLs rather than a blogroll.
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
the date it was added. The block also keeps a default sort order, a page size, and
whether to show avatars.

### Auto-fill and import happen on the server

A browser cannot fetch other people's sites directly, so the editor asks the plugin to
do it. There are two REST routes:

- `POST blockroll/v1/discover` takes a URL, fetches it, and pulls out the feed (from
  `<link rel="alternate">`), the name and description (from an h-card, then the feed,
  then the page title), and an avatar (from an h-card photo, then the favicon).
- `POST blockroll/v1/import` takes an OPML file, paste, or URL and turns its
  `<outline>` entries into links. Fetching extra details for each imported link is
  opt-in, so importing a 200-feed OPML does not fire 200 requests unless you ask it to.

### The frontend is plain PHP

The block is rendered by `render.php`. Every link becomes an h-card list item with the
XFN relationships as `rel`:

```html
<li class="h-card">
  <img class="u-photo" src="…" alt="" loading="lazy">
  <a class="u-url p-name" rel="friend met" href="https://example.com/">Example</a>
  <p class="p-note">A blog about examples</p>
  <a class="u-feed" rel="alternate" type="application/rss+xml" href="…/feed/">feed</a>
</li>
```

The whole list is always in the page, which keeps the microformats readable and works
without JavaScript. A small [Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/)
script handles the sorting and paging on top of that.

### OPML and discovery

The plugin registers an `opml` feed. On a single post or page it emits the OPML for
that page's blogroll. At the site root it emits a directory OPML that lists each
per-page OPML with `<outline type="link">`, so a reader gets one entry per blogroll
page rather than one big merged list.

To find those pages cheaply, the plugin keeps a private taxonomy up to date on save: a
post gets the term when it contains the block and loses it when it does not. The link
data still lives in the block, the taxonomy is only an index.

Pages that have a blogroll add the discovery link to their head, pointing at their own
OPML. The root directory is not advertised this way:

```html
<link rel="blogroll" type="text/xml" href="https://example.com/links/feed/opml"
      title="Example's blogroll">
```

## File layout

```
wordpress-blockroll/
├── blockroll.php              # plugin bootstrap
├── block.json                 # block metadata and attributes
├── render.php                 # PHP frontend render callback
├── includes/
│   ├── class-discovery.php    # REST /discover
│   ├── class-import.php       # REST /import (OPML parsing)
│   ├── class-opml.php         # opml feed + head discovery link
│   ├── class-index.php        # private taxonomy, kept in sync on save
│   └── class-xfn.php          # XFN vocabulary and rel helper
├── src/
│   ├── index.js               # registerBlockType
│   ├── edit.js                # editor UI
│   ├── view.js                # sort and paging (Interactivity API)
│   ├── editor.scss
│   └── style.scss
├── build/                     # compiled assets
├── package.json
├── composer.json
└── README.md
```

## Development

```bash
composer install
npm install

npm run build      # compile the block assets
npm run start      # watch mode
composer test      # PHPUnit
npm run lint:js
composer lint      # PHP CodeSniffer (WPCS)
```

## Status

Early.

## License

GPL-2.0-or-later.
