<?php

namespace Kntnt\BB_Personalized_Posts;

require_once Plugin::plugin_dir( 'classes/class-abstract-settings.php' );

class Settings extends Abstract_Settings {

	/**
	 * Returns the settings menu title.
	 */
	protected function menu_title() {
		return __( 'Personalized Posts', 'kntnt-bb-personalized-posts' );
	}

	/**
	 * Returns the settings page title.
	 */
	protected function page_title() {
		return __( "Kntnt's Personalized Posts for Beaver Builder", 'kntnt-bb-personalized-posts' );
	}

	/**
	 * Returns all fields used on the settings page.
	 */
	protected function fields() {

		$fields['layout_post_id'] = [
			'type' => 'select',
			'label' => __( 'Beaver Builder layout', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'The post ID of a Beaver Builder layout with "Personalized posts" as data source.', 'kntnt-bb-personalized-posts' ),
			'options' => wp_list_pluck( get_posts( [ 'post_type' => 'fl-builder-template', 'nopaging' => true ] ), 'post_title', 'ID' ),
		];

		$fields['selector'] = [
			'type' => 'text',
			'label' => __( 'jQuery selector', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'jQuery selector targeting the div element(s) which HTML should be replaced with the Beaver Builder layout above.', 'kntnt-bb-personalized-posts' ),
			'size' => 50,
		];

		$fields['taxonomies'] = [
			'type' => 'text group',
			'label' => __( 'Taxonomy weights', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'Enter a number before each taxonomy that should be taken into account when personalizing the content. The number should reflect the taxonomies importance relative to the other.', 'kntnt-bb-personalized-posts' ),
			'options' => wp_list_pluck( get_taxonomies( [ 'public' => true ], 'objects' ), 'label' ),
			'size' => 10,
			'validate' => function ( $value ) {
				foreach ( $value as $weight ) {
					if ( $weight && ! is_numeric( $weight ) ) return false;
				}
				return true;
			},
			'filter-after' => function ( $taxonomies ) { return array_filter( $taxonomies, function ( $v ) { return (bool) $v; } ); },
		];

		$fields['post_types'] = [
			'type' => 'checkbox group',
			'label' => __( 'Posts type', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'Select one or more post types to limit the shown posts to these post types.', 'kntnt-bb-personalized-posts' ),
			'options' => get_post_types( [ 'public' => true ] ),
			'filter-before' => function ( $post_types ) {
				return 'any' == $post_types ? [] : $post_types;
			},
			'filter-after' => function ( $post_types ) {
				return $post_types ? $post_types : 'any';
			},
		];

		return $fields;

	}

	// Returns an array where keys are taxonomies machine name and values are
	// corresponding name in clear text.
	private function get_taxonomies() {

		global $wp_taxonomies;
		foreach ( $wp_taxonomies as $taxonomy ) {
			$taxonomies[ $taxonomy->name ] = "$taxonomy->label ($taxonomy->name)";
		}
		return $taxonomies;
	}

}