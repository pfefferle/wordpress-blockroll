/**
 * Internal dependencies
 */
import { applyXfnToken, sanitizeXfn } from '../../src/blogroll/components/xfn';

describe( 'applyXfnToken', () => {
	it( 'adds a token', () => {
		expect( applyXfnToken( [], 'friend', true ) ).toEqual( [ 'friend' ] );
	} );

	it( 'removes a token', () => {
		expect( applyXfnToken( [ 'friend', 'met' ], 'met', false ) ).toEqual( [
			'friend',
		] );
	} );

	it( 'replaces within an exclusive group', () => {
		expect(
			applyXfnToken( [ 'friend', 'met' ], 'acquaintance', true )
		).toEqual( [ 'met', 'acquaintance' ] );
	} );

	it( 'me clears everything', () => {
		expect( applyXfnToken( [ 'friend', 'met' ], 'me', true ) ).toEqual( [
			'me',
		] );
	} );

	it( 'adding another token drops me', () => {
		expect( applyXfnToken( [ 'me' ], 'friend', true ) ).toEqual( [
			'friend',
		] );
	} );
} );

describe( 'sanitizeXfn', () => {
	it( 'drops unknown tokens', () => {
		expect( sanitizeXfn( [ 'friend', 'nonsense' ] ) ).toEqual( [
			'friend',
		] );
	} );

	it( 'the newest token wins within an exclusive group', () => {
		expect( sanitizeXfn( [ 'friend', 'met', 'acquaintance' ] ) ).toEqual( [
			'met',
			'acquaintance',
		] );
	} );

	it( 'me added last clears everything', () => {
		expect( sanitizeXfn( [ 'friend', 'met', 'me' ] ) ).toEqual( [ 'me' ] );
	} );

	it( 'a token added after me replaces it', () => {
		expect( sanitizeXfn( [ 'me', 'friend' ] ) ).toEqual( [ 'friend' ] );
	} );
} );
