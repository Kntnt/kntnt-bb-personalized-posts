<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Konzilo Personalizer for Beaver Builder
 * Plugin URI:        https://github.com/kntnt/konzilo-bb-personalizer
 * GitHub Plugin URI: https://github.com/kntnt/konzilo-bb-personalizer
 * Description:       Provides personalized posts as data source for Beaver Builder's Post, Post Slider and Post Carousel.
 * Version:           1.0.3
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       konzilo-bb-personalizer
 * Domain Path:       /languages
 */

namespace Konzilo\BB_Personalizer;

defined( 'WPINC' ) || die;

// Define WP_DEBUG as TRUE and uncomment next line to debug this plugin.
define( 'KONZILO_BB_PERSONALIZER', true );

require_once __DIR__ . '/classes/class-abstract-plugin.php';

class Plugin extends Abstract_Plugin {

	public static function is_acf_active() {
		return is_plugin_active( 'advanced-custom-fields/acf.php' ) || is_plugin_active( 'advanced-custom-fields-pro/acf.php' );
	}

	public function classes_to_load() {

		return [
			'public' => [
				'init' => [
					'BB_Extender',
					'Sourcer',
					'Scorer',
				],
			],
			'ajax' => [
				'admin_init' => [
					'Sourcer',
					'Scorer',
				],
			],
			'admin' => [
				'init' => [
					'ACF_Extender',
					'Settings',
					'Sourcer',
					'Scorer',
				],
			],
		];

	}

}

new Plugin();
