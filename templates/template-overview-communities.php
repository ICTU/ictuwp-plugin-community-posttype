<?php
/**
 *
 * template-overview-communities.php
 *
 * @version 1.0.1 - First version on live server
 */

//========================================================================================================

define( 'CONTAINER_ID', 'community_container' );


add_action( 'wp_enqueue_scripts', 'community_set_id_for_openbuttons' );

function community_set_id_for_openbuttons() {
	if ( ! is_admin() ) {
		$translation_array = array();

		$openclose = _x( "Toon alle community's", 'Labels detailbuttons', 'wp-rijkshuisstijl' );
		$openinit  = sprintf( _x( 'There are __NUMBER__ blocks with hidden text on this page. Use the button with \'%s\' to make the text available.', 'Labels detailbuttons', 'wp-rijkshuisstijl' ), $openclose );
		// Localize the script with new data
		$translation_array = array(
			'id'                        => CONTAINER_ID,
			'detailsbutton_init'        => $openinit,
			'detailsbutton_resultclose' => _x( 'All detail blocks are closed', 'Labels detailbuttons', 'wp-rijkshuisstijl' ),
			'detailsbutton_resultopen'  => _x( 'All detail blocks are open', 'Labels detailbuttons', 'wp-rijkshuisstijl' ),
			'open'                      => $openclose,
			'close'                     => _x( "Verberg alle community's", 'Labels detailbuttons', 'wp-rijkshuisstijl' )
		);
		wp_localize_script( 'details-element', 'detailssummarytranslate', $translation_array );

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
	$tax_query                   = array();
	$filter_explication          = '';

	if ( isset( $_GET['community_search_string'] ) && ( $_GET['community_search_string'] !== '' ) ) {

		// a search query for the supplemental 'communities' searchwp engine
		$searchterm  = sanitize_text_field( $_GET['community_search_string'] );
		$name_engine = 'communities';

		if ( class_exists( '\\SearchWP\\Query' ) ) {
			// SearchWP v4.x
			$search_page = isset( $_GET['swppg'] ) ? absint( $_GET['swppg'] ) : 1;

			$arguments          = array(
				'engine' => $name_engine, // The Engine name.
				'fields' => 'ids', // only return IDs
				'page'   => $search_page
			);
			$searchwp_query     = new \SearchWP\Query( $searchterm, $arguments );
			$community_list     = $searchwp_query->get_results();
			$filter_explication = "Gefilterd op '" . $searchterm . "'";

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


	return $return;

}

//========================================================================================================

function community_add_communities_grid( $doreturn = false ) {

	global $post;

	// variables
	$headerlevel                  = 'h3';
	$page_id                      = $post->ID;
	$blocks_row_terms             = '';
	$itemcount                    = 0;
	$columncount                  = 0;
	$colspan                      = 1;
	$return                       = '';
	$blocks_row_rss_items         = '';
	$losseblokken                 = '';
	$container_start              = '';
	$container_title              = '';
	$container_footer             = '';
	$alphabet_list                = '';
	$show_extra_blocks            = true;
	$date_format_badge            = get_option( 'date_format' ); // e.g. "F j, Y"
	$title_tag                    = 'h2';
	$list_layout                  = 'community_layout_list_grid';
	$id_header_sectionlabel       = CONTAINER_ID . '_header';
	$title_tag_start              = '<' . $title_tag . '>';
	$title_tag_end                = '</' . $title_tag . '>';
	$title_for_list               = ( get_field( 'community_list_title', $post ) ) ? get_field( 'community_list_title', $post ) : _x( 'Alle community\'s', 'community lijst titel', 'wp-rijkshuisstijl' );
	$show_alphabet_list           = false;
	$community_layout_list        = get_field( 'community_overview_page_layout', $post );
	$block_search_community_form  = get_field( 'community_layout_block_search_group', $post );
	$community_layout_terms_group = get_field( 'community_layout_terms_group', $post );
	$block_rss_agenda_items       = get_field( 'community_layout_block_agenda_group', $post );
	$block_rss_post_items         = get_field( 'community_layout_block_posts_group', $post );
	$block_latest_communities     = get_field( 'community_layout_block_latest_communities_group', $post );
	$community_list_items         = '';
	$community_list_items_start   = '';
	$community_list_items_end     = '';
	$thepage                      = get_theme_mod( 'customizer_community_pageid_overview' );


	$result            = community_get_selection();
	$list_with_postids = $result['list_with_postids'];
	$countertje        = count( $list_with_postids );

	if ( $community_layout_terms_group['community_layout_show_terms_lists'] === 'show_true' ) {
		$show_terms_list = true;
	} else {
		$show_terms_list = false;
	}

	if ( $community_layout_list ) {

		$list_layout = ( $community_layout_list['community_layout_list'] !== 'community_layout_list_grid' ) ? 'community_layout_list_accordion' : 'community_layout_list_grid';

		if ( $list_layout === 'community_layout_list_accordion' && $community_layout_list['community_layout_show_alphabet_list'] === 'show_true' ) {
			$show_alphabet_list = true;
		}

	}

	if ( isset( $result['is_filtered'] ) ) {
		$show_alphabet_list = false;
		$show_extra_blocks  = false;
		$list_layout        = 'community_layout_list_grid';
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

	$community_search_form = '';

	// ---------------------------------------------------------
	// construct community search form / filter
	if ( $block_search_community_form['community_layout_block_searchform_show'] !== 'show_false' ) {

		$searchform_input_label       = ( $block_search_community_form['community_layout_block_searchform_label'] ) ?: _x( 'Zoek community', 'label keyword veld', 'wp-rijkshuisstijl' );
		$searchform_button_label      = ( $block_search_community_form['community_layout_block_searchform_button_label'] ) ?: _x( 'Zoek', 'Zoek community input label', 'wp-rijkshuisstijl' );
		$searchform_input_placeholder = _x( '[zoekterm voor community]', 'placeholder input', 'wp-rijkshuisstijl' );

		$arghs_for_filter = array(
			'ID'           => $page_id,
			'container_id' => 'community_searchform',
			'input_label'  => $searchform_input_label,
			'button_label' => $searchform_button_label,
			'placeholder'  => $searchform_input_placeholder,

		);

		$community_search_form = rhswp_community_get_filter_form( $arghs_for_filter );

	}


	/**
	 * list layout: options
	 * - community_layout_list_accordion
	 *
	 * - community_layout_list_grid
	 * - search items
	 *
	 */

	// ---------------------------------------------------------
	// if requested, construct and append letter list
	if ( $show_alphabet_list ) {
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

	$container_title .= "\n" . '<div id="search-and-alphabet">';
	$container_title .= "\n" . '<h2 id="' . $id_header_sectionlabel . '" class="title">' . $title_for_list . '</h2>';
	if ( isset( $result['filter_explication'] ) ) {
		$container_title .= "\n" . '<p id="filter_explication">' . $result['filter_explication'] . '</p>';
		if ( $thepage ) {
			$container_title .= "\n" . '<a href="' . get_permalink( $thepage ) . '" class="reset">' . _x( 'Toon alle community\'s', 'reset filter', 'wp-rijkshuisstijl' ) . '</a>';
//			$container_title .= "\n" . '<button type="submit">' . _x( 'Toon alle community\'s', 'reset filter', 'wp-rijkshuisstijl' ) . '</button>';
		}
	}
	if ( $alphabet_list ) {
		$container_title .= "\n" . $alphabet_list;
	}
	if ( isset( $result['is_filtered'] ) ) {
		$container_title .= "\n" . $community_search_form;
	}
	$container_title .= "\n" . '</div>'; // #search-and-alphabet


	$inner_css_class = 'inner-css archive-custom-loop no-list-style';

	$container_start .= "\n" . '<section class="community-container border-top" aria-labelledby="' . $id_header_sectionlabel . '">';

	if ( 'community_layout_list_grid' !== $list_layout ) {
		if ( $show_alphabet_list ) {
			//   2 (a) each item shows only header (<details>), grouped by first letter
			$inner_css_class .= ' column-layout columncount-' . $columncount;
		} else {
			//   2 (b) each item shows only header (<details>), no grouping
//			$inner_css_class .= "\n" . ' grid';
		}
	} else {
		//   1 -  each item shown as solid block, but not for search results
		if ( $show_extra_blocks ) {
			$inner_css_class .= "\n" . ' grid';
		}
	}

	if ( isset( $result['is_filtered'] ) ) {
		//   2 (a) each item shows only header (<details>), grouped by first letter
		$inner_css_class .= ' search-results';
	}

	$container_start .= $container_title;

	$community_list_items_start .= "\n" . '<div class="' . $inner_css_class . '" id="' . CONTAINER_ID . '">';

	$community_list_items_end .= '</div><!-- .archive-custom-loop -->' . "\n";

	$container_footer .= '</section><!-- .community-container -->' . "\n";


	// ---------------------------------------------------------
	// construct the list with all relevant communities
	if ( ! empty( $list_with_postids ) ) {

		$postcounter          = 0;
		$community_list_items = '';

		if ( 'community_layout_list_grid' !== $list_layout ) {
			// show list with detail / summary items
			if ( $show_alphabet_list ) {
				$letter            = '';
				$list_start        = '<!--- $list_start initial --><ul>';
				$list_end          = '<!--- $list_end initial -->' . "\n";
				$blok_letter_close = '<!--- $blok_letter_close initial -->' . "\n"; // initiele waarde voor afsluiter
				$blok_letter_open  = '<div class="column-block">' . "\n";
				$community_list    = '<!--- start -->' . "\n";
			} else {
				$community_list    = '<!--- start -->' . "\n" . '<ul class="column-layout">' . "\n";
				$list_end          = "\n</ul>\n";
				$blok_letter_close = "\n";
			}

			foreach ( $list_with_postids as $post_id ) :

				$post           = get_post( $post_id );
				$current_letter = substr( strtolower( $post->post_name ), 0, 1 );
				$permalink      = get_permalink( $post );

				if ( $show_alphabet_list ) {

					// alleen dossiers met een geldige pagina tonen
					if ( $current_letter !== $letter ) {

						$community_list .= "\n" . $list_end;
						$community_list .= $blok_letter_close;
						$community_list .= "\n" . $blok_letter_open;
						$community_list .= "\n" . '<h3 id="list_' . strtolower( $current_letter ) . '">' . strtoupper( $current_letter ) . '</h3>';
						$community_list .= "\n" . $list_start;

						// reset waarden
						$letter = $current_letter;

						// overschrijf initiele waarde voor afsluiter
						$blok_letter_close = '</div>' . "\n";

						$list_start = '<ul>' . "\n";
						$list_end   = "</ul>\n";
					}
				}

				$community_list .= "\n" . '<li class="cat-item cat-item-' . $post->ID . '">';

				$title             = $post->post_title;
				$excerpt           = ( get_the_excerpt( $post ) ) ? get_the_excerpt( $post ) : '[beschrijving volgt]';
				$link_and_linktext = $post->post_title;
				if ( $permalink ) {
					$link_and_linktext = '<p class="read-more"><a href="' . $permalink . '">';
					$link_and_linktext .= sprintf( '%s <span class="visuallyhidden">%s</span>', _x( 'Lees meer', 'read more visible link text', 'wp-rijkshuisstijl' ), sprintf( _x( 'over de community %s', 'read more invisible link text', 'wp-rijkshuisstijl' ), $post->post_title ) );
					$link_and_linktext .= '</a></p>';
				}

				if ( $headerlevel && $title && $link_and_linktext && $excerpt ) {
					$community_list .= '<details><summary><' . $headerlevel . '>' . $title . '</' . $headerlevel . '></summary>';
					$community_list .= '<p>' . wp_strip_all_tags( $excerpt ) . '</p>';
					$community_list .= $link_and_linktext . '</details>';
				} else {
					$community_list .= $link_and_linktext;
				}
				$community_list .= '</li>';

				$postcounter ++;
			endforeach;

			$community_list_items .= $community_list;
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

	if ( $show_extra_blocks ) {


		// ---------------------------------------------------------
		// events from RSS feeds
		if ( isset( $block_rss_agenda_items['community_layout_block_agenda_show'] ) && $block_rss_agenda_items['community_layout_block_agenda_show'] !== 'show_false' ) {

			$overview_link   = $block_rss_agenda_items['overview_link'];
			$limit           = (int) ( $block_rss_agenda_items['max_items'] ) ?: 5;
			$title_block     = ( $block_rss_agenda_items['block_title'] ) ?: _x( 'Agenda', 'Header rss links', 'wp-rijkshuisstijl' );
			$rss_content     = '';
			$args_selection  = array(
				'event_type'     => 'events',
				'paging'         => false,
				'posts_per_page' => $limit,
				'echo'           => false
			);
			$community_items = community_feed_items_get( $args_selection );

			if ( ! $community_items ) {
				// no items
			} else {
				$args_in     = array(
					'items' => $community_items
				);
				$rss_content = community_feed_items_show( $args_in );
			}


			if ( $rss_content ) {

				$blocks_row_rss_items .= '<div class="griditem border-top colspan-' . $colspan . '" id="communities_agenda">';
				$blocks_row_rss_items .= $title_tag_start . $title_block . $title_tag_end;
				$blocks_row_rss_items .= $rss_content;
				if ( isset( $overview_link['url'] ) && isset( $overview_link['title'] ) ) {
					$blocks_row_rss_items .= '<p class="more"><a href="' . $overview_link['url'] . '">' . $overview_link['title'] . '</a></p>';
				}
				$blocks_row_rss_items .= '</div>'; // .griditem
			}


		}

		// ---------------------------------------------------------
		// posts from RSS feeds
		if ( isset( $block_rss_post_items['community_layout_block_posts_show'] ) && $block_rss_post_items['community_layout_block_posts_show'] !== 'show_false' ) {

			$overview_link                    = $block_rss_post_items['overview_link'];
			$limit                            = (int) ( $block_rss_post_items['max_items'] ) ?: 5;
			$title_block                      = ( $block_rss_post_items['block_title'] ) ?: _x( 'Berichten', 'Header rss links', 'wp-rijkshuisstijl' );
			$rss_content                      = '';
			$args_selection['event_type']     = 'posts';
			$args_selection['sort_order']     = 'DESC';
			$args_selection['posts_per_page'] = $limit;
			$community_items                  = community_feed_items_get( $args_selection );

			if ( ! $community_items ) {
				// no items
			} else {
				$args_in     = array(
					'type'  => 'posts',
					'items' => $community_items
				);
				$rss_content = community_feed_items_show( $args_in );
			}


			if ( $rss_content ) {

				$blocks_row_rss_items .= '<div class="griditem border-top colspan-' . $colspan . '" id="communities_news">';
				$blocks_row_rss_items .= $title_tag_start . $title_block . $title_tag_end;
				$blocks_row_rss_items .= $rss_content;
				if ( isset( $overview_link['url'] ) && isset( $overview_link['title'] ) ) {
					$blocks_row_rss_items .= '<p class="more"><a href="' . $overview_link['url'] . '">' . $overview_link['title'] . '</a></p>';
				}
				$blocks_row_rss_items .= '</div>'; // .griditem
			}
		}

		// ---------------------------------------------------------
		// last [x] communities
		if ( $block_latest_communities ) {

			$limit         = (int) ( $block_latest_communities['max_items'] ) ?: 5;
			$max_age       = (int) ( $block_latest_communities['community_layout_block_latest_communities_max_days'] ) ?: 365;
			$overview_link = $block_latest_communities['overview_link'];
			$title_block   = ( $block_latest_communities['block_title'] ) ?: _x( 'Laatst toegevoegd', 'label keyword veld', 'wp-rijkshuisstijl' );
			$args          = array(
				'max_items'     => $limit,
				'max_age'       => $max_age,
				'overview_link' => $overview_link,
				'css_class_ul'  => 'links'
			);

			$content = ictuwp_community_get_latest_list( $args );


			if ( $content ) {
				$itemcount ++;

				$blocks_row_rss_items .= '<div class="griditem border-top colspan-' . $colspan . '" id="communities_latest">';
				$blocks_row_rss_items .= $title_tag_start . $title_block . $title_tag_end;
				$blocks_row_rss_items .= $content;
				$blocks_row_rss_items .= '</div>'; // .griditem
			}


		}

		// ---------------------------------------------------------
		// append list of terms for community taxonomies
		if ( $show_terms_list ) {

			$show_counter = false;
			if ( $community_layout_terms_group['community_layout_show_terms_counter'] === 'show_true' ) {
				$show_counter = true;
			}

			$args2                    = array(
				'echo'            => false,
				'make_checkboxes' => false,
				'taxonomy'        => DO_COMMUNITYTYPE_CT,
				'css_class_ul'    => 'links',
				'show_counter'    => $show_counter,
				'hide_empty'      => true,
				'title'           => _n( 'Type community', 'Types community', 2, 'wp-rijkshuisstijl' ),
			);
			$community_types          = ictuwp_communityfilter_list( $args2 );
			$args2['taxonomy']        = DO_COMMUNITYTOPICS_CT;
			$args2['title']           = _n( 'Thema', 'Thema\'s', 2, 'wp-rijkshuisstijl' );
			$community_topics         = ictuwp_communityfilter_list( $args2 );
			$args2['taxonomy']        = DO_COMMUNITYAUDIENCE_CT;
			$args2['title']           = _n( 'Doelgroep', 'Doelgroepen', 2, 'wp-rijkshuisstijl' );
			$community_audiences      = ictuwp_communityfilter_list( $args2 );
			$args2['taxonomy']        = DO_COMMUNITYBESTUURSLAAG_CT;
			$args2['title']           = _n( 'Bestuurslaag', 'Overheidslagen', 2, 'wp-rijkshuisstijl' );
			$community_overheidslagen = ictuwp_communityfilter_list( $args2 );
			$terms_blocks             = '';
			$itemcount_terms          = 0;
			$columncount_terms        = 0;

			if ( $community_types || $community_topics || $community_audiences || $community_overheidslagen ) {
				$colspan = 1;

				if ( $community_topics ) {
					$itemcount_terms ++;

					$terms_blocks .= '<div class="griditem colspan-' . $colspan . '">';
					$terms_blocks .= $community_topics;
					$terms_blocks .= '</div>'; // .griditem
				}
				if ( $community_audiences ) {
					$itemcount_terms ++;

					$terms_blocks .= '<div class="griditem colspan-' . $colspan . '">';
					$terms_blocks .= $community_audiences;
					$terms_blocks .= '</div>'; // .griditem
				}
				if ( $community_types ) {
					$itemcount_terms ++;

					$terms_blocks .= '<div class="griditem colspan-' . $colspan . '">';
					$terms_blocks .= $community_types;
					$terms_blocks .= '</div>'; // .griditem
				}
				if ( $community_overheidslagen ) {
					$itemcount_terms ++;

					$terms_blocks .= '<div class="griditem colspan-' . $colspan . '">';
					$terms_blocks .= $community_overheidslagen;
					$terms_blocks .= '</div>'; // .griditem
				}

			}
			$columncount_terms = $itemcount_terms;


		}

	}

	$detailsblock = '';

	if ( $terms_blocks ) {


		$title        = ( $community_layout_terms_group['community_layout_terms_title'] ) ?: _x( 'Onderwerpen, types en doelgroepen', 'label keyword veld', 'wp-rijkshuisstijl' );
		$detailsblock .= '<details id="' . CONTAINER_ID . '_terms">';
		$detailsblock .= '<summary><h2>' . $title . '</h2></summary>';
		if ( $community_search_form ) {
			$detailsblock .= "\n" . $community_search_form;
		}

		$detailsblock .= '<div class="grid itemcount-' . $itemcount_terms . ' columncount-' . $columncount_terms . '">';
		$detailsblock .= $terms_blocks;
		$detailsblock .= '</div>'; // .wrap
		$detailsblock .= '</details>'; // .wrap

	}


	$return .= $container_start;
	$return .= $community_list_items_start;
	$return .= $community_list_items;
	$return .= $community_list_items_end;
	$return .= $detailsblock;
	$return .= $container_footer;


	if ( $blocks_row_rss_items ) {

		$return .= '<div class="grid itemcount-' . $itemcount . ' columncount-' . $columncount . '">';
		$return .= $blocks_row_rss_items;
		$return .= '</div>'; // .wrap

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

function NOT_USED_community_append_widget_sidebar() {

	if ( is_active_sidebar( RHSWP_WIDGET_AREA_COMMUNITY_OVERVIEW ) ) {
		echo '<div class="widget-single-footer">';
		dynamic_sidebar( RHSWP_WIDGET_AREA_COMMUNITY_OVERVIEW );
		echo '</div>';

	}

}

//========================================================================================================

function NOT_USED_community_add_communities_gridxxx( $doreturn = false ) {

	global $post;


	$return = '';

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


}

//========================================================================================================

function NOT_USED_community_get_selection_with_filters( $args = array() ) {

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
