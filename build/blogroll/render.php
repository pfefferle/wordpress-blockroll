<?php
/**
 * Frontend output of the blogroll block.
 *
 * Sorting and paging are plain links with query parameters; there is
 * no JavaScript on the frontend.
 *
 * @package Blockroll
 *
 * @var array $attributes Block attributes.
 */

use Blockroll\Links;
use Blockroll\Xfn;

$blockroll_links = array_map( array( Links::class, 'normalize' ), (array) ( $attributes['links'] ?? array() ) );
$blockroll_links = array_filter(
	$blockroll_links,
	function ( $link ) {
		return '' !== $link['url'];
	}
);

$blockroll_sortable = ! isset( $attributes['showSort'] ) || $attributes['showSort'];

$blockroll_sort = $blockroll_sortable ? get_query_var( 'blockroll-sort' ) : '';
if ( ! in_array( $blockroll_sort, array( 'name', 'added', 'manual' ), true ) ) {
	$blockroll_sort = $attributes['sortBy'] ?? 'name';
}

$blockroll_links = Links::sort( array_values( $blockroll_links ), $blockroll_sort );
$blockroll_show  = ! empty( $attributes['showAvatars'] );
$blockroll_per   = (int) ( $attributes['perPage'] ?? 0 );
$blockroll_total = count( $blockroll_links );
$blockroll_pages = $blockroll_per > 0 ? max( 1, (int) ceil( $blockroll_total / $blockroll_per ) ) : 1;
$blockroll_page  = min( max( 1, (int) get_query_var( 'blockroll-page', 1 ) ), $blockroll_pages );
$blockroll_dated = (bool) array_filter( wp_list_pluck( $blockroll_links, 'added' ) );

if ( ! $blockroll_links ) {
	return;
}

if ( $blockroll_per > 0 ) {
	$blockroll_links = array_slice( $blockroll_links, ( $blockroll_page - 1 ) * $blockroll_per, $blockroll_per );
}

$blockroll_sorts = array(
	'name' => __( 'By name', 'blockroll' ),
);
if ( $blockroll_dated ) {
	$blockroll_sorts['added'] = __( 'Newest first', 'blockroll' );
}
if ( 'manual' === ( $attributes['sortBy'] ?? 'name' ) ) {
	$blockroll_sorts['manual'] = __( 'Default', 'blockroll' );
}
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php if ( $blockroll_sortable && $blockroll_total > 1 && count( $blockroll_sorts ) > 1 ) : ?>
		<nav class="blockroll-controls">
			<span class="blockroll-sort">
				<?php esc_html_e( 'Sort:', 'blockroll' ); ?>
				<?php foreach ( $blockroll_sorts as $blockroll_key => $blockroll_label ) : ?>
					<?php
					$blockroll_sort_url = add_query_arg(
						array(
							'blockroll-sort' => $blockroll_key,
							'blockroll-page' => false,
						)
					);
					?>
					<?php if ( $blockroll_key === $blockroll_sort ) : ?>
						<span aria-current="true"><?php echo esc_html( $blockroll_label ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $blockroll_sort_url ); ?>"><?php echo esc_html( $blockroll_label ); ?></a>
					<?php endif; ?>
				<?php endforeach; ?>
			</span>
		</nav>
	<?php endif; ?>
	<ul class="blockroll-list">
		<?php foreach ( $blockroll_links as $blockroll_link ) : ?>
			<?php $blockroll_rel = Xfn::rel_string( $blockroll_link['xfn'] ); ?>
			<li class="h-card">
				<?php if ( $blockroll_show ) : ?>
					<?php if ( $blockroll_link['photo'] ) : ?>
						<img class="u-photo" src="<?php echo esc_url( $blockroll_link['photo'] ); ?>" alt="" loading="lazy" />
					<?php else : ?>
						<span class="blockroll-no-photo"></span>
					<?php endif; ?>
				<?php endif; ?>
				<a class="u-url p-name" rel="<?php echo esc_attr( trim( $blockroll_rel . ' noopener' ) ); ?>" target="_blank" href="<?php echo esc_url( $blockroll_link['url'] ); ?>"><?php echo esc_html( $blockroll_link['name'] ? $blockroll_link['name'] : $blockroll_link['url'] ); ?></a>
				<?php if ( $blockroll_link['description'] ) : ?>
					<p class="p-note"><?php echo esc_html( $blockroll_link['description'] ); ?></p>
				<?php endif; ?>
				<?php if ( $blockroll_link['feedUrl'] || $blockroll_link['xfn'] ) : ?>
					<div class="blockroll-meta">
						<?php if ( $blockroll_link['feedUrl'] ) : ?>
							<span class="blockroll-feed">
								<a href="<?php echo esc_url( 'feed:' . $blockroll_link['feedUrl'] ); ?>" aria-label="<?php esc_attr_e( 'Subscribe', 'blockroll' ); ?>" title="<?php esc_attr_e( 'Subscribe', 'blockroll' ); ?>"><svg class="blockroll-feed-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M5 10.2h-.8v1.5H5c1.9 0 3.8.8 5.1 2.1 1.4 1.4 2.1 3.2 2.1 5.1v.8h1.5V19c0-2.3-.9-4.5-2.6-6.2-1.6-1.6-3.8-2.6-6.1-2.6zm10.4-1.6C12.6 5.8 8.9 4.2 5 4.2h-.8v1.5H5c3.5 0 6.9 1.4 9.4 3.9s3.9 5.8 3.9 9.4v.8h1.5V19c0-3.9-1.6-7.6-4.4-10.4zM4 20h3v-3H4v3z"></path></svg></a>
								<a class="u-feed" rel="alternate noopener" target="_blank" type="application/rss+xml" href="<?php echo esc_url( $blockroll_link['feedUrl'] ); ?>" title="<?php esc_attr_e( 'Load the feed', 'blockroll' ); ?>"><?php esc_html_e( 'feed', 'blockroll' ); ?></a>
							</span>
						<?php endif; ?>
						<?php if ( $blockroll_link['feedUrl'] && $blockroll_link['xfn'] ) : ?>
							<span class="blockroll-divider" aria-hidden="true">&#183;</span>
						<?php endif; ?>
						<?php if ( $blockroll_link['xfn'] ) : ?>
							<ul class="blockroll-xfn">
								<?php foreach ( $blockroll_link['xfn'] as $blockroll_token ) : ?>
									<li><?php echo esc_html( $blockroll_token ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $blockroll_pages > 1 ) : ?>
		<nav class="blockroll-pager">
			<?php if ( $blockroll_page > 1 ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'blockroll-page', $blockroll_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'blockroll' ); ?></a>
			<?php endif; ?>
			<span>
			<?php
			/* translators: 1: current page, 2: number of pages */
			echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'blockroll' ), $blockroll_page, $blockroll_pages ) );
			?>
			</span>
			<?php if ( $blockroll_page < $blockroll_pages ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'blockroll-page', $blockroll_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'blockroll' ); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
	<?php $blockroll_post = get_post(); ?>
	<?php if ( ! empty( $attributes['showOpml'] ) && $blockroll_post ) : ?>
		<p class="blockroll-opml">
			<?php
			printf(
				wp_kses(
					/* translators: %1$s: OPML file URL */
					__( '<a href="%1$s" download="blogroll.opml">Download</a> or <a href="%1$s">open</a> this blogroll as an OPML file.', 'blockroll' ),
					array(
						'a' => array(
							'href'     => true,
							'download' => true,
						),
					)
				),
				esc_url( \Blockroll\Opml::feed_url( $blockroll_post ) )
			);
			?>
		</p>
	<?php endif; ?>
</div>
