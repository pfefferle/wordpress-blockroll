import { siteKey } from '../utils';

describe( 'siteKey', () => {
	it( 'ignores scheme, www, case and the trailing slash', () => {
		expect( siteKey( 'https://notiz.blog/' ) ).toBe(
			siteKey( 'http://www.Notiz.blog' )
		);
		expect( siteKey( 'notiz.blog' ) ).toBe( 'notiz.blog' );
	} );

	it( 'keeps paths apart', () => {
		expect( siteKey( 'https://a.example/blog' ) ).not.toBe(
			siteKey( 'https://a.example/' )
		);
	} );

	it( 'is empty for no address', () => {
		expect( siteKey( '' ) ).toBe( '' );
		expect( siteKey( undefined ) ).toBe( '' );
	} );
} );
