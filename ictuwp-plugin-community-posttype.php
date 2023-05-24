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
$slug       = 'community';
$slugtype   = 'community-type';
$slugtopics = 'onderwerpen-community';

if ( get_bloginfo( 'language' ) !== 'nl-NL' ) {
	// non Dutch slugs
	$slug       = 'community';
	$slugtopics = 'topics-community';
}


define( 'DO_COMMUNITY_CPT', $slug );
define( 'DO_COMMUNITYTYPE_CT', $slugtype );
define( 'DO_COMMUNITYTOPICS_CT', $slugtopics );

defined( 'DO_COMMUNITY_OVERVIEW_TEMPLATE' ) or define( 'DO_COMMUNITY_OVERVIEW_TEMPLATE', 'template-overview-communities.php' );
defined( 'DO_COMMUNITY_DETAIL_TEMPLATE' ) or define( 'DO_COMMUNITY_DETAIL_TEMPLATE', 'template-community-detail.php' );

//========================================================================================================
add_action( 'plugins_loaded', array( 'DO_COMMUNITY_CPT', 'init' ), 10 );


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

//	$object = get_post_type_object( CPT_PROJECT );

	if ( ! (
		is_singular( DO_COMMUNITY_CPT ) ||
//		is_post_type_archive( DO_COMMUNITY_CPT ) ||
		is_tax( DO_COMMUNITYTYPE_CT ) ||
		is_tax( DO_COMMUNITYTOPICS_CT ) ) ) {
		// niks doen we niet met een initiatief bezig zijn
		return $crumb;
	}


	// uit siteopties de pagina ophalen die het overzicht is van alle links
	if ( is_singular( DO_COMMUNITY_CPT ) ||
	     is_post_type_archive( DO_COMMUNITY_CPT )
	) {
		$page_initatieven = get_theme_mod( 'customizer_community_pageid_overview' );
	}

	$currentitem = explode( '</span>', $crumb );
	$parents     = array();
	$return      = '';

	if ( $page_initatieven ) {

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

//	} else {
//
//		// er is geen pagina bekend waaronder de items getoond worden
//		if ( is_singular( DO_COMMUNITY_CPT ) || is_singular( CPT_PROJECT ) ) {
//			return $crumb;
//		}
//
//		if ( is_post_type_archive( DO_COMMUNITY_CPT ) ||
//		     is_tax( CT_INITIATIEFTYPE ) ||
//		     is_tax( CT_PROVINCIE ) ) {
//			$obj = get_post_type_object( DO_COMMUNITY_CPT );
//
//			if ( is_post_type_archive( DO_COMMUNITY_CPT ) ) {
//
//			} elseif ( is_post_type_archive( DO_COMMUNITY_CPT ) ) {
//
//				$parents[] = array(
//					'text' => $obj->label,
//				);
//
//			} elseif ( is_tax( CT_INITIATIEFTYPE ) || is_tax( CT_PROVINCIE ) ) {
//				$parents[] = array(
//					'url'  => get_post_type_archive_link( DO_COMMUNITY_CPT ),
//					'text' => $obj->label,
//				);
//
//			}
//		} else {
//			// geen archief voor DO_COMMUNITY_CPT of is_tax( CT_INITIATIEFTYPE / CT_PROVINCIE )
//			$obj = get_post_type_object( CPT_PROJECT );
//
//			if ( is_post_type_archive( CPT_PROJECT ) ) {
//
//				$parents[] = array(
//					'text' => $obj->label,
//				);
//
//			} elseif ( is_tax( CT_PROJECTORGANISATIE ) ) {
//				$parents[] = array(
//					'url'  => get_post_type_archive_link( CPT_PROJECT ),
//					'text' => $obj->label,
//				);
//
//			}
//
//		}

	}

	foreach ( $parents as $link ) {
		if ( isset( $link['url'] ) && isset( $link['text'] ) ) {
			$return .= '<a href="' . $link['url'] . '">' . $link['text'] . '</a> ';
		} else {
			$return .= $link['text'] . '  ';
		}
	}

	if ( isset( $post->ID ) && $post->ID === $page_initatieven ) {
		//
	} elseif ( is_post_type_archive( DO_COMMUNITY_CPT ) ) {
		//
	} else {
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
