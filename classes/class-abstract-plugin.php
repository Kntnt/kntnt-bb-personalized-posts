<?php

namespace Konzilo\BB_Personalizer;

abstract class Abstract_Plugin {

	static private $ns;

	static private $plugin_dir;

	static private $unsatisfied_dependencies = null;

	public function __construct() {

		// This plugin's machine name a.k.a. slug.
		self::$ns = strtr( strtolower( __NAMESPACE__ ), '_\\', '--' );

		// Path to this plugin's directory relative file system root.
		self::$plugin_dir = strtr( dirname( __DIR__ ), '\\', '/' );

		// Install script runs only on install (not activation).
		// Uninstall script runs "magically" on uninstall.
		if ( is_readable( self::$plugin_dir . '/install.php' ) ) {
			register_activation_hook( self::$plugin_dir . '/' . self::$ns . '.php', function () {
				if ( null === get_option( self::$ns, null ) ) {
					require_once self::$plugin_dir . '/install.php';
				}
			} );
		}

		// Setup localization.
		add_action( 'plugins_loaded', function () {
			load_plugin_textdomain( self::$ns, false, self::$ns . '/languages' );
		} );

		// Setup this plugin to run.
		foreach ( $this->classes_to_load() as $context => $hoooks_and_classes ) {
			if ( $this->is_context( $context ) ) {
				foreach ( $hoooks_and_classes as $hook => $classes ) {
					foreach ( $classes as $class ) {
						add_action( $hook, [ $this->instance( $class ), 'run' ] );
					}
				}
			}

		}

	}

	// Returns context => hook => class relationships for classes to load.
	abstract protected function classes_to_load();

	// Returns an array of 'plugin_slug' => 'Plugin Name' for each plugin that
	// must be active for his plugin to work.
	static protected function dependencies() { return []; }

	// Name space of plugin.
	static public function ns() {
		return self::$ns;
	}

	// Plugin version.
	static public function version() {
		$key = self::$ns . '-plugin-version';
		$version = get_transient( $key );
		if ( ! $version ) {
			$version = get_plugin_data( self::plugin_dir( self::$ns . '.php' ), false, false )['Version'];
			set_transient( $key, $version, YEAR_IN_SECONDS );
		}
		return $version;
	}

	// Return an array of not active plugins that this plugin is dependent on.
	static public function unsatisfied_dependencies() {
		if ( null === self::$unsatisfied_dependencies ) {
			self::$unsatisfied_dependencies = [];
			foreach ( static::dependencies() as $slug => $name ) {
				if ( ! is_plugin_active( $slug ) ) {
					self::$unsatisfied_dependencies[ $slug ] = $name;
				}
			}
		}
		return self::$unsatisfied_dependencies;
	}

	// This plugin's path relative file system root, with no trailing slash.
	// If $rel_path is given, with or without leading slash, it is appended
	// with leading slash.
	static public function plugin_dir( $rel_path = '' ) {
		return self::str_join( self::$plugin_dir, $rel_path );
	}

	// This plugin's path relative WordPress root, with leading slash but no
	// trailing slash. If $rel_path is given, with or without leading slash,
	// it is appended with leading slash.
	static public function rel_plugin_dir( $rel_path = '' ) {
		return self::str_join( substr( self::$plugin_dir, strlen( ABSPATH ) - 1 ), ltrim( $rel_path, '/' ), '/' );
	}

	// The WordPress' root relative file system root, with no trailing slash.
	// If $rel_path is given, with or without leading slash, it is appended
	// with leading slash.
	static public function rel_wp_dir( $rel_path = '' ) {
		return self::str_join( ABSPATH, ltrim( $rel_path, '/' ), '/' );
	}

	// Returns the truth value of the statement that we are running in the
	// context asserted by $context.
	static public function is_context( $context ) {
		return 'any' == $context ||
		       'public' == $context && ( ! defined( 'WP_ADMIN' ) || ! WP_ADMIN ) ||
		       'ajax' == $context && defined( 'DOING_AJAX' ) && DOING_AJAX ||
		       'admin' == $context && defined( 'WP_ADMIN' ) && WP_ADMIN && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ||
		       'cron' == $context && defined( 'DOING_CRON' ) && DOING_CRON ||
		       'cli' == $context && defined( 'WP_CLI' ) && WP_CLI ||
		       isset( $_SERVER ) && isset( $_SERVER['SCRIPT_FILENAME'] ) && pathinfo( $_SERVER['SCRIPT_FILENAME'], PATHINFO_FILENAME ) == $context;
	}

	public static function is_debugging() {
		static $konzilo_debug;
		if ( ! $konzilo_debug ) $konzilo_debug = strtr( strtoupper( self::$ns ), '-', '_' );
		return defined( 'WP_DEBUG' ) && constant( 'WP_DEBUG' ) && defined( $konzilo_debug ) && constant( $konzilo_debug );
	}

	// Returns an instance of the class with the provided name.
	static public function instance( $class_name ) {
		$n = strtr( strtolower( $class_name ), '_', '-' );
		$class_name = __NAMESPACE__ . '\\' . $class_name;
		require_once self::$plugin_dir . "/classes/class-$n.php";
		return new $class_name();
	}

	static public function template( $file ) {
		return Plugin::plugin_dir( "includes/$file" );
	}

	// If $key is left out, null or empty, the option named as $plugin will be
	// returned if existing and the plugin exists and is active, otherwise
	// $default is returned. If $key is provided and is not null nor empty,
	// Plugin::option()['key'] is returned if existing, otherwise $default is
	// returned. If $plugin is left out, null or empty, this plugin is used.
	static public function option( $key = null, $default = false, $plugin = null ) {
		if ( $plugin ) {
			if ( ! is_plugin_active( $plugin ) ) {
				return $default;
			}
		}
		else {
			$plugin = self::$ns;
		}
		$opt = get_option( $plugin, null );
		if ( null === $opt ) {
			return $default;
		}
		if ( empty( $key ) ) {
			return $opt;
		}
		return isset( $opt[ $key ] ) ? $opt[ $key ] : $default;
	}

	static public function set_option( $key, $value ) {
		$opt = get_option( self::$ns, [] );
		$opt[ $key ] = $value;
		return update_option( self::$ns, $opt );
	}

	static public function delete_option( $key ) {
		$opt = get_option( self::$ns, [] );
		if ( isset( $opt[ $key ] ) ) {
			unset( $opt[ $key ] );
			return update_option( self::$ns, $opt );
		}
		return false;
	}

	public static final function log( $message = '', ...$args ) {
		if ( self::is_debugging() ) {
			$caller = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
			$caller = $caller[1]['class'] . '->' . $caller[1]['function'] . '()';
			foreach ( $args as &$arg ) {
				if ( is_array( $arg ) || is_object( $arg ) ) {
					$arg = print_r( $arg, true );
				}
			}
			$message = sprintf( $message, ...$args );
			error_log( "$caller: $message" );
		}
	}

	public static function str_join( $lhs, $rhs, $sep = '/' ) {
		return rtrim( $lhs, $sep ) . $sep . ltrim( $rhs, $sep );
	}

	public static function get_field( $field, $post_id, $single = true, $type = 'post' ) {
		if ( function_exists( 'get_field' ) ) {
			// If ACF is installed, let it get the field.
			return get_field( $field, $post_id );
		}
		else {
			// If ACF not installed, let's do it ourselves.
			return get_metadata( $type, $post_id, $field, true );
		}
	}

}
