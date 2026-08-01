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
				<?php if ( $blockroll_show && $blockroll_link['photo'] ) : ?>
					<img class="u-photo" src="<?php echo esc_url( $blockroll_link['photo'] ); ?>" alt="" loading="lazy" />
				<?php endif; ?>
				<a class="u-url p-name" <?php echo $blockroll_rel ? 'rel="' . esc_attr( $blockroll_rel ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( $blockroll_link['url'] ); ?>"><?php echo esc_html( $blockroll_link['name'] ? $blockroll_link['name'] : $blockroll_link['url'] ); ?></a>
				<?php if ( $blockroll_link['description'] ) : ?>
					<p class="p-note"><?php echo esc_html( $blockroll_link['description'] ); ?></p>
				<?php endif; ?>
				<?php if ( $blockroll_link['feedUrl'] ) : ?>
					<a class="u-feed" rel="alternate" type="application/rss+xml" href="<?php echo esc_url( $blockroll_link['feedUrl'] ); ?>"><?php esc_html_e( 'feed', 'blockroll' ); ?></a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
