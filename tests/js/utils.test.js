/**
 * Internal dependencies
 */
import { move, mergeDiscovered } from '../../src/blogroll/utils';

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

describe( 'mergeDiscovered', () => {
	it( 'fills empty fields only', () => {
		const merged = mergeDiscovered(
			{ url: 'https://a.example/', name: 'Mine', feedUrl: '' },
			{ name: 'Theirs', feedUrl: 'https://a.example/feed/', photo: 'https://a.example/p.jpg' }
		);
		expect( merged.name ).toBe( 'Mine' );
		expect( merged.feedUrl ).toBe( 'https://a.example/feed/' );
		expect( merged.photo ).toBe( 'https://a.example/p.jpg' );
		expect( merged.url ).toBe( 'https://a.example/' );
	} );
} );
