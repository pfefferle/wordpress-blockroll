<?php
/**
 * OPML template for a single blogroll page.
 *
 * @package Blockroll
 *
 * @var \WP_Post $post  The post.
 * @var array    $blockroll_links Normalized links.
 */

defined( 'ABSPATH' ) || exit;

echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
?>
<opml version="2.0">
	<head>
		<title><?php echo esc_xml( get_the_title( $post ) ); ?></title>
		<dateModified><?php echo esc_xml( get_post_modified_time( 'r', true, $post ) ); ?></dateModified>
		<ownerName><?php echo esc_xml( get_bloginfo( 'name' ) ); ?></ownerName>
	</head>
	<body>
<?php foreach ( $blockroll_links as $blockroll_link ) : ?>
		<outline text="<?php echo esc_attr( $blockroll_link['name'] ? $blockroll_link['name'] : $blockroll_link['url'] ); ?>" type="rss"<?php echo $blockroll_link['description'] ? ' description="' . esc_attr( $blockroll_link['description'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $blockroll_link['feedUrl'] ? ' xmlUrl="' . esc_url( $blockroll_link['feedUrl'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> htmlUrl="<?php echo esc_url( $blockroll_link['url'] ); ?>" />
<?php endforeach; ?>
	</body>
</opml>
