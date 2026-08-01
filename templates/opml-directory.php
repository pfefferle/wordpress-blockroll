<?php
/**
 * Directory OPML listing all blogroll pages.
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

echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
?>
<opml version="2.0">
	<head>
		<title>
		<?php
		/* translators: %s: site name */
		echo esc_xml( sprintf( __( 'Blogrolls on %s', 'blockroll' ), get_bloginfo( 'name' ) ) );
		?>
		</title>
	</head>
	<body>
<?php foreach ( $args['posts'] as $blockroll_post ) : ?>
		<outline text="<?php echo esc_attr( \Blockroll\Opml::title( $blockroll_post ) ); ?>" type="link" url="<?php echo esc_url( \Blockroll\Opml::feed_url( $blockroll_post ) ); ?>" />
<?php endforeach; ?>
	</body>
</opml>
