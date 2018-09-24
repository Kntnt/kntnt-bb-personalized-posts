<?php

namespace Kntnt\BB_Personalized_Posts;

class BB_Extender {

	public function run() {
		add_filter( 'fl_builder_render_settings_field', [ $this, 'bb_render_settings_field' ], 10, 3 );
	}

	public function bb_render_settings_field( $field, $name, $settings ) {
		if ( 'data_source' == $name ) {
			$field['options']['kntnt_bb_personalized_posts'] = __( 'Personalized posts', 'kntnt-bb-personalized-posts' );
			$field['toggle']['kntnt_bb_personalized_posts'] = [ 'fields' => [ 'posts_per_page' ] ];
		}
		return $field;
	}

}


