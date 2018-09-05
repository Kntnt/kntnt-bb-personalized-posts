<?php

namespace Kntnt\BB_Personalized_Posts;

abstract class Abstract_Settings {

	private $ns;

	public function __construct() {
		$this->ns = Plugin::ns();
	}

	/**
	 * Bootstrap instance of this class.
	 */
	public function run() {
		add_action( 'admin_menu', [ $this, 'add_options_page' ] );
		add_filter( "plugin_action_links_$this->ns/$this->ns.php", [ $this, 'add_plugin_action_links' ], 10, 2 );
	}

	/**
	 * Add settings page to the option menu.
	 */
	public function add_options_page() {
		add_options_page( $this->page_title(), $this->menu_title(), $this->capability(), $this->ns, [ $this, 'show_settings_page' ] );
	}

	/**
	 * Returns $links with a link to this setting page added.
	 */
	public function add_plugin_action_links( $actions ) {
		$settings_link_name = __( 'Settings', 'kntnt-bb-personalized-posts' );
		$settings_link_url = admin_url( "options-general.php?page={$this->ns}" );
		$actions[] = "<a href=\"$settings_link_url\">$settings_link_name</a>";
		return $actions;
	}

	/**
	 * Returns title used as menu item.
	 */
	abstract protected function menu_title();

	/**
	 * Returns title used as head of settings page.
	 */
	abstract protected function page_title();

	/**
	 * Returns all fields used on the settings page.
	 */
	abstract protected function fields();

	/**
	 * Returns necessary capability to access the settings page.
	 */
	protected function capability() {
		return 'manage_options';
	}

	/**
	 * Returns path to settings page.
	 */
	protected function settings_page_template() {
		return Plugin::plugin_dir( 'includes/settings-page.php' );
	}

	/**
	 * Show settings page and update options.
	 */
	public function show_settings_page() {

		// Abort if current user has not permission to access the settings page.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized use.', 'kntnt-bb-personalized-posts' ) );
		}

		// Update options if the page is shown after a form post.
		if ( isset( $_POST[ $this->ns ] ) ) {

			// Abort if the form's nonce is not correct or expired.
			if ( ! wp_verify_nonce( $_POST['_wpnonce'], $this->ns ) ) {
				wp_die( __( 'Nonce failed.', 'kntnt-bb-personalized-posts' ) );
			}

			// Update options.
			$this->update_options( $_POST[ $this->ns ] );

		}

		// Variables that will be visible for the settings-page template.
		$ns = $this->ns;
		$title = $this->page_title();
		$fields = $this->fields();
		$values = Plugin::option();

		// Default values that will be visible for the settings-page template.
		foreach ( $fields as $id => $field ) {

			// Set default if no value is saved.
			if ( ! isset( $values[ $id ] ) ) {
				$values[ $id ] = isset( $field['default'] ) ? $field['default'] : null;
			}

			// Filter saved value before outputting it.
			if ( isset( $field['filter-before'] ) ) {
				$filter = $field['filter-before'];
				$values[ $id ] = $filter( $values[ $id ] );
			}

		}

