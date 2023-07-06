<?php

if ( function_exists( 'genesis' ) ) {
	// Genesis wordt gebruikt als framework

	//* Force full-width-content layout
///	add_filter( 'genesis_pre_get_option_site_layout', '__genesis_return_full_width_content' );


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

		$columncount         = 3;


		$return      .= '<h2>' . _x( 'Alle community\'s', 'header overview', 'wp-rijkshuisstijl' ) . '</h2>';
		$return      .= '<div class="grid archive-custom-loop columncount-' . $columncount . '" id="communities_list">';
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

		if ( $community_types || $community_topics || $community_audiences ) {
			$return .= '<div class="taxonomylist grid" id="communities_filter">';
			$return .= $community_types . $community_topics . $community_audiences;
			$return .= '</div>';
		}

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

function ictuwp_communityfilter_list( $taxonomy = 'category', $title = '', $doecho = false, $exclude = '', $hide_empty = true ) {

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

			$return .= '<fieldset class="taxonomy ' . $taxonomy . '">';
			if ( $title ) {
				$return .= '<legend>' . $title . '</legend>';
			}

			foreach ( $terms as $term ) {
				$id = $term->slug . '_' . $term->term_id;
				$checked = ' checked';
				$return .= '<label for="' . $id . '"><input id="' . $id . '" type="checkbox" name="' . $term->slug . '" value="' . $term->term_id . '"' . $checked . '>' . $term->name . '</label>';
			}
			$return .= '</fieldset>';

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
