<?php

/**
 * Custom Post Type: Community
 * Custom Taxonomies: Communitytype, Communitygrootte
 *
 * @see https://developer.wordpress.org/reference/functions/register_taxonomy/
 * @see https://developer.wordpress.org/reference/functions/get_taxonomy_labels/
 *
 * ----------------------------------------------------- */

//========================================================================================================

//$page_initatieven = get_theme_mod( 'customizer_community_pageid_overview' );
// if ( $page_initatieven ) {
// TODO zorg ervoor dat de slug van de pagina gebruikt wordt in de slug van de commmunity CPT
//	$slug_of_posttype = get_permalink( $page_initatieven );
//	$crumb            = str_replace( get_bloginfo( 'url' ) . '/', '', $slug_of_posttype );
//	$slug_of_posttype = $crumb;
// } else {
$slug_of_posttype = DO_COMMUNITY_CPT;
// }

$args = array(
	'label'               => esc_html__( DO_COMMUNITY_CPT, 'wp-rijkshuisstijl' ),
	'description'         => '',
	'labels'              => array(
		'name'                  => esc_html_x( 'Community\'s', 'post type', 'wp-rijkshuisstijl' ),
		'singular_name'         => esc_html_x( 'Community', 'post type', 'wp-rijkshuisstijl' ),
		'menu_name'             => esc_html_x( 'Community\'s', 'post type', 'wp-rijkshuisstijl' ),
		'name_admin_bar'        => esc_html_x( 'Community', 'post type', 'wp-rijkshuisstijl' ),
		'archives'              => esc_html_x( 'Overzicht community\'s', 'post type', 'wp-rijkshuisstijl' ),
		'attributes'            => esc_html_x( 'Eigenschappen community', 'post type', 'wp-rijkshuisstijl' ),
		'parent_item_colon'     => esc_html_x( 'Parent Map:', 'post type', 'wp-rijkshuisstijl' ),
		'all_items'             => esc_html_x( 'Alle community\'s', 'post type', 'wp-rijkshuisstijl' ),
		'add_new_item'          => esc_html_x( 'Community toevoegen', 'post type', 'wp-rijkshuisstijl' ),
		'add_new'               => esc_html_x( 'Toevoegen', 'post type', 'wp-rijkshuisstijl' ),
		'new_item'              => esc_html_x( 'Nieuw community', 'post type', 'wp-rijkshuisstijl' ),
		'edit_item'             => esc_html_x( 'Bewerk community', 'post type', 'wp-rijkshuisstijl' ),
		'update_item'           => esc_html_x( 'Update community', 'post type', 'wp-rijkshuisstijl' ),
		'view_item'             => esc_html_x( 'Bekijk community', 'post type', 'wp-rijkshuisstijl' ),
		'view_items'            => esc_html_x( 'Bekijk community\'s', 'post type', 'wp-rijkshuisstijl' ),
		'search_items'          => esc_html_x( 'Zoek community', 'post type', 'wp-rijkshuisstijl' ),
		'not_found'             => esc_html_x( 'Not found', 'post type', 'wp-rijkshuisstijl' ),
		'not_found_in_trash'    => esc_html_x( 'Not found in Trash', 'post type', 'wp-rijkshuisstijl' ),
		'featured_image'        => esc_html_x( 'Featured Image', 'post type', 'wp-rijkshuisstijl' ),
		'set_featured_image'    => esc_html_x( 'Set featured image', 'post type', 'wp-rijkshuisstijl' ),
		'remove_featured_image' => esc_html_x( 'Remove featured image', 'post type', 'wp-rijkshuisstijl' ),
		'use_featured_image'    => esc_html_x( 'Use as featured image', 'post type', 'wp-rijkshuisstijl' ),
		'insert_into_item'      => esc_html_x( 'Insert into Map', 'post type', 'wp-rijkshuisstijl' ),
		'uploaded_to_this_item' => esc_html_x( 'Uploaded to this community', 'post type', 'wp-rijkshuisstijl' ),
		'items_list'            => esc_html_x( 'Map list', 'post type', 'wp-rijkshuisstijl' ),
		'items_list_navigation' => esc_html_x( 'Maps list navigation', 'post type', 'wp-rijkshuisstijl' ),
		'filter_items_list'     => esc_html_x( 'Filter community list', 'post type', 'wp-rijkshuisstijl' ),
	),
	'supports'            => array( 'title', 'author', 'excerpt', 'editor', 'thumbnail' ),
	'hierarchical'        => false,
	'public'              => true,
	'show_ui'             => true,
	'show_in_menu'        => true,
	'show_in_admin_bar'   => true,
	'show_in_nav_menus'   => true,
	'can_export'          => true,
	'has_archive'         => false, 
	'exclude_from_search' => false,
	'publicly_queryable'  => true,
	'rewrite'             => array( 'slug' => $slug_of_posttype ),
	'capability_type'     => 'post'
);

