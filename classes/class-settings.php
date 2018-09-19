<?php

namespace Kntnt\BB_Personalized_Posts;

require_once Plugin::plugin_dir( 'classes/class-abstract-settings.php' );

class Settings extends Abstract_Settings {

	private $taxonomies = [];

	public function __construct() {

		parent::__construct();

		add_action( 'kntnt_cip_init', function ( $cip ) {
			$this->taxonomies = $cip->taxonomies();
			Plugin::log( 'CIP taxonomies: %s', join( ', ', $this->taxonomies ) );
		} );

	}

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
			'options' => [ '' => '' ] + wp_list_pluck( get_posts( [ 'post_type' => 'fl-builder-template', 'nopaging' => true ] ), 'post_title', 'ID' ),
			'default' => '',
		];

		$fields['selector'] = [
			'type' => 'text',
			'label' => __( 'jQuery selector', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'jQuery selector targeting the div element(s) which HTML should be replaced with the Beaver Builder layout above.', 'kntnt-bb-personalized-posts' ),
			'required' => true,
			'size' => 50,
		];

		$fields['taxonomies'] = [
			'type' => 'integer group',
			'label' => __( 'Taxonomy weights', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'Enter a number that reflect the taxonomies importance relative to each other (e.g. 500, 200 and 100).', 'kntnt-bb-personalized-posts' ),
			'options' => ( function () {
				$taxonomies = [];
				foreach ( $this->taxonomies as $slug ) {
					$taxonomies[ $slug ] = get_taxonomy( $slug )->label;
				}
				return $taxonomies;
			} )(),
			'min' => 1,
			'default' => 100,
			'validate' => function ( $value ) {
				foreach ( $value as $weight ) {
					if ( $weight && ! is_numeric( $weight ) ) return false;
				}
				return true;
			},
			'filter-after' => function ( $taxonomies ) { return array_filter( $taxonomies, function ( $v ) { return (bool) $v; } ); },
		];

		$fields['sort_order'] = [
			'type' => 'select',
			'label' => __( 'Sort order', 'kntnt-bb-personalized-posts' ),
			'description' => __( 'Sort order among posts with equal relevance.', 'kntnt-bb-personalized-posts' ),
			'options' => [
				'as-is' => __( 'No particular order (fastest)', 'kntnt-bb-personalized-posts' ),
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

		$fields['db_limit'] = [
			'type' => 'integer',
			'label' => __( 'Database limit', 'kntnt-bb-personalized-posts' ),
			'description' => __( sprintf( 'Enter an integer to limit the maximum numbers of rows that will be fetched from the database (i.e. the number of SQL\'s LIMIT clause). If set, you must consider that typically there are several times more rows than posts, and the limit is applied after sorting the rows but before ranking posts. Most sites don\'t have that many posts or that heavy traffic that requires a limit. So leave it empty if you can. If WP_DEBUG is false (%s), the sorted and ranked list of posts is cached.', defined( 'WP_DEBUG' ) && WP_DEBUG ? 'which it\'s NOT!' : 'which it is' ), 'kntnt-bb-personalized-posts' ),
			'default' => '',
			'min' => 1,
			'size' => 50,
		];

		return $fields;

	}

}
