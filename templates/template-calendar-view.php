<?php
/**
 *
 * template-calendar-view.php
 *
 * Version:             1.2.5
 * Version description: Shows a complete calendar of upcoming events
 */

//========================================================================================================


if ( function_exists( 'genesis' ) ) {
	// Genesis wordt gebruikt als framework

	//* Force full-width-content layout
	add_filter( 'genesis_pre_get_option_site_layout', '__genesis_return_full_width_content' );

	// remove 'page' class from body
	add_filter( 'body_class', 'community_remove_body_classes' );

	// append table to entry_content
	add_action( 'genesis_entry_content', 'community_add_calendar_view', 22 );

	// social media share buttons
	add_action( 'genesis_entry_content', 'rhswp_append_socialbuttons', 24 );


	// make it so
	genesis();

} else {

	// geen Genesis, maar wel dezelfde content, ongeveer, soort van
	global $post;

	get_header(); ?>

	<div id="primary" class="content-area">
		<div id="content" class="clearfix">

			<?php echo community_add_calendar_view() ?>

		</div><!-- #content -->
	</div><!-- #primary -->

	<?php

	get_sidebar();

	get_footer();


}

//========================================================================================================

function community_add_calendar_view( $doreturn = false ) {
	// haal alle community events op
	// zet deze in een array met als sleutel een datum
	// haal alle normale events op
	// zet deze in een array met als sleutel een datum
	//
	// per datum nul of meer evenementen
	//
	// maak een tabel met 12 maanden

	$return = '';

	$current_year = date('Y');
	$current_month = date('m');
	$currentDate = date ("Y-m-d", strtotime( $current_year . '-' . $current_month . '-1' ));

	echo $currentDate;

	$tabel  = '<table border="1"><caption>' . _x('Alle events gepresenteerd in een tabel', '') . '</caption>';
	$tabel  .= '<tr><td></td>';
	for ($dag = 1; $dag <= 31; $dag++) {
		$tabel  .= '<th scope="col">dag ' . $dag . '</th>';
	}
	$tabel  .= '</tr>';

	for ($maandcounter = 1; $maandcounter <= 12; $maandcounter++) {

		$tabel  .= '<tr>
		<th scope="row">Maand: '. $maandcounter . '</th>';
		for ($dag = 1; $dag <= 31; $dag++) {
			$tabel  .= '<th scope="col">datum: ' . $dag . '-' . $maandcounter . '</th>';
		}
		$tabel  .= '</tr>';
	}


	while (strtotime($date) <= strtotime($end_date)) {
		echo "$date\n";
		$date = date ("Y-m-d", strtotime("+1 day", strtotime($date)));
	}
	/*
	 * <table>
	  <caption>
		He-Man and Skeletor facts
	  </caption>
	  <tr>
		<td></td>
		<th scope="col" class="heman">He-Man</th>
		<th scope="col" class="skeletor">Skeletor</th>
	  </tr>
	  <tr>
		<th scope="row">Role</th>
		<td>Hero</td>
		<td>Villain</td>
	  </tr>
	  <tr>
		<th scope="row">Weapon</th>
		<td>Power Sword</td>
		<td>Havoc Staff</td>
	  </tr>
	  <tr>
		<th scope="row">Dark secret</th>
		<td>Expert florist</td>
		<td>Cries at romcoms</td>
	  </tr>
	</table>

	 */
	$tabel  .= '</table>';


	$return .= $tabel;

	if ( $doreturn ) {
		return $return;
	} else {
		echo $return;
	}

}

//========================================================================================================


