/**
 * Internal dependencies
 */
import {
	move,
	mergeDiscovered,
	needsEmbedding,
} from '../../src/blogroll/utils';

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
			{
				name: 'Theirs',
				feedUrl: 'https://a.example/feed/',
				photo: 'https://a.example/p.jpg',
			}
		);
		expect( merged.name ).toBe( 'Mine' );
		expect( merged.feedUrl ).toBe( 'https://a.example/feed/' );
		expect( merged.photo ).toBe( 'https://a.example/p.jpg' );
		expect( merged.url ).toBe( 'https://a.example/' );
	} );
} );

describe( 'needsEmbedding', () => {
	it( 'knows an image that comes from another site', () => {
		expect( needsEmbedding( 'https://a.example/p.jpg' ) ).toBe( true );
	} );

	it( 'leaves an embedded image alone', () => {
		expect( needsEmbedding( 'data:image/png;base64,iVBORw0KGgo=' ) ).toBe(
			false
		);
	} );

	it( 'leaves an image from the own site alone', () => {
		expect(
			needsEmbedding( window.location.origin + '/uploads/a.png' )
		).toBe( false );
	} );

	it( 'has nothing to do without an image', () => {
		expect( needsEmbedding( '' ) ).toBe( false );
		expect( needsEmbedding( undefined ) ).toBe( false );
	} );
} );
