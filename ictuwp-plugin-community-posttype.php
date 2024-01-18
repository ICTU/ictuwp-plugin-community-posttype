<?php

/**
 * @link                https://github.com/ICTU/ictuwp-plugin-community-posttype
 * @package             ictuwp-plugin-community-posttype
 *
 * @wordpress-plugin
 * Plugin Name:         ICTU / Digitale Overheid / Community
 * Plugin URI:          https://github.com/ICTU/ictuwp-plugin-community-posttype
 * Description:         Plugin voor het aanmaken van posttype 'community' en bijbehorende taxonomieen.
 * Version:             1.0.1
 * Version description: First version on live server
 * Author:              Paul van Buuren
 * Author URI:          https://github.com/ICTU/ictuwp-plugin-community-posttype/
 * License:             GPL-2.0+
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:         wp-rijkshuisstijl
 * Domain Path:         /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

//========================================================================================================

// Dutch slug for taxonomy
$slug              = 'community';
$slugtype          = 'community-type';
$slugtopics        = 'onderwerpen-community';
$slugaudiences     = 'doelgroepen-community';
$slugoverheidslaag = 'overheidslaag';

if ( get_bloginfo( 'language' ) !== 'nl-NL' ) {
	// non Dutch slugs
	$slug          = 'community';
	$slugtopics    = 'topics-community';
	$slugaudiences = 'audience-community';
}


define( 'DO_COMMUNITY_CPT', $slug );
define( 'DO_COMMUNITYTYPE_CT', $slugtype );
define( 'DO_COMMUNITYTOPICS_CT', $slugtopics );
define( 'DO_COMMUNITYAUDIENCE_CT', $slugaudiences );
define( 'DO_COMMUNITYBESTUURSLAAG_CT', $slugoverheidslaag );

defined( 'DO_COMMUNITY_OVERVIEW_TEMPLATE' ) or define( 'DO_COMMUNITY_OVERVIEW_TEMPLATE', 'template-overview-communities.php' );
defined( 'DO_COMMUNITY_PAGE_RSS_AGENDA' ) or define( 'DO_COMMUNITY_PAGE_RSS_AGENDA', 'template-rss-agenda.php' );
defined( 'DO_COMMUNITY_DETAIL_TEMPLATE' ) or define( 'DO_COMMUNITY_DETAIL_TEMPLATE', 'template-community-detail.php' );

//if ( ! defined( 'RHSWP_WIDGET_AREA_COMMUNITY_OVERVIEW' ) ) {
//	define( 'RHSWP_WIDGET_AREA_COMMUNITY_OVERVIEW', 'sidebar-community-overview' );
//}

define( 'DO_COMMUNITYTYPE_CT_VAR', 'communitytype' );
define( 'DO_COMMUNITYTOPICS_CT_VAR', 'communitytopic' );
define( 'DO_COMMUNITYAUDIENCE_CT_VAR', 'communityaudience' );
define( 'DO_COMMUNITYBESTUURSLAAG_CT_VAR', 'communitygov' );


//========================================================================================================
add_action( 'plugins_loaded', array( 'DO_COMMUNITY_CPT', 'init' ), 10 );

//========================================================================================================

require_once plugin_dir_path( __FILE__ ) . 'includes/widget-filter.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/widget-last-added.php';

//========================================================================================================

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.0.1
 */


