/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Number of pages for the current context.
 *
 * @param {Object} context Interactivity context.
 * @param {number} total   Number of list items.
 * @return {number} Page count.
 */
const pageCount = ( context, total ) =>
	context.perPage > 0 ? Math.ceil( total / context.perPage ) : 1;

/**
 * The block's list element for the current element.
 *
 * @return {HTMLElement|null} The list.
 */
const getList = () =>
	getElement().ref.closest( '[data-wp-interactive]' )?.querySelector(
		'.blockroll-list'
	);

/**
 * Sort and page the rendered list according to the context.
 */
const apply = () => {
	const context = getContext();
	const list = getList();
	if ( ! list ) {
		return;
	}
	const items = Array.from( list.children );

	if ( 'name' === context.sortBy ) {
		items.sort( ( a, b ) =>
			a.dataset.name.localeCompare( b.dataset.name, undefined, {
				sensitivity: 'base',
			} )
		);
	} else if ( 'added' === context.sortBy ) {
		items.sort( ( a, b ) => b.dataset.added.localeCompare( a.dataset.added ) );
	} else {
		// Restore the order the editor saved.
		items.sort( ( a, b ) => a.dataset.index - b.dataset.index );
	}
	items.forEach( ( item ) => list.appendChild( item ) );

	const per = context.perPage;
	items.forEach( ( item, i ) => {
		item.hidden =
			per > 0 &&
			( i < ( context.page - 1 ) * per || i >= context.page * per );
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
			return context.page >= pageCount( context, total );
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
