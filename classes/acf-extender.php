<?php

namespace Konzilo\BB_Personalizer;

class ACF_Extender {

	private $taxonomies;

	public function __construct() {
		$this->taxonomies = array_flip( Plugin::option( 'priority_custom_fields', 0 ) );
	}

	public function run() {
		if ( Plugin::is_acf_active() ) {
			add_filter( 'acf/load_field', [ $this, 'acf_load_field' ] );
		}
	}

	public function acf_load_field( $field ) {

		Plugin::log();

		if ( key_exists( $field['name'], $this->taxonomies ) ) {
			$taxonomy = $this->taxonomies[ $field['name'] ];
			$terms = get_terms( [ 'taxonomy' => $taxonomy ] );
			foreach ( $terms as $term ) {
				$field['choices'][ $term->slug ] = $term->name;
			}
		}
		return $field;
	}

}


