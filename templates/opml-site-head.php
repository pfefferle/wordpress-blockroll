<?php
/**
 * Head of the OPML files of the whole site: the directory and the
 * following list.
 *
 * @package Blockroll
 *
 * @var array $args {
 *     Template arguments.
 *
 *     @type string     $title Title of the file.
 *     @type \WP_Post[] $posts Posts the file is made of.
 * }
 */

defined( 'ABSPATH' ) || exit;

// The file is as fresh as its most recently changed blogroll page.
$blockroll_modified = 0;
foreach ( $args['posts'] as $blockroll_post ) {
	$blockroll_modified = max( $blockroll_modified, (int) get_post_modified_time( 'U', true, $blockroll_post ) );
}
?>
	<head>
		<title><?php echo esc_xml( $args['title'] ); ?></title>
<?php if ( $blockroll_modified ) : ?>
		<dateModified><?php echo esc_xml( gmdate( 'r', $blockroll_modified ) ); ?></dateModified>
<?php endif; ?>
		<ownerName><?php echo esc_xml( get_bloginfo( 'name' ) ); ?></ownerName>
		<ownerId><?php echo esc_url( home_url( '/' ) ); ?></ownerId>
	</head>
