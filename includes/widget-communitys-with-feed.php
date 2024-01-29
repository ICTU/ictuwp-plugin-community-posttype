<?php
/*
Widget Name: Widget Last Added Community
URI: https://github.com/ICTU/ictuwp-plugin-community-posttype
Description: Add widget showing last added communities
*/

/**
 * Register Widget
 *
 */
function ictuwp_community_load_widget_valid_feeds() {

	register_widget( 'community_widget_communities_with_feeds' );

}


/**
 * Community Filter Class
 *
 */
class community_widget_communities_with_feeds extends WP_Widget {

	/**
	 * Constructor
	 *
	 * @return void
	 **/
	function __construct() {
		load_plugin_textdomain( 'wp-rijkshuisstijl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		$widget_ops = array(
			'classname'   => 'widget-communities-with-feeds',
			'description' => __( "Lijst van community\'s met actieve feed voor agenda of berichten.", 'wp-rijkshuisstijl' )
		);
		parent::__construct( 'widget-communities-with-feeds', _x( '(DO - community) actieve feeds', 'widget name', 'wp-rijkshuisstijl' ), $widget_ops );

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

		if ( ! is_page() ) {
			// this widget should only be used for pages
			return;
		}

		$community_list        = '';
		$current_page_template = get_post_meta( get_the_id(), '_wp_page_template', true );
		$widget_title          = esc_attr( $instance['title'] );
		$template_type         = esc_attr( $instance['template_type'] );


		if ( $template_type === $current_page_template ) {
			$type = 'posts';
			if ( ( DO_COMMUNITY_PAGE_RSS_AGENDA == $template_type ) ) {
				$type = 'events';
			}
			$feed_ids = community_get_feed_ids_for_feed_type( $type );

			if ( $feed_ids ) {
				$array_alala    = array();
				$cssclasses     = array( 'import-items', $args['css_class_ul'] );
				$community_list = '<ul class="' . implode( ' ', $cssclasses ) . '">';

				foreach ( $feed_ids as $current_feed_id ) {
					$current_feed_cpt = get_post( $current_feed_id );

					if ( $current_feed_cpt->post_status === 'publish' ) {
						$extra_info = '';

						if ( $type === 'events' ) {
							// different field for events feed
							$community_cpt = get_field( 'community_rssfeed_relations_events', $current_feed_id );
						} else {
							// different field for posts feed
							$community_cpt = get_field( 'community_rssfeed_relations_post', $current_feed_id );
						}
						if ( is_object( $community_cpt[0] ) ) {
							$community    = $community_cpt[0];
							$default_name = $community->post_title;
//							$alt_name     = rhswp_filter_alternative_title( $community->ID, $community->post_title );
//							if ( $alt_name && ( $alt_name !== $default_name ) ) {
//								// check if a short name is available
//								$extra_info = '<span class="source">' . $alt_name . '</span>';
//							}
//							$extra_info .= ' id: ' . $current_feed_cpt->ID;

							$array_alala[ sanitize_title( $default_name ) ] = '<li><a href="' . get_permalink( $community->ID ) . '">' . $default_name . '</a> ' . $extra_info . '</li>';
						}
					}
				}

				ksort( $array_alala );

				$community_list .= implode( '', $array_alala );
				$community_list .= '</ul>';

			}

		}

		if ( $community_list ) {


			echo $before_widget;

			if ( $community_list ) {

				if ( ! empty( $widget_title ) ) {
					echo $before_title . $widget_title . $after_title;
				}
				if ( ! empty( $feed_ids_description ) ) {
					echo '<p>' . esc_html( $feed_ids_description ) . '</p>';
				}
				echo $community_list;
			}

			echo $after_widget;
		}
		wp_reset_query();
		wp_reset_postdata();

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
		$instance['title']         = esc_attr( $new_instance['title'] );
		$instance['description']   = esc_attr( $new_instance['description'] );
		$instance['template_type'] = esc_attr( $new_instance['template_type'] );

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
			'title'         => _x( 'Community\'s', 'default title', 'wp-rijkshuisstijl' ),
			'description'   => '',
			'template_type' => DO_COMMUNITY_PAGE_RSS_AGENDA,
		);

		$instance = wp_parse_args( (array) $instance, $defaults );
		if ( $instance['template_type'] === DO_COMMUNITY_PAGE_RSS_AGENDA ) {
			$selected1 = ' checked';
			$selected2 = '';
		} else {
			$selected1                 = '';
			$selected2                 = ' checked';
			$instance['template_type'] = DO_COMMUNITY_PAGE_RSS_POSTS;
		}

		?>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel', 'wp-rijkshuisstijl' ); ?>
				:</label>
			<input class="widefat" type="text" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>"
				   value="<?php echo $instance['title']; ?>"/>
		</p>


		<p>
			<label
				for="<?php echo $this->get_field_id( 'description' ); ?>"><?php _e( 'Beschrijving', 'wp-rijkshuisstijl' ); ?>
				:</label>
			<textarea class="widefat" type="text" id="<?php echo $this->get_field_id( 'description' ); ?>"
					  name="<?php echo $this->get_field_name( 'description' ); ?>"><?php echo $instance['description']; ?></textarea>
		</p>

		<p>
		<fieldset>
			<legend><?php _e( 'Voor welke pagina-template?', 'wp-rijkshuisstijl' ); ?></legend>
			<?php

			$name = $this->get_field_name( 'template_type' );
			$id1  = $this->get_field_id( 'template_type_1' );
			$id2  = $this->get_field_id( 'template_type_2' );
			echo '<p><input type="radio" name="' . $name . '" value="' . DO_COMMUNITY_PAGE_RSS_AGENDA . '" id="' . $id1 . '"' . $selected1 . ' /><label for="' . $id1 . '">' . _x( "[community] toon agenda", "naam template", "wp-rijkshuisstijl" ) . '</label></p>';
			echo '<p><input type="radio" name="' . $name . '" value="' . DO_COMMUNITY_PAGE_RSS_POSTS . '" id="' . $id2 . '"' . $selected2 . ' /><label for="' . $id2 . '">' . _x( "[community] toon berichten", "naam template", "wp-rijkshuisstijl" ) . '</label></p>';
			?>
		</fieldset>
		</p>

		<?php

	}

}

//========================================================================================================

