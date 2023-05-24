<?php

if ( function_exists( 'genesis' ) ) {

	// Genesis wordt gebruikt als framework
	// dit geldt voor o.m. het theme voor Digitale Overheid

	// in de breadcrumb zetten we de link naar de algemene kaart
//	add_filter( 'genesis_single_crumb', 'projecten_initiatieven_filter_breadcrumb', 10, 2 );
//	add_filter( 'genesis_page_crumb', 'projecten_initiatieven_filter_breadcrumb', 10, 2 );
//	add_filter( 'genesis_archive_crumb', 'projecten_initiatieven_filter_breadcrumb', 10, 2 );


	add_action( 'genesis_entry_content', 'rhswp_show_dossiers_by_alphabet', 17 );


	// make it so
	genesis();

} else {

	// geen Genesis, maar wel dezelfde content, ongeveer, soort van
	global $post;

	get_header(); ?>

	<div id="primary" class="content-area">
		<div id="content" class="clearfix">

			<?php echo rhswp_show_dossiers_by_alphabet() ?>

		</div><!-- #content -->
	</div><!-- #primary -->

	<?php

	get_sidebar();

	get_footer();


}

//========================================================================================================

function rhswp_show_dossiers_by_alphabet( $doreturn = false ) {

	global $post;

	$argscount = array(
		'post_type'      => DO_COMMUNITY_CPT,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'posts_per_page' => - 1,
	);

	// Assign predefined $args to your query
	$contentblockpostscount = new WP_query();
	$contentblockpostscount->query( $argscount );
	$headerlevel = 'h3';

	$return = '';

	if ( $contentblockpostscount->have_posts() ) {


		$return      .= '<h2>' . _x( 'Alle community\'s', 'header overview', 'wp-rijkshuisstijl' ) . '</h2>';
		$return      .= '<div class="grid archive-custom-loop columncount-2">';
		$postcounter = 0;

		while ( $contentblockpostscount->have_posts() ) : $contentblockpostscount->the_post();

			$postcounter ++;
			$current_post_id = isset( $post->ID ) ? $post->ID : 0;
			$args2           = array(
				'ID'          => $current_post_id,
				'itemclass'   => 'griditem griditem--post colspan-1',
				'type'        => 'posts_normal',
				'headerlevel' => $headerlevel,
			);
			$return          .= rhswp_get_grid_item( $args2 );

			do_action( 'genesis_after_entry' );

		endwhile;

		$return .= '</div>';

	} else {
		$return .= 'geen initiatieven';
	}

	wp_reset_query();
	wp_reset_postdata();

	if ( $doreturn ) {
		return $return;
	} else {
		echo $return;
	}
}

//========================================================================================================


