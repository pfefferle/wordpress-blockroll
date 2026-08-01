/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { compareLinks, pageCount, isHiddenOnPage } from './utils';

/**
 * The block root for the current element.
 *
 * @return {HTMLElement|null} The root.
 */
const getRoot = () => getElement().ref.closest( '[data-wp-interactive]' );

/**
 * Sort and page the rendered list according to the context.
 */
const apply = () => {
	const root = getRoot();
	const list = root?.querySelector( '.blockroll-list' );
	if ( ! list ) {
		return;
	}
	const context = getContext();
	const items = Array.from( list.children );

	items.sort( ( a, b ) =>
		compareLinks(
			{
				name: a.dataset.name,
				added: a.dataset.added,
				index: a.dataset.index,
			},
			{
				name: b.dataset.name,
				added: b.dataset.added,
				index: b.dataset.index,
			},
			context.sortBy
		)
	);
	items.forEach( ( item ) => list.appendChild( item ) );

	items.forEach( ( item, i ) => {
		item.hidden = isHiddenOnPage( i, context.page, context.perPage );
	} );

	const prev = root.querySelector( '.blockroll-prev' );
	const next = root.querySelector( '.blockroll-next' );
	if ( prev ) {
		prev.disabled = context.page <= 1;
	}
	if ( next ) {
		next.disabled =
			context.page >= pageCount( items.length, context.perPage );
	}
};

store( 'blockroll', {
	actions: {
		setSort( event ) {
			const context = getContext();
			context.sortBy = event.target.value;
			context.page = 1;
			apply();
		},
		nextPage() {
			getContext().page++;
			apply();
		},
		prevPage() {
			const context = getContext();
			context.page = Math.max( 1, context.page - 1 );
			apply();
		},
	},
	callbacks: {
		init() {
			getRoot()
				?.querySelector( '.blockroll-controls' )
				?.removeAttribute( 'hidden' );
			apply();
		},
	},
} );
