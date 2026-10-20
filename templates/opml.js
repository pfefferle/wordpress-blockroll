/**
 * Shows an OPML document of the plugin as a page in browsers without XSLT.
 *
 * Browsers with XSLT apply opml.xsl and never run this script. The others
 * run it, because the OPML has an XHTML script element in it, and it
 * replaces the XML with the same page as the stylesheet, see
 * https://jakearchibald.com/2025/making-xml-human-readable-without-xslt/
 *
 * The translated labels are passed in a JSON script element with the id
 * "blockroll-opml-l10n".
 */
( function () {
	const XHTML = 'http://www.w3.org/1999/xhtml';
	const opml = document.documentElement;

	/**
	 * Creates an HTML element. Text is added as text, never as markup.
	 *
	 * @param {string}           name       The tag name.
	 * @param {Object}           attributes The attributes.
	 * @param {...(Node|string)} children   The children.
	 * @return {Element} The element.
	 */
	const el = ( name, attributes = {}, ...children ) => {
		const element = document.createElementNS( XHTML, name );

		for ( const [ key, value ] of Object.entries( attributes ) ) {
			element.setAttribute( key, value );
		}

		element.append( ...children.filter( ( child ) => null !== child ) );

		return element;
	};

	/**
	 * Parses markup of the plugin itself, e.g. a translation with a link.
	 *
	 * @param {string} html The markup.
	 * @return {Node[]} The nodes.
	 */
	const markup = ( html ) => [
		...new window.DOMParser().parseFromString( html, 'text/html' ).body
			.childNodes,
	];

	/**
	 * Returns a URL if it is a web address, e.g. not "javascript:".
	 *
	 * @param {string|null} url The URL.
	 * @return {string|null} The absolute URL, or null.
	 */
	const webUrl = ( url ) => {
		if ( ! url ) {
			return null;
		}

		try {
			const parsed = new URL( url, window.location.href );
			return [ 'http:', 'https:' ].includes( parsed.protocol )
				? parsed.href
				: null;
		} catch {
			return null;
		}
	};

	/**
	 * A link that opens in a new tab, without href if the URL is not a web address.
	 *
	 * @param {Object}           attributes The attributes.
	 * @param {string|null}      url        The URL.
	 * @param {...(Node|string)} children   The children.
	 * @return {Element} The link.
	 */
	const link = ( attributes, url, ...children ) => {
		const href = webUrl( url );
		return el(
			'a',
			{
				...attributes,
				target: '_blank',
				rel: 'noopener noreferrer',
				...( href ? { href } : {} ),
			},
			...children
		);
	};

	/**
	 * The child outlines of an element.
	 *
	 * @param {Element} parent The parent element.
	 * @return {Element[]} The outlines.
	 */
	const outlines = ( parent ) =>
		[ ...parent.children ].filter(
			( child ) => 'outline' === child.localName
		);

	// Chrome ignores defer in XML documents and runs the script before the
	// rest of the document is parsed.
	document.addEventListener( 'DOMContentLoaded', () => {
		const l10n = JSON.parse(
			document.getElementById( 'blockroll-opml-l10n' ).textContent
		);
		const title = opml.querySelector( 'head > title' )?.textContent || '';
		const modified = opml.querySelector(
			'head > dateModified'
		)?.textContent;
		const body = opml.querySelector( 'body' );

		/**
		 * One site, used both on its own and inside a group.
		 *
		 * @param {Element} outline The outline of the site.
		 * @param {string}  heading The heading, "h3" inside a group.
		 * @return {Element[]} The heading, the description and the feed.
		 */
		const site = ( outline, heading ) => {
			const description = outline.getAttribute( 'description' );
			const feed = outline.getAttribute( 'xmlUrl' );
			const href = webUrl( feed );

			return [
				el(
					heading,
					{},
					link(
						{},
						outline.getAttribute( 'htmlUrl' ),
						outline.getAttribute( 'text' ) || ''
					)
				),
				description ? el( 'p', { class: 'meta' }, description ) : null,
				feed
					? el(
							'ul',
							{ class: 'feeds' },
							el(
								'li',
								{},
								el( 'span', { class: 'format' }, 'FEED' ),
								link( { class: 'url' }, feed, feed ),
								el(
									'a',
									{
										class: 'subscribe',
										title: l10n.subscribeIn,
										...( href
											? { href: 'feed:' + href }
											: {} ),
									},
									l10n.subscribe
								)
							)
					  )
					: null,
			];
		};

		const style = `
			:root { color-scheme: light dark; --accent: #2271b1; --muted: #646970; --line: #dcdcde; --intro: #f0f6fc; }
			@media (prefers-color-scheme: dark) { :root { --accent: #72aee6; --muted: #a7aaad; --line: #3c434a; --intro: #1d2327; } }
			* { box-sizing: border-box; }
			svg { max-width: 100%; }
			a { color: var(--accent); }
			body { --gap: 5vw; margin: 0; font-family: system-ui; line-height: 1.7; }
			h1,h2,h3 { margin-block-start: 0; margin-block-end: 0; }
			.pb-5 { padding-bottom: calc(var(--gap) / 3); }
			.meta { color: var(--muted); }
			.group {
				font-size: 0.9rem;
				text-transform: uppercase;
				letter-spacing: 0.08em;
				color: var(--muted);
				padding-block-end: 0.4rem;
				margin-block-end: 1rem;
				border-block-end: 1px solid var(--line);
			}
			.container {
				display: grid;
				gap: var(--gap);
				max-width: 46rem;
				width: 95%;
				margin: auto;
			}
			.intro {
				background-color: var(--intro);
				margin-block-end: var(--gap);
				padding-block: calc(var(--gap) / 2);
			}
			.intro .container {
				gap: 1rem;
				grid-template-columns:  4fr 2fr;
				align-items: top;
			}
			@media (min-width: 40rem) {
				.intro .container {
				grid-template-columns:  4fr 1fr;
				align-items: center;
				}
			}
			.intro svg { width: 5rem; height: auto; justify-self: end; }
			.recent {
				padding-block-end: var(--gap);
			}
			.feeds {
				list-style: none;
				margin-block: 0.5rem 0;
				padding: 0;
				display: grid;
				gap: 0.4rem;
			}
			.feeds li {
				display: grid;
				grid-template-columns: 3.5rem 1fr auto;
				align-items: baseline;
				gap: 0.25rem 0.6rem;
			}
			.feeds .format {
				font-weight: 600;
				font-size: 0.8rem;
				color: var(--accent);
			}
			.feeds .url {
				min-width: 0;
				font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
				font-size: 0.85rem;
				word-break: break-all;
			}
			.feeds .subscribe {
				padding: 0.15em 0.7em;
				border: 1px solid var(--line);
				border-radius: 6px;
				font-size: 0.8rem;
				text-decoration: none;
				white-space: nowrap;
			}
			.feeds .subscribe:hover {
				border-color: var(--accent);
			}
			@media (max-width: 32rem) {
				.feeds li {
					display: flex;
					flex-wrap: wrap;
				}
				.feeds .url {
					flex-basis: 100%;
					order: 1;
				}
				.feeds .subscribe {
					order: 2;
				}
			}
		`;

		const icon = markup( `
			<svg xmlns="http://www.w3.org/2000/svg" version="1.1" id="OPMLicon" width="128" height="128" viewBox="0 0 256 256">
				<title></title>
				<defs>
					<linearGradient x1="0.903" y1="0.903" x2="0.096" y2="0.096" id="OPMLg">
						<stop offset="0.0" stop-color="#264fa1"/>
						<stop offset="0.1134" stop-color="#2658a1"/>
						<stop offset="0.2984" stop-color="#2570a1"/>
						<stop offset="0.5" stop-color="#2492a1"/>
						<stop offset="0.6147" stop-color="#2489a0"/>
						<stop offset="0.8017" stop-color="#26719f"/>
						<stop offset="1.0" stop-color="#27509d"/>
					</linearGradient>
				</defs>
				<rect width="256" height="256" rx="55" ry="55" x="0" y="0" fill="#244fa1"/>
				<rect width="246" height="246" rx="50" ry="50" x="5" y="5" fill="#2492a1"/>
				<rect width="236" height="236" rx="47" ry="47" x="10" y="10" fill="url(#OPMLg)"/>
				<circle cx="128" cy="128" r="23" fill="#fff"/>
				<path fill="#fff" d="M128 35a93 93 0 1 0 93 93A93 93 0 0 0 128 35Zm0 158a65 65 0 1 1 65-65A65 65 0 0 1 128 193Z"/>
			</svg>
		` ).find( ( node ) => 'svg' === node.localName );
		icon.querySelector( 'title' ).textContent = l10n.icon;

		const html = el(
			'html',
			{},
			el(
				'head',
				{},
				el( 'title', {}, title ),
				el( 'meta', {
					name: 'viewport',
					content:
						'width=device-width, initial-scale=1, maximum-scale=1',
				} ),
				el( 'style', { type: 'text/css' }, style )
			),
			el(
				'body',
				{},
				el(
					'nav',
					{ class: 'intro' },
					el(
						'div',
						{ class: 'container' },
						el(
							'div',
							{},
							el( 'p', {}, ...markup( l10n.intro ) ),
							el( 'p', {}, el( 'small', {}, l10n.import ) ),
							el(
								'p',
								{},
								el( 'small', {}, ...markup( l10n.learnMore ) )
							)
						),
						icon
					)
				),
				el(
					'div',
					{ class: 'container' },
					el(
						'header',
						{},
						el( 'h1', {}, title ),
						modified
							? el(
									'p',
									{ class: 'meta' },
									l10n.lastUpdated.replace( '%s', modified )
							  )
							: null
					),
					el(
						'section',
						{ class: 'recent' },
						// One block per site; the directory OPML has type="include" outlines instead.
						...( body ? outlines( body ) : [] ).map(
							( outline ) => {
								const type = outline.getAttribute( 'type' );

								if ( 'include' === type || 'link' === type ) {
									const url = outline.getAttribute( 'url' );
									return el(
										'div',
										{ class: 'pb-5' },
										el(
											'h2',
											{},
											outline.getAttribute( 'text' ) || ''
										),
										el(
											'ul',
											{ class: 'feeds' },
											el(
												'li',
												{},
												el(
													'span',
													{ class: 'format' },
													'OPML'
												),
												link(
													{ class: 'url' },
													url,
													url || ''
												)
											)
										)
									);
								}

								// A page with several blogrolls nests them, one outline per blogroll.
								const children = outlines( outline );
								if ( children.length ) {
									return el(
										'div',
										{ class: 'pb-5' },
										el(
											'h2',
											{ class: 'group' },
											outline.getAttribute( 'text' ) || ''
										),
										...children.map( ( child ) =>
											el(
												'div',
												{ class: 'pb-5' },
												...site( child, 'h3' )
											)
										)
									);
								}

								return el(
									'div',
									{ class: 'pb-5' },
									...site( outline, 'h2' )
								);
							}
						)
					)
				)
			)
		);

		opml.replaceWith( html );
	} );
} )();
