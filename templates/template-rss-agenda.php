<?php

/**
 *
 * template-rss-agenda.php
 *
 * @version 1.0.1 - First version on live server
 */

if ( function_exists( 'genesis' ) ) {
	// Genesis wordt gebruikt als framework

	// social media share buttons
	add_action( 'genesis_entry_content', 'rhswp_append_socialbuttons', 26 ); // gedaan, eerst widget, dan socmed

	// extra widget ruimte
	add_action( 'genesis_entry_content', 'rhswp_append_widgets_before_socmed_footer', 24 );

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

	global $post;


	$showform    = ( get_field( 'posts_overview_filterform_show', $post->ID ) === 'posts_overview_filterform_show_yes' ) ? true : false;
	$filter_form = '';
	if ( $showform ) {

		$formname       = ( get_field( 'posts_overview_filterform_title', $post->ID ) ) ?: _x( 'Filter op thema', 'button label berichten', 'wp-rijkshuisstijl' );
		$button_label   = ( get_field( 'posts_overview_filterform_submit_label', $post->ID ) ) ?: _x( 'Filter', 'button label berichten', 'wp-rijkshuisstijl' );
		$args_selection = array(
			'event_type'   => 'events',
			'form_id'      => 'community_events_filter',
			'echo'         => false,
			'form_name'    => esc_html( $formname ),
			'button_label' => esc_html( $button_label ),
			'debug'        => true
		);
		$filter_form    = community_feed_add_filter_form( $args_selection );
	}

	if ( in_array( (int) get_query_var( DO_COMMUNITY_MAX_VAR ), DO_COMMUNITY_MAX_OPTIONS ) ) {
		$maxnr = get_query_var( DO_COMMUNITY_MAX_VAR );
	} else {
		$maxnr = DO_COMMUNITY_MAX_DEFAULT;
	}

	$args_selection = array(
		'event_type'     => 'events',
		'paging'         => 1,
		'posts_per_page' => $maxnr,
		'echo'           => false
	);

	echo '<pre>';
	var_dump( $args_selection );
	echo '</pre>';
	die( 'o nee!' );

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
		echo $filter_form;
		echo $rss_content;
	}

	genesis_posts_nav();
	wp_reset_query();
	wp_reset_postdata();

}

