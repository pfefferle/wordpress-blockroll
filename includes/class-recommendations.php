<?php
/**
 * The well-known recommendations of the site.
 *
 * @package Blockroll
 */

namespace Blockroll;

/**
 * The recommendations at /.well-known/recommendations.opml.
 *
 * For now the same file as the Directory at the site root. A class of its
 * own, so the two can go separate ways: overriding render() is enough.
 */
class Recommendations extends Directory {
	/**
	 * Path of the well-known recommendations, without the leading slash.
	 *
	 * See https://opml.org/
	 */
	const PATH = '.well-known/recommendations.opml';

	/**
	 * Value of the `opml` query var that asks for the recommendations.
	 */
	const QUERY_VALUE = 'recommendations';
}
