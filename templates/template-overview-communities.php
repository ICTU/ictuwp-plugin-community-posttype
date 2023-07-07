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

	$filtered  = false;
	$argscount = array(
		'post_type'      => DO_COMMUNITY_CPT,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'posts_per_page' => - 1,
	);

	$filter_community_types     = ictuwp_communityfilter_get_filter_items( DO_COMMUNITYTYPE_CT, __( 'Types', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) );
	$filter_community_topics    = ictuwp_communityfilter_get_filter_items( DO_COMMUNITYTOPICS_CT, __( 'Onderwerpen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) );
	$filter_community_audiences = ictuwp_communityfilter_get_filter_items( DO_COMMUNITYAUDIENCE_CT, __( 'Doelgroepen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) );

	if ( $filter_community_types || $filter_community_topics || $filter_community_audiences ) {
		$argscount['tax_query']             = array();
		$argscount['tax_query']['relation'] = 'AND';
		$filtered                           = true;

		if ( isset( $filter_community_types['ids'] ) ) {
			$argscount['tax_query'][] = array(
				'taxonomy' => DO_COMMUNITYTYPE_CT,
				'field'    => 'term_id',
				'terms'    => $filter_community_types['ids'],
			);
		}
		if ( isset( $filter_community_topics['ids'] ) ) {
			$argscount['tax_query'][] = array(
				'taxonomy' => DO_COMMUNITYTOPICS_CT,
				'field'    => 'term_id',
				'terms'    => $filter_community_topics['ids'],
			);
		}
		if ( isset( $filter_community_audiences['ids'] ) ) {
			$argscount['tax_query'][] = array(
				'taxonomy' => DO_COMMUNITYAUDIENCE_CT,
				'field'    => 'term_id',
				'terms'    => $filter_community_audiences['ids'],
			);
		}
	}

	// Assign predefined $args to your query
	$community_list = new WP_query();
	$community_list->query( $argscount );
	$headerlevel                     = 'h3';
	$title                           = _x( 'Alle community\'s', 'header overview', 'wp-rijkshuisstijl' );
	$filter_explication              = '';
	$explication_community_types     = '';
	$explication_community_topics    = '';
	$explication_community_audiences = '';
	$return                          = '';
	$community_types                 = '';
	$community_topics                = '';
	$community_audiences             = '';
	$columncount                     = 3;

	if ( $community_list->have_posts() ) {

		if ( $community_list->post_count < 3 ) {
			$columncount = $community_list->post_count;
		}

		if ( $filtered ) {
			$title = sprintf( _n( '%s community gevonden', '%s community\'s gevonden', $community_list->post_count, 'wp-rijkshuisstijl' ), number_format_i18n( $community_list->post_count ) );

			if ( isset( $filter_community_types['ids'] ) ) {
				if ( count( $filter_community_types['labels'] ) > 1 ) {
					// more than 1 community_types selected
					$explication_community_types = 'types: ' . implode( ' of ', $filter_community_types['labels'] );
				} else {
					$explication_community_types = 'type: ' . implode( ' of ', $filter_community_types['labels'] );
				}
			}
			if ( isset( $filter_community_topics['ids'] ) ) {
				if ( count( $filter_community_topics['labels'] ) > 1 ) {
					// more than 1 topic selected
					$explication_community_topics = 'onderwerpen: ' . implode( ' of ', $filter_community_topics['labels'] );
				} else {
					$explication_community_topics = 'onderwerp: ' . implode( ' of ', $filter_community_topics['labels'] );
				}
			}
			if ( isset( $filter_community_audiences['ids'] ) ) {
//				$explication_community_audiences = implode( ', ', $filter_community_audiences['labels'] );
				if ( count( $filter_community_audiences['labels'] ) > 1 ) {
					// more than 1 topic selected
					$explication_community_topics = 'doelgroepen: ' . implode( ' of ', $filter_community_audiences['labels'] );
				} else {
					$explication_community_topics = 'doelgroep: ' . implode( ' of ', $filter_community_audiences['labels'] );
				}

			}

			if ( $explication_community_types || $explication_community_topics || $explication_community_audiences ) {
				$filter_explication = 'Gefilterd op ';
				if ( $explication_community_types ) {
					$filter_explication .= ' ' . $explication_community_types;
				}
				if ( $explication_community_topics ) {
					if ( $explication_community_types ) {
						$filter_explication .= ', gecombineerd met ' . $explication_community_topics;
					} else {
						$filter_explication .= ' ' . $explication_community_topics;
					}
				}
				if ( $explication_community_audiences ) {
					if ( $explication_community_topics || $explication_community_types ) {
						$filter_explication .= ', gecombineerd met ' . $explication_community_audiences;
					} else {
						$filter_explication .= ' ' . $explication_community_audiences;
					}
				}

			}

		}

		if ( $filter_explication ) {
			$return .= '<p>' . $filter_explication . '.</p>';
		}

		$return      .= '<h2>' . $title . '</h2>';
		$return      .= '<div class="grid archive-custom-loop columncount-' . $columncount . '" id="communities_list">';
		$postcounter = 0;

		while ( $community_list->have_posts() ) : $community_list->the_post();

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
		$return .= _x( 'Geen community\'s gevonden', 'no results', 'wp-rijkshuisstijl' );
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

function ictuwp_communityfilter_get_filter_items( $taxonomy = 'category', $title = '' ) {

	$return = array();

	if ( taxonomy_exists( $taxonomy ) ) {

		$args = array(
			'taxonomy'           => $taxonomy,
			'orderby'            => 'name',
			'order'              => 'ASC',
			'hide_empty'         => true,
			'ignore_custom_sort' => true,
			'echo'               => 0,
			'hierarchical'       => true,
			'title_li'           => '',
		);

		$terms = get_terms( $args );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {

			foreach ( $terms as $term ) {
				$check_id    = $term->slug . '_' . $term->term_id;
				$check_value = isset( $_GET[ $check_id ] ) ? (int) $_GET[ $check_id ] : 0;

				if ( $check_value === $term->term_id ) {
					if ( $taxonomy === DO_COMMUNITYTOPICS_CT ) {
						// no lower case for this tax
						$return['labels'][] = $term->name;
					} else {
						// lower case for all else
						$return['labels'][] = strtolower( $term->name );
					}
					$return['ids'][] = $term->term_id;
				}
			}

		}
	}

	return $return;

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
