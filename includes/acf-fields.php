<?php

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	return;

} else {


	acf_add_local_field_group( array(
		'key'                   => 'group_651fc35278095',
		'title'                 => '(community\'s) - instellingen overzichtspagina',
		'fields'                => array(
			array(
				'key'               => 'field_651fc353dd88b',
				'label'             => 'Titel boven de lijst met community\'s',
				'name'              => 'community_list_title',
				'aria-label'        => '',
				'type'              => 'text',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'default_value'     => 'Alle community\'s',
				'maxlength'         => '',
				'placeholder'       => '',
				'prepend'           => '',
				'append'            => '',
			),
			array(
				'key'               => 'field_654794e955c7f',
				'label'             => 'Layout',
				'name'              => 'community_overview_page_layout',
				'aria-label'        => '',
				'type'              => 'group',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'layout'            => 'row',
				'sub_fields'        => array(
					array(
						'key'               => 'field_651fd26e067d9',
						'label'             => 'Opmaak voor een community-item',
						'name'              => 'community_layout_list',
						'aria-label'        => '',
						'type'              => 'radio',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(
							'community_layout_list_grid'      => 'Samenvatting direct zichtbaar',
							'community_layout_list_accordion' => 'Ingeklapt met alleen de titel zichtbaar',
						),
						'default_value'     => 'community_layout_list_grid',
						'return_format'     => 'value',
						'allow_null'        => 0,
						'other_choice'      => 0,
						'layout'            => 'vertical',
						'save_other_choice' => 0,
					),
					array(
						'key'               => 'field_651fcb4645e27',
						'label'             => 'Toon alfabetische groepering?',
						'name'              => 'community_layout_show_alphabet_list',
						'aria-label'        => '',
						'type'              => 'radio',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_651fd26e067d9',
									'operator' => '==',
									'value'    => 'community_layout_list_accordion',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(
							'show_true'  => 'Ja, toon alfabet-lijst',
							'show_false' => 'Nee, toon geen alfabet-lijst',
						),
						'default_value'     => 'show_true',
						'return_format'     => 'value',
						'allow_null'        => 0,
						'other_choice'      => 0,
						'layout'            => 'vertical',
						'save_other_choice' => 0,
					),
				),
			),
			array(
				'key'               => 'field_6544cb42c57df',
				'label'             => 'Zoekformulier',
				'name'              => 'community_layout_block_search_group',
				'aria-label'        => '',
				'type'              => 'group',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'layout'            => 'row',
				'sub_fields'        => array(
					array(
						'key'               => 'field_6544c991b519b',
						'label'             => 'Toon zoekformuliertje voor community?',
						'name'              => 'community_layout_block_searchform_show',
						'aria-label'        => '',
						'type'              => 'radio',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(
							'show_true'  => 'Ja, toon zoekformuliertje',
							'show_false' => 'Nee, toon zoekformuliertje niet',
						),
						'default_value'     => 'show_true',
						'return_format'     => 'value',
						'allow_null'        => 0,
						'other_choice'      => 0,
						'layout'            => 'vertical',
						'save_other_choice' => 0,
					),
					array(
						'key'               => 'field_65394aa916f35',
						'label'             => 'Label voor het inputveld',
						'name'              => 'community_layout_block_searchform_label',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544c991b519b',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 'Zoek community',
						'maxlength'         => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					),
					array(
						'key'               => 'field_6544ca596b6ca',
						'label'             => 'Label voor knop',
						'name'              => 'community_layout_block_searchform_button_label',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544c991b519b',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 'Zoek',
						'maxlength'         => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					),
				),
			),
			array(
				'key'               => 'field_6544b9ea9f23c',
				'label'             => 'Agenda',
				'name'              => 'community_layout_block_agenda_group',
				'aria-label'        => '',
				'type'              => 'group',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'layout'            => 'row',
				'sub_fields'        => array(
					array(
						'key'               => 'field_6544bad483c9a',
						'label'             => 'Toon agenda?',
						'name'              => 'community_layout_block_agenda_show',
						'aria-label'        => '',
						'type'              => 'radio',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(
							'show_true'  => 'Ja, toon agenda',
							'show_false' => 'Nee, verberg agenda',
						),
						'default_value'     => 'show_true',
						'return_format'     => 'value',
						'allow_null'        => 0,
						'other_choice'      => 0,
						'layout'            => 'vertical',
						'save_other_choice' => 0,
					),
					array(
						'key'               => 'field_6544ba119f23d',
						'label'             => 'Titel',
						'name'              => 'block_title',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bad483c9a',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 'Agenda',
						'maxlength'         => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					),
					array(
						'key'               => 'field_6544ba249f23e',
						'label'             => 'Aantal items',
						'name'              => 'max_items',
						'aria-label'        => '',
						'type'              => 'number',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bad483c9a',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 5,
						'min'               => 1,
						'max'               => 20,
						'placeholder'       => '',
						'step'              => '',
						'prepend'           => '',
						'append'            => '',
					),
					array(
						'key'               => 'field_6544bd08df4f0',
						'label'             => 'Link naar overzicht',
						'name'              => 'overview_link',
						'aria-label'        => '',
						'type'              => 'link',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bad483c9a',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'return_format'     => 'array',
					),
					array(
						'key'                  => 'field_6544ee155c50c',
						'label'                => 'RSS template',
						'name'                 => 'rss_template',
						'aria-label'           => '',
						'type'                 => 'post_object',
						'instructions'         => '',
						'required'             => 0,
						'conditional_logic'    => 0,
						'wrapper'              => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'post_type'            => array(
							0 => 'wprss_feed_template',
						),
						'post_status'          => '',
						'taxonomy'             => '',
						'return_format'        => 'object',
						'multiple'             => 0,
						'allow_null'           => 0,
						'bidirectional'        => 0,
						'ui'                   => 1,
						'bidirectional_target' => array(),
					),
				),
			),
			array(
				'key'               => 'field_6544bb599f9d8',
				'label'             => 'Berichten',
				'name'              => 'community_layout_block_posts_group',
				'aria-label'        => '',
				'type'              => 'group',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'layout'            => 'row',
				'sub_fields'        => array(
					array(
						'key'               => 'field_6544bb599f9d9',
						'label'             => 'Toon berichten?',
						'name'              => 'community_layout_block_posts_show',
						'aria-label'        => '',
						'type'              => 'radio',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(
							'show_true'  => 'Ja, toon berichten',
							'show_false' => 'Nee, verberg berichten',
						),
						'default_value'     => 'show_true',
						'return_format'     => 'value',
						'allow_null'        => 0,
						'other_choice'      => 0,
						'layout'            => 'vertical',
						'save_other_choice' => 0,
					),
					array(
						'key'               => 'field_6544bb599f9da',
						'label'             => 'Titel',
						'name'              => 'block_title',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bb599f9d9',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 'Berichten',
						'maxlength'         => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					),
					array(
						'key'               => 'field_6544bb599f9db',
						'label'             => 'Aantal items',
						'name'              => 'max_items',
						'aria-label'        => '',
						'type'              => 'number',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bb599f9d9',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 5,
						'min'               => 1,
						'max'               => 20,
						'placeholder'       => '',
						'step'              => '',
						'prepend'           => '',
						'append'            => '',
					),
					array(
						'key'               => 'field_6544bd42df4f1',
						'label'             => 'Link naar overzicht',
						'name'              => 'overview_link',
						'aria-label'        => '',
						'type'              => 'link',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bb599f9d9',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'return_format'     => 'array',
					),
					array(
						'key'                  => 'field_6544ee6909a2b',
						'label'                => 'RSS template',
						'name'                 => 'rss_template',
						'aria-label'           => '',
						'type'                 => 'post_object',
						'instructions'         => '',
						'required'             => 0,
						'conditional_logic'    => 0,
						'wrapper'              => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'post_type'            => array(
							0 => 'wprss_feed_template',
						),
						'post_status'          => '',
						'taxonomy'             => '',
						'return_format'        => 'object',
						'multiple'             => 0,
						'allow_null'           => 0,
						'bidirectional'        => 0,
						'ui'                   => 1,
						'bidirectional_target' => array(),
					),
				),
			),
			array(
				'key'               => 'field_6544bbb8d946d',
				'label'             => 'Recent toegevoegde community\'s',
				'name'              => 'community_layout_block_latest_communities_group',
				'aria-label'        => '',
				'type'              => 'group',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'layout'            => 'row',
				'sub_fields'        => array(
					array(
						'key'               => 'field_6544bbb8d946e',
						'label'             => 'Toon recent toegevoegde community\'s?',
						'name'              => 'community_layout_block_latest_communities_show',
						'aria-label'        => '',
						'type'              => 'radio',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(
							'community_layout_block_latest_communities_show_true'  => 'Ja, toon recent toegevoegde community\'s',
							'community_layout_block_latest_communities_show_false' => 'Nee, verberg recent toegevoegde community\'s',
						),
						'default_value'     => 'community_layout_block_latest_communities_show_true',
						'return_format'     => 'value',
						'allow_null'        => 0,
						'other_choice'      => 0,
						'layout'            => 'vertical',
						'save_other_choice' => 0,
					),
					array(
						'key'               => 'field_6544bbb8d946f',
						'label'             => 'Titel',
						'name'              => 'block_title',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bbb8d946e',
									'operator' => '==',
									'value'    => 'community_layout_block_latest_communities_show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 'Laatst toegevoegde community\'s',
						'maxlength'         => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					),
					array(
						'key'               => 'field_6544bbb8d9470',
						'label'             => 'Aantal items',
						'name'              => 'max_items',
						'aria-label'        => '',
						'type'              => 'number',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bbb8d946e',
									'operator' => '==',
									'value'    => 'community_layout_block_latest_communities_show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 5,
						'min'               => 1,
						'max'               => 20,
						'placeholder'       => '',
						'step'              => '',
						'prepend'           => '',
						'append'            => '',
					),
					array(
						'key'               => 'field_6545075e0341a',
						'label'             => 'Link naar overzicht',
						'name'              => 'overview_link',
						'aria-label'        => '',
						'type'              => 'link',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bbb8d946e',
									'operator' => '==',
									'value'    => 'community_layout_block_latest_communities_show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'return_format'     => 'array',
					),
					array(
						'key'               => 'field_6545072e03419',
						'label'             => 'Niet ouder dan [x] dagen',
						'name'              => 'community_layout_block_latest_communities_max_days',
						'aria-label'        => '',
						'type'              => 'number',
						'instructions'      => 'Als geen community jong genoeg is (korter dan [x] dagen geleden gepubliceerd) dan wordt dit hele blok verborgen.',
						'required'          => 1,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6544bbb8d946e',
									'operator' => '==',
									'value'    => 'community_layout_block_latest_communities_show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 365,
						'min'               => 1,
						'max'               => 1000,
						'placeholder'       => '',
						'step'              => '',
						'prepend'           => '',
						'append'            => '',
					),
				),
			),
			array(
				'key'               => 'field_65536d6ac90ea',
				'label'             => 'Toon de taxonomieën',
				'name'              => 'community_layout_terms_group',
				'aria-label'        => '',
				'type'              => 'group',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'layout'            => 'row',
				'sub_fields'        => array(
					array(
						'key'               => 'field_651fd21086331',
						'label'             => 'Toon lijstjes met taxonomieen?',
						'name'              => 'community_layout_show_terms_lists',
						'aria-label'        => '',
						'type'              => 'radio',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(
							'show_true'  => 'Ja, toon lijstjes',
							'show_false' => 'Nee, toon lijstjes niet',
						),
						'default_value'     => 'show_true',
						'return_format'     => 'value',
						'allow_null'        => 0,
						'other_choice'      => 0,
						'layout'            => 'vertical',
						'save_other_choice' => 0,
					),
					array(
						'key'               => 'field_655370b1c90eb',
						'label'             => 'Titel',
						'name'              => 'community_layout_terms_title',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_651fd21086331',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 'Onderwerpen, types en doelgroepen',
						'maxlength'         => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					),
					array(
						'key'               => 'field_65537a8e264be',
						'label'             => 'Toon aantal?',
						'name'              => 'community_layout_show_terms_counter',
						'aria-label'        => '',
						'type'              => 'radio',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_651fd21086331',
									'operator' => '==',
									'value'    => 'show_true',
								),
							),
						),
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(
							'show_true'  => 'Ja, toon het aantal community\'s',
							'show_false' => 'Nee, toon geen aantal',
						),
						'default_value'     => 'show_true',
						'return_format'     => 'value',
						'allow_null'        => 0,
						'other_choice'      => 0,
						'layout'            => 'vertical',
						'save_other_choice' => 0,
					),
				),
			),
		),
		'location'              => array(
			array(
				array(
					'param'    => 'page_template',
					'operator' => '==',
					'value'    => 'template_overview_communities.php',
				),
			),
		),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'field',
		'hide_on_screen'        => '',
		'active'                => true,
		'description'           => '',
		'show_in_rest'          => 0,
	) );


	acf_add_local_field_group( array(
		'key'                   => 'group_646ddf37d8817',
		'title'                 => '(community\'s) - Enkele community: selecteer feeds en links',
		'fields'                => array(
			array(
				'key'               => 'field_646ddf382db93',
				'label'             => 'Link naar community',
				'name'              => 'community_url',
				'aria-label'        => '',
				'type'              => 'link',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'return_format'     => 'array',
			),
			array(
				'key'                  => 'field_6543cafcadd92',
				'label'                => 'Berichten RSS feed',
				'name'                 => 'rss_feed_source_posts',
				'aria-label'           => '',
				'type'                 => 'post_object',
				'instructions'         => 'Deze moet eerst worden toegevoegd, voordat je de feed hier kunt selecteren. Een feed voeg je toe via:
[admin] > \'RSS Aggregator\' > Feed sources > Add new',
				'required'             => 0,
				'conditional_logic'    => 0,
				'wrapper'              => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'post_type'            => array(
					0 => 'wprss_feed',
				),
				'post_status'          => array(
					0 => 'publish',
				),
				'taxonomy'             => '',
				'return_format'        => 'object',
				'multiple'             => 0,
				'allow_null'           => 0,
				'bidirectional'        => 1,
				'bidirectional_target' => array(
					0 => 'field_654a234245d19',
				),
				'ui'                   => 1,
			),
			array(
				'key'                  => 'field_6543f0c702aa6',
				'label'                => 'Evenementen RSS feed',
				'name'                 => 'rss_feed_source_events',
				'aria-label'           => '',
				'type'                 => 'post_object',
				'instructions'         => 'Deze moet eerst worden toegevoegd, voordat je de feed hier kunt selecteren. Een feed voeg je toe via:
[admin] > \'RSS Aggregator\' > Feed sources > Add new',
				'required'             => 0,
				'conditional_logic'    => 0,
				'wrapper'              => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'post_type'            => array(
					0 => 'wprss_feed',
				),
				'post_status'          => array(
					0 => 'publish',
				),
				'taxonomy'             => '',
				'return_format'        => 'object',
				'multiple'             => 0,
				'allow_null'           => 0,
				'bidirectional'        => 1,
				'bidirectional_target' => array(
					0 => 'field_654a23e85a3bf',
				),
				'ui'                   => 1,
			),
		),
		'location'              => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => DO_COMMUNITY_CPT,
				),
			),
		),
		'menu_order'            => 0,
		'position'              => 'acf_after_title',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'field',
		'hide_on_screen'        => '',
		'active'                => true,
		'description'           => '',
		'show_in_rest'          => 0,
	) );

	acf_add_local_field_group( array(
		'key'                   => 'group_654a22ea1492f',
		'title'                 => '(community\'s) - Instellingen RSS feed: type en selecteer community',
		'fields'                => array(
			array(
				'key'               => 'field_654a22ea45d18',
				'label'             => 'Type',
				'name'              => 'community_rssfeed_type',
				'aria-label'        => '',
				'type'              => 'radio',
				'instructions'      => '',
				'required'          => 0,
				'conditional_logic' => 0,
				'wrapper'           => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'choices'           => array(
					'event' => 'Agenda',
					'posts' => 'Berichten',
				),
				'default_value'     => 'posts',
				'return_format'     => 'value',
				'allow_null'        => 0,
				'other_choice'      => 0,
				'layout'            => 'vertical',
				'save_other_choice' => 0,
			),
			array(
				'key'                  => 'field_654a234245d19',
				'label'                => 'Bij welke community horen deze berichten?',
				'name'                 => 'community_rssfeed_relations_post',
				'aria-label'           => '',
				'type'                 => 'relationship',
				'instructions'         => '',
				'required'             => 0,
				'conditional_logic'    => array(
					array(
						array(
							'field'    => 'field_654a22ea45d18',
							'operator' => '==',
							'value'    => 'posts',
						),
					),
				),
				'wrapper'              => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'post_type'            => array(
					0 => DO_COMMUNITY_CPT,
				),
				'post_status'          => '',
				'taxonomy'             => '',
				'filters'              => array(
					0 => 'search',
				),
				'return_format'        => 'object',
				'min'                  => 0,
				'max'                  => 1,
				'elements'             => '',
				'bidirectional'        => 1,
				'bidirectional_target' => array(
					0 => 'field_6543cafcadd92',
				),
			),
			array(
				'key'                  => 'field_654a23e85a3bf',
				'label'                => 'Bij welke community horen deze agenda-items?',
				'name'                 => 'community_rssfeed_relations_events',
				'aria-label'           => '',
				'type'                 => 'relationship',
				'instructions'         => '',
				'required'             => 0,
				'conditional_logic'    => array(
					array(
						array(
							'field'    => 'field_654a22ea45d18',
							'operator' => '==',
							'value'    => 'event',
						),
					),
				),
				'wrapper'              => array(
					'width' => '',
					'class' => '',
					'id'    => '',
				),
				'post_type'            => array(
					0 => DO_COMMUNITY_CPT,
				),
				'post_status'          => '',
				'taxonomy'             => '',
				'filters'              => array(
					0 => 'search',
				),
				'return_format'        => 'object',
				'min'                  => 0,
				'max'                  => 1,
				'elements'             => '',
				'bidirectional'        => 1,
				'bidirectional_target' => array(
					0 => 'field_6543f0c702aa6',
				),
			),
		),
		'location'              => array(
			array(
				array(
					'param'    => 'post_type',
					'operator' => '==',
					'value'    => 'wprss_feed',
				),
			),
		),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen'        => '',
		'active'                => true,
		'description'           => '',
		'show_in_rest'          => 0,
	) );


}
