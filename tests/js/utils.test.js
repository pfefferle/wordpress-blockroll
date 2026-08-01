/**
 * Internal dependencies
 */
import {
	move,
	compareLinks,
	pageCount,
	isHiddenOnPage,
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

describe( 'compareLinks', () => {
	const ann = { name: 'ann', added: '2026-01-01', index: 2 };
	const ben = { name: 'Ben', added: '2026-06-01', index: 0 };

	it( 'sorts by name, case-insensitive', () => {
		expect( compareLinks( ann, ben, 'name' ) ).toBeLessThan( 0 );
		expect( compareLinks( ben, ann, 'name' ) ).toBeGreaterThan( 0 );
	} );

	it( 'sorts by added, newest first', () => {
		expect( compareLinks( ben, ann, 'added' ) ).toBeLessThan( 0 );
	} );

	it( 'sorts manually by index', () => {
		expect( compareLinks( ben, ann, 'manual' ) ).toBeLessThan( 0 );
	} );

	it( 'compares string indexes numerically', () => {
		expect(
			compareLinks(
				{ name: '', added: '', index: '9' },
				{ name: '', added: '', index: '10' },
				'manual'
			)
		).toBeLessThan( 0 );
	} );
} );

describe( 'pageCount', () => {
	it( 'is 1 when paging is off', () => {
		expect( pageCount( 100, 0 ) ).toBe( 1 );
	} );

	it( 'rounds up', () => {
		expect( pageCount( 11, 5 ) ).toBe( 3 );
	} );

	it( 'is at least 1 for an empty list', () => {
		expect( pageCount( 0, 5 ) ).toBe( 1 );
	} );
} );

describe( 'isHiddenOnPage', () => {
	it( 'shows everything when paging is off', () => {
		expect( isHiddenOnPage( 42, 1, 0 ) ).toBe( false );
	} );

	it( 'shows only the current page', () => {
		expect( isHiddenOnPage( 0, 1, 2 ) ).toBe( false );
		expect( isHiddenOnPage( 1, 1, 2 ) ).toBe( false );
		expect( isHiddenOnPage( 2, 1, 2 ) ).toBe( true );
		expect( isHiddenOnPage( 2, 2, 2 ) ).toBe( false );
		expect( isHiddenOnPage( 0, 2, 2 ) ).toBe( true );
	} );
} );