if ( ! class_exists( 'DO_COMMUNITY_CPT' ) ) :

	class DO_COMMUNITY_CPT {

		protected $template_overview_communities;

		/** ----------------------------------------------------------------------------------------------------
		 * Init
		 */
		public static function init() {

			$newtaxonomy = new self();

		}

		/** ----------------------------------------------------------------------------------------------------
		 * Constructor
		 */
		public function __construct() {

			$this->template_overview_communities = 'template_overview_communities.php';
			$this->template_page_agenda          = 'template-rss-agenda.php';
			$this->template_page_posts           = 'template-rss-posts.php';

			$this->fn_ictu_community_setup_actions();

		}

		/** ----------------------------------------------------------------------------------------------------
		 * Hook this plugins functions into WordPress.
		 * Use priority = 20, to ensure that the taxonomy is registered for post types from other plugins,
		 * such as the podcasts plugin (seriously-simple-podcasting)
		 */
		private function fn_ictu_community_setup_actions() {

			add_action( 'init', array( $this, 'fn_ictu_community_register_posttypes' ), 20 );

			// add page templates
//			add_filter( 'template_include', array( $this, 'fn_ictu_community_append_template_locations' ) );

			// filter the breadcrumbs
//			add_filter( 'wpseo_breadcrumb_links', array( $this, 'fn_ictu_community_yoast_filter_breadcrumb' ) );

			// add the page template to the templates list
			add_filter( 'theme_page_templates', array( $this, 'fn_ictu_community_add_page_template' ) );

			// provide the file location to the template
			add_filter( 'template_include', array( $this, 'ictuwp_community_determine_template' ) );


			// Register widgets
			add_action( 'widgets_init', 'ictuwp_communityfilter_load_widgets' );
			add_action( 'widgets_init', 'ictuwp_load_widget_last_added_communities' );

			// sort alphabetically and list all communities for a single taxonomy term
			add_action( 'pre_get_posts', array( $this, 'fn_ictu_community_modify_main_query' ), 999 );

			// add the page template to the templates list
			add_filter( 'acf/include_fields', array( $this, 'fn_ictu_community_acf_fields' ) );

		}

		/** ----------------------------------------------------------------------------------------------------
		 * Do actually register the post types we need
		 *
		 * @return void
		 */
		public function fn_ictu_community_register_posttypes() {

			require_once plugin_dir_path( __FILE__ ) . 'includes/register-community-posttype.php';

		}

		/** ----------------------------------------------------------------------------------------------------
		 * Do actually register the post types we need
		 *
		 * @return void
		 */
		public function fn_ictu_community_acf_fields() {

			require_once plugin_dir_path( __FILE__ ) . 'includes/acf-fields.php';

		}


		/** ----------------------------------------------------------------------------------------------------
		 * Do actually register the post types we need
		 *
		 * @return void
		 */
		public function fn_ictu_community_add_page_template( $post_templates ) {

			$post_templates[ $this->template_overview_communities ] = _x( "[community] overzicht", "naam template", "wp-rijkshuisstijl" );
			$post_templates[ $this->template_page_agenda ]          = _x( "[community] toon agenda", "naam template", "wp-rijkshuisstijl" );
			$post_templates[ $this->template_page_posts ]           = _x( "[community] toon berichten", "naam template", "wp-rijkshuisstijl" );

			return $post_templates;

		}

		//========================================================================================================
		/*
		 * Deze function wijzigt de main query voor archives van community's en bijbehorden taxonomieen.
		 * Door deze wijziging wordt op 1 pagina een overzicht getoond van ALLE community's bij een bepaalde
		 * taxonomie, en de lijst wordt alfabetisch gesorteerd op titel
		 */
		public function fn_ictu_community_modify_main_query( $query ) {

			global $query_vars;

			if ( ! is_admin() && $query->is_main_query() ) {

				if ( is_post_type_archive( DO_COMMUNITY_CPT ) ||
				     ( is_tax( DO_COMMUNITYTYPE_CT ) ) ||
				     ( is_tax( DO_COMMUNITYTOPICS_CT ) ) ||
				     ( is_tax( DO_COMMUNITYAUDIENCE_CT ) ) ) {
					// geen pagination voor overzichten van:
					// - community types
					// - community onderwerpen
					// - community doelgroepen
					$query->set( 'posts_per_page', - 1 );
					$query->set( 'orderby', 'title' );
					$query->set( 'order', 'ASC' );

					return $query;

				}

			}

			return $query;
		}


		/** ----------------------------------------------------------------------------------------------------
		 * Do actually register the post types we need
		 *
		 * @return void
		 */
		public function ictuwp_community_determine_template( $archive_template ) {

			global $post;

			if ( is_search() ) {
				// do not overwrite search template
				return $archive_template;
			}

			$page_template = get_post_meta( get_the_id(), '_wp_page_template', true );

			if ( is_singular( DO_COMMUNITY_CPT ) ) {
//				// het is een single voor CPT = DO_COMMUNITY_CPT
//				$archive_template = dirname( __FILE__ ) . '/templates/single-initiatief.php';
			} elseif ( is_tax( DO_COMMUNITYTYPE_CT ) || is_tax( DO_COMMUNITYTOPICS_CT ) || is_tax( DO_COMMUNITYAUDIENCE_CT ) ) {

				$archive_template = dirname( __FILE__ ) . '/templates/archive-communities.php';

			} elseif ( 'template-rss-agenda.php' == $page_template ) {

				$archive_template = dirname( __FILE__ ) . '/templates/template-rss-agenda.php';

			} elseif ( 'template-rss-posts.php' == $page_template ) {

				$archive_template = dirname( __FILE__ ) . '/templates/template-rss-posts.php';

			} elseif ( 'template_overview_communities.php' == $page_template ) {

				// het is een overzicht van community's
				$archive_template = dirname( __FILE__ ) . '/templates/template-overview-communities.php';

			}

			return $archive_template;

		}


		/**
		 * Checks if the template is assigned to the page
		 *
		 * @in: $template (string)
		 *
		 * @return: $template (string)
		 *
		 */
		public function fn_ictu_community_append_template_locations( $template ) {

			// Get global post
			global $post;
			$file       = '';
			$pluginpath = plugin_dir_path( __FILE__ );


			if ( $post ) {
				// Do we have a post of whatever kind at hand?
				// Get template name; this will only work for pages, obviously
				$page_template = get_post_meta( $post->ID, '_wp_page_template', true );

				if ( ( DO_COMMUNITY_OVERVIEW_TEMPLATE === $page_template ) || ( DO_COMMUNITY_DETAIL_TEMPLATE === $page_template || DO_COMMUNITY_PAGE_RSS_AGENDA === $page_template ) ) {
					// these names are added by this plugin, so we return
					// the actual file path for this template
					$file = $pluginpath . $page_template;
				} else {
					// exit with the already set template
					return $template;
				}

			} else {
				// Not a post, not a term, return the template
				return $template;
			}

			// Just to be safe, check if the file actually exists
			if ( $file && file_exists( $file ) ) {
				return $file;
			} else {
				// o dear, who deleted the file?
				echo $file;
			}

			// If all else fails, return template
			return $template;
		}


		/**
		 * Filter the Yoast SEO breadcrumb
		 *
		 * @in: $links (array)
		 *
		 * @return: $links (array)
		 *
		 */
		public function fn_ictu_community_yoast_filter_breadcrumb( $links ) {

			// exceptions here

			return $links;

		}

		/**
		 * Retrieve a page that is the overview page. This
		 * page shows all available items.
		 *
		 * @in: $args (array)
		 *
		 * @return: $overview_page_id (integer)
		 *
		 */

		private function fn_ictu_community_get_thema_overview_page( $args = array() ) {

			$return = 0;

			// TODO: discuss if we need to make this page a site setting
			// there is no technical way to limit the number of pages with
			// template DO_COMMUNITY_OVERVIEW_TEMPLATE, so the page we find may not be
			// the exact desired page for in the breadcrumb.
			//
			// Try and find 1 Page
			// with the DO_COMMUNITY_OVERVIEW_TEMPLATE template...
			$page_template_query_args = array(
				'number'      => 1,
				'sort_column' => 'post_date',
				'sort_order'  => 'DESC',
				'meta_key'    => '_wp_page_template',
				'meta_value'  => DO_COMMUNITY_OVERVIEW_TEMPLATE
			);
			$overview_page            = get_pages( $page_template_query_args );

			if ( $overview_page && isset( $overview_page[0]->ID ) ) {
				$return = $overview_page[0]->ID;
			}

			return $return;

		}


	}

endif;


//========================================================================================================

/**
 * Load plugin textdomain.
 * only load translations if we can safely assume the taxonomy is active
 */
add_action( 'init', 'fn_ictu_community_load_plugin_textdomain' );

