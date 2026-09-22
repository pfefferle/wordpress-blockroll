import { toUrl } from '../utils';

describe( 'toUrl', () => {
	it( 'keeps a full address', () => {
		expect( toUrl( 'https://notiz.blog/' ) ).toBe( 'https://notiz.blog/' );
		expect( toUrl( 'http://notiz.blog' ) ).toBe( 'http://notiz.blog' );
	} );

	it( 'adds https to a bare domain', () => {
		expect( toUrl( 'pfefferle.org' ) ).toBe( 'https://pfefferle.org' );
		expect( toUrl( '  pfefferle.org/blog ' ) ).toBe(
			'https://pfefferle.org/blog'
		);
	} );

	it( 'leaves other schemes alone', () => {
		expect( toUrl( 'feed:https://a.example/feed/' ) ).toBe(
			'feed:https://a.example/feed/'
		);
	} );
} );

describe( 'toUrl with a port', () => {
	it( 'treats a port as part of the address, not as a scheme', () => {
		expect( toUrl( 'example.com:8080' ) ).toBe(
			'https://example.com:8080'
		);
		expect( toUrl( 'localhost:3000/blog' ) ).toBe(
			'https://localhost:3000/blog'
		);
	} );

	it( 'still keeps a real scheme', () => {
		expect( toUrl( 'https://example.com:8080/' ) ).toBe(
			'https://example.com:8080/'
		);
		expect( toUrl( 'feed:https://a.example/feed/' ) ).toBe(
			'feed:https://a.example/feed/'
		);
	} );
} );
