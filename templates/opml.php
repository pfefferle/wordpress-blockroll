<?php
/**
 * OPML template for a single blogroll page.
 *
 * @package Blockroll
 *
 * @var array $args {
 *     Template arguments.
 *
 *     @type \WP_Post $post  The post.
 *     @type array    $links Normalized links.
 * }
 */

defined( 'ABSPATH' ) || exit;

\Blockroll\Opml::prolog();
?>
<opml version="2.0">
	<head>
		<title><?php echo esc_xml( \Blockroll\Opml::title( $args['post'] ) ); ?></title>
		<dateModified><?php echo esc_xml( get_post_modified_time( 'r', true, $args['post'] ) ); ?></dateModified>
		<ownerName><?php echo esc_xml( get_the_author_meta( 'display_name', $args['post']->post_author ) ); ?></ownerName>
	</head>
	<body>
<?php foreach ( $args['links'] as $blockroll_link ) : ?>
		<outline text="<?php echo esc_attr( $blockroll_link['name'] ? $blockroll_link['name'] : $blockroll_link['url'] ); ?>" type="rss"<?php echo $blockroll_link['description'] ? ' description="' . esc_attr( $blockroll_link['description'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $blockroll_link['feedUrl'] ? ' xmlUrl="' . esc_url( $blockroll_link['feedUrl'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> htmlUrl="<?php echo esc_url( $blockroll_link['url'] ); ?>" />
<?php endforeach; ?>
	</body>
</opml>
