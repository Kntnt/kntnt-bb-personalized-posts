<?php

namespace Kntnt\BB_Personalized_Posts;


class Scorer {

	private $taxonomy_field_map;

	private $weights;

	private $taxonomies;

	private $priority_score;

	public function __construct() {
		$this->taxonomy_field_map = Plugin::option( 'priority_custom_fields', [] );
		$this->weights = Plugin::option( 'taxonomies', [] );
		$this->taxonomies = array_keys( $this->weights );
		$this->priority_score = Plugin::option( 'priority_score', 0 );
	}

	public function run() {
		add_filter( 'kntnt_bb_personalized_posts_calculate_scores', [ $this, 'calculate_scores' ], 10, 3 );
	}

	public function calculate_scores( $scores, $posts, $visitor_profile ) {

		Plugin::log();

		// For each posts that should be scored, get its post id and its terms
		// and calculate its score.
		foreach ( $posts as $post_id => $post_profile ) {

			// Create a score entry and set it to 0 if not already existing.
			if ( ! isset( $scores[ $post_id ] ) ) {
				$scores[ $post_id ] = 0;
			}

			// Count the number of matches between the priority profile of the
			// post under consideration and the visitor's profile.
			$priority_match_count = - 1;

			// For each taxonomy that are used for scoring…
			foreach ( $this->taxonomies as $taxonomy ) {

				// If the taxonomy under consideration exists in both the post's
				// and the visitor's profile, add to the score a contribution
				// based on the match.
				if ( isset( $post_profile[ $taxonomy ] ) && isset( $visitor_profile[ $taxonomy ] ) ) {
					$scores[ $post_id ] += $this->profile_match_count( $taxonomy, $post_profile, $visitor_profile ) * $this->visitor_profile_match_score( $taxonomy, $visitor_profile );
				}

				// If $priority_match_count == 0, let it be 0.
				// If $priority_match_count != 0 and the taxonomy under
				// consideration exists in both the post's priority profile and
				// the visitor's profile, count matching terms. If none, set
				// $priority_match_count to 0. Otherwise, add the found matches
				// to $priority_match_count. This value is used below to
				// determine if the post under consideration should get
				// additional score to reflect it has priority.
				$priority_match_count = $this->priority_profile_match_count( $taxonomy, $visitor_profile, $post_id, $priority_match_count );

			}

			// If the post under consideration has priority,
			// add the priority score.
			if ( $priority_match_count > 0 ) {
				$scores[ $post_id ] += $this->priority_score;
			}

		}

		if ( Plugin::is_debugging() ) {
			Plugin::log( $this->reasoning( $scores, $visitor_profile ) );
		}

		return $scores;

	}

	public function get_priority_profile( $post_id ) {
		$priority_profile = [];
		foreach ( $this->taxonomy_field_map as $taxonomy => $field ) {
			if ( $terms = $this->get_priority_profile_terms( $post_id, $taxonomy ) ) {
				$priority_profile[ $taxonomy ] = $terms;
			}
		}
		return $priority_profile;
	}

	public function get_priority_profile_terms( $post_id, $taxonomy ) {
		return Plugin::get_field( $this->taxonomy_field_map[ $taxonomy ], $post_id );
	}

	private function profile_match_count( $taxonomy, $post_profile, $visitor_profile ) {

		// Count the number of terms in common between the post's and the
		// visitor's profile.
		return count( array_intersect( $post_profile[ $taxonomy ], $visitor_profile[ $taxonomy ] ) );

	}

	private function visitor_profile_match_score( $taxonomy, $visitor_profile ) {

		// Each taxonomy has a weight (see settings page). This weight is
		// divided equally among the visitor's terms in the taxonomy.
		return $this->weights[ $taxonomy ] / count( $visitor_profile[ $taxonomy ] );

	}

	private function priority_profile_match_count( $taxonomy, $visitor_profile, $post_id, $priority_match_count ) {

		// If the assumption that the post has priority still stands
		// true, and the taxonomy under consideration exists in the
		// visitors profile…
		if ( 0 != $priority_match_count && isset( $visitor_profile[ $taxonomy ] ) ) {

			// If the taxonomy under consideration exists in the
			// posts's priority profile…
			if ( $priority_profile_terms = $this->get_priority_profile_terms( $post_id, $taxonomy ) ) {

				// We have found a non empty priority profile. Let's count.
				if ( $priority_match_count < 0 ) {
					$priority_match_count = 0;
				}

				// Count the number of matches between the post's priority
				// profile and visitor's profile.
				$count = count( array_intersect( $visitor_profile[ $taxonomy ], $priority_profile_terms ) );

				// If matches were found, add the number of found matches to
				// the counter. Set the counter to 0 otherwise, signaling
				// mismatch, preventing priority.
				if ( $count ) {
					$priority_match_count += $count;
				}
				else {
					$priority_match_count = 0;
				}

			}

		}

		return $priority_match_count;

	}

	private function reasoning( $scores, $visitor_profile ) {

		// User's profile
		$msg = "\n\tVisitor profile:";
		foreach ( $visitor_profile as $taxonomy => $terms ) {
			$msg .= "\n\t\t$taxonomy: " . join( ', ', array_map( function ( $e ) { return "'$e'"; }, $terms ) ) . " where each match gives " . $this->visitor_profile_match_score( $taxonomy, $visitor_profile ) . " points";
		}

		foreach ( $scores as $post_id => $score ) {

			$terms = wp_get_object_terms( $post_id, $this->taxonomies);
			$terms = wp_list_pluck($terms, 'taxonomy', 'slug');
			$post_profile = [];
			foreach($terms as $term => $taxonomy) {
				$post_profile[$taxonomy][] = $term;
			}

			$msg .= "\n\n\tPost $post_id gets $score points.";

			// Post's regular profile.
			$msg .= "\n\t\tRegular profile:";
			foreach ( $post_profile as $taxonomy => $terms ) {
				$msg .= "\n\t\t\t$taxonomy: " . join( ', ', array_map( function ( $e ) { return "'$e'"; }, $terms ) );
			}

			// Post's regular profile matches.
			$msg .= "\n\t\tRegular matches:";
			$priority_match_count = - 1;
			foreach ( $this->taxonomies as $taxonomy ) {
				if ( isset( $post_profile[ $taxonomy ] ) && isset( $visitor_profile[ $taxonomy ] ) ) {
					$msg .= "\n\t\t\t$taxonomy: " . $this->profile_match_count( $taxonomy, $post_profile, $visitor_profile ) . ' match(es)';
				}
				$priority_match_count = $this->priority_profile_match_count( $taxonomy, $visitor_profile, $post_id, $priority_match_count );
			}

			// Post's priority profile (if any).
			if ( $priority_profile = $this->get_priority_profile( $post_id ) ) {
				$msg .= "\n\t\tPriority profile:";
				foreach ( $priority_profile as $taxonomy => $terms ) {
					$msg .= "\n\t\t\t$taxonomy: " . join( ', ', array_map( function ( $e ) { return "'$e'"; }, $terms ) );
				}
			}

			// Post's priority profile matches.
			if ( $priority_match_count > 0 ) {
				$msg .= "\n\t\tPriority match.";
			}

		}

		return $msg;

	}

}