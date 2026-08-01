/**
 * Pure helpers for sorting, paging, and list handling.
 *
 * Kept free of WordPress imports so they are plain-unit-testable.
 */

/**
 * Move an array element.
 *
 * @param {Array}  list Array.
 * @param {number} from Index to move.
 * @param {number} to   Target index.
 * @return {Array} New array.
 */
export function move( list, from, to ) {
	const next = [ ...list ];
	next.splice( to, 0, ...next.splice( from, 1 ) );
	return next;
}

/**
 * Compare two entries for a sort order.
 *
 * @param {Object} a      First entry: { name, added, index }.
 * @param {Object} b      Second entry.
 * @param {string} sortBy One of "name", "added", "manual".
 * @return {number} Comparison result.
 */
export function compareLinks( a, b, sortBy ) {
	if ( 'name' === sortBy ) {
		return a.name.localeCompare( b.name, undefined, {
			sensitivity: 'base',
		} );
	}
	if ( 'added' === sortBy ) {
		return b.added.localeCompare( a.added );
	}
	return a.index - b.index;
}

/**
 * Number of pages.
 *
 * @param {number} total   Number of entries.
 * @param {number} perPage Entries per page; 0 disables paging.
 * @return {number} Page count, at least 1.
 */
export function pageCount( total, perPage ) {
	return perPage > 0 ? Math.max( 1, Math.ceil( total / perPage ) ) : 1;
}

/**
 * Whether the entry at a position is hidden on the current page.
 *
 * @param {number} index   Position in the sorted list.
 * @param {number} page    Current page, 1-based.
 * @param {number} perPage Entries per page; 0 disables paging.
 * @return {boolean} True when hidden.
 */
export function isHiddenOnPage( index, page, perPage ) {
	return (
		perPage > 0 &&
		( index < ( page - 1 ) * perPage || index >= page * perPage )
	);
}
