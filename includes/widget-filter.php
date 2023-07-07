<?php
/*
Plugin Name: BE Subpages Widget
Plugin URI: http://www.billerickson.net
Description: Lists subpages of the current section
Version: 1.6.1
Author: Bill Erickson
Author URI: http://www.billerickson.net
License: GPLv2
*/

/**
 * Register Widget
 *
 */

function ictuwp_communityfilter_load_widgets() {

	register_widget( 'ICTUWP_community_filter' );

}


/**
 * Subpages Widget Class
 *
 * @author       Bill Erickson <bill@billerickson.net>
 * @copyright    Copyright (c) 2011, Bill Erickson
 * @license      http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class ICTUWP_community_filter extends WP_Widget {

	/**
	 * Constructor
	 *
	 * @return void
	 **/
	function __construct() {
		load_plugin_textdomain( 'wp-rijkshuisstijl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		$widget_ops = array(
			'classname'   => 'widget_community_filter',
			'description' => __( "Widget community filter", 'wp-rijkshuisstijl' )
		);
		parent::__construct( 'widget-community_filter', __( '(DO) filter voor community pagina', 'wp-rijkshuisstijl' ), $widget_ops );

	}

	/**
	 * Outputs the HTML for this widget.
	 *
	 * @param array, An array of standard parameters for widgets in this theme
	 * @param array, An array of settings for this widget instance
	 *
	 * @return void Echoes it's output
	 **/
	function widget( $args, $instance ) {

		extract( $args, EXTR_SKIP );

		global $post;

		$community_types     = ictuwp_communityfilter_list( DO_COMMUNITYTYPE_CT, __( 'Types', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );
		$community_topics    = ictuwp_communityfilter_list( DO_COMMUNITYTOPICS_CT, __( 'Onderwerpen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );
		$community_audiences = ictuwp_communityfilter_list( DO_COMMUNITYAUDIENCE_CT, __( 'Doelgroepen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );
		$current_post_id     = ( is_object( $post ) ? $post->ID : 0 );
		$title               = esc_attr( $instance['title'] );
		$description         = esc_attr( $instance['widget_description'] );
		$thepage             = get_theme_mod( 'customizer_community_pageid_overview' );

		if ( ! $thepage ) {
			$message = _x( 'Er is nog geen overzichtspagina ingesteld voor het overzicht van community\'s. Gebruik hiervoor de customizer: kies een pagina onder "Community\'s".', 'warning', 'wp-rijkshuisstijl' );
			echo '<p>' . $message . '</p>';
		} elseif ( ( $community_types || $community_topics || $community_audiences ) && ( $current_post_id === $thepage ) ) {
			echo $before_widget;

			echo '<form id="communities_filter" action="' . get_permalink( $thepage ) . '" method="get">';
			if ( ! empty( $title ) ) {
				echo $before_title . $title . $after_title;
			}
			if ( ! empty( $description ) ) {
				echo '<p>' . $description . '</p>';
			}
			echo $community_types . $community_topics . $community_audiences;
			echo '<div class="submit-buttons">';
			echo '<button type="submit">' . __( 'Filter', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) . '</button>';
			echo '<a href="' . get_permalink( $thepage ) . '">' . __( 'Filter weghalen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) . '</a>';
			echo '</div>';
			echo '</form>';
			echo $after_widget;
		}

	}


	/**
	 * Sanitizes form inputs on save
	 *
	 * @param array $new_instance
	 * @param array $old_instance
	 *
	 * @return array $new_instance
	 */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title']              = esc_attr( $new_instance['title'] );
		$instance['widget_description'] = esc_attr( $new_instance['widget_description'] );

		return $instance;
	}

	/**
	 * Build the widget's form
	 *
	 * @param array $instance , An array of settings for this widget instance
	 *
	 * @return null
	 */
	function form( $instance ) {

		/* Set up some default widget settings. */
		$defaults = array(
			'title'              => '',
			'widget_description' => '',
		);
		$instance = wp_parse_args( (array) $instance, $defaults );
		$thepage  = get_theme_mod( 'customizer_community_pageid_overview' );

		if ( ! $thepage ) {
			$message = _x( 'Er is nog geen overzichtspagina ingesteld voor het overzicht van community\'s. Gebruik hiervoor de customizer: kies een pagina onder "Community\'s".', 'warning', 'wp-rijkshuisstijl' );
			echo '<p>' . $message . '</p>';
		}

		?>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel', 'wp-rijkshuisstijl' ); ?></label>
			<input class="widefat" type="text" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>"/>
		</p>


		<p>
			<label
				for="<?php echo $this->get_field_id( 'widget_description' ); ?>"><?php _e( 'Beschrijving', 'wp-rijkshuisstijl' ); ?></label>
			<textarea class="widefat" type="text" id="<?php echo $this->get_field_id( 'widget_description' ); ?>"
					  name="<?php echo $this->get_field_name( 'widget_description' ); ?>"><?php echo $instance['widget_description']; ?></textarea>
		</p>

		<?php

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
				$id        = $term->slug . '_' . $term->term_id;
				$checked   = '';
				$dossierid = isset( $_GET[ $id ] ) ? (int) $_GET[ $id ] : 0; // een dossier

				if ( $dossierid === $term->term_id ) {
					$checked = ' checked';
				}
				$return .= '<label for="' . $id . '"><input id="' . $id . '" type="checkbox" name="' . $id . '" value="' . $term->term_id . '"' . $checked . '>' . $term->name . '</label>';
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

