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
		'event_type'   => 'events',
		'form_id'      => 'community_events_filter',
		'echo'         => false,
		'form_name'    => _x( 'Filter op thema', 'button label berichten', 'wp-rijkshuisstijl' ),
		'button_label' => _x( 'Filter', 'button label berichten', 'wp-rijkshuisstijl' ),
		'debug'        => true
	);

	$filter_form = community_feed_add_filter_form( $args_selection );

	$args_selection = array(
		'event_type'     => 'events',
		'paging'         => 1,
		'posts_per_page' => 20, // perhaps: get_option( 'posts_per_page' )?
		'echo'           => false
	);

	$rss_items = community_feed_items_get( $args_selection );

	if ( ! $rss_items ) {
		// no items
	} else {
		$args_in     = array(
			'extra_info' => true,
			'title'      => _x( 'Agenda', 'Header rss links', 'wp-rijkshuisstijl' ),
			'items'      => $rss_items,
			'cssclass'   => 'template-rss-agenda'
		);
		$rss_content = community_feed_items_show( $args_in );
		echo $rss_content;
	}

	genesis_posts_nav();

	echo $filter_form;
	
	wp_reset_query();
	wp_reset_postdata();

}

