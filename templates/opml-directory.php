<?php
/**
 * Directory OPML listing each listed blogroll.
 *
 * @package Blockroll
 *
 * @var array $args {
 *     Template arguments.
 *
 *     @type \WP_Post[] $posts Posts that contain a blogroll block.
 * }
 */

defined( 'ABSPATH' ) || exit;

\Blockroll\Opml::prolog();
?>
<opml version="2.0">
<?php
load_template(
	dirname( BLOCKROLL_PLUGIN_FILE ) . '/templates/opml-site-head.php',
	false,
	array(
		/* translators: %s: site name */
		'title' => sprintf( __( 'Blogrolls on %s', 'blockroll' ), get_bloginfo( 'name' ) ),
		'posts' => $args['posts'],
	)
);
?>
	<body>
<?php foreach ( $args['posts'] as $blockroll_post ) : ?>
	<?php foreach ( \Blockroll\Opml::listed_groups( $blockroll_post ) as $blockroll_group ) : ?>
		<outline text="<?php echo esc_xml( $blockroll_group['own_file'] ? \Blockroll\Opml::group_title( $blockroll_group, \Blockroll\Opml::title( $blockroll_post ) ) : \Blockroll\Opml::title( $blockroll_post ) ); ?>" type="include" url="<?php echo esc_url( \Blockroll\Opml::group_url( $blockroll_post, $blockroll_group ) ); ?>" />
	<?php endforeach; ?>
<?php endforeach; ?>
	</body>
</opml>
