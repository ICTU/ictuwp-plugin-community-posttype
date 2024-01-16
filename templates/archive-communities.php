<?php
/**
 *
 * archive-communities.php
 *
 */


if ( function_exists( 'genesis' ) ) {

	// Genesis wordt gebruikt als framework

	// modify breadcrumb if necessary
	if ( function_exists( 'communitys_filter_breadcrumb' ) ) {
		add_filter( 'genesis_single_crumb', 'communitys_filter_breadcrumb', 10, 2 );
		add_filter( 'genesis_page_crumb', 'communitys_filter_breadcrumb', 10, 2 );
		add_filter( 'genesis_archive_crumb', 'communitys_filter_breadcrumb', 10, 2 );
	}

	// titel toevoegen
	add_action( 'genesis_before_loop', 'ictuwp_community_archive_title', 8 );


	/** standard loop vervangen door custom loop */
	remove_action( 'genesis_loop', 'genesis_do_loop' );
	add_action( 'genesis_loop', 'ictuwp_community_term_archive_list' );


	// make it so
	genesis();

} else {

	// geen Genesis, maar wel dezelfde content, ongeveer, soort van
	die( 'aaargh!' );

}

//========================================================================================================

function ictuwp_community_term_archive_list( $doreturn = false ) {

	global $post;
	global $wp_query;

	$return = '';

	if ( have_posts() ) {

//		$itemcount = found_posts();
//		$itemcount = 3;
		$itemcount   = $wp_query->post_count;
		$columncount = 1;
		$postcounter = 0;
		if ( $itemcount < $columncount ) {
			$columncount = $itemcount;
		}

		echo '<h2>' . $itemcount . ' ' . _n( 'community', "community's", $itemcount, 'wp-rijkshuisstijl' ) . '</h2>';
		echo '<div class="grid columncount-' . $columncount . ' itemcount-' . $itemcount . '">';

		while ( have_posts() ) : the_post();
			$postcounter ++;
			$contentblock_post_id = get_the_ID();
			$args2                = array(
				'ID'                      => $contentblock_post_id,
				'contentblock_label_show' => false,
				'itemclass'               => 'griditem griditem--post colspan-2',
			);


			echo rhswp_get_grid_item( $args2 );
		endwhile;
		echo '</div>'; // .grid

	}


	if ( $doreturn ) {
		return $return;
	} else {
		echo $return;
	}
}

//========================================================================================================
