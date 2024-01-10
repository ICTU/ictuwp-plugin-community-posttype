<?php

/**
 *
 * template-rss-agenda.php
 *
 * @version 1.0.1 - First version on live server
 */

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
		'event_type'     => 'events',
		'paging'         => false,
		'posts_per_page' => - 1,
		'echo'           => false
	);

	$rss_items = community_feed_items_get( $args_selection );

	if ( ! $rss_items ) {
		// no items
	} else {
		$args_in     = array(
			'extra_info' => true,
			'title'      => _x( 'Agenda', 'Header rss links', 'wp-rijkshuisstijl' ),
			'items'      => $rss_items
		);
		$rss_content = community_feed_items_show( $args_in );
		echo $rss_content;
	}

	wp_reset_query();
	wp_reset_postdata();

}

function sort_by_date( $a, $b ) {
	return strcmp( $a["date"], $b["date"] );
}
