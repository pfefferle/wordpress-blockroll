import { hasLegacyLinks, migrateLinks } from '../utils';

describe( 'hasLegacyLinks', () => {
	it( 'is true only for a non-empty links array', () => {
		expect( hasLegacyLinks( { links: [ { url: 'x' } ] } ) ).toBe( true );
		expect( hasLegacyLinks( { links: [] } ) ).toBe( false );
		expect( hasLegacyLinks( {} ) ).toBe( false );
	} );
} );

describe( 'migrateLinks', () => {
	it( 'empties the attribute and returns the links with an address', () => {
		const [ attributes, links ] = migrateLinks( {
			anchor: 'blogroll',
			sortBy: 'manual',
			links: [
				{ url: 'https://a.example/', name: 'A' },
				{ name: 'no url' },
				{ url: 'https://b.example/', xfn: [ 'me' ] },
			],
		} );
		expect( attributes ).toEqual( {
			anchor: 'blogroll',
			sortBy: 'manual',
			links: [],
		} );
		expect( links ).toEqual( [
			{ url: 'https://a.example/', name: 'A' },
			{ url: 'https://b.example/', xfn: [ 'me' ] },
		] );
	} );
} );
