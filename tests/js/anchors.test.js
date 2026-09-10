import { slugOf, uniqueAnchor } from '../../src/blogroll/anchors';

describe( 'slugOf', () => {
	it( 'slugs a name', () => {
		expect( slugOf( 'Podcasts' ) ).toBe( 'podcasts' );
		expect( slugOf( 'Podcasts über Musik' ) ).toBe( 'podcasts-uber-musik' );
	} );

	it( 'falls back for an empty name', () => {
		expect( slugOf( '' ) ).toBe( 'blogroll' );
		expect( slugOf( undefined ) ).toBe( 'blogroll' );
		expect( slugOf( '???' ) ).toBe( 'blogroll' );
	} );
} );

describe( 'uniqueAnchor', () => {
	it( 'appends a counter while the anchor is taken', () => {
		expect( uniqueAnchor( 'blogs', [] ) ).toBe( 'blogs' );
		expect( uniqueAnchor( 'blogs', [ 'blogs' ] ) ).toBe( 'blogs-1' );
		expect( uniqueAnchor( 'blogs', [ 'blogs', 'blogs-1' ] ) ).toBe(
			'blogs-2'
		);
	} );
} );
