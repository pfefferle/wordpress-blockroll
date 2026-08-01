/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { compareLinks, pageCount, isHiddenOnPage } from './utils';

/**
 * The block's list element for the current element.
 *
 * @return {HTMLElement|null} The list.
 */
const getList = () =>
	getElement()
		.ref.closest( '[data-wp-interactive]' )
		?.querySelector( '.blockroll-list' );

/**
 * Sort and page the rendered list according to the context.
 */
const apply = () => {
	const list = getList();
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
};

store( 'blockroll', {
	state: {
		get isFirstPage() {
			return getContext().page <= 1;
		},
		get isLastPage() {
			const context = getContext();
			const list = getList();
			const total = list ? list.children.length : 0;
			return context.page >= pageCount( total, context.perPage );
		},
	},
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
			const { ref } = getElement();
			ref.querySelector( '.blockroll-controls' )?.removeAttribute(
				'hidden'
			);
			apply();
		},
	},
} );
