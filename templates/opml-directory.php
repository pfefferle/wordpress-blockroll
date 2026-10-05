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

// The directory is as fresh as its most recently changed blogroll page.
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
		echo esc_xml( sprintf( __( 'Blogrolls on %s', 'blockroll' ), get_bloginfo( 'name' ) ) );
		?>
		</title>
<?php if ( $blockroll_modified ) : ?>
		<dateModified><?php echo esc_xml( gmdate( 'r', $blockroll_modified ) ); ?></dateModified>
<?php endif; ?>
		<ownerName><?php echo esc_xml( get_bloginfo( 'name' ) ); ?></ownerName>
		<ownerId><?php echo esc_url( home_url( '/' ) ); ?></ownerId>
	</head>
	<body>
<?php foreach ( $args['posts'] as $blockroll_post ) : ?>
	<?php foreach ( \Blockroll\Opml::listed_groups( $blockroll_post ) as $blockroll_group ) : ?>
		<outline text="<?php echo esc_xml( $blockroll_group['own_file'] ? \Blockroll\Opml::group_title( $blockroll_group, \Blockroll\Opml::title( $blockroll_post ) ) : \Blockroll\Opml::title( $blockroll_post ) ); ?>" type="include" url="<?php echo esc_url( \Blockroll\Opml::group_url( $blockroll_post, $blockroll_group ) ); ?>" />
	<?php endforeach; ?>
<?php endforeach; ?>
	</body>
</opml>