		// Render settings page; include the settings-page template.
		include $this->settings_page_template();

	}

	/**
	 * Validates that $integer is either empty or an integer.
	 *
	 * @param $integer The value to validate.
	 *
	 * @return bool True if and only if $integer is either an empty scalar (e.g.
	 * an empty string but not an empty array) or an integer.
	 */
	protected function validate_integer( $integer ) {
		return empty( $integer ) || ! is_numeric( $integer ) && ( ( (int) $integer ) === $integer ); // is_int don't handle strings
	}

	/**
	 * Validates that $number is either empty or a number.
	 *
	 * @param $number The value to validate.
	 *
	 * @return bool True if and only if $number is either an empty scalar (e.g.
	 * an empty string but not an empty array) or an integer or floating point
	 * number.
	 */
	protected function validate_number( $number ) {
		return empty( $number ) || is_numeric( $number );
	}

	/**
	 * Validates that $val is an URL.
	 *
	 * @param $url The value to validate.
	 *
	 * @return bool True if and only if $url is a proper formatted URL.
	 */
	protected function validate_url( $url ) {
		return false !== filter_var( $url, FILTER_VALIDATE_URL );
	}

	/**
	 * Validates that $email is an email address.
	 *
	 * @param $email The value to validate.
	 *
	 * @return bool True if and only if $email is a proper formatted email
	 * address.
	 */
	protected function validate_email( $email ) {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Validates that the value(s) in $values match the options in $options.
	 *
	 * @param       $val        Either a value or an array of values to
	 *                          validate.
	 * @param array $options    An associative array where the keys are allowed
	 *                          values of $val.
	 *
	 * @return bool True if and only if a single value in $value match an option
	 *              in $option or if all values in an array $values of values
	 *              match an option in $option.
	 */
	protected function validate_options( $val, $options ) {
		if ( ! is_array( $val ) ) {
			if ( ! empty( $val ) && ! array_key_exists( $val, $options ) ) {
				return false;
			}
		}
		else {
			foreach ( $val as $key => $value ) {
				if ( ! array_key_exists( $key, $options ) ) {
					return false;
				}
			}

		}
		return true;
	}

	/**
	 * Validate, sanitize and save field values.
	 */
	private function update_options( $opt ) {

		$fields = $this->fields();

		// Validate inputted values.
		$validates = true;
		foreach ( $fields as $id => $field ) {

			// Some fields (e.g. select multiple) are left out in $opt if
			// nothing is set. Add them and set their value to null.
			if ( ! isset( $opt[ $id ] ) ) $opt[ $id ] = null;

			// Select multiple needs special treatment to be consistent with
			// other fields having options.
			if ( 'select multiple' == $field['type'] ) {
				$opt[ $id ] = array_combine( $opt[ $id ], $opt[ $id ] );
			}

			// Validate fields for which there exists pre-defined baseline
			// validators. More sophisticated validation can be defined in
			// the field settings.
			$validator = 'validate_' . $field['type'];
			if ( method_exists( $this, $validator ) ) {
				if ( ! $this->$validator( $opt[ $id ] ) ) {
					$validates = false;
					$this->notify_error( $field );
				}
			}

			// Validate fields with pre-defined options.
			if ( isset( $field['options'] ) ) {
				if ( ! $this->validate_options( $opt[ $id ], $field['options'] ) ) {
					$validates = false;
					$this->notify_error( $field );
				}
			}

			if ( isset( $field['validate'] ) ) {

				$validator = $field['validate'];
				if ( ! $validator( $opt[ $id ] ) ) {
					$validates = false;
					$this->notify_error( $field );
				}

			}

		}

		if ( $validates ) {

			// Filter inputted values.
			foreach ( $fields as $id => $field ) {
				if ( isset( $field['filter-after'] ) ) {
					$filter = $field['filter-after'];
					$opt[ $id ] = $filter( $opt [ $id ] );
				}
			}

			// Save inputted values.
			update_option( $this->ns, $opt );

			// Success notification
			$this->notify_success();

		}

	}

	private function notify_error( $field ) {
		if ( isset( $field['validate-error-message'] ) ) {
			$message = $field['validate-error-message'];
		}
		else if ( $field['label'] ) {
			$message = sprintf( __( '<strong>ERROR:</strong> Invalid data in the field <em>%s</em>.', 'kntnt-bb-personalized-posts' ), $field['label'] );
		}
		else {
			$message = __( '<strong>ERROR:</strong> Please review the settings and try again.', 'kntnt-bb-personalized-posts' );
		}
		$this->notify_admin( $message, 'error' );
	}

	private function notify_success( $field ) {
		if ( isset( $field['validate-success-message'] ) ) {
			$message = $field['validate-success-message'];
		}
		else {
			$message = __( 'Successfully saved settings.', 'kntnt-bb-personalized-posts' );
		}
		$this->notify_admin( $message, 'success' );
	}

	private function notify_admin( $message, $type ) {
		echo "<div class=\"notice notice-$type is-dismissible\"><p>$message</p></div>";
	}

}
