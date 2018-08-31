<?php

namespace Kntnt\BB_Personalized_Posts;

class Sourcer {

	private $profile;

	public function run() {
		if ( Plugin::option( 'selector' ) && Plugin::option( 'layout_post_id' ) ) {
			add_filter( 'kntnt_personalized_content_selector', [ $this, 'get_selector' ] );
			add_filter( 'kntnt_personalized_content_output', [ $this, 'get_bb_posts_output' ], 10, 3 );
			add_filter( 'fl_builder_loop_query_args', [ $this, 'loop_query_args' ] );
			add_action( 'save_post', [ $this, 'purge_cache' ] );
			add_action( 'deleted_post', [ $this, 'purge_cache' ] );
			add_action( 'created_term', [ $this, 'purge_cache' ] );
			add_action( 'edit_term', [ $this, 'purge_cache' ] );
			add_action( 'delete_term', [ $this, 'purge_cache' ] );
		}
	}

	public function get_selector( $selector ) {
		return Plugin::option( 'selector' );
	}

	public function get_bb_posts_output( $content, $profile, $param ) {

		// Store the profile; we don't need it now, but later.
		$this->profile = $profile;

		$args = [
			'post__in' => [ Plugin::option( 'layout_post_id' ) ],
			'post_type' => [ 'fl-theme-layout', 'fl-builder-template' ],
			'ignore_sticky_posts' => true,
			'posts_per_page' => - 1,
			'orderby' => 'post__in',
		];

		ob_start();
		\FLBuilder::render_query( $args );
		wp_styles()->do_items();
		wp_scripts()->do_items();
		$out = ob_get_clean();

		return $out;

	}

	public function loop_query_args( $args ) {
		if ( 'kntnt_bb_personalized_posts' == $args['settings']->data_source ) {
			if ( $post_ids = $this->recommended_post_ids() ) {
				$args['post__in'] = $post_ids;
				$args['post_type'] = 'any';
				$args['orderby'] = 'post__in';
				unset( $args['fields'] );
				unset( $args['nopaging'] );
				unset( $args['post__not_in'] );
				unset( $args['author__not_in'] );
				unset( $args['tax_query'] );
				unset( $args['order'] );
				return $args;
			}
			else {
				return [];
			}
		}
		return $args;
	}

	public function purge_cache() {
		delete_transient( 'kntnt-bb-personalized-posts');
	}

	/**
	 * Returns an array of post ID:s sorted from most to least recommended
	 * based on the profile $profile.
	 *
	 * @param array $profile The current visitor's profile.
	 *
	 * @return array         ID:s of posts in order from most to least
	 *                       recommended based on th current users profile.
	 */
	protected function recommended_post_ids() {

		$weights = Plugin::option( 'taxonomies', [] );

		// TODO: VERIFY THAT THIS DO THE MATH CORRECT
		$score = [];
		foreach ( $this->get_posts() as $post_id => $taxonomies ) {
			$score[ $post_id ] = 0;
			foreach ( $taxonomies as $taxonomy => $terms ) {
				$profile_match_count = count( array_intersect( $terms, $this->profile[ $taxonomy ] ) );
				$profile_match_score = $weights[ $taxonomy ] / count( $this->profile[ $taxonomy ] );
				$score[ $post_id ] += $profile_match_count * $profile_match_score;
			}
		}

		arsort( $score );

		return array_keys( $score );

	}

	private function get_posts() {

		// Return cached result if existing.
		if ( false !== ( $posts = get_transient( 'kntnt-bb-personalized-posts' ) ) ) return $posts;

		// Prepare variables to use below.
		$options = array_keys( Plugin::option( 'taxonomies', [] ) );
		$in_clause = substr( str_repeat( "'%s',", count( $options ) ), 0, - 1 );
		array_unshift( $options, Plugin::option( 'post_types', 'any' ) );

		global $wpdb;

		// SQL query.
		$query = <<<SQL
			SELECT p.id AS post_id, tt.taxonomy, t.slug as term FROM $wpdb->posts AS p
			LEFT JOIN $wpdb->term_relationships AS tr ON (p.ID = tr.object_id)
			LEFT JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
			LEFT JOIN $wpdb->terms AS t ON ( tt.term_id = t.term_id)
			WHERE p.post_type = %s
			AND p.post_status = 'publish'
			AND tt.taxonomy IN ( $in_clause )
			ORDER BY p.id;
SQL;

		// Query database.
		$rows = $wpdb->get_results( $wpdb->prepare( $query, $options ) );

		// Structure result as $posts[ post_id ][ taxonomy ] = [ term, term, … ]
		$posts = [];
		foreach ( $rows as $row ) {
			$posts[ $row->post_id ][ $row->taxonomy ][] = $row->term;
		}

		// Cache the result
		set_transient( 'kntnt-bb-personalized-posts', $posts, 0 );

		return $posts;

	}

}