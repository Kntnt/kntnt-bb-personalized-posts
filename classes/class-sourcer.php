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

		Plugin::log();

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

		Plugin::log();

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
		Plugin::log();
		delete_transient( 'kntnt-bb-personalized-posts' );
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

		Plugin::log();

		$weights = Plugin::option( 'taxonomies', [] );

		$scores = [];
		$posts = $this->get_posts();
		foreach ( $posts as $post_id => $taxonomies ) {
			$scores[ $post_id ] = 0;
			foreach ( $taxonomies as $taxonomy => $terms ) {
				if ( isset( $this->profile[ $taxonomy ] ) && isset( $weights[ $taxonomy ] ) ) {
					$profile_match_count = count( array_intersect( $terms, $this->profile[ $taxonomy ] ) );
					$profile_match_score = $weights[ $taxonomy ] / count( $this->profile[ $taxonomy ] );
					$scores[ $post_id ] += $profile_match_count * $profile_match_score;
				}

			}
		}

		arsort( $scores );

		if ( Plugin::is_debugging() ) {
			array_walk( $scores, function ( $score, $post_id ) use ( $posts, $weights ) {
				$msg = [];
				foreach ( $posts[ $post_id ] as $taxonomy => $terms ) {
					$msg[] = '  - ' . join( ', ', array_intersect( $terms, $this->profile[ $taxonomy ] ) ) . " in $taxonomy, where each match gives " . $weights[ $taxonomy ] . " / " . count( $this->profile[ $taxonomy ] ) . " = " . ( $weights[ $taxonomy ] / count( $this->profile[ $taxonomy ] ) ) . " points";
				}
				$msg = join(" and\n", $msg);
				$msg = "Post $post_id scored $score due to following matches:\n$msg";
				Plugin::log( $msg );
			} );
		}

		return array_keys( $scores );

	}

	private function get_posts() {

		global $wpdb;

		Plugin::log();

		$sort_alternatives = [
			'id-asc' => 'ID ASC',
			'id-desc' => 'ID DESC',
			'created-asc' => 'post_date ASC',
			'created-desc' => 'post_date DESC',
			'modified-asc' => 'post_modified ASC',
			'modified-desc' => 'post_modified DESC',
			'comments-asc' => 'comment_count ASC',
			'comments-desc' => 'comment_count DESC',
			'title-asc' => 'post_title ASC',
			'title-desc' => 'post_title DESC',
			'author-asc' => 'post_author ASC',
			'author-desc' => 'post_author DESC',
			'random' => 'Rand()',
		];

		// Return cached result if existing and we aren't debugging.
		if ( ! Plugin::is_debugging() && false !== ( $posts = get_transient( 'kntnt-bb-personalized-posts' ) ) ) {
			Plugin::log( "Returns cached posts." );
			return $posts;
		}

		// In post types clause
		$options[] = array_keys( Plugin::option( 'post_types', [] ) );

		// In taxonomies clause
		$options[] = array_intersect( array_keys( Plugin::option( 'taxonomies', [] ) ), array_keys( $this->profile ) );

		// In terms clause
		$options[] = array_reduce( $this->profile, 'array_merge', [] );

		// Placeholders
		foreach ( $options as $option ) {
			$placeholders[] = substr( str_repeat( "%s,", count( $option ) ), 0, - 1 );
		}

		$sort_order = ( [
			'id-asc' => 'p.ID ASC',
			'id-desc' => 'p.ID DESC',
			'created-asc' => 'p.post_date ASC',
			'created-desc' => 'p.post_date DESC',
			'modified-asc' => 'p.post_modified ASC',
			'modified-desc' => 'p.post_modified DESC',
			'comments-asc' => 'p.comment_count ASC',
			'comments-desc' => 'p.comment_count DESC',
			'title-asc' => 'p.post_title ASC',
			'title-desc' => 'p.post_title DESC',
			'author-asc' => 'p.post_author ASC',
			'author-desc' => 'p.post_author DESC',
			'random' => 'Rand()',
		] )[ Plugin::option( 'sort_order', 'random' ) ];

		$query = <<<SQL
			SELECT p.id AS post_id, tt.taxonomy, t.slug as term FROM $wpdb->posts AS p
			LEFT JOIN $wpdb->term_relationships AS tr ON (p.ID = tr.object_id)
			LEFT JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
			LEFT JOIN $wpdb->terms AS t ON ( tt.term_id = t.term_id)
			WHERE p.post_type IN ( {$placeholders[0]} )
			AND p.post_status = 'publish'
			AND tt.taxonomy IN ( {$placeholders[1]} )
			AND t.slug IN ( {$placeholders[2]} )
			ORDER BY $sort_order;
SQL;

		$query = $wpdb->prepare( $query, array_reduce( $options, 'array_merge', [] ) );
		Plugin::log( "Database query:\n%s", $query );

		// Structure result as $posts[ post_id ][ taxonomy ] = [ term, term, … ]
		$posts = [];
		foreach ( $wpdb->get_results( $query ) as $row ) {
			$posts[ $row->post_id ][ $row->taxonomy ][] = $row->term;
		}

		// Cache the result
		set_transient( 'kntnt-bb-personalized-posts', $posts, 0 );

		Plugin::log( "Returns database posts." );
		return $posts;

	}

}