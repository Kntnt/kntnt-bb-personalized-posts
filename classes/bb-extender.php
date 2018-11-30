<?php

namespace Konzilo\BB_Personalizer;

class BB_Extender {

	public function run() {
		add_filter( 'fl_builder_render_settings_field', [ $this, 'bb_render_settings_field' ], 10, 3 );
	}

	public function bb_render_settings_field( $field, $name, $settings ) {
		if ( 'data_source' == $name ) {
			$field['options']['konzilo_bb_personalizer'] = __( 'Personalized posts', 'konzilo-bb-personalizer' );
			$field['toggle']['konzilo_bb_personalizer'] = [ 'fields' => [ 'posts_per_page' ] ];
		}
		return $field;
	}

}


