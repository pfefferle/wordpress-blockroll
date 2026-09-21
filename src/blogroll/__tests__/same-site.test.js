import { sameSite } from '../utils';

describe( 'sameSite', () => {
	it( 'ignores scheme, www, case and the trailing slash', () => {
		expect(
			sameSite( 'https://notiz.blog/', 'http://www.Notiz.blog' )
		).toBe( true );
		expect( sameSite( 'notiz.blog', 'https://notiz.blog/' ) ).toBe( true );
	} );

	it( 'keeps paths apart', () => {
		expect(
			sameSite( 'https://a.example/blog', 'https://a.example/' )
		).toBe( false );
	} );

	it( 'never matches an empty address', () => {
		expect( sameSite( '', '' ) ).toBe( false );
		expect( sameSite( undefined, 'https://a.example/' ) ).toBe( false );
	} );
} );
