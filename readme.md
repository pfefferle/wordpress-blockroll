# Blogroll & Podroll Block

- Contributors: pfefferle
- Donate link: https://notiz.blog/donate/
- Tags: blogroll, podroll, opml, links, feeds
- Requires at least: 6.3
- Tested up to: 7.1
- Stable tag: 1.0.0
- Requires PHP: 7.4
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Share the blogs and podcasts you follow, and let other people subscribe to your list.

## Description

A blogroll is a list of the sites you read, put on your own site so other people can
find them. This plugin adds one block for that. You collect the links, the block renders
them on your page, and visitors can take the whole list with them into their feed reader.

It works for anything you want to recommend: blogs, podcasts, the people you know, the
sites of your local club.

**Adding a link is one field.** Paste the address and the plugin looks up the site's
name, description, icon and feed for you. You can correct all of it afterwards, and you
can fill in everything by hand if you prefer.

**Nothing to configure.** There is no settings page and no extra database table. The
list is part of the page it sits on, so it is backed up, revisioned and exported
together with that page.

**Other people can subscribe.** Your list is published as an OPML file, the format feed
readers use for subscription lists. Someone can hand that address to their reader and
follow everything you recommend, and readers can find it on your site by themselves. If
you change your list, their copy stays up to date.

**Bring your list along.** Import the OPML export from your feed reader and the block is
filled in one go, instead of pasting a hundred links by hand.

**Say how you know someone.** You can mark an entry as a friend, a colleague, or someone
you have met. It shows up as a small note under the entry, and it is readable for tools
that map who knows whom.

**Sorting and paging for visitors.** Long lists get pages, and visitors can sort by name
or by what you added last. There is no JavaScript in the list at all, every click is a
normal page load, and you can switch the sorting off per block.

## Frequently Asked Questions

### Where do I find the block?

In the editor, add a block and search for "Blogroll". Put it on any page or post. You
can have several, on one page or spread over many.

### How do I add a link?

Paste the address of the site and the plugin fills in the rest: name, description, icon,
and the feed, if it finds one. Everything stays editable, and links can be reordered by
dragging.

### Can I import the subscriptions from my feed reader?

Yes. Every feed reader can export your subscriptions as an OPML file. Upload that file
in the block, paste its content, or give the plugin the address of an OPML file that is
already online.

Looking up extra details for each imported link is optional, so importing a list of two
hundred feeds does not fetch two hundred sites unless you ask for it.

### How do other people subscribe to my blogroll?

Add `?opml` to the address of the page your blogroll is on, for
example `https://example.com/links/?opml`. That is the address to hand to a feed reader.

You do not have to tell anyone about it. Pages with a blogroll announce it in their
HTML, and so does your homepage, so a reader that looks for it will find it. Sites that
collect blogrolls look at `https://example.com/.well-known/recommendations.opml`, which
lists all blogrolls on your site.

There is also a download link under the list, for people who prefer a file.

### Does it work for podcasts?

Yes. A podroll is a blogroll of podcasts, and both are the same kind of list. Podcast
apps read the same OPML files.

### Where are the settings?

There are none. Everything belongs to the block: which links, in which order, how many
per page, whether icons and sorting are shown.

### Does the plugin send data anywhere?

Only where you point it. When you paste a link, your site fetches that address to read
the site's name, feed and icon, and when you import a list from an address, your site
fetches that file. Nothing else leaves your server, and there is no tracking, no
analytics and no account anywhere.

### Does this need JavaScript?

Not for your visitors. The list, the sorting and the paging are plain HTML rendered on
your server. The editor uses JavaScript, like the rest of the block editor does.

### Where is the developer documentation?

The markup of the list, the OPML endpoints, the discovery links and the two REST
routes are described in the
[developer docs](https://github.com/pfefferle/wordpress-blockroll/blob/main/docs/developers.md).

## Screenshots

1. A blogroll on a page, with the sorting and a link to each feed.
2. The same list as an OPML file, which is what a feed reader gets when someone subscribes.

## Changelog

Project and support maintained on github at [pfefferle/wordpress-blockroll](https://github.com/pfefferle/wordpress-blockroll).

### 1.0.0

* First release.

## Installation

Follow the normal instructions for [installing WordPress plugins](https://wordpress.org/documentation/article/manage-plugins/).

### Automatic Plugin Installation

To add a WordPress Plugin using the [built-in plugin installer](https://wordpress.org/documentation/article/manage-plugins/#installing-plugins):

1. Go to Plugins > Add New.
1. Type "`blogroll`" into the **Search Plugins** box.
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
