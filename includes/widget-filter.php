<?php
/*
Widget Name: Widget for Community Filter
URI: https://github.com/ICTU/ictuwp-plugin-community-posttype
Description: Add form with filter actions for community overview page
*/

/**
 * Register Widget
 *
 */
function ictuwp_communityfilter_load_widgets() {

	register_widget( 'ICTUWP_community_filter' );

}


/**
 * Community Filter Class
 *
 */
class ICTUWP_community_filter extends WP_Widget {


	//----------------------------------------------------------------------------------------------------

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
		parent::__construct( 'widget-community_filter', _x( '(DO) community filter widget', 'widget name', 'wp-rijkshuisstijl' ), $widget_ops );

	}

	//----------------------------------------------------------------------------------------------------

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

		$form_communities = rhswp_community_get_filter_form( array(
			'ID'           => get_queried_object_id(),
			'title'        => $instance['title'],
			'description'  => $instance['widget_description'],
			'after_title'  => $after_title,
			'before_title' => $before_title
		) );

		if ( $form_communities ) {
			echo $before_widget;
			echo $form_communities;
//			echo community_feed_sources_get();
			echo $after_widget;
		}

	}

	//----------------------------------------------------------------------------------------------------

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

	//----------------------------------------------------------------------------------------------------

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

	//----------------------------------------------------------------------------------------------------

}



//========================================================================================================

