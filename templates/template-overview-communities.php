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

function community_get_selection( $args = array() ) {

	$return                      = array();
	$return['list_with_postids'] = array();
	$filter_community_types      = ictuwp_communityfilter_get_filter_items( DO_COMMUNITYTYPE_CT, __( 'Types', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) );
	$filter_community_topics     = ictuwp_communityfilter_get_filter_items( DO_COMMUNITYTOPICS_CT, __( 'Onderwerpen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) );
	$filter_community_audiences  = ictuwp_communityfilter_get_filter_items( DO_COMMUNITYAUDIENCE_CT, __( 'Doelgroepen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) );
	$filter_explication          = '';
	$tax_query                   = '';

	// if any values are submitted, append these to the filter optiones
	if ( $filter_community_types || $filter_community_topics || $filter_community_audiences ) {
		$tax_query                          = array();
		$tax_query['tax_query']             = array();
		$tax_query['tax_query']['relation'] = 'AND';

		$explication_community_types     = '';
		$explication_community_topics    = '';
		$explication_community_audiences = '';

		// for community types
		if ( isset( $filter_community_types['ids'] ) ) {
			$tax_query['tax_query'][] = array(
				'taxonomy' => DO_COMMUNITYTYPE_CT,
				'field'    => 'term_id',
				'terms'    => $filter_community_types['ids'],
			);
		}

		// for community topics
		if ( isset( $filter_community_topics['ids'] ) ) {
			$tax_query['tax_query'][] = array(
				'taxonomy' => DO_COMMUNITYTOPICS_CT,
				'field'    => 'term_id',
				'terms'    => $filter_community_topics['ids'],
			);
		}

		// for community audiences
		if ( isset( $filter_community_audiences['ids'] ) ) {
			$tax_query['tax_query'][] = array(
				'taxonomy' => DO_COMMUNITYAUDIENCE_CT,
				'field'    => 'term_id',
				'terms'    => $filter_community_audiences['ids'],
			);
		}

		// explain the filtering in normal words
		if ( isset( $filter_community_types['ids'] ) ) {
			if ( count( $filter_community_types['labels'] ) > 1 ) {
				// more than 1 community_types selected
				$explication_community_types = 'types: ' . implode( ' of ', $filter_community_types['labels'] ); // TODO TRANSLATE !!!
			} else {
				$explication_community_types = 'type: ' . implode( ' of ', $filter_community_types['labels'] ); // TODO TRANSLATE !!!
			}
		}
		if ( isset( $filter_community_topics['ids'] ) ) {
			if ( count( $filter_community_topics['labels'] ) > 1 ) {
				// more than 1 topic selected
				$explication_community_topics = 'onderwerpen: ' . implode( ' of ', $filter_community_topics['labels'] ); // TODO TRANSLATE !!!
			} else {
				$explication_community_topics = 'onderwerp: ' . implode( ' of ', $filter_community_topics['labels'] ); // TODO TRANSLATE !!!
			}
		}
		if ( isset( $filter_community_audiences['ids'] ) ) {
			if ( count( $filter_community_audiences['labels'] ) > 1 ) {
				// more than 1 topic selected
				$explication_community_topics = 'doelgroepen: ' . implode( ' of ', $filter_community_audiences['labels'] ); // TODO TRANSLATE !!!
			} else {
				$explication_community_topics = 'doelgroep: ' . implode( ' of ', $filter_community_audiences['labels'] ); // TODO TRANSLATE !!!
			}

		}

		// stitch it together
		if ( $explication_community_types || $explication_community_topics || $explication_community_audiences ) {
			$filter_explication = 'Gefilterd op '; // TODO TRANSLATE !!!
			if ( $explication_community_types ) {
				$filter_explication .= ' ' . $explication_community_types;
			}
			if ( $explication_community_topics ) {
				if ( $explication_community_types ) {
					$filter_explication .= ', gecombineerd met ' . $explication_community_topics; // TODO TRANSLATE !!!
				} else {
					$filter_explication .= ' ' . $explication_community_topics;
				}
			}
			if ( $explication_community_audiences ) {
				if ( $explication_community_topics || $explication_community_types ) {
					$filter_explication .= ', gecombineerd met ' . $explication_community_audiences; // TODO TRANSLATE !!!
				} else {
					$filter_explication .= ' ' . $explication_community_audiences;
				}
			}
		}

	}

	if ( isset( $_GET['community_search_string'] ) && ( $_GET['community_search_string'] !== '' ) ) {

		// a search query for the supplemental 'communities' searchwp engine
		$searchterm  = sanitize_text_field( $_GET['community_search_string'] );
		$name_engine = 'communities';

		if ( class_exists( '\\SearchWP\\Query' ) ) {
			// SearchWP v4.x
			$search_page = isset( $_GET['swppg'] ) ? absint( $_GET['swppg'] ) : 1;

			$arguments = array(
				'engine' => $name_engine, // The Engine name.
				'fields' => 'ids', // only return IDs
				'page'   => $search_page
			);
			if ( $tax_query ) {
				$arguments['s']         = $searchterm;
				$arguments['tax_query'] = $tax_query;
			}
			$searchwp_query = new \SearchWP\Query( $searchterm, $arguments );
			$community_list = $searchwp_query->get_results();

			if ( $filter_explication ) {
				$filter_explication .= " en  '" . $searchterm . "'";
			} else {
				$filter_explication = "Gefilterd op '" . $searchterm . "'";
			}

			if ( count( $community_list ) > 0 ) {
				foreach ( $community_list as $the_id ) :
					$return['list_with_postids'][] = $the_id;
				endforeach;
			}
		}

	} else {

		// standard WordPress query, with some extra arguments
		$argscount = array(
			'post_type'      => DO_COMMUNITY_CPT,
			'post_status'    => 'publish',
			'orderby'        => 'name',
			'order'          => 'ASC',
			'posts_per_page' => - 1,
		);

		if ( $tax_query ) {
			$argscount['tax_query'] = $tax_query;
		}

		// Assign predefined $args to your query
		$community_list = new WP_query();
		$community_list->query( $argscount );

		if ( $community_list->have_posts() ) {
			while ( $community_list->have_posts() ) : $community_list->the_post();
				$the_id                        = get_the_id();
				$return['list_with_postids'][] = $the_id;
			endwhile;
		}

	}

	// also return explications
	if ( $filter_explication ) {
		$return['is_filtered']        = true;
		$return['filter_explication'] = $filter_explication;
	}
	if ( $filter_community_types ) {
		$return['filter_community_types'] = $filter_community_types;
	}
	if ( $filter_community_topics ) {
		$return['filter_community_topics'] = $filter_community_topics;
	}
	if ( $filter_community_audiences ) {
		$return['filter_community_audiences'] = $filter_community_audiences;
	}

	return $return;

}

//========================================================================================================

function community_add_communities_grid( $doreturn = false ) {

	global $post;

	// variables
	$headerlevel                  = 'h3';
	$page_id                      = $post->ID;
	$title                        = ( get_field( 'community_list_title', $post ) ) ? get_field( 'community_list_title', $post ) : _x( 'Alle community\'s', 'header overview', 'wp-rijkshuisstijl' );
	$list_layout                  = ( get_field( 'community_layout_list', $post ) === 'community_layout_list_accordion' ) ? 'community_layout_list_accordion' : 'community_layout_list_grid';
	$community_layout_show_filter = ( get_field( 'community_layout_show_filter', $post ) !== 'community_layout_show_filter_false' ) ? true : false;
	if ( 'community_layout_list_grid' === $list_layout ) {
		$community_show_alphabet_list = false;
	} else {
		$community_show_alphabet_list = ( get_field( 'community_layout_show_alphabet_list', $post ) !== 'community_layout_show_alphabet_list_false' ) ? true : false;
	}

	$return              = '';
	$community_types     = '';
	$community_topics    = '';
	$community_audiences = '';
	$container_labelid   = CONTAINER_ID . '_header';
	$filter_form         = '';
	$alphabet_list       = '';
	$items               = '';
	$result              = community_get_selection();
	$list_with_postids   = $result['list_with_postids'];
	$countertje          = count( $list_with_postids );

	if ( isset( $result['filter_community_types'] ) ) {
		$filter_community_types = $result['filter_community_types'];
	} else {
		$filter_community_types = 0;
	}
	if ( isset( $result['filter_community_topics'] ) ) {
		$filter_community_topics = $result['filter_community_topics'];
	} else {
		$filter_community_topics = 0;
	}
	if ( isset( $result['filter_community_audiences'] ) ) {
		$filter_community_audiences = $result['filter_community_audiences'];
	} else {
		$filter_community_audiences = 0;
	}

	if ( isset( $result['is_filtered'] ) ) {
		$community_show_alphabet_list = false;
		$list_layout                  = 'community_layout_list_grid';
		if ( $countertje > 0 ) {
			$title = sprintf( _n( '%s community gevonden', '%s community\'s gevonden', $countertje, 'wp-rijkshuisstijl' ), number_format_i18n( $countertje ) );
		}
	}

	// ---------------------------------------------------------
	// column count for grid
	if ( $countertje < 3 ) {
		$columncount = $countertje;
	} elseif ( $countertje === 4 ) {
		$columncount = 2;
	} else {
		$columncount = 3;
	}

	// ---------------------------------------------------------
	// append filter
	if ( $community_layout_show_filter ) {

		$arghs_for_filter = array(
			'ID'           => $page_id,
			'type'         => 'details',
			'title'        => 'Filter',
			'container_id' => 'details_community_filter',
			'before_title' => '<h2>',
			'after_title'  => '</h2>',
		);
		if ( isset( $result['is_filtered'] ) ) {
			$arghs_for_filter['is_open'] = true;
		}
		$filter_form = rhswp_community_get_filter_form( $arghs_for_filter );

	}

	// ---------------------------------------------------------
	// append letter list
	if ( $community_show_alphabet_list ) {
		$letter        = '';
		$alphabet_list = '<div class="alphabet">';

		foreach ( $list_with_postids as $post_id ) :

			$post = get_post( $post_id );
			$slug = $post->post_name;

			$current_letter = substr( strtolower( $slug ), 0, 1 );
			if ( $current_letter !== $letter ) {
				if ( strtoupper( $current_letter ) ) {
					$alphabet_list .= '<a href="#list_' . strtolower( $current_letter ) . '"><span>' . strtoupper( $current_letter ) . '</span></a>' . "\n";
				}
				$letter = $current_letter;
			}

		endforeach;

		$alphabet_list .= '</div>'; // .alphabet

	}

	// ---------------------------------------------------------
	// construct the list with all relevant communities
	if ( ! empty( $list_with_postids ) ) {

		$postcounter = 0;

		if ( 'community_layout_list_grid' !== $list_layout ) {
			// show list with detail / summary items

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

			$items .= '<div class="archive-custom-loop columncount-' . $columncount . '">';
			$items .= '<h2 id="' . $container_labelid . '">' . $title . '</h2>';
			$items .= '<section class="' . $css . '" id="' . CONTAINER_ID . '" aria-labelledby="' . $container_labelid . '">';

			foreach ( $list_with_postids as $post_id ) :

				$post = get_post( $post_id );

				// if possible add list of tax.terms
				$extra_html = '';
				if ( $filter_community_types || $filter_community_topics || $filter_community_audiences ) {
					$extra_html = rhswp_community_single_terms( true, $post->ID, false, false );
				}

				$current_letter = substr( strtolower( $post->post_name ), 0, 1 );
				$permalink      = get_permalink( $post );

				if ( $community_show_alphabet_list ) {

					// alleen dossiers met een geldige pagina tonen
					if ( $current_letter !== $letter ) {

						$cummunitylijst .= "\n" . $list_end;
						$cummunitylijst .= $blok_letter_close;
						$cummunitylijst .= "\n" . $blok_letter_open;
						$cummunitylijst .= "\n" . '<h3 id="list_' . strtolower( $current_letter ) . '">' . strtoupper( $current_letter ) . '</h3>';
						$cummunitylijst .= "\n" . $list_start;

						// reset waarden
						$letter = $current_letter;

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
					$link_and_linktext .= sprintf( '%s <span class="visuallyhidden">%s</span>', _x( 'Lees meer', 'read more visible link text', 'wp-rijkshuisstijl' ), sprintf( _x( 'over de community %s', 'read more invisible link text', 'wp-rijkshuisstijl' ), $post->post_title ) );
					$link_and_linktext .= '</a></p>';
				}

				if ( $headerlevel && $titel && $link_and_linktext && $excerpt ) {
					$cummunitylijst .= '<details><summary><' . $headerlevel . '>' . $titel . '</' . $headerlevel . '></summary>';
					$cummunitylijst .= '<p>' . wp_strip_all_tags( $excerpt ) . '</p>';
					$cummunitylijst .= $extra_html;
					$cummunitylijst .= $link_and_linktext . '</details>';
				} else {
					$cummunitylijst .= $link_and_linktext;
				}
				$cummunitylijst .= '</li>';

			endforeach;

			$items .= $cummunitylijst;
			$items .= $list_end;
			$items .= $blok_letter_close;
			$items .= '</section><!-- .dossier-list column-layout -->' . "\n";
			$items .= '</div><!-- #community_container -->' . "\n";

		} else {

			$items .= '<h2 id="' . $container_labelid . '">' . $title . '</h2>';
			if ( isset( $result['filter_explication'] ) ) {
				$items .= '<p>' . $result['filter_explication'] . '</p>';
			}

			$items .= '<div class="archive-custom-loop grid columncount-' . $columncount . '" id="' . CONTAINER_ID . '" aria-labelledby="' . $container_labelid . '">';

			foreach ( $list_with_postids as $post_id ) :

				$post = get_post( $post_id );

				$postcounter ++;
				// if possible add list of tax.terms
				$extra_html = '';
				if ( $filter_community_types || $filter_community_topics || $filter_community_audiences ) {
//					$extra_html = rhswp_community_single_terms( true, $post->ID, false, false );
				}


				$current_post_id = isset( $post->ID ) ? $post->ID : 0;
				$args_grid_item  = array(
					'ID'          => $current_post_id,
					'itemclass'   => 'griditem griditem--community colspan-1',
					'type'        => 'posts_normal',
					'headerlevel' => $headerlevel,
				);

//				$this_item = rhswp_get_grid_item( $args_grid_item ) . $extra_html;
				$this_item = rhswp_get_grid_item( $args_grid_item );
				$items     .= $this_item;

				do_action( 'genesis_after_entry' );

			endforeach;

			$items .= '</div>'; // .archive-custom-loop columncount-

		}


		if ( $community_types || $community_topics || $community_audiences ) {
			$items .= '<div class="taxonomylist grid" id="communities_filter">';
			$items .= $community_types . $community_topics . $community_audiences;
			$items .= '</div>';
		}

	} else {
		$items .= _x( 'Geen community\'s gevonden', 'no results', 'wp-rijkshuisstijl' );
	}

	if ( $filter_form || $filter_form ) {
		$return .= '<div class="filters-and-alphabet">';
		$return .= $alphabet_list;
		$return .= $filter_form;
		$return .= '</div>';
	}
	$return .= $items;

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

