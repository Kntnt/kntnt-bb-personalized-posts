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
			'label' => __( 'Beaver Builder template', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'The Beaver Builder template with "Personalized posts" as data source.', 'kntnt-bb-personalized-posts' ),
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
			'options' => wp_list_pluck( get_post_types( [ 'public' => true ], 'objects' ), 'name' ),
			'default' => [ 'post' ],
			'filter-after' => function ( $post_types ) {
				return $post_types ? $post_types : 'any';
			},
			'filter-before' => function ( $post_types ) {
				return 'any' == $post_types ? [] : $post_types;
			},
		];

		$fields['sort_order'] = [
			'type' => 'select',
			'label' => __( 'Sort order', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'Sort order among posts with equal relevance.', 'kntnt-bb-personalized-posts' ),
			'options' => [
				'as-is' => __('No particular order (fastest)','kntnt-bb-personalized-posts' ),
				'id-asc' => __( 'Post id ascending', 'kntnt-bb-personalized-posts' ),
				'id-desc' => __( 'Post id descending', 'kntnt-bb-personalized-posts' ),
				'created-asc' => __( 'Created date ascending', 'kntnt-bb-personalized-posts' ),
				'created-desc' => __( 'Created date descending', 'kntnt-bb-personalized-posts' ),
				'modified-asc' => __( 'Modified ate ascending', 'kntnt-bb-personalized-posts' ),
				'modified-desc' => __( 'Modified date descending', 'kntnt-bb-personalized-posts' ),
				'comments-asc' => __( 'Comment count ascending', 'kntnt-bb-personalized-posts' ),
				'comments-desc' => __( 'Comment count descending', 'kntnt-bb-personalized-posts' ),
				'title-asc' => __( 'Title Ascending', 'kntnt-bb-personalized-posts' ),
				'title-desc' => __( 'Title descending', 'kntnt-bb-personalized-posts' ),
				'author-asc' => __( 'Author ascending', 'kntnt-bb-personalized-posts' ),
				'author-desc' => __( 'Author descending', 'kntnt-bb-personalized-posts' ),
				'random' => __( 'Random', 'kntnt-bb-personalized-posts' ),
			],
			'default' => 'as-is',
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
