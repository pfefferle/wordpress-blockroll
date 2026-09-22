/**
 * Internal dependencies
 */
import { hasLinkBlocks, isLegacyList } from '../utils';

const link = ( url ) => ( { name: 'blockroll/link', attributes: { url } } );
const paragraph = { name: 'core/paragraph', attributes: {} };
const legacy = { links: [ { url: 'https://a.example/', name: 'A' } ] };

describe( 'hasLinkBlocks', () => {
	it( 'counts link blocks only', () => {
		expect( hasLinkBlocks( [ link( 'https://a.example/' ) ] ) ).toBe( true );
		expect( hasLinkBlocks( [ paragraph ] ) ).toBe( false );
		expect( hasLinkBlocks( [] ) ).toBe( false );
		expect( hasLinkBlocks( undefined ) ).toBe( false );
	} );
} );

describe( 'isLegacyList', () => {
	it( 'is a list with links in the attribute and none as blocks', () => {
		expect( isLegacyList( legacy, [] ) ).toBe( true );
	} );

	it( 'is one even when something else is in it', () => {
		expect( isLegacyList( legacy, [ paragraph ] ) ).toBe( true );
	} );

	it( 'is not one when the links are blocks already', () => {
		expect( isLegacyList( legacy, [ link( 'https://b.example/' ) ] ) ).toBe(
			false
		);
	} );

	it( 'is not one without links in the attribute', () => {
		expect( isLegacyList( { links: [] }, [] ) ).toBe( false );
		expect( isLegacyList( {}, [] ) ).toBe( false );
	} );
} );
