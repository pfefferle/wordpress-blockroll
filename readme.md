# Blockroll

- Contributors: pfefferle
- Donate link: https://notiz.blog/donate/
- Tags: blogroll, opml, xfn, indieweb, microformats
- Requires at least: 6.3
- Tested up to: 7.1
- Stable tag: 0.1.0
- Requires PHP: 7.4
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

A blogroll block: share a list of the blogs and sites you follow.

## Description

Blockroll adds one block to the editor. You put in a list of sites you read or people
you know, and it renders them as [h-cards](https://microformats.org/wiki/h-card) with
[XFN](https://gmpg.org/xfn/) relationships, lets visitors sort and page through them,
and publishes the whole list as OPML so other people can subscribe to it.

The name is a bad pun: a Gutenberg *block* that renders a *blogroll*.

**One block, no options page.** Everything lives in the block, there is no settings
screen and no extra database table. The link data is saved in the block's attributes,
inside the post content.

**Add a link, get the rest for free.** Paste a URL and the plugin tries to find the
site's feed, name, description, and avatar for you. You can edit any of it afterwards.

**XFN and h-cards.** Each entry is marked up as an h-card, and the relationships you
pick (friend, met, colleague, and so on) are written as `rel` values on the link.

**Sorting and paging on the frontend.** Both are plain links with query parameters,
rendered and handled entirely in PHP. There is no JavaScript on the frontend at all.
You can turn the visitor-facing sorting off per block.

**OPML out.** The block exposes its links as OPML at `{page}/?opml`. The site root,
`/?opml`, is a directory that lists all of those per-page OPMLs.

**Blogroll discovery.** Pages with a blogroll advertise their own OPML in the head with
`<link rel="blogroll">`, following
[Dave Winer's proposal](https://danq.me/2024/05/03/23615/), and the front page
advertises them too, so readers can find the blogroll from the homepage.

**OPML in.** You can seed a block by importing an OPML file, for example the export
from your feed reader.

## Frequently Asked Questions

### Where is the settings page?

There is none. Everything is a block attribute, so the whole blogroll travels with the
post content. Each link holds `url`, `name`, `description`, `feedUrl`, `photo`, an
`xfn` array, and the date it was added. The block also keeps a default sort order, a
page size, whether to show site icons, and whether visitors may re-sort the list.

### How does the auto-fill work?

A browser cannot fetch other people's sites directly, so the editor asks the plugin to
do it. There are two REST routes:

* `POST blockroll/v1/discover` takes a URL, fetches it, and pulls out the feed (from
  `<link rel="alternate">`), the name and description (from an h-card, then the feed,
  then the page title), and an avatar (from an h-card photo, then the favicon).
* `POST blockroll/v1/import` takes an OPML file, paste, or URL and turns its
  `<outline>` entries into links.

Fetching extra details for each imported link is opt-in, so importing a 200-feed OPML
does not fire 200 requests unless you ask it to.

### What does the markup look like?

Every link becomes an h-card list item with the XFN relationships as `rel`:

    <li class="h-card">
      <img class="u-photo" src="…" alt="" loading="lazy">
      <a class="u-url p-name" rel="friend met noopener" target="_blank" href="https://example.com/">Example</a>
      <p class="p-note">A blog about examples</p>
      <a href="feed:…/feed/" title="Subscribe">…</a>
      <a class="u-feed" rel="alternate noopener" type="application/rss+xml" href="…/feed/">feed</a>
    </li>

The feed icon links with the `feed:` protocol, so a click lands in the visitor's feed
reader; the "feed" text links to the plain URL. The chosen relationships are also shown
as a small list under each entry.

### How do sorting and paging work?

They are links with the query parameters `blockroll-sort` and `blockroll-page`, so each
click is a normal page load and only the current page of links is in the HTML. Sort
options only show up when they make sense: "Newest first" needs links with dates, and
the manual order is only offered (as "Default") when it is the block's own default.

### How do I get the OPML of a page?

Append `?opml` to the page URL. The site root, `/?opml`, gives you a directory OPML
that lists each per-page OPML with `<outline type="include">`, so a reader gets one entry
per blogroll page rather than one big merged list. `include` means the entries are
referenced, not copied: a reader that follows them pulls in the current list every time
instead of keeping a stale copy.

This is a plain query var rather than a rewrite rule, so nothing has to be flushed, and
if you disable the plugin the URL falls back to the page itself instead of a 404. On a
page without a blogroll the query var is simply ignored and the normal page loads.

The directory is also served at `/.well-known/recommendations.opml`, so a reader can find
it by convention instead of parsing the HTML first. That path has no page behind it, so
it does get a rewrite rule, which is flushed on activation. It is always the directory,
even when there is only one blogroll page, so the URL means the same thing everywhere.

### How does the plugin find the pages that have a blogroll?

It keeps a private taxonomy up to date on save: a post gets the term when it contains
the block and loses it when it does not. The link data still lives in the block, the
taxonomy is only an index.

Pages that have a blogroll add the discovery link to their head, pointing at their own
OPML, and the front page repeats those links:

    <link rel="blogroll" type="text/xml" href="https://example.com/links/?opml"
          title="Example's blogroll">

The root directory URL never appears in a head, since it is a list of OPMLs rather than
a blogroll.

## Changelog

Project and support maintained on github at [pfefferle/wordpress-blockroll](https://github.com/pfefferle/wordpress-blockroll).

### Unreleased

* Serve the directory OPML at `/.well-known/recommendations.opml`
* Add `dateModified`, `ownerName` and `ownerId` to the directory OPML, with the date
  taken from the most recently changed blogroll page
* Mark the blogroll list up as XOXO, with `class="xoxo blogroll"` on the list
* Add the XFN profile link, `<link rel="profile" href="https://gmpg.org/xfn/11" />`, to
  every page, because XFN is not limited to the blogroll
* List the per-page OPMLs in the directory as `type="include"` instead of `type="link"`,
  so a reader references them instead of keeping a copy
* Serve the OPML as a `?opml` query var instead of an `opml` feed, so no rewrite rules
  are registered and nothing has to be flushed on activation
* Serve the normal page instead of a 404 when a page without a blogroll is requested
  as OPML, matching what happens when the plugin is disabled
* Declare all public query vars in one place and drop the query var normalizing filter
  that wrote into every frontend request
* Add the plugin's Update URI

## Installation

Follow the normal instructions for [installing WordPress plugins](https://wordpress.org/documentation/article/manage-plugins/).

### Automatic Plugin Installation

To add a WordPress Plugin using the [built-in plugin installer](https://wordpress.org/documentation/article/manage-plugins/#installing-plugins):

1. Go to Plugins > Add New.
1. Type "`blockroll`" into the **Search Plugins** box.
1. Find the WordPress Plugin you wish to install.
    1. Click **Details** for more information about the Plugin and instructions you may wish to print or save to help setup the Plugin.
    1. Click **Install Now** to install the WordPress Plugin.
1. The resulting installation screen will list the installation as successful or note any problems during the install.
1. If successful, click **Activate Plugin** to activate it, or **Return to Plugin Installer** for further actions.

### Manual Plugin Installation

There are a few cases when manually installing a WordPress Plugin is appropriate.

* If you wish to control the placement and the process of installing a WordPress Plugin.
* If your server does not permit automatic installation of a WordPress Plugin.
* If you want to try the [latest development version](https://github.com/pfefferle/wordpress-blockroll).

Installation of a WordPress Plugin manually requires FTP familiarity and the awareness that you may put your site at risk if you install a WordPress Plugin incompatible with the current version or from an unreliable source.

Backup your site completely before proceeding.

To install a WordPress Plugin manually:

* Download your WordPress Plugin to your desktop.
    * Download from [the WordPress directory](https://wordpress.org/plugins/blockroll/)
    * Download from [GitHub](https://github.com/pfefferle/wordpress-blockroll/releases)
* If downloaded as a zip archive, extract the Plugin folder to your desktop.
* With your FTP program, upload the Plugin folder to the `wp-content/plugins` folder in your WordPress directory online.
* Go to the Plugins screen and find the newly uploaded Plugin in the list.
* Click **Activate** to activate it.

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
└── readme.md
```
