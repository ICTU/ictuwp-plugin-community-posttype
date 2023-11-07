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
function ictuwp_load_widget_last_added_communities() {

	register_widget( 'ICTUWP_widget_last_added_communities' );

}


/**
 * Community Filter Class
 *
 */
class ICTUWP_widget_last_added_communities extends WP_Widget {

	/**
	 * Constructor
	 *
	 * @return void
	 **/
	function __construct() {
		load_plugin_textdomain( 'wp-rijkshuisstijl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		$widget_ops = array(
			'classname'   => 'widget_community_recent_additions',
			'description' => __( "Widget last added communities", 'wp-rijkshuisstijl' )
		);
		parent::__construct( 'widget-community_recent_additions', _x( '(DO) community recent toegevoegd widget', 'widget name', 'wp-rijkshuisstijl' ), $widget_ops );

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

		$title       = esc_attr( $instance['title'] );
		$description = esc_attr( $instance['widget_description'] );
		$maxnr_posts = esc_attr( $instance['maxnr_posts'] );
		$max_age     = esc_attr( $instance['max_age'] );
		$args        = array(
			'max_items'     => $maxnr_posts,
			'max_age'       => $max_age,
			'overview_link' => $overview_link
		);

		$content = ictuwp_community_get_latest_list( $args );

		if ( $content ) {

			echo $before_widget;

			if ( ! empty( $title ) ) {
				echo $before_title . $title . $after_title;
			}
			if ( ! empty( $description ) ) {
				echo '<p>' . $description . '</p>';
			}

			echo $content;
			echo $after_widget;
		} else {
			echo '<pre>';
			var_dump( $args );
			echo '</pre>';
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
		$instance['maxnr_posts']        = esc_attr( $new_instance['maxnr_posts'] );
		$instance['max_age']            = esc_attr( $new_instance['max_age'] );

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
			'title'              => _x( 'Recent toegevoegde community\'s', 'default title', 'wp-rijkshuisstijl' ),
			'widget_description' => '',
			'maxnr_posts'        => '3',
		);

		$instance = wp_parse_args( (array) $instance, $defaults );
		$max_days = ( (int) $instance['max_age'] > 0 ) ? (int) $instance['max_age'] : 90;

		?>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel', 'wp-rijkshuisstijl' ); ?>
				:</label>
			<input class="widefat" type="text" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>"/>
		</p>


		<p>
			<label
				for="<?php echo $this->get_field_id( 'widget_description' ); ?>"><?php _e( 'Beschrijving', 'wp-rijkshuisstijl' ); ?>
				:</label>
			<textarea class="widefat" type="text" id="<?php echo $this->get_field_id( 'widget_description' ); ?>"
					  name="<?php echo $this->get_field_name( 'widget_description' ); ?>"><?php echo $instance['widget_description']; ?></textarea>
		</p>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'maxnr_posts' ); ?>"><?php echo _x( 'Maximum aantal community\'s', 'label', 'wp-rijkshuisstijl' ); ?>
				:</label>
			<?php
			echo '<select id="' . $this->get_field_id( 'maxnr_posts' ) . '" name="' . $this->get_field_name( 'maxnr_posts' ) . '">';
			$max = 10;
			$i   = 1;
			while ( $i <= $max ) {
				$checked = '';
				if ( (int) $i === (int) $instance['maxnr_posts'] ) {
					$checked = ' selected';
				}
				echo '<option value="' . $i . '"' . $checked . '>' . $i . '</option>';
				$i ++;
			}
			echo '</select>';
			?>
		</p>

		<p>
			<label
				for="<?php echo $this->get_field_id( 'max_age' ); ?>"><?php echo _x( 'Niet ouder dan hoeveel dagen?', 'label', 'wp-rijkshuisstijl' ); ?>
				:</label>

			<input class="widefat" type="number" id="<?php echo $this->get_field_id( 'max_age' ); ?>"
				   name="<?php echo $this->get_field_name( 'max_age' ); ?>" value="<?php echo $max_days; ?>"/>


		</p>

		<?php

	}

}

//========================================================================================================

