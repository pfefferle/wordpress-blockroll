<?php
/**
 * Following list: the feeds of every listed blogroll, see followspec.org.
 *
 * @package Blockroll
 *
 * @var array $args {
 *     Template arguments.
 *
 *     @type \WP_Post[] $posts Posts that contain a blogroll block.
 *     @type array      $links Normalized links with a feed, each feed once.
 * }
 */

defined( 'ABSPATH' ) || exit;

// The list is as fresh as its most recently changed blogroll page.
$blockroll_modified = 0;
foreach ( $args['posts'] as $blockroll_post ) {
	$blockroll_modified = max( $blockroll_modified, (int) get_post_modified_time( 'U', true, $blockroll_post ) );
}

\Blockroll\Opml::prolog();
?>
<opml version="2.0">
	<head>
		<title>
		<?php
		/* translators: %s: site name */
		echo esc_xml( sprintf( __( 'Feeds followed on %s', 'blockroll' ), get_bloginfo( 'name' ) ) );
		?>
		</title>
<?php if ( $blockroll_modified ) : ?>
		<dateModified><?php echo esc_xml( gmdate( 'r', $blockroll_modified ) ); ?></dateModified>
<?php endif; ?>
		<ownerName><?php echo esc_xml( get_bloginfo( 'name' ) ); ?></ownerName>
		<ownerId><?php echo esc_url( home_url( '/' ) ); ?></ownerId>
	</head>
	<body>
<?php echo \Blockroll\Opml::outlines( array( array( 'links' => $args['links'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</body>
</opml>