register_post_type( DO_COMMUNITY_CPT, $args );

// add tags to this CPT
register_taxonomy_for_object_type( 'post_tag', DO_COMMUNITY_CPT );

//========================================================================================================

// Communitytype
$labels = array(
	'name'              => esc_html_x( 'Type community', 'taxonomy', 'wp-rijkshuisstijl' ),
	'singular_name'     => esc_html_x( 'Communitytype', 'taxonomy singular name', 'wp-rijkshuisstijl' ),
	'search_items'      => esc_html_x( 'Search communitytype', 'taxonomy', 'wp-rijkshuisstijl' ),
	'all_items'         => esc_html_x( 'All communitytypes', 'taxonomy', 'wp-rijkshuisstijl' ),
	'parent_item'       => esc_html_x( 'Parent communitytype', 'taxonomy', 'wp-rijkshuisstijl' ),
	'parent_item_colon' => esc_html_x( 'Parent communitytype:', 'taxonomy', 'wp-rijkshuisstijl' ),
	'edit_item'         => esc_html_x( 'Edit communitytype', 'taxonomy', 'wp-rijkshuisstijl' ),
	'view_item'         => esc_html_x( 'Bekijk communitytype', 'taxonomy', 'wp-rijkshuisstijl' ),
	'update_item'       => esc_html_x( 'Update communitytype', 'taxonomy', 'wp-rijkshuisstijl' ),
	'add_new_item'      => esc_html_x( 'Add new communitytype', 'taxonomy', 'wp-rijkshuisstijl' ),
	'new_item_name'     => esc_html_x( 'New communitytype name', 'taxonomy', 'wp-rijkshuisstijl' ),
	'menu_name'         => esc_html_x( 'Types', 'taxonomy', 'wp-rijkshuisstijl' ),
);

$args = array(
	'hierarchical'      => true,
	'labels'            => $labels,
	'show_ui'           => true,
	'show_admin_column' => true,
	'query_var'         => true,
	'rewrite'           => array( 'slug' => DO_COMMUNITYTYPE_CT ),
);

register_taxonomy( DO_COMMUNITYTYPE_CT, array( DO_COMMUNITY_CPT ), $args );

//========================================================================================================

// Community-onderwerpen
$labels = array(
	'name'              => esc_html_x( 'Thema\'s', 'taxonomy', 'wp-rijkshuisstijl' ),
	'singular_name'     => esc_html_x( 'Thema', 'taxonomy singular name', 'wp-rijkshuisstijl' ),
	'search_items'      => esc_html_x( 'Search thema\'s', 'taxonomy', 'wp-rijkshuisstijl' ),
	'all_items'         => esc_html_x( 'Alle thema\'s', 'taxonomy', 'wp-rijkshuisstijl' ),
	'parent_item'       => esc_html_x( 'Parent thema', 'taxonomy', 'wp-rijkshuisstijl' ),
	'parent_item_colon' => esc_html_x( 'Parent thema:', 'taxonomy', 'wp-rijkshuisstijl' ),
	'view_item'         => esc_html_x( 'Bekijk thema', 'taxonomy', 'wp-rijkshuisstijl' ),
	'edit_item'         => esc_html_x( 'Edit thema', 'taxonomy', 'wp-rijkshuisstijl' ),
	'update_item'       => esc_html_x( 'Update thema', 'taxonomy', 'wp-rijkshuisstijl' ),
	'add_new_item'      => esc_html_x( 'Add new thema', 'taxonomy', 'wp-rijkshuisstijl' ),
	'new_item_name'     => esc_html_x( 'New thema name', 'taxonomy', 'wp-rijkshuisstijl' ),
	'menu_name'         => esc_html_x( 'Thema\'s', 'taxonomy', 'wp-rijkshuisstijl' ),
);

$args = array(
	'hierarchical'      => true,
	'labels'            => $labels,
	'show_ui'           => true,
	'show_admin_column' => true,
	'query_var'         => true,
	'sort'              => false,
	'rewrite'           => array( 'slug' => DO_COMMUNITYTOPICS_CT ),
);

register_taxonomy( DO_COMMUNITYTOPICS_CT, array( DO_COMMUNITY_CPT ), $args );

//========================================================================================================

