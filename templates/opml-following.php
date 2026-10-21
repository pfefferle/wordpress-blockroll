<?php
/**
 * Following list: the feeds of every listed blogroll, see followspec.org.
 *
 * @package Blockroll
 *
 * @var array $args {
 *     Template arguments.
 *
 *     @type \WP_Post[] $posts Posts with a blogroll that adds a feed to the list.
 *     @type array      $links Normalized links with a feed, each feed once.
 * }
 */

defined( 'ABSPATH' ) || exit;

\Blockroll\Opml::prolog();
?>
<opml version="2.0">
<?php \Blockroll\Opml::script(); ?>
<?php
load_template(
	dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml-site-head.php',
	false,
	array(
		/* translators: %s: site name */
		'title' => sprintf( __( 'Feeds followed on %s', 'blockroll' ), get_bloginfo( 'name' ) ),
		'posts' => $args['posts'],
	)
);
?>
	<body>
<?php echo \Blockroll\Opml::outlines( array( array( 'links' => $args['links'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</body>
</opml>
