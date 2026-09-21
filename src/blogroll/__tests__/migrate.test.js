import { hasLegacyLinks, linkBlockAttributes, migrateLinks } from '../utils';

describe( 'linkBlockAttributes', () => {
	it( 'fills every field', () => {
		expect( linkBlockAttributes( { url: 'https://a.example/' } ) ).toEqual(
			{
				url: 'https://a.example/',
				name: '',
				description: '',
				feedUrl: '',
				photo: '',
				xfn: [],
				added: '',
			}
		);
	} );

	it( 'drops unknown fields and keeps known ones', () => {
		expect(
			linkBlockAttributes( {
				url: 'https://a.example/',
				name: 'A',
				xfn: [ 'friend' ],
				extra: 1,
			} )
		).toEqual( {
			url: 'https://a.example/',
			name: 'A',
			description: '',
			feedUrl: '',
			photo: '',
			xfn: [ 'friend' ],
			added: '',
		} );
	} );
} );

describe( 'hasLegacyLinks', () => {
	it( 'is true only for a non-empty links array', () => {
		expect( hasLegacyLinks( { links: [ { url: 'x' } ] } ) ).toBe( true );
		expect( hasLegacyLinks( { links: [] } ) ).toBe( false );
		expect( hasLegacyLinks( {} ) ).toBe( false );
	} );
} );

describe( 'migrateLinks', () => {
	it( 'empties the attribute and returns the links as block attributes', () => {
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
		expect( links.map( ( link ) => link.url ) ).toEqual( [
			'https://a.example/',
			'https://b.example/',
		] );
		expect( links[ 1 ].xfn ).toEqual( [ 'me' ] );
	} );
} );
