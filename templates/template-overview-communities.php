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

function community_add_communities_gridxx( $doreturn = false ) {

	global $post;


}

//========================================================================================================

function community_add_communities_grid( $doreturn = false ) {

	global $post;

	// variables
	$headerlevel                 = 'h3';
	$page_id                     = $post->ID;
	$community_terms_list        = '';
	$itemcount                   = 0;
	$columncount                 = 0;
	$colspan                     = 1;
	$blockidattribute            = '';
	$extra_blocks                = '';
	$losseblokken                = '';
	$date_format                 = get_option( 'date_format' ); // e.g. "F j, Y"
	$title_tag                   = 'h2';
	$title_tag_start             = '<' . $title_tag . '>';
	$title_tag_end               = '</' . $title_tag . '>';
	$title_for_list              = ( get_field( 'community_list_title', $post ) ) ? get_field( 'community_list_title', $post ) : _x( 'Alle community\'s', 'community lijst titel', 'wp-rijkshuisstijl' );
	$list_layout                 = ( get_field( 'community_layout_list', $post ) === 'community_layout_list_accordion' ) ? 'community_layout_list_accordion' : 'community_layout_list_grid';
	$block_search_community_form = get_field( 'community_layout_block_search_group', $post );
	$block_rss_agenda_items      = get_field( 'community_layout_block_agenda_group', $post );
	$block_rss_post_items        = get_field( 'community_layout_block_posts_group', $post );
	$block_latest_communities    = get_field( 'community_layout_block_latest_communities_group', $post );


	if ( $block_rss_agenda_items['community_layout_block_posts_show'] !== 'show_false' ) {

		if ( $block_rss_agenda_items['rss_sources'] ) {

			$itemcount ++;

			$feeds = array();
			foreach ( $block_rss_agenda_items['rss_sources'] as $item ) {
				$feeds[] .= $item->post_name;
			}
			$title_block   = ( $block_rss_agenda_items['block_title'] ) ?: _x( 'Berichten', 'label keyword veld', 'wp-rijkshuisstijl' );
			$overview_link = $block_rss_agenda_items['overview_link'];
			$limit         = (int) ( $block_rss_agenda_items['max_items'] ) ?: 5;
			$template      = ( $block_rss_agenda_items['rss_template'] ) ? ' template="' . $block_rss_agenda_items['rss_template']->post_name . '"' : '';
			$shortcode     = '[wp-rss-aggregator' . $template . ' feeds="' . implode( ',', $feeds ) . '" limit="' . $limit . '" pagination="off"]';
			$content       = do_shortcode( $shortcode );

			if ( $content ) {
				$extra_blocks .= '<div class="griditem colspan-' . $colspan . '">';
				$extra_blocks .= $title_tag_start . $title_block . $title_tag_end;
				$extra_blocks .= $content;
				if ( isset( $overview_link['url'] ) && isset( $overview_link['title'] ) ) {
					$extra_blocks .= '<p class="more"><a href="' . $overview_link['url'] . '">' . $overview_link['title'] . '</a></p>';
				}
				$extra_blocks .= '</div>'; // .griditem
			}
		}

	}

	if ( $block_rss_post_items['community_layout_block_posts_show'] !== 'show_false' ) {

		if ( $block_rss_post_items['rss_sources'] ) {

			$itemcount ++;

			$feeds = array();
			foreach ( $block_rss_post_items['rss_sources'] as $item ) {
				$feeds[] .= $item->post_name;
			}

			$title_block   = ( $block_rss_post_items['block_title'] ) ?: _x( 'Berichten', 'label keyword veld', 'wp-rijkshuisstijl' );
			$overview_link = $block_rss_post_items['overview_link'];
			$limit         = (int) ( $block_rss_post_items['max_items'] ) ?: 5;
			$template      = ( $block_rss_post_items['rss_template'] ) ? ' template="' . $block_rss_post_items['rss_template']->post_name . '"' : '';
			$shortcode     = '[wp-rss-aggregator' . $template . ' feeds="' . implode( ',', $feeds ) . '" limit="' . $limit . '" pagination="off"]';
			$content       = do_shortcode( $shortcode );

			if ( $content ) {
				$extra_blocks .= '<div class="griditem colspan-' . $colspan . '">';
				$extra_blocks .= $title_tag_start . $title_block . $title_tag_end;
				$extra_blocks .= $content;
				if ( isset( $overview_link['url'] ) && isset( $overview_link['title'] ) ) {
					$extra_blocks .= '<p class="more"><a href="' . $overview_link['url'] . '">' . $overview_link['title'] . '</a></p>';
				}
				$extra_blocks .= '</div>'; // .griditem
			}
		}


	}

	if ( $block_latest_communities ) {
		$content       = '';
		$limit         = (int) ( $block_latest_communities['max_items'] ) ?: 5;
		$max_age       = (int) ( $block_latest_communities['community_layout_block_latest_communities_max_days'] ) ?: 365;
		$overview_link = $block_latest_communities['overview_link'];
		$date_after    = date( 'Y-m-d', strtotime( ' - ' . $max_age . ' days' ) );
		$title_block   = ( $block_rss_post_items['block_title'] ) ?: _x( 'Laatst toegevoegd', 'label keyword veld', 'wp-rijkshuisstijl' );

		$argscount = array(
			'post_type'      => DO_COMMUNITY_CPT,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'date_query'     => array( 'after' => $date_after ),
			'posts_per_page' => $limit,
		);

		// Assign predefined $args to your query
		$community_list = new WP_query();
		$community_list->query( $argscount );

		if ( $community_list->have_posts() ) {

//			$content .= '<p>alles na ' . date( $date_format, strtotime( $date_after ) ) . '</p>';
			$content .= '<ul>';

			while ( $community_list->have_posts() ) : $community_list->the_post();
				$the_id    = get_the_id();
				$post_date = get_the_date( $date_format, $the_id );
				$content   .= '<li><a href="' . get_permalink( $the_id ) . '">' . get_the_title( $the_id ) . '</a> (' . $post_date . ')</li>';
			endwhile;
			$content .= '</ul>';
			if ( isset( $overview_link['url'] ) && isset( $overview_link['title'] ) ) {
				$content .= '<p class="more"><a href="' . $overview_link['url'] . '">' . $overview_link['title'] . '</a></p>';
			}

		}

		if ( $content ) {
			$itemcount ++;

			$extra_blocks .= '<div class="griditem colspan-' . $colspan . '">';
			$extra_blocks .= $title_tag_start . $title_block . $title_tag_end;
			$extra_blocks .= $content;
			$extra_blocks .= '</div>'; // .griditem
		}


	}

	if ( $extra_blocks ) {

		$losseblokken .= '<section class="losseblokken"' . $blockidattribute . '>';
		$losseblokken .= '<div class="grid itemcount-' . $itemcount . ' columncount-' . $columncount . '">';
		$losseblokken .= $extra_blocks; // .wrap
		$losseblokken .= '</div>'; // .wrap
		$losseblokken .= '</section>'; // .losseblokken
	}

	$community_layout_show_terms_filter = ( get_field( 'community_layout_show_terms_lists', $post ) !== 'community_layout_show_terms_filter_false' ) ? true : false;
	if ( 'community_layout_list_grid' === $list_layout ) {
		$community_show_alphabet_list = false;
	} else {
		$community_show_alphabet_list = ( get_field( 'community_layout_show_alphabet_list', $post ) !== 'community_layout_show_alphabet_list_false' ) ? true : false;
	}

	$return                = '';
	$container_before      = '';
	$container_after       = '';
	$container_labelid     = CONTAINER_ID . '_header';
	$community_search_form = '';
	$alphabet_list         = '';
	$result                = community_get_selection();
	$list_with_postids     = $result['list_with_postids'];
	$countertje            = count( $list_with_postids );

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
			$title_for_list = sprintf( _n( '%s community gevonden', '%s community\'s gevonden', $countertje, 'wp-rijkshuisstijl' ), number_format_i18n( $countertje ) );
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
	// construct community search form / filter
	if ( $block_search_community_form['community_layout_block_searchform_show'] !== 'show_false' ) {

		$searchform_input_label       = ( $block_search_community_form['community_layout_block_searchform_label'] ) ?: _x( 'Zoek community input label', 'label keyword veld', 'wp-rijkshuisstijl' );
		$searchform_button_label      = ( $block_search_community_form['community_layout_block_searchform_button_label'] ) ?: _x( 'Zoek', 'Zoek community input label', 'wp-rijkshuisstijl' );
		$searchform_input_placeholder = _x( '[zoekterm voor community]', 'placeholder input', 'wp-rijkshuisstijl' );

		$arghs_for_filter = array(
			'ID'           => $page_id,
			'container_id' => 'community_filter',
			'input_label'  => $searchform_input_label,
			'button_label' => $searchform_button_label,
			'placeholder'  => $searchform_input_placeholder,

		);

		$community_search_form = rhswp_community_get_filter_form( $arghs_for_filter );

	}


	// ---------------------------------------------------------
	// append list of terms for community taxonomies
	if ( $community_layout_show_terms_filter ) {

		$community_types     = ictuwp_communityfilter_list( DO_COMMUNITYTYPE_CT, _n( 'Type community', 'Types community', 2, 'wp-rijkshuisstijl' ), false, $args['ID'], false, $make_checkboxes );
		$community_topics    = ictuwp_communityfilter_list( DO_COMMUNITYTOPICS_CT, _n( 'Onderwerp community', 'Onderwerpen community', 2, 'wp-rijkshuisstijl' ), false, $args['ID'], false, $make_checkboxes );
		$community_audiences = ictuwp_communityfilter_list( DO_COMMUNITYAUDIENCE_CT, _n( 'Doelgroep', 'Doelgroepen', 2, 'wp-rijkshuisstijl' ), false, $args['ID'], false, $make_checkboxes );
		$extra_blocks        = '';
		$community_terms     = rhswp_community_get_terms_list( $arghs_for_filter );

		if ( $community_types || $community_topics || $community_audiences ) {
			$itemcount = 0;
			$colspan   = 1;

			if ( $community_types ) {
				$itemcount ++;

				$extra_blocks .= '<div class="griditem colspan-' . $colspan . '">';
				$extra_blocks .= $community_types;
				$extra_blocks .= '</div>'; // .griditem
			}
			if ( $community_topics ) {
				$itemcount ++;

				$extra_blocks .= '<div class="griditem colspan-' . $colspan . '">';
				$extra_blocks .= $community_topics;
				$extra_blocks .= '</div>'; // .griditem
			}
			if ( $community_audiences ) {
				$itemcount ++;

				$extra_blocks .= '<div class="griditem colspan-' . $colspan . '">';
				$extra_blocks .= $community_audiences;
				$extra_blocks .= '</div>'; // .griditem
			}

			$community_terms_list = '<section' . $blockidattribute . '>';
			$community_terms_list .= '<div class="grid itemcount-' . $itemcount . ' columncount-' . $columncount . '">';
			$community_terms_list .= $extra_blocks; // .wrap
			$community_terms_list .= '</div>'; // .wrap
			$community_terms_list .= '</section>'; // .losseblokken
		}


	}

	// ---------------------------------------------------------
	// if requested, construct and append letter list
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
	/**
	 * Three options for layout:
	 *   1 -  each community item shown as solid block
	 *   2 -  each community item shown within a <details> tag, with options:
	 *   2 (a) grouped by first letter, if letter list is requested
	 *   2 (b) no grouping
	 */
	$container_before .= '<h2 id="' . $container_labelid . '">' . $title_for_list . '</h2>';
	if ( isset( $result['filter_explication'] ) ) {
		$container_before .= '<p>' . $result['filter_explication'] . '</p>';
	}
	if ( $alphabet_list || $community_search_form ) {
		$container_before .= '<div class="filters-and-alphabet">';
		$container_before .= $community_search_form;
		$container_before .= $alphabet_list;
		$container_before .= '</div>';
	}

	$container_before .= '<div class="archive-custom-loop columncount-' . $columncount . '">';

	if ( 'community_layout_list_grid' !== $list_layout ) {
		if ( $community_show_alphabet_list ) {
			//   2 (a) each item shows only header (<details>), grouped by first letter
			$container_before .= '<section class="community-container no-list-style column-layout" id="' . CONTAINER_ID . '" aria-labelledby="' . $container_labelid . '">';
		} else {
			//   2 (b) each item shows only header (<details>), no grouping
			$container_before .= '<section class="community-container no-list-style" id="' . CONTAINER_ID . '" aria-labelledby="' . $container_labelid . '">';
		}
	} else {
		//   1 -  each item shown as solid block
		$container_before .= '<section class="community-container no-list-style grid" id="' . CONTAINER_ID . '" aria-labelledby="' . $container_labelid . '">';
	}

	$container_after .= '</section><!-- .dossier-list column-layout -->' . "\n";
	$container_after .= '</div><!-- #community_container -->' . "\n";

	// ---------------------------------------------------------
	// construct the list with all relevant communities
	if ( ! empty( $list_with_postids ) ) {

		$postcounter          = 0;
		$community_list_items = '';

		if ( 'community_layout_list_grid' !== $list_layout ) {
			// show list with detail / summary items
			if ( $community_show_alphabet_list ) {
				$letter            = '';
				$list_start        = '<!--- $list_start initial --><ul>';
				$list_end          = '<!--- $list_end initial -->' . "\n";
				$blok_letter_close = '<!--- $blok_letter_close initial -->' . "\n"; // initiele waarde voor afsluiter
				$blok_letter_open  = '<div class="column-block">' . "\n";
				$cummunitylijst    = '<!--- start -->' . "\n";
			} else {
				$cummunitylijst    = '<!--- start -->' . "\n" . '<ul class="column-layout">' . "\n";
				$list_end          = "\n</ul>\n";
				$blok_letter_close = "\n";
			}

			foreach ( $list_with_postids as $post_id ) :

				$post           = get_post( $post_id );
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

						$list_start = '<ul>' . "\n";
						$list_end   = "</ul>\n";
					}
				}

				$cummunitylijst .= "\n" . '<li class="cat-item cat-item-' . $post->ID . '">';

				$titel             = $post->post_title;
				$excerpt           = ( get_the_excerpt( $post ) ) ? get_the_excerpt( $post ) : '[beschrijving volgt]';
				$link_and_linktext = $post->post_title;
				if ( $permalink ) {
					$link_and_linktext = '<p class="read-more"><a href="' . $permalink . '">';
					$link_and_linktext .= sprintf( '%s <span class="visuallyhidden">%s</span>', _x( 'Lees meer', 'read more visible link text', 'wp-rijkshuisstijl' ), sprintf( _x( 'over de community %s', 'read more invisible link text', 'wp-rijkshuisstijl' ), $post->post_title ) );
					$link_and_linktext .= '</a></p>';
				}

				if ( $headerlevel && $titel && $link_and_linktext && $excerpt ) {
					$cummunitylijst .= '<details><summary><' . $headerlevel . '>' . $titel . '</' . $headerlevel . '></summary>';
					$cummunitylijst .= '<p>' . wp_strip_all_tags( $excerpt ) . '</p>';
					$cummunitylijst .= $link_and_linktext . '</details>';
				} else {
					$cummunitylijst .= $link_and_linktext;
				}
				$cummunitylijst .= '</li>';

			endforeach;

			$community_list_items .= $cummunitylijst;
			$community_list_items .= $list_end;
			$community_list_items .= $blok_letter_close;

		} else {


			foreach ( $list_with_postids as $post_id ) :

				$postcounter ++;

				$post            = get_post( $post_id );
				$current_post_id = isset( $post->ID ) ? $post->ID : 0;
				$args_grid_item  = array(
					'ID'          => $current_post_id,
					'itemclass'   => 'griditem griditem--community colspan-1',
					'type'        => 'posts_normal',
					'headerlevel' => $headerlevel,
				);

				$this_item            = rhswp_get_grid_item( $args_grid_item );
				$community_list_items .= $this_item;

				do_action( 'genesis_after_entry' );

			endforeach;


		}


	} else {
		$community_list_items .= _x( 'Geen community\'s gevonden', 'no results', 'wp-rijkshuisstijl' );
	}

	$return .= $container_before;
	$return .= $community_list_items;
	$return .= $container_after;


	if ( $losseblokken ) {
		$return .= $losseblokken;
	}


	if ( $community_terms_list ) {
		$return .= $community_terms_list;
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

