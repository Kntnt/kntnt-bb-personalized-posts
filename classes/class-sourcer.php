<?php

namespace Konzilo\BB_Personalizer;

class Sourcer {

	private $profile;

	private $cache;

	private $post_types = [];

	public function __construct() {

		$this->ns = Plugin::ns();

		$this->cache = Plugin::instance( 'Cache' );

		add_action( 'konzilo/content-intelligence/init', function ( $ci ) {
			if ( Plugin::is_context( 'ajax' ) ) {
				$this->post_types = $ci->post_types();
				Plugin::log( 'CI post types: %s', join( ', ', $this->post_types ) );
			}
		} );

	}

	public function run() {
		if ( Plugin::is_context( 'admin' ) ) {
			add_action( 'save_post', [ $this->cache, 'purge' ] );
			add_action( 'deleted_post', [ $this->cache, 'purge' ] );
			add_action( 'created_term', [ $this->cache, 'purge' ] );
			add_action( 'edit_term', [ $this->cache, 'purge' ] );
			add_action( 'update_option_' . Plugin::ns(), [ $this->cache, 'purge' ] );
		}
		else if ( Plugin::option( 'selector' ) && Plugin::option( 'layout_post_id' ) ) {
			add_filter( 'konzilo/personalizer/selector', [ $this, 'get_selector' ] );
			add_filter( 'konzilo/personalizer/output', [ $this, 'get_bb_posts_output' ], 10, 3 );
			add_filter( 'fl_builder_loop_query_args', [ $this, 'loop_query_args' ] );
		}
	}

	public function get_selector( $selector ) {
		return Plugin::option( 'selector' );
	}

	public function get_bb_posts_output( $content, $profile, $param ) {

		Plugin::log();

		/* TODO: REMOVE THIS WHEN BB BUG(?) IS FIXED.
		 * Beaver Builder Page Builder (BBPB) 2.1.4.5 works without this
		 * workaround but 2.1.5.2 and 2.1.6.3 don't. The difference is that
		 * jquery-imagesloaded is enqueued in 2.1.4.5 but not in 2.1.5.2 and
		 * 2.1.6.3
		 *
		 * It seems that BBPB from version 2.1.5.2 instead of
		 * jquery-imagesloaded use /wp-includes/js/imagesloaded.min.js. Since
		 * jquery-imagesloaded is a jQuery plugin and imagesloaded is not,
		 * BBPB have been rewritten with respect to how this code is loaded
		 * and used.
		 *
		 * But it seems to exist dependencies of jquery-imagesloaded that have
		 * not yet been fixed. At least the Post Grid module has such dependency
		 * (see line 83 in …/bb-plugin/modules/post-grid/js/frontend.js).
		 *
		 * If not another plugin or theme (e.g. Beaver Builder Framework Theme)
		 * enqueue jquery-imagesloaded, a layout using post grid will not be
		 * shown and following error message are written to the console:
		 * "TypeError: wrap.imagesLoaded is not a function".
		 *
		 * The workaround below enqueue the version of jquery.imagesloaded
		 * used by the BBPB 2.1.4.5.
		 */
		wp_enqueue_script( 'jquery-imagesloaded', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.imagesloaded/3.2.0/imagesloaded.min.js', [ 'jquery' ] );

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

		if ( 'konzilo_bb_personalizer' == $args['settings']->data_source ) {

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

	protected function calculate_scores() {

		Plugin::log();

		$posts = $this->get_posts();

		/**
		 * Filter the score table.
		 * By default Konzilo\BB_Personalizer\Scorer implements this filter.
		 */
		$scores = apply_filters( 'konzilo_bb_personalizer_calculate_scores', [], $posts, $this->profile );

		if ( in_array( Plugin::option( 'sort_order' ), [ 'as-is', 'random' ] ) ) {
			arsort( $scores );
		}
		else {
			$this->stable_arsort( $scores );
		}

		return array_keys( $scores );

	}

	private function recommended_post_ids() {

		Plugin::log();

		if ( Plugin::is_debugging() ) {

			$scores = $this->calculate_scores();

		}
		else {

			$key = $this->cache->create_key( [ 'profile_content_scores', $this->profile ] );
			$scores = $this->cache->get( $key );

			if ( false === $scores ) {
				$scores = $this->calculate_scores();
				$this->cache->set( $key, $scores, 0 );
			}

		}

		return $scores;

	}

	private function get_posts() {

		global $wpdb;

		// In post types clause
		list( $options[], $placeholders[] ) = $this->db_option( $this->post_types, '%s' );

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

		return $posts;

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

}
