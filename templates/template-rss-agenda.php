<?php
if ( function_exists( 'genesis' ) ) {
	// Genesis wordt gebruikt als framework

	//* Force full-width-content layout
//	add_filter( 'genesis_pre_get_option_site_layout', '__genesis_return_full_width_content' );


	// append grid to entry_content
	add_action( 'genesis_entry_content', 'community_add_agenda_grid', 20 );

	// remove 'page' class from body
	add_filter( 'body_class', 'community_remove_body_classes' );

	// append content blocks
	if ( function_exists( 'rhswp_extra_contentblokken_checker' ) && function_exists( 'rhswp_write_extra_contentblokken' ) ) {

		if ( rhswp_extra_contentblokken_checker() ) {
			add_action( 'genesis_entry_content', 'rhswp_write_extra_contentblokken', 30 );
		}
	}

	// make it so
	genesis();

} else {

	// no genesis

}

function community_add_agenda_grid( $args = array() ) {

	$args_selection = array(
		'event_type'     => 'event',
		'paging'         => false,
		'posts_per_page' => - 1,
		'echo'           => false
	);

	$community_items = community_feed_items_get( $args_selection );

	// Query to get all feed items for display
	$date_format = 'j F H:i';// get_option( 'date_format' ); // e.g. "F j, Y"
	$date_format = get_option( 'date_format' );
	$time_format = get_option( 'time_format' );

	if ( $community_items->have_posts() ) {
		echo '<ul class="agenda">';
		while ( $community_items->have_posts() ) : $community_items->the_post();
			$post_meta = get_post_meta( $community_items->post->ID, 'wprss_item_date' );
//			$agenda_datum_string = $post_meta['wprss_item_date'];
			$time     = date_i18n( $time_format, strtotime( $post_meta[0] ) );
			$date     = date_i18n( $date_format, strtotime( $post_meta[0] ) );
			$time_tag = '<time datetime="' . $time . '">' . $time . '</time>';
//			$time     = ;
			$date_tag = '<time datetime="' . date_i18n( $date_format, strtotime( $post_meta[0] ) ) . '">' . $date . ' - ' . $time . ' </time>';
//			echo '<li>' . $post_meta[0] . ' ' . get_the_title() . ' - (publish: ' . get_the_date() . ')</li>';
			echo '<li><span>' . $date_tag . '<a href="' . get_permalink() . '">' . get_the_title() . '</a></span></li>';
//			$item        = array(
//				'id'             => get_the_id(),
//				'title'          => get_the_title(),
//				'url'            => get_permalink(),
//				'date'           => strtotime( $post_meta[0] ),
//				'date_formatted' => date( $date_format, strtotime( $post_meta[0] ) ),
//				'post_date'      => get_the_date()
//			);

		endwhile;
		echo '</ul>';

	}


	wp_reset_query();
	wp_reset_postdata();

}

function sort_by_date( $a, $b ) {
	return strcmp( $a["date"], $b["date"] );
}
