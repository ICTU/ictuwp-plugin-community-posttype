<?php

/**
 * @link                https://github.com/ICTU/ictuwp-plugin-community-posttype
 * @package             ictuwp-plugin-community-posttype
 *
 * @wordpress-plugin
 * Plugin Name:         ICTU / Digitale Overheid / Community
 * Plugin URI:          https://github.com/ICTU/ictuwp-plugin-community-posttype
 * Description:         Plugin voor het aanmaken van posttype 'community' en bijbehorende taxonomieen.
 * Version:             0.0.1
 * Version description: Initial version.
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
$slug          = 'community';
$slugtype      = 'community-type';
$slugtopics    = 'onderwerpen-community';
$slugaudiences = 'doelgroepen-community';

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

defined( 'DO_COMMUNITY_OVERVIEW_TEMPLATE' ) or define( 'DO_COMMUNITY_OVERVIEW_TEMPLATE', 'template-overview-communities.php' );
defined( 'DO_COMMUNITY_DETAIL_TEMPLATE' ) or define( 'DO_COMMUNITY_DETAIL_TEMPLATE', 'template-community-detail.php' );

//========================================================================================================
add_action( 'plugins_loaded', array( 'DO_COMMUNITY_CPT', 'init' ), 10 );

//========================================================================================================

require_once plugin_dir_path( __FILE__ ) . 'includes/widget-filter.php';

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
			add_filter( 'template_include', array( $this, 'led_template_page_initiatieven' ) );


			// Register widgets
//			add_action( 'widgets_init', array( $this, 'fn_ictu_community_register_widgets' ), 20 );
			add_action( 'widgets_init', 'ictuwp_communityfilter_load_widgets' );


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
		public function fn_ictu_community_add_page_template( $post_templates ) {

			$post_templates[ $this->template_overview_communities ] = _x( 'Overview communities', "naam template", 'wp-rijkshuisstijl' );

			return $post_templates;

		}

		/** ----------------------------------------------------------------------------------------------------
		 * Do actually register the post types we need
		 *
		 * @return void
		 */
		public function led_template_page_initiatieven( $archive_template ) {

			global $post;

			if ( is_search() ) {
				// do not overwrite search template
				return $archive_template;
			}

			$page_template = get_post_meta( get_the_id(), '_wp_page_template', true );

			if ( is_singular( DO_COMMUNITY_CPT ) ) {
//				// het is een single voor CPT = DO_COMMUNITY_CPT
//				$archive_template = dirname( __FILE__ ) . '/templates/single-initiatief.php';

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

				if ( ( DO_COMMUNITY_OVERVIEW_TEMPLATE === $page_template ) || ( DO_COMMUNITY_DETAIL_TEMPLATE === $page_template ) ) {
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
		DO_COMMUNITY_DETAIL_TEMPLATE   => _x( '[Community] community-detail', 'label page template', 'wp-rijkshuisstijl' )
	);

	return $return_array;

}

//========================================================================================================


/**
 * Voeg een paginaselector toe aan de customizer
 * zie [admin] > Weergave > Customizer > Initiatievenkaart
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

function rhswp_community_single_terms( $doreturn = false, $post_id = 0 ) {

	global $post;

	$return              = '';
	$values              = '';
	if ( ! $post_id ) {
		if ( get_the_id() ) {
			$post_id = get_the_id();
		} else {
			return;
		}
	}
	$community_topics    = get_the_terms( $post_id, DO_COMMUNITYTOPICS_CT );
	$community_types     = get_the_terms( $post_id, DO_COMMUNITYTYPE_CT );
	$community_audiences = get_the_terms( $post_id, DO_COMMUNITYAUDIENCE_CT );

	// toon aan welk onderwerp deze community is gekoppeld
	if ( $community_topics && ! is_wp_error( $community_topics ) ) :
		$labels = '<dd>';

		foreach ( $community_topics as $term ) {
			$labels .= '<a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a>';
			if ( next( $community_topics ) ) {
				$labels .= ', ';
			}
		}
		$labels .= '</dd>';

		if ( $labels ) {
			$values .= '<dt>' . _n( 'Onderwerp community', 'Onderwerpen community', count( $community_topics ), 'wp-rijkshuisstijl' ) . '</dt>';
			$values .= $labels;
		}

	endif;

	// toon aan welk type deze community is gekoppeld
	if ( $community_types && ! is_wp_error( $community_types ) ) :
		$labels = '<dd>';

		foreach ( $community_types as $term ) {
			$labels .= '<a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a>';
			if ( next( $community_types ) ) {
				$labels .= ', ';
			}
		}

		$labels .= '</dd>';

		if ( $labels ) {
			$values .= '<dt>' . _n( 'Type community', 'Types community', count( $community_types ), 'wp-rijkshuisstijl' ) . '</dt>';
			$values .= $labels;
		}

	endif;

	// toon aan welk doelgroep deze community is gekoppeld
	if ( $community_audiences && ! is_wp_error( $community_audiences ) ) :
		$labels = '<dd>';

		foreach ( $community_audiences as $term ) {
			$labels .= '<a href="' . get_term_link( $term->term_id ) . '">' . $term->name . '</a>';
			if ( next( $community_audiences ) ) {
				$labels .= ', ';
			}
		}

		$labels .= '</dd>';

		if ( $labels ) {
			$values .= '<dt>' . _n( 'Doelgroep', 'Doelgroepen', count( $community_audiences ), 'wp-rijkshuisstijl' ) . '</dt>';
			$values .= $labels;
		}

	endif;


	if ( has_term( '', RHSWP_CT_DOSSIER, $post->ID ) ) {
		// get dossier terms and their links

		$terms = get_the_terms( $post->ID, RHSWP_CT_DOSSIER );
		if ( $terms && ! is_wp_error( $terms ) ) {

			$labels = '<dd>';

			foreach ( $terms as $term ) {
				$labels .= '<a href="';
				if ( function_exists( 'rhswp_get_pagelink_for_dossier' ) ) {
					$labels .= rhswp_get_pagelink_for_dossier( $term );
				} else {
					$labels .= get_term_link( $term->term_id, RHSWP_CT_DOSSIER );
				}
				$labels .= '">' . $term->name . '</a>';

				if ( next( $terms ) ) {
					$labels .= ', ';
				}
			}

			$labels .= '</dd>';

			if ( $labels ) {
				$values .= '<dt>' . _x( 'Hoort bij', 'label dossiers bij een single community', 'wp-rijkshuisstijl' ) . '</dt>';
				$values .= $labels;
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

