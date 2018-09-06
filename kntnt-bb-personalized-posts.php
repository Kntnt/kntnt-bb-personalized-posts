<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Kntnt's Personalized Posts for Beaver Builder
 * Plugin URI:        https://github.com/Kntnt/kntnt-bb-personalized-posts
 * Description:       Provides personalized posts as data source for Beaver Builder's Post, Post Slider and Post Carousel.
 * Version:           1.0.0
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       kntnt-bb-personalized-posts
 * Domain Path:       /languages
 */

namespace Kntnt\BB_Personalized_Posts;

defined( 'WPINC' ) || die;

require_once __DIR__ . '/classes/class-plugin.php';

new Plugin( [
	'public' => [
		'init' => [
			'UI',
			'Sourcer',
			'Meta_Tag_Writer',
		],
	],
	'ajax' => [
		'admin_init' => [
			'Sourcer',
		],
	],
	'admin' => [
		'init' => [
			'Settings',
			'Sourcer',
		],
	],
] );
