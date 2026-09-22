/**
 * The config of @wordpress/scripts, with one addition: the focus-outside
 * hook of @wordpress/compose. It has kept its experimental prefix for
 * years, and it is what the editor's own popovers and dropdowns use.
 */
const config = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...config,
	{
		rules: {
			'@wordpress/no-unsafe-wp-apis': [
				'error',
				{ '@wordpress/compose': [ '__experimentalUseFocusOutside' ] },
			],
		},
	},
];
