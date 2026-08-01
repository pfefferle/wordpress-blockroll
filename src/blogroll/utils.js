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
