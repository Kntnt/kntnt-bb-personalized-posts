<?php

namespace Kntnt\BB_Personalized_Posts;

class Cache {

	private $ns;

	private $keys = null;

	private $keys_changed = false;

	public function __construct() {

		$this->ns = Plugin::ns();
		$this->keys = Plugin::option( 'cache_keys', [] );

		add_action( 'shutdown', function () {
			if ( $this->keys_changed ) {
				$this->save_keys();
			}
		}, 100 );

	}

	/**
	 *  Delete all cached items.
	 */
	public function purge() {
		foreach ( $this->keys as $key => $dummy ) {
			if ( delete_transient( $key ) ) {
				unset( $this->keys[ $key ] );
				$this->keys_changed = true;
			}
		}
		return $this->keys;
	}

	/**
	 *  Returns the cached value uniquely identified by $uid, or the value of
	 *  $default if no cached value exists. If $default is {http://php.net/manual/en/language.types.callable.php callable},
	 *  it is evaluated with the  optional provided arguments.
	 */
	public function get( $key ) {
		return get_transient( $key );
	}

	/**
	 * Saves $value in the cache uniquely identified by $uid. The value will
	 * remain in cache at longest the time in seconds given by $expiration.
	 */
	public function set( $key, $value, $expiration = DAY_IN_SECONDS ) {
		$is_set = set_transient( $key, $value, $expiration );
		if ( $is_set ) {
			$this->keys[ $key ] = true;
			$this->keys_changed = true;
		}
		return $is_set;
	}

	/**
	 * Deletes the cached value uniquely identified by $uid.
	 */
	public function delete( $key ) {
		$is_deleted = delete_transient( $key );
		if ( $is_deleted ) {
			unset( $this->keys[ $key ] );
			$this->keys_changed = true;
		}
		return $is_deleted;
	}

	/**
	 * Creates a key that uniquely identifies the combination of arguments passed.
	 */
	public function create_key( ...$keys ) {
		if ( is_scalar( $keys ) ) {
			$keys = [ $keys ];
		}
		return $this->ns . '_' . md5( array_reduce( $keys, function ( $str, $key ) { return $str . json_encode( $key ); }, '' ) );
	}

	public function save_keys() {
		Plugin::set_option( 'cache_keys', $this->keys );
	}

}