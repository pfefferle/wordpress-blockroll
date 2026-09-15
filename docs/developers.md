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
those links. Feeds carry the same information as `<source:blogroll>`. The directory
address is never advertised, since it is a list of OPMLs rather than a blogroll.

Two REST routes back the editor, because a browser cannot fetch other people's sites
itself:

* `POST blockroll/v1/discover` takes a URL and returns feed, name, description and photo.
* `POST blockroll/v1/import` takes an OPML file, paste, or URL and returns links.
* `GET blockroll/v1/sources` returns a collection of source choices contributed
  through `blockroll_sources`.
* `GET blockroll/v1/sources/{source}/links` returns normalized links for a
  registered source so the editor can preview dynamic sources.

Plugins can provide dynamic block sources without storing their links in the post
content. Add a label with `blockroll_sources`, then return link arrays for that slug
with `blockroll_source_links`. Returned links use the same shape as saved links:
`url`, `name`, `description`, `feedUrl`, `photo`, `xfn`, and `added`. The render path
and OPML export both normalize those links before output. Source slugs should use
lowercase letters, digits, dashes and underscores so they survive `sanitize_key()`.
Use `blockroll_source_help` to show source-specific help text in the editor when that
source is selected. Use `blockroll_source_help_url` to add a link to the place where
the source can be managed.

```php
add_filter(
	'blockroll_sources',
	function ( $sources ) {
		$sources['my-reader'] = __( 'My Reader', 'my-plugin' );
		return $sources;
	}
);

add_filter(
	'blockroll_source_links',
	function ( $links, $source, $attributes ) {
		if ( 'my-reader' !== $source ) {
			return $links;
		}

		return array(
			array(
				'url'         => 'https://example.com/',
				'name'        => 'Example',
				'description' => 'A site from another plugin.',
				'feedUrl'     => 'https://example.com/feed/',
				'photo'       => 'https://example.com/avatar.jpg',
				'xfn'         => array(),
				'added'       => '2026-09-15',
			),
		);
	},
	10,
	3
);

add_filter(
	'blockroll_source_help',
	function ( $help, $source ) {
		if ( 'my-reader' !== $source ) {
			return $help;
		}

		return __( 'Manage this source in My Reader.', 'my-plugin' );
	},
	10,
	2
);

add_filter(
	'blockroll_source_help_url',
	function ( $url, $source ) {
		if ( 'my-reader' !== $source ) {
			return $url;
		}

		return admin_url( 'admin.php?page=my-reader' );
	},
	10,
	2
);
```

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
│   ├── editor.scss
│   └── style.scss
├── build/                     # compiled assets (committed)
├── tests/                     # PHPUnit and Jest tests
├── package.json
├── composer.json
├── readme.md
└── docs/developers.md
```
