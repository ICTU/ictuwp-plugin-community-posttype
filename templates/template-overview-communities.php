<?php

//========================================================================================================

define( 'CONTAINER_ID', 'community_container' );


add_action( 'wp_enqueue_scripts', 'community_set_id_for_openbuttons' );

function community_set_id_for_openbuttons() {
	if ( ! is_admin() ) {
		$translation_array = array(
			'id' => CONTAINER_ID,
		);
		wp_localize_script( 'details-element', 'detailssummarycontainerid', $translation_array );

	}
}


if ( function_exists( 'genesis' ) ) {
	// Genesis wordt gebruikt als framework

	//* Force full-width-content layout
	add_filter( 'genesis_pre_get_option_site_layout', '__genesis_return_full_width_content' );


	// append grid to entry_content
	add_action( 'genesis_entry_content', 'community_add_communities_grid', 20 );

	// remove 'page' class from body
	add_filter( 'body_class', 'community_remove_body_classes' );

	// append content blocks
	if ( function_exists( 'rhswp_extra_contentblokken_checker' ) && function_exists( 'rhswp_write_extra_contentblokken' ) ) {

		if ( rhswp_extra_contentblokken_checker() ) {
			add_action( 'genesis_entry_content', 'rhswp_write_extra_contentblokken', 30 );
		}
	}

	add_action( 'genesis_entry_content', 'community_append_widget_sidebar', 40 );


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
		'orderby'        => 'name',
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
	$title                           = ( get_field( 'community_list_title', $post ) ) ? get_field( 'community_list_title', $post ) : _x( 'Alle community\'s', 'header overview', 'wp-rijkshuisstijl' );
	$community_layout_show_filter    = ( get_field( 'community_layout_show_filter', $post ) !== 'community_layout_show_filter_false' ) ? true : false;
	$community_show_alphabet_list    = ( get_field( 'community_layout_show_alphabet_list', $post ) !== 'community_layout_show_alphabet_list_false' ) ? true : false;
	$list_layout                     = ( get_field( 'community_layout_list', $post ) === 'community_layout_list_accordion' ) ? 'community_layout_list_accordion' : 'community_layout_list_grid';
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
		$postcounter = 0;

		if ( $community_layout_show_filter ) {
//			$return .= '<h1>HIER WEL EEN FILTER</h1>';
			if ( is_active_sidebar( RHSWP_WIDGET_AREA_COMMUNITY_OVERVIEW ) ) {
				$return .= '<div class="widget-single-footer">';
				$return .= dynamic_sidebar( RHSWP_WIDGET_AREA_COMMUNITY_OVERVIEW );
				$return .= '</div>';
			}
		} else {
			//
//			$return .= '<h1>HIER WEL GEEN FILTER</h1>';
		}

		if ( 'community_layout_list_grid' !== $list_layout ) {

			// show list with detail / summary items
			if ( $community_show_alphabet_list ) {
				$return .= '<div class="alphabet">';
				while ( $community_list->have_posts() ) : $community_list->the_post();
					$huidigeletter = substr( strtolower( $post->post_name ), 0, 1 );
					if ( $huidigeletter !== $letter ) {
						$return .= '<a href="#list_' . strtolower( $huidigeletter ) . '"><span>' . strtoupper( $huidigeletter ) . '</span></a>' . "\n";
						$letter = $huidigeletter;
					}
				endwhile;

				$return .= '</div>'; // .alphabet

			}

			$css = 'no-list-style community-container';

			if ( $community_show_alphabet_list ) {
				$letter            = '';
				$list_start        = '<!--- $list_start initial --><ul>';
				$list_end          = '<!--- $list_end initial -->' . "\n";
				$blok_letter_close = '<!--- $blok_letter_close initial -->' . "\n"; // initiele waarde voor afsluiter
				$blok_letter_open  = '<div class="column-block">' . "\n";
				$cummunitylijst    = '<!--- start -->' . "\n";
				$css               .= ' column-layout';
			} else {
				$cummunitylijst    = '<!--- start -->' . "\n" . '<ul>' . "\n";
				$list_end          = "\n</ul>\n";
				$blok_letter_close = "\n";
			}

			$return .= '<div class="archive-custom-loop columncount-' . $columncount . '">';
			$return .= '<div class="' . $css . '" id="' . CONTAINER_ID . '">';

			while ( $community_list->have_posts() ) : $community_list->the_post();

				$huidigeletter = substr( strtolower( $post->post_name ), 0, 1 );
				$permalink     = get_permalink( $post );

				if ( $community_show_alphabet_list ) {

					// alleen dossiers met een geldige pagina tonen
					if ( $huidigeletter !== $letter ) {

						$cummunitylijst .= "\n" . $list_end;
						$cummunitylijst .= $blok_letter_close;
						$cummunitylijst .= "\n" . $blok_letter_open;
						$cummunitylijst .= "\n" . '<h2 id="list_' . strtolower( $huidigeletter ) . '">' . strtoupper( $huidigeletter ) . '</h2>';
						$cummunitylijst .= "\n" . $list_start;

						// reset waarden
						$letter = $huidigeletter;
						// overschrijf initiele waarde voor afsluiter
						$blok_letter_close = '</div>' . "\n";

						$list_start = "<ul>\n";
						$list_end   = "</ul>\n";
					}
				}

				$cummunitylijst .= "\n" . '<li class="cat-item cat-item-' . $post->ID . '">';

				$titel             = $post->post_title;
				$excerpt           = get_the_excerpt( $post );
				$link_and_linktext = $post->post_title;
				if ( $permalink ) {
					$link_and_linktext = '<p class="read-more"><a href="' . $permalink . '">';
					$link_and_linktext .= $post->post_title;
					$link_and_linktext .= '</a></p>';
				}

				if ( $headerlevel && $titel && $link_and_linktext && $excerpt ) {
					$cummunitylijst .= '<details><summary><' . $headerlevel . '>' . $titel . '</' . $headerlevel . '></summary><p>' . wp_strip_all_tags( $excerpt ) . '</p>' . $link_and_linktext . '</details>';
				} else {
					$cummunitylijst .= $link_and_linktext;
				}
				$cummunitylijst .= '</li>';

			endwhile;

			$return .= $cummunitylijst;
			$return .= $list_end;
			$return .= $blok_letter_close;
			$return .= '</div><!-- .dossier-list column-layout -->' . "\n";
			$return .= '</div><!-- #community_container -->' . "\n";

		} else {

			$return .= '<div class="archive-custom-loop columncount-' . $columncount . '" id="' . CONTAINER_ID . '">';

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
			$return .= '</div>'; // .archive-custom-loop columncount-

		}


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

function community_append_widget_sidebar() {

	if ( is_active_sidebar( RHSWP_WIDGET_AREA_COMMUNITY_OVERVIEW ) ) {
		echo '<div class="widget-single-footer">';
		dynamic_sidebar( RHSWP_WIDGET_AREA_COMMUNITY_OVERVIEW );
		echo '</div>';

	}

}

//========================================================================================================

