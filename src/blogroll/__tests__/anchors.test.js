import { isGeneratedFrom, slugOf, uniqueAnchor } from '../anchors';

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

describe( 'isGeneratedFrom', () => {
	it( 'recognizes the slug of the name, with or without a counter', () => {
		expect( isGeneratedFrom( 'blogs', 'Blogs' ) ).toBe( true );
		expect( isGeneratedFrom( 'blogs-1', 'Blogs' ) ).toBe( true );
		expect( isGeneratedFrom( 'blogs-12', 'Blogs' ) ).toBe( true );
		expect( isGeneratedFrom( 'blogroll', '' ) ).toBe( true );
	} );

	it( 'does not take a hand-set anchor for a generated one', () => {
		expect( isGeneratedFrom( 'blogs-archive', 'Blogs' ) ).toBe( false );
		expect( isGeneratedFrom( 'my-blogs', 'Blogs' ) ).toBe( false );
		expect( isGeneratedFrom( 'blogs-', 'Blogs' ) ).toBe( false );
	} );
} );
