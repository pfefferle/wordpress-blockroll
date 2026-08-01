/**
 * Internal dependencies
 */
import { move } from '../../src/blogroll/utils';

describe( 'move', () => {
	it( 'moves an element down', () => {
		expect( move( [ 'a', 'b', 'c' ], 0, 1 ) ).toEqual( [ 'b', 'a', 'c' ] );
	} );

	it( 'moves an element up', () => {
		expect( move( [ 'a', 'b', 'c' ], 2, 0 ) ).toEqual( [ 'c', 'a', 'b' ] );
	} );

	it( 'does not mutate the original', () => {
		const original = [ 'a', 'b' ];
		move( original, 0, 1 );
		expect( original ).toEqual( [ 'a', 'b' ] );
	} );
} );