function fn_ictu_community_load_plugin_textdomain() {

	load_plugin_textdomain( 'wp-rijkshuisstijl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

}

//========================================================================================================

/**
 * Returns array of allowed page templates
 *
 * @return array with extra templates
 */
function fn_ictu_community_add_templates() {

	$return_array = array(
		DO_COMMUNITY_OVERVIEW_TEMPLATE => _x( '[Community] alle community\'s', 'label page template', 'wp-rijkshuisstijl' ),
		DO_COMMUNITY_DETAIL_TEMPLATE   => _x( '[Community] community-detail', 'label page template', 'wp-rijkshuisstijl' ),
		DO_COMMUNITY_PAGE_RSS_AGENDA   => _x( '[Community] pagina agenda', 'label page template', 'wp-rijkshuisstijl' )

	);

	return $return_array;

}

//========================================================================================================


/**
 * Voeg een paginaselector toe aan de customizer
 * zie [admin] > Weergave > Customizer > Community's
 */
function community_append_customizer_field( $wp_customize ) {

	//	eigen sectie voor Theme Customizer
	$wp_customize->add_section( 'customizer_communities', array(
		'title'       => _x( 'Community\'s', 'customizer menu', 'wp-rijkshuisstijl' ),
		'capability'  => 'edit_theme_options',
		'description' => _x( 'Instellingen voor community\'s.', 'customizer menu', 'wp-rijkshuisstijl' ),
	) );

	// add dropdown with pages to appoint the new slug for the CPT
	$wp_customize->add_setting( 'customizer_community_pageid_overview', array(
		'capability'        => 'edit_theme_options',
		'sanitize_callback' => 'community_sanitize_initiatief_pagina',
	) );
	$wp_customize->add_control( 'customizer_community_pageid_overview', array(
		'type'        => 'dropdown-pages',
		'section'     => 'customizer_communities', // Add a default or your own section
		'label'       => _x( 'Pagina met alle community\'s', 'customizer menu', 'wp-rijkshuisstijl' ),
		'description' => _x( 'In het kruimelpad en in de URL voor een initiatief zal deze pagina terugkomen.', 'customizer menu', 'wp-rijkshuisstijl' ),
	) );

}

add_action( 'customize_register', 'community_append_customizer_field' );


//========================================================================================================

// zorg dat een geldige pagina wordt teruggegeven
function community_sanitize_initiatief_pagina( $page_id, $setting ) {

	$value = $setting->default;

	// Alleen een geldige ID accepteren
	$page_id = absint( $page_id );

	if ( $page_id ) {

		if ( 'publish' != get_post_status( $page_id ) ) {
			// alleen geubliceerde pagina's accepteren
			return $value;
		} else {
// TODO zorg ervoor dat de slug van de pagina gebruikt wordt in de slug van de commmunity CPT
//			$page_initatieven = get_theme_mod( 'customizer_community_pageid_overview' );
//			if ( $page_id === $page_initatieven ) {
//				// no change
//			} else {
//				$theline = 'Community page is changed';
//				error_log( $theline );
//				flush_rewrite_rules();
//
//			}

		}

		$value = $page_id;

	}

	return $value;

}

//========================================================================================================

function communitys_filter_breadcrumb( $crumb = '', $args = '' ) {

	global $post;

	if ( ! (
		is_singular( DO_COMMUNITY_CPT ) ||
		is_tax( DO_COMMUNITYTYPE_CT ) ||
		is_tax( DO_COMMUNITYTOPICS_CT ) ||
		is_tax( DO_COMMUNITYAUDIENCE_CT ) ) ) {
		// hier niks doen, omdat we niet met een initiatief bezig zijn
		return $crumb;
	} else {
		// uit siteopties de pagina ophalen die het overzicht is van alle links
		$page_initatieven = get_theme_mod( 'customizer_community_pageid_overview' );
	}

	$currentitem = explode( '</span>', $crumb );
	$termid      = null;
	$parents     = array();
	$return      = '';

	if ( ! $page_initatieven ) {
		return $crumb;
	} else {

		// haal de ancestors op voor deze pagina
		$ancestors = get_post_ancestors( $page_initatieven );
		if ( is_post_type_archive( DO_COMMUNITY_CPT ) ) {
			$parents[] = array(
				'text' => get_the_title( $page_initatieven ),
			);
		} else {
			$parents[] = array(
				'url'  => get_page_link( $page_initatieven ),
				'text' => get_the_title( $page_initatieven ),
			);
		}

		if ( $ancestors ) {

			// haal de hele keten aan ancestors op en zet ze in de returnstring
			foreach ( $ancestors as $ancestorid ) {
				// Prepend one or more elements to the beginning of an array
				array_unshift( $parents, [
					'url'  => get_page_link( $ancestorid ),
					'text' => get_the_title( $ancestorid ),
				] );
			}
		}
	}

	foreach ( $parents as $link ) {
		if ( isset( $link['url'] ) && isset( $link['text'] ) ) {
			$return .= '<a href="' . $link['url'] . '" class="parent-link">' . $link['text'] . '</a> ';
		} else {
			$return .= $link['text'] . '  ';
		}
	}

	if ( isset( $post->ID ) && $post->ID === $page_initatieven ) {
		//
	} elseif ( is_post_type_archive( DO_COMMUNITY_CPT ) ) {
		//
	} else {
		$queried_object = get_queried_object();
		$termid         = $queried_object->term_id;

		if ( $termid ) {
			$term   = get_term( $termid );
			$return .= $term->name;
		} elseif ( is_singular( CPT_PROJECT ) || is_singular( DO_COMMUNITY_CPT ) ) {
			$return .= get_the_title( $post->ID );
		} else {
			//
		}
	}

	return $return;

}

//========================================================================================================
/**
 * Show lists with this posts's links to taxonomies DO_COMMUNITYTYPE_CT, DO_COMMUNITYTOPICS_CT, DO_COMMUNITYAUDIENCE_CT
 *
 * @param $doreturn
 *
 *
 * @return string|void
 */

function rhswp_community_single_terms( $doreturn = false, $post_id = 0, $show_dossiers = true, $clickable_links = true ) {

	global $post;

	$return = '';
	$values = '';
	if ( ! $post_id ) {
		if ( get_the_id() ) {
			$post_id = get_the_id();
		} else {
			return;
		}
	}
	$community_topics         = get_the_terms( $post_id, DO_COMMUNITYTOPICS_CT );
	$community_types          = get_the_terms( $post_id, DO_COMMUNITYTYPE_CT );
	$community_audiences      = get_the_terms( $post_id, DO_COMMUNITYAUDIENCE_CT );
	$community_overheidslagen = get_the_terms( $post_id, DO_COMMUNITYBESTUURSLAAG_CT );
	$community_tags           = get_the_terms( $post_id, 'post_tag' );

	// toon aan welk onderwerp deze community is gekoppeld
	if ( $community_topics && ! is_wp_error( $community_topics ) ) :
		$labels = '<dd>';

		foreach ( $community_topics as $term ) {
			if ( $clickable_links ) {
				$labels .= '<a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a>';
			} else {
				$labels .= $term->name;
			}
			if ( next( $community_topics ) ) {
				$labels .= ', ';
			}
		}
		$labels .= '</dd>';

		if ( $labels ) {
			$values .= '<dt>' . _n( 'Thema', 'Thema\'s', count( $community_topics ), 'wp-rijkshuisstijl' ) . ': </dt>';
			$values .= $labels;
		}

	endif;

	// toon aan welk type deze community is gekoppeld
	if ( $community_types && ! is_wp_error( $community_types ) ) :
		$labels = '<dd>';

		foreach ( $community_types as $term ) {
			if ( $clickable_links ) {
				$labels .= '<a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a>';
			} else {
				$labels .= $term->name;
			}
			if ( next( $community_types ) ) {
				$labels .= ', ';
			}
		}

		$labels .= '</dd>';

		if ( $labels ) {
			$values .= '<dt>' . _n( 'Type community', 'Types community', count( $community_types ), 'wp-rijkshuisstijl' ) . ': </dt>';
			$values .= $labels;
		}

	endif;

	// toon aan welk doelgroep deze community is gekoppeld
	if ( $community_audiences && ! is_wp_error( $community_audiences ) ) :
		$labels = '<dd>';

		foreach ( $community_audiences as $term ) {
			if ( $clickable_links ) {
				$labels .= '<a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a>';
			} else {
				$labels .= $term->name;
			}
			if ( next( $community_audiences ) ) {
				$labels .= ', ';
			}
		}

		$labels .= '</dd>';

		if ( $labels ) {
			$values .= '<dt>' . _n( 'Doelgroep', 'Doelgroepen', count( $community_audiences ), 'wp-rijkshuisstijl' ) . ': </dt>';
			$values .= $labels;
		}

	endif;

	// toon aan welk doelgroep deze community is gekoppeld
	if ( $community_overheidslagen && ! is_wp_error( $community_overheidslagen ) ) :
		$labels = '<dd>';

		foreach ( $community_overheidslagen as $term ) {
			if ( $clickable_links ) {
				$labels .= '<a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a>';
			} else {
				$labels .= $term->name;
			}
			if ( next( $community_overheidslagen ) ) {
				$labels .= ', ';
			}
		}

		$labels .= '</dd>';

		if ( $labels ) {
			$values .= '<dt>' . _n( 'Overheidslaag', 'Overheidslagen', count( $community_overheidslagen ), 'wp-rijkshuisstijl' ) . ': </dt>';
			$values .= $labels;
		}

	endif;


	if ( $show_dossiers ) {
		if ( has_term( '', RHSWP_CT_DOSSIER, $post->ID ) ) {
			// get dossier terms and their links

			$terms = get_the_terms( $post->ID, RHSWP_CT_DOSSIER );
			if ( $terms && ! is_wp_error( $terms ) ) {

				$labels = '<dd>';

				foreach ( $terms as $term ) {

					if ( $clickable_links ) {
						$labels .= '<a href="';
						if ( function_exists( 'rhswp_get_pagelink_for_dossier' ) ) {
							$labels .= rhswp_get_pagelink_for_dossier( $term );
						} else {
							$labels .= get_term_link( $term->term_id, RHSWP_CT_DOSSIER );
						}
						$labels .= '">' . $term->name . '</a>';
					} else {
						$labels .= $term->name;
					}


					if ( next( $terms ) ) {
						$labels .= ', ';
					}
				}

				$labels .= '</dd>';

				if ( $labels ) {
					$values .= '<dt>' . _n( 'Onderwerp', 'Onderwerpen', count( $terms ), 'wp-rijkshuisstijl' ) . ': </dt>';
					$values .= $labels;
				}
			}
		}
	}

	if ( $values ) {
		$return = '<dl class="community">';
		$return .= $values;
		$return .= '</dl>';
	}

	if ( $doreturn ) {
		return $return;
	} else {
		echo $return;
	}
}

//========================================================================================================

function rhswp_community_get_terms_list( $args ) {
	global $post;
	$return   = '';
	$defaults = array(
		'ID'           => 0,
		'title'        => '',
		'type'         => 'div', // 'div' or 'details'
		'container_id' => 0,
		'is_open'      => 0,
		'cssclass'     => 0,
		'description'  => '',
		'before_title' => '<h2>',
		'after_title'  => '</h2>',
		'echo'         => false
	);
	$args     = wp_parse_args( $args, $defaults );

	if ( is_singular( DO_COMMUNITY_CPT ) ) {
		$make_checkboxes = 1;
	} else {
		$make_checkboxes = 0;
	}

	$args2 = array(
		'echo'            => false,
		'exclude'         => $args['ID'],
		'hide_empty'      => false,
		'make_checkboxes' => $make_checkboxes,
	);

	$args2['taxonomy']        = DO_COMMUNITYTYPE_CT;
	$args2['title']           = _n( 'Type community', 'Types community', 2, 'wp-rijkshuisstijl' );
	$community_types          = ictuwp_communityfilter_list( $args2 );
	$args2['taxonomy']        = DO_COMMUNITYTOPICS_CT;
	$args2['title']           = _n( 'Onderwerp community', 'Onderwerpen community', 2, 'wp-rijkshuisstijl' );
	$community_topics         = ictuwp_communityfilter_list( $args2 );
	$args2['taxonomy']        = DO_COMMUNITYAUDIENCE_CT;
	$args2['title']           = _n( 'Doelgroep', 'Doelgroepen', 2, 'wp-rijkshuisstijl' );
	$community_audiences      = ictuwp_communityfilter_list( $args2 );
	$args2['taxonomy']        = DO_COMMUNITYBESTUURSLAAG_CT;
	$args2['title']           = _n( 'Bestuurslaag', 'Overheidslagen', 2, 'wp-rijkshuisstijl' );
	$community_overheidslagen = ictuwp_communityfilter_list( $args2 );

	if ( $community_types || $community_topics || $community_audiences || $community_overheidslagen ) {
		$return .= '<div class="fieldsets">';
		$return .= $community_topics;
		$return .= $community_types;
		$return .= $community_audiences;
		$return .= $community_overheidslagen;
		$return .= '</div>';
	} else {
		$return .= '<p>' . _x( 'We konden geen lijst met filters maken.', 'warning', 'wp-rijkshuisstijl' ) . '</p>';
	}

	return $return;

}

//========================================================================================================

function rhswp_community_get_filter_form( $args ) {

	$defaults            = array(
		'ID'           => 0,
		'title'        => '',
		'type'         => 'div', // 'div' or 'details'
		'container_id' => 'widget_community_filter',
		'is_open'      => 0,
		'cssclass'     => 0,
		'description'  => '',
		'input_label'  => _x( 'Zoek een community', 'label keyword veld', 'wp-rijkshuisstijl' ),
		'button_label' => _x( 'Zoeken', 'label input', 'wp-rijkshuisstijl' ),
		'placeholder'  => _x( '[zoekterm voor community]', 'placeholder input', 'wp-rijkshuisstijl' ),
		'before_title' => '<legend>',
		'after_title'  => '</legend>',
		'echo'         => false
	);
	$args                = wp_parse_args( $args, $defaults );
	$title               = '';
	$description         = '';
	$thepage             = get_theme_mod( 'customizer_community_pageid_overview' );
	$return              = '';
	$attr_id             = '';
	$attr_classes        = '';
	$container_tag_start = '';
	$container_tag_end   = '';
	$title               = '';
	$description         = '';

	if ( $args['container_id'] ) {
		$attr_id = $args['container_id'];
	}
	if ( $args['cssclass'] ) {
		$attr_classes = ' class="' . $args['cssclass'] . '"';
	}

	if ( isset( $_GET['community_search_string'] ) ) {
		$community_search_string = sanitize_text_field( $_GET['community_search_string'] );
	} else {
		$community_search_string = '';
	}

	if ( $args['title'] ) {
		$title               .= $args['before_title'] . $args['title'] . $args['after_title'];
		$container_tag_start = '<fieldset>';
		$container_tag_end   = '</fieldset>';
	}

	if ( $args['description'] ) {
		$description .= '<p>' . $args['description'] . '</p>';
	}

	$return .= '<form id="' . $attr_id . '"' . $attr_classes . ' action="' . get_permalink( $thepage ) . '" method="get">';
	$return .= $container_tag_start;
	$return .= $title;
	$return .= $description;
	$return .= '<div class="submit-buttons">';
	$return .= '<label for="community_search_string">' . $args['input_label'] . '</label>';
	$return .= '<input type="search" id="community_search_string" name="community_search_string" value="' . $community_search_string . '" placeholder="' . $args['placeholder'] . '">';
	$return .= '<button type="submit" id="widget_community_filter-submit">' . $args['button_label'] . '</button>';
	$return .= '</div>';
	$return .= '</form>';
	$return .= $container_tag_end;

	if ( $return ) {
		return $return;
	} else {
		return false;
	}

}

//========================================================================================================

function ictuwp_community_get_latest_list( $argslist ) {


	$default_args = array(
		'max_items'     => 10,
		'max_age'       => 180,
		'overview_link' => '',
		'css_class_ul'  => 'communities communities-latest',
		'date_format'   => 'j F'
	);

	$return     = '';
	$args       = wp_parse_args( $argslist, $default_args );
	$date_after = date( 'Y-m-d', strtotime( ' - ' . $args['max_age'] . ' days' ) );

	$argscount = array(
		'post_type'      => DO_COMMUNITY_CPT,
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'date_query'     => array( 'after' => $date_after ),
		'posts_per_page' => $args['max_items'],
	);

	// Assign predefined $args to your query
	$community_list = new WP_query();
	$community_list->query( $argscount );

	if ( $community_list->have_posts() ) {

//			$return .= '<p>alles na ' . date( $date_format, strtotime( $date_after ) ) . '</p>';
		if ( $args['css_class_ul'] ) {
			$return .= '<ul class="' . $args['css_class_ul'] . '">';
		} else {
			$return .= '<ul>';
		}

		while ( $community_list->have_posts() ) : $community_list->the_post();
			$the_id = get_the_id();
//			$post_date = get_the_date( $args['date_format'], $the_id );
			$return .= '<li><a href="' . get_permalink( $the_id ) . '">' . get_the_title( $the_id ) . '</a></li>';
		endwhile;
		$return        .= '</ul>';
		$overview_link = $args['overview_link'];
		if ( isset( $overview_link['url'] ) && isset( $overview_link['title'] ) ) {
			$return .= '<p class="more"><a href="' . $overview_link['url'] . '">' . $overview_link['title'] . '</a></p>';
		}
	}

	return $return;
}

//========================================================================================================

function ictuwp_communityfilter_list( $args_in = array() ) {

	$return = '';

	$defaults = array(
		'taxonomy'        => 'category',
		'title'           => '',
		'echo'            => false,
		'exclude'         => false,
		'hide_empty'      => true,
		'make_checkboxes' => true,
		'header_tag'      => 'h3',
		'show_counter'    => false,
		'title_li'        => '',
		'css_class_ul'    => '',
	);
	$args     = wp_parse_args( $args_in, $defaults );


	if ( taxonomy_exists( $args['taxonomy'] ) ) {

		$args_terms = array(
			'taxonomy'           => $args['taxonomy'],
			'orderby'            => 'name',
			'order'              => 'ASC',
			'hide_empty'         => ( $args['hide_empty'] ? true : false ),
			'ignore_custom_sort' => true,
			'echo'               => 0,
			'hierarchical'       => true,
		);

		if ( $args['exclude'] ) {
			// do not include this term in the list
			$args_terms['exclude']    = $args['exclude'];
			$args_terms['hide_empty'] = true;
		}

		if ( isset( $args['show_counter'] ) ) {
			$args_terms['count'] = true;
		}

		$terms = get_terms( $args_terms );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {

			if ( $args['make_checkboxes'] ) {

				$return .= '<fieldset class="taxonomy ' . $args['taxonomy'] . '">';
				if ( $args['title'] ) {
					$return .= '<legend>' . $args['title'] . '</legend>';
				}

				foreach ( $terms as $term ) {
					$id        = $term->slug . '_' . $term->term_id;
					$checked   = '';
					$dossierid = isset( $_GET[ $id ] ) ? (int) $_GET[ $id ] : 0; // een dossier

					if ( $dossierid === $term->term_id ) {
						$checked = ' checked';
					}
					$return .= '<label for="' . $id . '"><input id="' . $id . '" type="checkbox" name="' . $id . '" value="' . $term->term_id . '"' . $checked . '>' . $term->name . '</label>';
				}
				$return .= '</fieldset>';

			} else {

				$return .= '<div class="taxonomy ' . $args['taxonomy'] . '">';
				if ( $args['title'] ) {
					$return .= '<' . $args['header_tag'] . '>' . $args['title'] . '</' . $args['header_tag'] . '>';
				}

				if ( $args['css_class_ul'] ) {
					$return .= '<ul class="' . $args['css_class_ul'] . '">';
				} else {
					$return .= '<ul>';
				}
				foreach ( $terms as $term ) {
					$return .= '<li><a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a>';
					if ( $args['show_counter'] ) {
						if ( $term->count ) {
							$return .= ' (' . $term->count . ' ' . _n( 'community', "community's", $term->count, 'wp-rijkshuisstijl' ) . ')';
						} else {
							$return .= " (nog geen community's)";
						}
					}
					$return .= '</li>';
				}
				$return .= '</ul>';
				$return .= '</div>';
			}

		}
	}

	if ( $args['echo'] ) {
		echo $return;
	} else {
		return $return;
	}

}

//========================================================================================================

/*
 * Deze functie hoest een titel op boven de lijst met initiatieven.
 * Deze titel komt voor op een overzicht van ALLE initiatieven of een lijst
 * met initiatieven per provincie.
 * Je ziet ook het aantal initiatieven.
 */
function ictuwp_community_archive_title( $doreturn = false ) {

	global $wp_query;
	global $post;
	global $wp_taxonomies;

	$archive_title       = _x( "Overzicht community's", "naam template", "wp-rijkshuisstijl" );
	$archive_description = '';
	$return              = '';

	$term_id = get_queried_object_id();
	$term    = get_term( $term_id );

	if ( $term && ! is_wp_error( $term ) ) {


		$archive_title = $wp_taxonomies[ $term->taxonomy ]->labels->singular_name . ': ' . $term->name;
		if ( $term->description ) {
			$archive_description = $term->description;
		}
	}

	$return = '<h1>' . $archive_title . '</h1>';


	if ( $archive_description ) {
		$return .= '<p>' . $archive_description . '</p>';
	}

	if ( $doreturn ) {
		return $return;
	} else {
		echo $return;
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

/**
 * Returns the ID of RSS feeds that have a specified type (ACF field: community_rssfeed_type)
 *
 * @param $type_feed
 *
 * @return array with IDs, or false
 */
function community_get_feed_ids_for_feed_type( $type_feed = 'events' ) {

	$type = ( $type_feed === 'events' ) ? 'event' : 'posts'; // should be either ( "event": "Agenda" OR 	"posts": "Berichten" )

	if ( $type_feed ) {

		$args_feeds = array(
			'post_type'      => 'wprss_feed',
			'post_status'    => 'publish',
			'meta_key'       => 'community_rssfeed_type',
			'meta_value'     => $type,
			'fields'         => 'ids',
			'posts_per_page' => - 1,
		);

		$result_query = new WP_Query( $args_feeds );
		$feeds        = $result_query->posts;
		wp_reset_postdata();

		if ( $feeds ) {
			return $feeds;
		}
	}

	return false;

}

//========================================================================================================

/**
 * Returns a collection of RSS items for a selection (feed type; number of items etc).
 *
 * @param $args
 *
 * @return false|WP_Query
 */
function community_select_list( $args = array() ) {
	$defaults = array(
		'terms_list' => array(),
		'name'       => '',
		'id'         => '',
		'default'    => '',
		'label'      => 'label',
		'type'       => 'select',
		'echo'       => false,
	);

	// set up arguments
	$args   = wp_parse_args( $args, $defaults );
	$return = '';

	if ( count( $args['terms_list'] ) && $args['id'] && $args['name'] ) {

		$selectid = $args['type'] . '_' . $args['id'];

		$return .= '<label for="' . $selectid . '">' . $args['label'] . '</label>';
		$return .= '<select id="' . $selectid . '" name="' . $args['name'] . '">';

		if ( ! $args['default'] ) {
			$return .= '<option value="">' . _x( '-selecteer-', 'asd', 'dad' ) . '</option> ';

		}

		foreach ( $args['terms_list'] as $term ) {
			$selected = '';
			if ( strval( $term->term_id ) === strval( $args['default'] ) ) {
				$selected = ' selected ';
			}
//				echo '<li><a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a> ';

			$return .= '<option value="' . $term->term_id . '"' . $selected . '>' . $term->name . '</option> ';

			/*
			 *
							$args2          = array(
								'post_type' => DO_COMMUNITY_CPT,
								'tax_query' => array(
									array(
										'taxonomy' => DO_COMMUNITYTYPE_CT,
										'field'    => 'slug',
										'terms'    => $term->slug
									)
								)
							);
							$community_list = get_posts( $args2 );

							if ( $community_list && ! is_wp_error( $community_list ) ) {
								echo '<br> communities: ';
								foreach ( $community_list as $key => $post1 ) {
									echo '<a href="' . get_permalink( $post1->ID ) . '">' . get_the_title( $post1->ID ) . '</a> ';
								}
								echo ' ';
							}

			 */

		}
		$return .= '</select>';
	}

	if ( $args['echo'] ) {
		echo $return;
	} else {
		return $return;
	}

}

//========================================================================================================

add_filter( 'query_vars', 'community_add_query_vars' );

function community_add_query_vars( $query_vars ) {

	$query_vars[] = DO_COMMUNITYTYPE_CT_VAR;
	$query_vars[] = DO_COMMUNITYTOPICS_CT_VAR;
	$query_vars[] = DO_COMMUNITYAUDIENCE_CT_VAR;
	$query_vars[] = DO_COMMUNITYAUDIENCE_CT_VAR;

	return $query_vars;
}

//========================================================================================================

/**
 * Returns a collection of RSS items for a selection (feed type; number of items etc).
 *
 * @param $args
 *
 * @return false|WP_Query
 */
function community_feed_sources_get( $args = array() ) {

	global $wpdb;

	$return   = '';
	$defaults = array(
		'form_name'      => '',
		'title_tag'      => 'h2',
		'form_id'        => 'form_id',
		'button_label'   => _x( 'Filter berichten', 'button label default', 'wp-rijkshuisstijl' ),
		'method'         => 'get',
		'action'         => $_SERVER['REQUEST_URI'],
		'event_type'     => 'posts',
		'post_types'     => 'wprss_feed_item',
		'paging'         => false,
		'source'         => null,
		'sort_order'     => 'ASC',
		'posts_per_page' => - 1,
		'echo'           => false,
		'debug'          => false,
	);

	// set up arguments
	$args = wp_parse_args( $args, $defaults );
	$type = ( $args['event_type'] === 'events' ) ? 'rss_feed_source_events' : 'rss_feed_source_posts'; // should be either ( "event": "Agenda" OR 	"posts": "Berichten" )

	if ( $type ) {

		wp_reset_postdata();

		/*
		 * Query explanation:
		 *
		 * --- feed item ---> feed source ---> community ---> taxonomy terms ---> filter
		 *
		 * A single Communities CPT may have value(s) for ACF field 'rss_feed_source_events'
		 * and / or 'rss_feed_source_posts'. These ACF fields point to an RSS feed source (post type: 'wprss_feed_id')
		 * that may have RSS items (post type: 'wprss_feed_item') available.
		 * If RSS items exist in {$wpdb->prefix}posts, they are linked to a feed source. So if we have feed
		 * sources in {$wpdb->prefix}postmeta, we have a feed with actual posts. For a feed with posts we get the
		 * attached Community CPT, via either 'rss_feed_source_events' or 'rss_feed_source_posts'. For these
		 * Community CPTs we retrieve the relevant custom taxonomy terms. These terms are offered as a filter.
		 * 
		 */
		$community_post_ids = $wpdb->get_results( "SELECT DISTINCT post_id FROM {$wpdb->prefix}postmeta where meta_key = '" . $type . "' and  meta_value in (SELECT DISTINCT meta_value as feedID FROM {$wpdb->prefix}postmeta WHERE meta_key = 'wprss_feed_id')" );

		if ( is_array( $community_post_ids ) ) {
			$valid_feeds       = array();
			$last_community_id = end( $community_post_ids );
			if ( $args['debug'] ) {
				$return .= '<p>Dit zijn de ' . ( ( $args['event_type'] === 'events' ) ? 'agenda-items' : 'berichten' ) . ' die horen bij ';
			}

			foreach ( $community_post_ids as $key => $value ) {
				$valid_feeds[] = $value->post_id;

				if ( $args['debug'] ) {
					$return .= '<a href="' . get_permalink( $value->post_id ) . '">' . get_the_title( $value->post_id ) . '</a>';
					if ( $value->post_id === $last_community_id->post_id ) {
						$return .= '.</p>';
					} else {
						$return .= ', ';
					}
				}
			}
		}

		if ( $valid_feeds ) {

			$args_select = array(
				'type' => 'select',
				'echo' => false
			);

			$return .= '<form id="' . $args['form_id'] . '" method="' . $args['method'] . '" action="' . $args['action'] . '">';
			if ( $args['form_name'] ) {
				$return .= '<' . $args['title_tag'] . '>' . $args['form_name'] . '</' . $args['title_tag'] . '>';
			}
			$community_types = get_terms(
				array(
					'taxonomy'   => DO_COMMUNITYTYPE_CT,
					'object_ids' => $valid_feeds,
				)
			);

			if ( $community_types ) {

				$default = get_query_var( DO_COMMUNITYTYPE_CT_VAR );
				if ( $default ) {
					$args_select['default'] = $default;
				}
				$args_select['name']       = DO_COMMUNITYTYPE_CT_VAR;
				$args_select['id']         = DO_COMMUNITYTYPE_CT_VAR . '_id';
				$args_select['terms_list'] = $community_types;
				$args_select['label']      = DO_COMMUNITYTYPE_CT;
				$return                    .= community_select_list( $args_select );
			}

			$community_topics = get_terms(
				array(
					'taxonomy'   => DO_COMMUNITYTOPICS_CT,
					'object_ids' => $valid_feeds,
				)
			);

			if ( $community_topics ) {

				$default = get_query_var( DO_COMMUNITYTOPICS_CT_VAR );
				if ( $default ) {
					$args_select['default'] = $default;
				}
				$args_select['name']       = DO_COMMUNITYTOPICS_CT_VAR;
				$args_select['id']         = DO_COMMUNITYTOPICS_CT_VAR . '_id';
				$args_select['terms_list'] = $community_topics;
				$args_select['label']      = DO_COMMUNITYTOPICS_CT;
				$return                    .= community_select_list( $args_select );
			}


			$community_audiences = get_terms(
				array(
					'taxonomy'   => DO_COMMUNITYAUDIENCE_CT,
					'object_ids' => $valid_feeds,
				)
			);

			if ( $community_audiences ) {

				$default = get_query_var( DO_COMMUNITYAUDIENCE_CT_VAR );
				if ( $default ) {
					$args_select['default'] = $default;
				}
				$args_select['name']       = DO_COMMUNITYAUDIENCE_CT_VAR;
				$args_select['id']         = DO_COMMUNITYAUDIENCE_CT_VAR;
				$args_select['terms_list'] = $community_audiences;
				$args_select['label']      = DO_COMMUNITYAUDIENCE_CT;
				$return                    .= community_select_list( $args_select );
			}

			$community_strata = get_terms(
				array(
					'taxonomy'   => DO_COMMUNITYBESTUURSLAAG_CT,
					'object_ids' => $valid_feeds,
				)
			);

			if ( $community_strata ) {

				$default = get_query_var( DO_COMMUNITYBESTUURSLAAG_CT_VAR );
				if ( $default ) {
					$args_select['default'] = $default;
				}
				$args_select['name']       = DO_COMMUNITYBESTUURSLAAG_CT_VAR;
				$args_select['id']         = DO_COMMUNITYBESTUURSLAAG_CT_VAR;
				$args_select['terms_list'] = $community_strata;
				$args_select['label']      = DO_COMMUNITYBESTUURSLAAG_CT;
				$return                    .= community_select_list( $args_select );
			}

			$return .= '<button type="submit" id="widget_community_filter-submit">' . $args['button_label'] . '</button>';
			$return .= '</form>';

		}

	}

	if ( $args['echo'] ) {
		echo $return;
	} else {
		return $return;
	}

}

//========================================================================================================

/**
 * Returns a collection of RSS items for a selection (feed type; number of items etc).
 *
 * @param $args
 *
 * @return false|WP_Query
 */
function community_feed_items_get( $args = array() ) {

	global $wp_query;

	$defaults = array(
		'event_type'     => 'events',
		'post_types'     => 'wprss_feed_item',
		'paging'         => false,
		'source'         => null,
		'sort_order'     => 'ASC',
		'posts_per_page' => - 1,
		'echo'           => false,
	);

	// set up arguments
	$args = wp_parse_args( $args, $defaults );

	// get the IDs for all feeds of whicht the type correspond to $args['event_type'] ('event' or 'posts')
	$event_type = ( $args['event_type'] === 'events' ) ? 'events' : 'posts';
	if ( $args['source'] ) {
		// feed ID is given
		$feeds = array( $args['source']->ID );
	} else {
		// retrieve correct feed IDs
		$feeds = community_get_feed_ids_for_feed_type( $event_type );
	}

	$post_type = $args['post_types'];

	// query the feed items
	$feed_items_args = array(
		'post_type'           => $post_type,
		'posts_per_page'      => $args['posts_per_page'],
		'orderby'             => 'meta_value',
		'meta_key'            => 'wprss_item_date',
		'order'               => $args['sort_order'],
		'suppress_filters'    => true,
		'ignore_sticky_posts' => true,
		'meta_query'          => array(
			'relation' => 'AND',
			array(
				'key'     => 'wprss_feed_id',
				'compare' => 'EXISTS',
			),
		),
	);

	// do we need paging?
	if ( $args['paging'] ) {
		if ( get_query_var( 'paged' ) ) {
			$paged = get_query_var( 'paged' );
		} elseif ( get_query_var( 'page' ) ) {
			$paged = get_query_var( 'page' );
		} else {
			$paged = 1;
		}

		$feed_items_args['paged'] = $paged;
	}


	// feeds found, append to selection
	if ( ! empty( $feeds ) ) {
		$feed_items_args['meta_query'] = array(
			array(
				'key'     => 'wprss_feed_id',
				'value'   => $feeds,
				'type'    => 'numeric',
				'compare' => 'IN',
			),
		);
	}

	$wp_query = new WP_Query( $feed_items_args );

	if ( $wp_query->have_posts() ) {
		return $wp_query;
	} else {
		return false;
	}
}

//========================================================================================================

if ( 222 === 333 ) {

	add_filter( 'wprss_single_feed_output', 'my_function' );
	/**
	 * Add 'Hello World' after each feed item.
	 */
	function my_function( $output ) {
		$output .= 'Hello World';

		return $output;
	}

	//========================================================================================================

	add_filter( 'wpra/feeds/templates/feed_item_collection', 'wpra_sort_feed_items_alpha' );

	function wpra_sort_feed_items_alpha( $collection ) {
		return $collection->filter( [
			'order_by' => 'title',
			'order'    => 'ASC',
		] );
	}

	//========================================================================================================

	function x_wprss_display_feed_items( $args = [] ) {
		$settings = get_option( 'wprss_settings_general' );
		$args     = wprss_get_shortcode_default_args( $args );

		$args = apply_filters( 'wprss_shortcode_args', $args );

		$query_args = $settings;
		if ( isset( $args['limit'] ) ) {
			$query_args['feed_limit'] = filter_var( $args['limit'], FILTER_VALIDATE_INT, [
				'options' => [
					'min_range' => 1,
					'default'   => $query_args['feed_limit'],
				],
			] );
		}

		if ( isset( $args['pagination'] ) ) {
			$query_args['pagination'] = $args['pagination'];
		}

		if ( isset( $args['source'] ) ) {
			$query_args['source'] = $args['source'];
		} elseif ( isset( $args['exclude'] ) ) {
			$query_args['exclude'] = $args['exclude'];
		}

		$query_args = apply_filters( 'wprss_process_shortcode_args', $query_args, $args );

		$feed_items = wprss_get_feed_items_query( $query_args );

		do_action( 'wprss_display_template', $args, $feed_items );
	}

}


function community_feed_items_show( $items = array() ) {

	$return = '';

	$defaults     = array(
		'ID'           => 0,
		'title'        => '',
		'type'         => 'events', // 'events' or 'posts'
		'description'  => '',
		'items'        => array(),
		'before_title' => '<h2>',
		'after_title'  => '</h2>',
		'extra_info'   => false,
		'show_date'    => false,
		'cssclass'     => '',
		'echo'         => false
	);
	$args         = wp_parse_args( $items, $defaults );
	$tag_subtitle = 'h3';

	if ( str_contains( strtolower( $args['before_title'] ), 'h3' ) ) {
		$tag_subtitle = 'h4';
	} elseif ( str_contains( strtolower( $args['before_title'] ), 'h4' ) ) {
		$tag_subtitle = 'h5';
	}

	//	Query to get all feed items for display
	$date_format_badge = 'j M';
	$date_format_year  = 'Y';
	$date_format_month = 'F';

	if ( $args['type'] === 'events' ) {
		// events
		$cssclass = 'agenda';
	} else {
		// posts
		$cssclass = 'posts links';
	}

	if ( $args['cssclass'] ) {
		$cssclass .= ' ' . $args['cssclass'];
	}

	if ( ! $args['items'] ) {
		return false;
	}
	$items = $args['items'];

	if ( $items->have_posts() ) {
		$current_date  =
		$month_previous = date_i18n( $date_format_month, time() );
		$year_previous = date_i18n( $date_format_year, time() );
		$postcounter   = 0;
		$cssclass_a    = '';

		if ( $args['title'] ) {
			$return .= $args['before_title'] . $args['title'] . $args['after_title'];
		}


		while ( $items->have_posts() ) : $items->the_post();

			$current_item_id = $items->post->ID;
			$container_start = '';
			$container_end   = '';
			$extra_info      = '';

			if ( $postcounter < 1 ) {
				$show_opening_tag = true;
				// $postcounter = 0;
				if ( $args['type'] === 'events' ) {

					$post_meta          = get_post_meta( $current_item_id, 'wprss_item_date', true );
					$month_current_item = date_i18n( $date_format_month, strtotime( $post_meta ) );
					$year_current_item  = date_i18n( $date_format_year, strtotime( $post_meta ) );

					if ( ( $month_previous === $month_current_item ) || ( $year_previous === $year_current_item ) ) {
					} else {
						$show_opening_tag = false;
					}
				}
				if ( $show_opening_tag ) {
					$return .= '<ul class="import-items ' . $cssclass . '">';
				}
			}


			if ( $args['extra_info'] ) {

				$community_id   = '';
				$community_name = '';
				$feed_id        = get_post_meta( $current_item_id, 'wprss_feed_id', true );
				$extra_info     = 'events';

				if ( $args['type'] === 'events' ) {
					// different field for events feed
					$community_id = get_field( 'community_rssfeed_relations_events', $feed_id );
				} else {
					// different field for posts feed
					$extra_info   = 'posts';
					$community_id = get_field( 'community_rssfeed_relations_post', $feed_id );
				}
				if ( $community_id[0]->ID ) {
					$community_name = get_the_title( $community_id[0]->ID );
				}

				if ( $community_name ) {
					$extra_info = '<span class="source">' . $community_name . '</span>';
				}

				$container_start = '<span class="link-and-info">';
				$container_end   = '</span>';
			} else {
				$cssclass_a = 'class="no-info"';
			}

			$postcounter ++;


			if ( $args['type'] === 'events' ) {
				$debug    = false;
				$cssclass = '';
//				if ( $community_name === 'iBestuur' ) {
//					$debug = true;
//				}

				$post_meta          = community_get_event_date( $current_item_id, $debug );
				$month_current_item = date_i18n( $date_format_month, strtotime( $post_meta ) );
				$year_current_item  = date_i18n( $date_format_year, strtotime( $post_meta ) );

				if ( $month_previous !== $month_current_item ) {
					if ( $postcounter === 1 ) {
					} else {
						$return .= '</ul>';
					}
					if ( $year_previous !== $year_current_item ) {
						$return .= '<' . $tag_subtitle . '>' . $year_current_item . ' - ' . $month_current_item . '</' . $tag_subtitle . '>';
					} else {
						$return .= '<' . $tag_subtitle . '>' . ucfirst( $month_current_item ) . '</' . $tag_subtitle . '>';
					}
					if ( $args['cssclass'] ) {
						$cssclass .= ' ' . $args['cssclass'];
					}

					$return .= '<ul class="import-items agenda' . $cssclass . '"><li>';
				} else {
					$return .= '<li>';
				}

				$date     = date_i18n( $date_format_badge, strtotime( $post_meta ) );
				$date_tag = '<time datetime="' . date_i18n( $date_format_badge, strtotime( $post_meta ) ) . '">' . $date . '</time>';

				$return         .= '<span class="date date-event">' . $date_tag . '</span> ' . $container_start . '<a href="' . get_permalink() . '"' . $cssclass_a . '>' . get_the_title() . '</a>';
				$return         .= $extra_info . $container_end;
				$return         .= '</li>';
				$month_previous = $month_current_item;
				$year_previous  = $year_current_item;

			} else {
				$date_string = '';
				if ( $args['show_date'] ) {
					$date        = get_the_date( $date_format_badge );
					$date_string = '<span class="date date-publish">' . $date . '</span>';
				}

				$return .= '<li>';
				$return .= $date_string . ' ' . $container_start . '<a href="' . get_permalink() . '"' . $cssclass_a . '>' . get_the_title() . '</a>';
				$return .= $extra_info . $container_end;
				$return .= '</li>';

			}


		endwhile;

		$return .= '</ul>';

	}

	return $return;
}

function community_get_event_date( $item_id = 0, $debug = false ) {
	$date = 0;
	if ( $item_id ) {
		$item_date = get_post_meta( $item_id, 'wprss_item_date', true );
		$post_meta = get_post_meta( $item_id );

//		if ( $debug ) {
//			echo '<h1>ID: ' . $item_id . '</h1>';
//			echo '<pre>';
//			var_dump( $post_meta );
//			echo '</pre>';
//		}

		if ( $item_date ) {
			return $item_date;
		} else {
		}
	}

	return $date;

}
