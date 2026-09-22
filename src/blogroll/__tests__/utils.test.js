/**
 * Internal dependencies
 */
import { mergeDiscovered } from '../utils';

describe( 'mergeDiscovered', () => {
	it( 'fills empty fields only', () => {
		const merged = mergeDiscovered(
			{ url: 'https://a.example/', name: 'Mine', feedUrl: '' },
			{
				name: 'Theirs',
				feedUrl: 'https://a.example/feed/',
				photo: 'https://a.example/p.jpg',
			}
		);
		expect( merged.name ).toBe( 'Mine' );
		expect( merged.feedUrl ).toBe( 'https://a.example/feed/' );
		expect( merged.photo ).toBe( 'https://a.example/p.jpg' );
		expect( merged.url ).toBe( 'https://a.example/' );
	} );
} );
