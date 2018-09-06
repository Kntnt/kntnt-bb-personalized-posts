<?php

namespace Kntnt\BB_Personalized_Posts;

class Sourcer {

	private $profile;

	/**
	 * Delete cached list of sorted and ranked posts.
	 */
	static public function purge_cache() {
		Plugin::log();
		delete_transient( 'kntnt-bb-personalized-posts' );
	}

	public function run() {
		if ( Plugin::is_context( 'admin' ) ) {
			add_action( 'save_post', [ 'Kntnt\BB_Personalized_Posts\Sourcer', 'purge_cache' ] );
			add_action( 'deleted_post', [ 'Kntnt\BB_Personalized_Posts\Sourcer', 'purge_cache' ] );
			add_action( 'created_term', [ 'Kntnt\BB_Personalized_Posts\Sourcer', 'purge_cache' ] );
			add_action( 'edit_term', [ 'Kntnt\BB_Personalized_Posts\Sourcer', 'purge_cache' ] );
			add_action( 'update_option_' . Plugin::ns(), [ 'Kntnt\BB_Personalized_Posts\Sourcer', 'purge_cache' ] );
		}
		else if ( Plugin::option( 'selector' ) && Plugin::option( 'layout_post_id' ) ) {
			add_filter( 'kntnt_personalized_content_selector', [ $this, 'get_selector' ] );
			add_filter( 'kntnt_personalized_content_output', [ $this, 'get_bb_posts_output' ], 10, 3 );
			add_filter( 'fl_builder_loop_query_args', [ $this, 'loop_query_args' ] );
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

		if ( 'kntnt_bb_personalized_posts' == $args['settings']->data_source ) {

			Plugin::log();

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

		$scores = [];
		$posts = $this->get_posts();
		foreach ( $posts as $post_id => $taxonomies ) {
			$scores[ $post_id ] = 0;
			foreach ( $taxonomies as $taxonomy => $terms ) {
				if ( isset( $this->profile[ $taxonomy ] ) ) {
					$scores[ $post_id ] += $this->profile_match_count( $taxonomy, $terms ) * $this->profile_match_score( $taxonomy );
				}

			}
		}

		if ( in_array( Plugin::option( 'sort_order' ), [ 'as-is', 'random' ] ) ) {
			arsort( $scores );
		}
		else {
			$this->stable_arsort( $scores );
		}

		if ( Plugin::is_debugging() ) {

			// Log the user's profile
			$msg = "\n\tUser profile:";
			foreach ( $this->profile as $taxonomy => $terms ) {
				$msg .= "\n\t\t$taxonomy: " . join( ', ', array_map( function ( $e ) { return "'$e'"; }, $terms ) ) . " where each match gives " . $this->profile_match_score( $taxonomy ) . " points";
			}

			// Log the posts profile and accumulated points (score)
			foreach ( $scores as $post_id => $score ) {
				$msg .= "\n\tPost $post_id get $score points based on matching between user's profile and the post's profile:";
				foreach ( $posts[ $post_id ] as $taxonomy => $terms ) {
					$msg .= "\n\t\t$taxonomy: " . join( ', ', array_map( function ( $e ) { return "'$e"; }, $terms ) ) . " where " . $this->profile_match_count( $taxonomy, $terms ) . " match(es)";
				}
			}

			Plugin::log( $msg );

		}

		return array_keys( $scores );

	}

	private function get_posts() {

		global $wpdb;

		Plugin::log();

		// Return cached result if existing and we aren't debugging.
		if ( ! Plugin::is_debugging() && false !== ( $posts = get_transient( 'kntnt-bb-personalized-posts' ) ) ) {
			Plugin::log( "Returns cached posts." );
			return $posts;
		}

		// In post types clause
		list( $options[], $placeholders[] ) = $this->db_option( array_keys( Plugin::option( 'post_types', [] ) ), '%s' );

		// In taxonomies clause
		list( $options[], $placeholders[] ) = $this->db_option( array_intersect( array_keys( Plugin::option( 'taxonomies', [] ) ), array_keys( $this->profile ) ), '%s' );

		// In terms clause
		list( $options[], $placeholders[] ) = $this->db_option( array_reduce( $this->profile, 'array_merge', [] ), '%s' );

		// Sort order clause
		if ( 'as-is' != Plugin::option( 'sort_order' ) ) {
			$sort_order = ( [
				'id-asc' => 'ORDER BY p.ID ASC',
				'id-desc' => 'ORDER BY p.ID DESC',
				'created-asc' => 'ORDER BY p.post_date ASC',
				'created-desc' => 'ORDER BY p.post_date DESC',
				'modified-asc' => 'ORDER BY p.post_modified ASC',
				'modified-desc' => 'ORDER BY p.post_modified DESC',
				'comments-asc' => 'ORDER BY p.comment_count ASC',
				'comments-desc' => 'ORDER BY p.comment_count DESC',
				'title-asc' => 'ORDER BY p.post_title ASC',
				'title-desc' => 'ORDER BY p.post_title DESC',
				'author-asc' => 'ORDER BY p.post_author ASC',
				'author-desc' => 'ORDER BY p.post_author DESC',
				'random' => 'ORDER BY Rand()',
			] )[ Plugin::option( 'sort_order', 'random' ) ];
		}
		else {
			$sort_order = '';
		}

		// Limit clause
		if ( Plugin::option( 'db_limit' ) ) {
			$limit = 'LIMIT ' . Plugin::option( 'db_limit' );
		}
		else {
			$limit = '';
		}

		$query = <<<SQL
			SELECT p.id AS post_id, tt.taxonomy, t.slug as term FROM $wpdb->posts AS p
			LEFT JOIN $wpdb->term_relationships AS tr ON (p.ID = tr.object_id)
			LEFT JOIN $wpdb->term_taxonomy AS tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
			LEFT JOIN $wpdb->terms AS t ON ( tt.term_id = t.term_id)
			WHERE p.post_status = 'publish'
			AND p.post_type IN ( {$placeholders[0]} )
			AND tt.taxonomy IN ( {$placeholders[1]} )
			AND t.slug IN ( {$placeholders[2]} )
			$sort_order
			$limit;
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

	private function profile_match_score( $taxonomy ) {
		$weights = Plugin::option( 'taxonomies', [] );
		return $weights[ $taxonomy ] / count( $this->profile[ $taxonomy ] );
	}

	private function profile_match_count( $taxonomy, $terms ) {
		return count( array_intersect( $terms, $this->profile[ $taxonomy ] ) );
	}

	/**
	 * Array sort is not stable in PHP (since 4.1.0); the order of two elements
	 * that compare equal is not guaranteed to be as before sorting. This
	 * method provides a stable alternative to {@link http://php.net/manual/en/function.arsort.php arsort()}.
	 *
	 * Credit: Martijn van der Lee (see {@link http://vanderlee.github.io/PHP-stable-sort-functions/ PHP stable sort}
	 *
	 * @param array $array      The array to be sorted.
	 * @param int   $sort_flags See {@link http://php.net/manual/en/function.sort.php sort()}
	 *
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	private function stable_arsort( array &$array, $sort_flags = SORT_REGULAR ) {

		$index = 0;

		foreach ( $array as &$item ) {
			$item = [ $index ++, $item ];
		}

		$result = uasort( $array, function ( $a, $b ) use ( $sort_flags ) {
			if ( $a[1] == $b[1] ) {
				return $a[0] - $b[0];
			}
			$set = [ - 1 => $b[1], 1 => $a[1] ];
			asort( $set, $sort_flags );
			reset( $set );
			return key( $set );
		} );

		foreach ( $array as &$item ) {
			$item = $item[1];
		}

		return $result;

	}

	private function db_option( $option, $format ) {
		if ( is_array( $option ) ) {
			$format = substr( str_repeat( "$format,", count( $option ) ), 0, - 1 );
		}
		else {
			$option = [ $option ];
		}
		return [ $option, $format ];
	}

}