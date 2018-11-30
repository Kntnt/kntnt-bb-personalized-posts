<?php

namespace Konzilo\BB_Personalizer;

class Plugin extends Abstract_Plugin {

	public static function is_acf_active() {
		return is_plugin_active( 'advanced-custom-fields/acf.php' ) || is_plugin_active( 'advanced-custom-fields-pro/acf.php' );
	}

	static protected function dependencies() {
		return [
			'konzilo-personalizer/konzilo-personalizer.php' => __('Konzilo Personalizer', 'konzilo-bb-personalizer'),
		];
	}

	protected function classes_to_load() {

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
