<?php

defined( 'WPINC' ) || die;

add_option( 'kntnt-bb-personalized-posts', [
	'cip_url' => '',
	'selector' => '',
	'taxonomies' => [],
	'post_types' => [ 'post' => 'post' ],
	'sort_order' => 'random',
] );
