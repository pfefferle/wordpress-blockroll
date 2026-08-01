<?php
/**
 * Frontend output of the blogroll block.
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
$blockroll_sort  = $attributes['sortBy'] ?? 'name';
$blockroll_links = Links::sort( array_values( $blockroll_links ), $blockroll_sort );
$blockroll_show  = ! empty( $attributes['showAvatars'] );
$blockroll_per   = (int) ( $attributes['perPage'] ?? 0 );
$blockroll_dated = (bool) array_filter( wp_list_pluck( $blockroll_links, 'added' ) );

if ( ! $blockroll_links ) {
	return;
}
?>
<div
	<?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>
	data-wp-interactive="blockroll"
	data-wp-init="callbacks.init"
	<?php
	echo wp_kses_data(
		wp_interactivity_data_wp_context(
			array(
				'perPage' => $blockroll_per,
				'sortBy'  => $blockroll_sort,
				'page'    => 1,
			)
		)
	);
	?>
>
	<div class="blockroll-controls" hidden>
		<label>
			<?php esc_html_e( 'Sort', 'blockroll' ); ?>
			<select data-wp-on--change="actions.setSort">
				<option value="name" <?php selected( 'name', $blockroll_sort ); ?>><?php esc_html_e( 'By name', 'blockroll' ); ?></option>
				<?php if ( $blockroll_dated ) : ?>
					<option value="added" <?php selected( 'added', $blockroll_sort ); ?>><?php esc_html_e( 'Newest first', 'blockroll' ); ?></option>
				<?php endif; ?>
				<option value="manual" <?php selected( 'manual', $blockroll_sort ); ?>><?php esc_html_e( 'Custom order', 'blockroll' ); ?></option>
			</select>
		</label>
		<?php if ( $blockroll_per > 0 ) : ?>
			<span class="blockroll-pager">
				<button type="button" data-wp-on--click="actions.prevPage" data-wp-bind--disabled="state.isFirstPage"><?php esc_html_e( 'Previous', 'blockroll' ); ?></button>
				<button type="button" data-wp-on--click="actions.nextPage" data-wp-bind--disabled="state.isLastPage"><?php esc_html_e( 'Next', 'blockroll' ); ?></button>
			</span>
		<?php endif; ?>
	</div>
	<ul class="blockroll-list">
		<?php foreach ( $blockroll_links as $blockroll_i => $blockroll_link ) : ?>
			<?php $blockroll_rel = Xfn::rel_string( $blockroll_link['xfn'] ); ?>
			<li
				class="h-card"
				data-name="<?php echo esc_attr( $blockroll_link['name'] ? $blockroll_link['name'] : $blockroll_link['url'] ); ?>"
				data-added="<?php echo esc_attr( $blockroll_link['added'] ); ?>"
				data-index="<?php echo esc_attr( $blockroll_i ); ?>"
			>
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
							<a class="u-feed" rel="alternate noopener" target="_blank" type="application/rss+xml" href="<?php echo esc_url( $blockroll_link['feedUrl'] ); ?>"><svg class="blockroll-feed-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M5 10.2h-.8v1.5H5c1.9 0 3.8.8 5.1 2.1 1.4 1.4 2.1 3.2 2.1 5.1v.8h1.5V19c0-2.3-.9-4.5-2.6-6.2-1.6-1.6-3.8-2.6-6.1-2.6zm10.4-1.6C12.6 5.8 8.9 4.2 5 4.2h-.8v1.5H5c3.5 0 6.9 1.4 9.4 3.9s3.9 5.8 3.9 9.4v.8h1.5V19c0-3.9-1.6-7.6-4.4-10.4zM4 20h3v-3H4v3z"></path></svg><?php esc_html_e( 'feed', 'blockroll' ); ?></a>
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
</div>
