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

		$community_types     = ictuwp_communityfilter_list( DO_COMMUNITYTYPE_CT, __( 'Types', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );
		$community_topics    = ictuwp_communityfilter_list( DO_COMMUNITYTOPICS_CT, __( 'Onderwerpen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );
		$community_audiences = ictuwp_communityfilter_list( DO_COMMUNITYAUDIENCE_CT, __( 'Doelgroepen', 'taxonomie-lijst', 'wp-rijkshuisstijl' ), false, get_queried_object_id() );
		$title               = esc_attr( $instance['title'] );
		$description         = esc_attr( $instance['widget_description'] );
		$thepage             = get_theme_mod( 'customizer_community_pageid_overview' );

		if ( $community_types || $community_topics || $community_audiences ) {
			echo $before_widget;

			echo '<form id="communities_filter" action="' . get_permalink( $thepage ) . '" method="get">';
			if ( ! empty( $title ) ) {
				echo $before_title . $title . $after_title;
			}
			echo $community_types . $community_topics . $community_audiences;
			echo '<button type="submit">' . __( 'Filter', 'taxonomie-lijst', 'wp-rijkshuisstijl' ) . '</button>';
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
		$instance['title']             = esc_attr( $new_instance['title'] );
		$instance['widget_description']             = esc_attr( $new_instance['widget_description'] );

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
			'title'             => '',
			'widget_description' => '',
		);
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel', 'wp-rijkshuisstijl' ); ?></label>
			<input class="widefat" type="text" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>"/>
		</p>


		<p>
			<label
				for="<?php echo $this->get_field_id( 'widget_description' ); ?>"><?php _e( 'Beschrijving', 'wp-rijkshuisstijl' ); ?></label>
			<input class="widefat" type="text" id="<?php echo $this->get_field_id( 'widget_description' ); ?>"
				   name="<?php echo $this->get_field_name( 'widget_description' ); ?>" value="<?php echo $instance['widget_description']; ?>"/>
		</p>


		<?php
	}

}

