<?php

namespace Konzilo\BB_Personalizer;

require_once Plugin::plugin_dir( 'classes/class-abstract-settings.php' );

class Settings extends Abstract_Settings {

	private $taxonomies = [];

	public function __construct() {

		parent::__construct();

		add_action( 'konzilo/content-intelligence/init', function ( $ci ) {
			$this->taxonomies = $ci->taxonomies();
			Plugin::log( 'CI taxonomies: %s', join( ', ', $this->taxonomies ) );
		} );

	}

	/**
	 * Returns the settings menu title.
	 */
	protected function menu_title() {
		return __( 'Personalized Posts', 'konzilo-bb-personalizer' );
	}

	/**
	 * Returns the settings page title.
	 */
	protected function page_title() {
		return __( "Konzilo Personalized Posts for Beaver Builder", 'konzilo-bb-personalizer' );
	}

	/**
	 * Returns all fields used on the settings page.
	 */
	protected function fields() {

		$disabled = (bool) Plugin::unsatisfied_dependencies();

		$fields['layout_post_id'] = [
			'type' => 'select',
			'label' => __( 'Beaver Builder template', 'konzilo-bb-personalizer' ),
			'description' => __( 'The Beaver Builder template with "Personalized posts" as data source.', 'konzilo-bb-personalizer' ),
			'options' => [ '' => '' ] + wp_list_pluck( get_posts( [ 'post_type' => 'fl-builder-template', 'nopaging' => true ] ), 'post_title', 'ID' ),
			'default' => '',
			'disabled' => $disabled,
		];

		$fields['selector'] = [
			'type' => 'text',
			'label' => __( 'jQuery selector', 'konzilo-bb-personalizer' ),
			'description' => __( 'jQuery selector targeting the div element(s) which HTML should be replaced with the Beaver Builder layout above.', 'konzilo-bb-personalizer' ),
			'required' => true,
			'size' => 50,
			'disabled' => $disabled,
		];

		$fields['taxonomies'] = [
			'type' => 'integer group',
			'label' => __( 'Taxonomy weights', 'konzilo-bb-personalizer' ),
			'description' => __( 'Enter a number that reflect the taxonomies importance relative to each other (e.g. 500, 200 and 100).', 'konzilo-bb-personalizer' ),
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
			'disabled' => $disabled,
		];

		if ( Plugin::is_acf_active() ) {

			$fields['priority_custom_fields'] = [
				'type' => 'select group',
				'label' => __( 'Priority custom fields', 'konzilo-bb-personalizer' ),
				'description' => __( 'For each taxonomy enter the custom field key that can be used to prioritize posts.', 'konzilo-bb-personalizer' ),
				'options' => ( function () {
					$taxonomies = [];
					foreach ( $this->taxonomies as $slug ) {
						$taxonomies[ $slug ] = [
							'label' => get_taxonomy( $slug )->label,
							'options' => $this->get_acf_fields(),
						];
					}
					return $taxonomies;
				} )(),
				'disabled' => $disabled,
			];

		}
		else {

			$fields['priority_custom_fields'] = [
				'type' => 'text group',
				'label' => __( 'Priority custom fields', 'konzilo-bb-personalizer' ),
				'description' => __( 'For each taxonomy enter the custom field key that can be used to prioritize posts.', 'konzilo-bb-personalizer' ),
				'options' => ( function () {
					$taxonomies = [];
					foreach ( $this->taxonomies as $slug ) {
						$taxonomies[ $slug ] = get_taxonomy( $slug )->label;
					}
					return $taxonomies;
				} )(),
				'disabled' => $disabled,
			];

		}

		$fields['priority_score'] = [
			'type' => 'integer',
			'label' => __( 'Priority score', 'konzilo-bb-personalizer' ),
			'description' => __( 'The value added to the score of posts that are prioritized.', 'konzilo-bb-personalizer' ),
			'size' => 50,
			'disabled' => $disabled,
		];

		$fields['sort_order'] = [
			'type' => 'select',
			'label' => __( 'Sort order', 'konzilo-bb-personalizer' ),
			'description' => __( 'Sort order among posts with equal relevance.', 'konzilo-bb-personalizer' ),
			'options' => [
				'as-is' => __( 'No particular order (fastest)', 'konzilo-bb-personalizer' ),
				'id-asc' => __( 'Post id ascending', 'konzilo-bb-personalizer' ),
				'id-desc' => __( 'Post id descending', 'konzilo-bb-personalizer' ),
				'created-asc' => __( 'Created date ascending', 'konzilo-bb-personalizer' ),
				'created-desc' => __( 'Created date descending', 'konzilo-bb-personalizer' ),
				'modified-asc' => __( 'Modified ate ascending', 'konzilo-bb-personalizer' ),
				'modified-desc' => __( 'Modified date descending', 'konzilo-bb-personalizer' ),
				'comments-asc' => __( 'Comment count ascending', 'konzilo-bb-personalizer' ),
				'comments-desc' => __( 'Comment count descending', 'konzilo-bb-personalizer' ),
				'title-asc' => __( 'Title Ascending', 'konzilo-bb-personalizer' ),
				'title-desc' => __( 'Title descending', 'konzilo-bb-personalizer' ),
				'author-asc' => __( 'Author ascending', 'konzilo-bb-personalizer' ),
				'author-desc' => __( 'Author descending', 'konzilo-bb-personalizer' ),
				'random' => __( 'Random', 'konzilo-bb-personalizer' ),
			],
			'default' => 'as-is',
			'disabled' => $disabled,
		];

		$fields['db_limit'] = [
			'type' => 'integer',
			'label' => __( 'Database limit', 'konzilo-bb-personalizer' ),
			'description' => __( sprintf( 'Enter an integer to limit the maximum numbers of rows that will be fetched from the database (i.e. the number of SQL\'s LIMIT clause). If set, you must consider that typically there are several times more rows than posts, and the limit is applied after sorting the rows but before ranking posts. Most sites don\'t have that many posts or that heavy traffic that requires a limit. So leave it empty if you can. If WP_DEBUG is false (%s), the sorted and ranked list of posts is cached.', defined( 'WP_DEBUG' ) && WP_DEBUG ? 'which it\'s NOT!' : 'which it is' ), 'konzilo-bb-personalizer' ),
			'default' => '',
			'min' => 1,
			'size' => 50,
			'disabled' => $disabled,
		];

		$fields['submit'] = [
			'type' => 'submit',
			'disabled' => $disabled,
		];

		return $fields;

	}

	private function get_acf_fields() {
		global $wpdb;
		$query = "SELECT CONCAT(post_excerpt, ' (\"', post_title, '\")') as 'title', post_excerpt as 'name' FROM $wpdb->posts where post_type = 'acf-field'";
		$fields = $wpdb->get_results( $query );
		$fields = wp_list_pluck( $fields, 'title', 'name' );
		unset( $fields[''] ); // Some ACF fields (e.g. accordion) lack name if they don't have a title, resulting in an element with an empty key. Remove it.
		return $fields;
	}

}
