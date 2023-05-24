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

		}

		/** ----------------------------------------------------------------------------------------------------
		 * Do actually register the post types we need
		 *
		 * @return void
		 */
		public function fn_ictu_community_register_posttypes() {

			require_once plugin_dir_path( __FILE__ ) . 'includes/register-community-posttype.php';

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



