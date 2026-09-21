<?php
/**
 * OPML template for a single blogroll page.
 *
 * @package Blockroll
 *
 * @var array $args {
 *     Template arguments.
 *
 *     @type \WP_Post $post   The post.
 *     @type string   $title  Title of the list.
 *     @type array    $groups Blogroll blocks, each with a "name", an "anchor" and "links".
 * }
 */

defined( 'ABSPATH' ) || exit;

// Posts created without an author have none, and an empty element helps nobody.
$blockroll_owner = get_the_author_meta( 'display_name', $args['post']->post_author );

\Blockroll\Opml::prolog();
?>
<opml version="2.0">
	<head>
		<title><?php echo esc_xml( $args['title'] ); ?></title>
		<dateModified><?php echo esc_xml( get_post_modified_time( 'r', true, $args['post'] ) ); ?></dateModified>
<?php if ( $blockroll_owner ) : ?>
		<ownerName><?php echo esc_xml( $blockroll_owner ); ?></ownerName>
<?php endif; ?>
	</head>
	<body>
<?php echo \Blockroll\Opml::outlines( $args['groups'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</body>
</opml>
