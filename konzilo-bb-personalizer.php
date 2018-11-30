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
// define( 'KONZILO_BB_PERSONALIZER', true );

spl_autoload_register( function ( $class ) {
	$ns_len = strlen( __NAMESPACE__ );
	if ( 0 == substr_compare( $class, __NAMESPACE__, 0, $ns_len ) ) {
		require_once __DIR__ . '/classes/' . strtr( strtolower( substr( $class, $ns_len + 1 ) ), '_', '-' ) . '.php';
	}
} );

new Plugin();