// Community-onderwerpen
$labels = array(
	'name'              => esc_html_x( 'Overheidslagen', 'taxonomy', 'wp-rijkshuisstijl' ),
	'singular_name'     => esc_html_x( 'Overheidslaag', 'taxonomy singular name', 'wp-rijkshuisstijl' ),
	'search_items'      => esc_html_x( 'Search overheidslagen', 'taxonomy', 'wp-rijkshuisstijl' ),
	'all_items'         => esc_html_x( 'Alle overheidslagen', 'taxonomy', 'wp-rijkshuisstijl' ),
	'parent_item'       => esc_html_x( 'Parent overheidslaag', 'taxonomy', 'wp-rijkshuisstijl' ),
	'parent_item_colon' => esc_html_x( 'Parent overheidslaag:', 'taxonomy', 'wp-rijkshuisstijl' ),
	'view_item'         => esc_html_x( 'Bekijk overheidslaag', 'taxonomy', 'wp-rijkshuisstijl' ),
	'edit_item'         => esc_html_x( 'Edit overheidslaag', 'taxonomy', 'wp-rijkshuisstijl' ),
	'update_item'       => esc_html_x( 'Update overheidslaag', 'taxonomy', 'wp-rijkshuisstijl' ),
	'add_new_item'      => esc_html_x( 'Add new overheidslaag', 'taxonomy', 'wp-rijkshuisstijl' ),
	'new_item_name'     => esc_html_x( 'New overheidslaag name', 'taxonomy', 'wp-rijkshuisstijl' ),
	'menu_name'         => esc_html_x( 'Overheidslagen', 'taxonomy', 'wp-rijkshuisstijl' ),
);

$args = array(
	'hierarchical'      => true,
	'labels'            => $labels,
	'show_ui'           => true,
	'show_admin_column' => true,
	'query_var'         => true,
	'sort'              => false,
	'rewrite'           => array( 'slug' => DO_COMMUNITYBESTUURSLAAG_CT ),
);

register_taxonomy( DO_COMMUNITYBESTUURSLAAG_CT, array( DO_COMMUNITY_CPT ), $args );

//========================================================================================================

// Community-doelgroepen
$labels = array(
	'name'              => esc_html_x( 'Community-doelgroepen', 'taxonomy', 'wp-rijkshuisstijl' ),
	'singular_name'     => esc_html_x( 'Doelgroep', 'taxonomy singular name', 'wp-rijkshuisstijl' ),
	'search_items'      => esc_html_x( 'Search doelgroepen', 'taxonomy', 'wp-rijkshuisstijl' ),
	'all_items'         => esc_html_x( 'Alle doelgroepen', 'taxonomy', 'wp-rijkshuisstijl' ),
	'parent_item'       => esc_html_x( 'Parent doelgroep', 'taxonomy', 'wp-rijkshuisstijl' ),
	'parent_item_colon' => esc_html_x( 'Parent doelgroep:', 'taxonomy', 'wp-rijkshuisstijl' ),
	'edit_item'         => esc_html_x( 'Edit doelgroep', 'taxonomy', 'wp-rijkshuisstijl' ),
	'view_item'         => esc_html_x( 'Bekijk doelgroep', 'taxonomy', 'wp-rijkshuisstijl' ),
	'update_item'       => esc_html_x( 'Update doelgroep', 'taxonomy', 'wp-rijkshuisstijl' ),
	'add_new_item'      => esc_html_x( 'Add new doelgroep', 'taxonomy', 'wp-rijkshuisstijl' ),
	'new_item_name'     => esc_html_x( 'New doelgroep name', 'taxonomy', 'wp-rijkshuisstijl' ),
	'menu_name'         => esc_html_x( 'Doelgroepen', 'taxonomy', 'wp-rijkshuisstijl' ),
);

$args = array(
	'hierarchical'      => true,
	'labels'            => $labels,
	'show_ui'           => true,
	'show_admin_column' => true,
	'query_var'         => true,
	'sort'              => false,
	'rewrite'           => array( 'slug' => DO_COMMUNITYAUDIENCE_CT ),
);

register_taxonomy( DO_COMMUNITYAUDIENCE_CT, array( DO_COMMUNITY_CPT ), $args );

//========================================================================================================

// If the dossier taxonomy exists, make it available to DO_COMMUNITY_CPT as well
if ( defined( 'RHSWP_CT_DOSSIER' ) && taxonomy_exists( RHSWP_CT_DOSSIER ) ) {

	register_taxonomy_for_object_type( RHSWP_CT_DOSSIER, DO_COMMUNITY_CPT );

}
