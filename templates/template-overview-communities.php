<?php

if ( function_exists( 'genesis' ) ) {
	// Genesis wordt gebruikt als framework

	//* Force full-width-content layout
	add_filter( 'genesis_pre_get_option_site_layout', '__genesis_return_full_width_content' );


//	add_action( 'genesis_entry_content', 'community_add_communities_grid', 17 );

	add_action( 'genesis_entry_footer', 'community_add_communities_grid', 17 );


	add_filter( 'body_class', 'community_remove_body_classes' );

	// make it so
	genesis();

} else {

	// geen Genesis, maar wel dezelfde content, ongeveer, soort van
	global $post;

	get_header(); ?>

	<div id="primary" class="content-area">
		<div id="content" class="clearfix">

			<?php echo community_add_communities_grid() ?>

		</div><!-- #content -->
	</div><!-- #primary -->

	<?php

	get_sidebar();

	get_footer();


}

//========================================================================================================

function community_add_communities_grid( $doreturn = false ) {

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

	$return              = '';
	$community_types     = '';
	$community_topics    = '';
	$community_audiences = '';

	if ( $contentblockpostscount->have_posts() ) {


		$return      .= '<h2>' . _x( 'Alle community\'s', 'header overview', 'wp-rijkshuisstijl' ) . '</h2>';
		$return      .= '<div class="grid archive-custom-loop columncount-3">';
		$postcounter = 0;

		while ( $contentblockpostscount->have_posts() ) : $contentblockpostscount->the_post();

			$postcounter ++;
			$current_post_id = isset( $post->ID ) ? $post->ID : 0;
			$args2           = array(
				'ID'          => $current_post_id,
				'itemclass'   => 'griditem griditem--community colspan-1',
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

	$community_types     = community_show_taxonomy_list( DO_COMMUNITYTYPE_CT, __( 'Types', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );
	$community_topics    = community_show_taxonomy_list( DO_COMMUNITYTOPICS_CT, __( 'Onderwerpen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );
	$community_audiences = community_show_taxonomy_list( DO_COMMUNITYAUDIENCE_CT, __( 'Doelgroepen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );

	if ( $community_types || $community_topics || $community_audiences ) {
		$return .= '<div class="taxonomylist grid">';
		$return .= $community_types . $community_topics . $community_audiences;
		$return .= '</div>';
	}

	if ( $doreturn ) {
		return $return;
	} else {
		echo $return;
	}
}

//========================================================================================================

function community_show_taxonomy_list( $taxonomy = 'category', $title = '', $doecho = false, $exclude = '', $hide_empty = true ) {

	$return = '';

	if ( taxonomy_exists( $taxonomy ) ) {

		$args = array(
			'taxonomy'           => $taxonomy,
			'orderby'            => 'name',
			'order'              => 'ASC',
			'hide_empty'         => $hide_empty,
			'ignore_custom_sort' => true,
			'echo'               => 0,
			'hierarchical'       => true,
			'title_li'           => '',
		);

		if ( $exclude ) {
			// do not include this term in the list
			$args['exclude']    = $exclude;
			$args['hide_empty'] = true;
		}

		$terms = get_terms( $args );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {

			$return .= '<section class="taxonomy ' . $taxonomy . ' griditem griditem--post colspan-1"">';
			if ( $title ) {
				$return .= '<h2>' . $title . '</h2>';
			}

			$return .= '<ul>';
			foreach ( $terms as $term ) {
				$return .= '<li><a href="' . get_term_link( $term ) . '">' . $term->name . '</a>';
				if ( $term->description ) {
					$return .= '<br>' . $term->description;
				}
				$return .= '</li>';
			}
			$return .= '</ul>';
			$return .= '</section>';

		}
	}

	if ( $doecho ) {
		echo $return;
	} else {
		return $return;
	}

}

//========================================================================================================

/*
 * Remove 'page' from body classes
 */
function community_remove_body_classes( $classes ) {

	$delete_value = 'page';

	if ( ( $key = array_search( $delete_value, $classes ) ) !== false ) {
		unset( $classes[ $key ] );
	}

	return $classes;

}

//========================================================================================================
