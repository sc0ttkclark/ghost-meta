<?php
/*
Plugin Name: Ghost Meta
Plugin URI: https://www.scottkclark.com/
Description: Ghost meta allows you to store multiple meta values in a single meta record, with an function API that mirrors the Metadata API.
Version: 1.0
Author: Scott Kingsley Clark
Author URI: https://www.scottkclark.com/
Text Domain: ghost-meta

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Load Ghost Meta
add_action( 'plugins_loaded', array( 'Ghost_Meta', 'get_instance' ) );

class Ghost_Meta {

	/**
	 * Ghost Meta class instance
	 *
	 * @var Ghost_Meta
	 */
	protected static $instance;

	/**
	 * Meta type for Ghost meta.
	 *
	 * @param string
	 */
	public $meta_type = 'ghost';

	/**
	 * Meta key for Ghost meta storage.
	 *
	 * @param string
	 */
	public $meta_key = '_ghost_meta';

	/**
	 * Setup instance if not initiated, then return class object.
	 *
	 * @return Ghost_Meta
	 */
	public static function get_instance() {

		$class_name = get_called_class();

		if ( ! static::$instance || ! is_a( static::$instance, $class_name ) ) {
			static::$instance = new static;
		}

		return static::$instance;

	}

	/**
	 * Add hooks for Ghost meta.
	 */
	private function __construct() {

		if ( ! has_filter( 'get_' . $this->meta_type . '_metadata', array( $this, 'get_metadata' ) ) ) {
			add_filter( 'get_' . $this->meta_type . '_metadata', array( $this, 'get_metadata' ), 10, 4 );
		}

		if ( ! has_filter( 'add_' . $this->meta_type . '_metadata', array( $this, 'add_metadata' ) ) ) {
			add_filter( 'add_' . $this->meta_type . '_metadata', array( $this, 'add_metadata' ), 10, 5 );
		}

		if ( ! has_filter( 'update_' . $this->meta_type . '_metadata', array( $this, 'update_metadata' ) ) ) {
			add_filter( 'update_' . $this->meta_type . '_metadata', array( $this, 'update_metadata' ), 10, 5 );
		}

		if ( ! has_filter( 'delete_' . $this->meta_type . '_metadata', array( $this, 'delete_metadata' ) ) ) {
			add_filter( 'delete_' . $this->meta_type . '_metadata', array( $this, 'delete_metadata' ), 10, 5 );
		}

		if ( ! has_filter( 'ep_post_sync_args', array( $this, 'ep_post_sync_args' ) ) ) {
			add_filter( 'ep_post_sync_args', array( $this, 'ep_post_sync_args' ), 10, 2 );
		}

		include_once 'functions.php';

	}

	/**
	 * Filter get_metadata for Ghost Meta.
	 *
	 * @param null|array|string $value     The value get_metadata() should return - a single metadata value,
	 *                                     or an array of values.
	 * @param int               $object_id Object ID.
	 * @param string            $meta_key  Meta key.
	 * @param bool              $single    Whether to return only the first value of the specified $meta_key.
	 *
	 * @return array|string
	 */
	public function get_metadata( $value, $object_id, $meta_key, $single ) {

		$meta_info = $this->get_meta_info_from_key( $meta_key );
		$meta_type = $meta_info['type'];
		$meta_key  = $meta_info['key'];

		$meta = $this->get_ghost_meta( $meta_type, $object_id );

		if ( ! $meta_key ) {
			return $meta;
		}

		if ( isset( $meta[ $meta_key ] ) ) {
			if ( $single ) {
				$value = maybe_unserialize( $meta[ $meta_key ][0] );
			} else {
				$value = array_map( 'maybe_unserialize', $meta[ $meta_key ] );
			}
		} elseif ( $single ) {
			$value = '';
		} else {
			$value = array();
		}

		return $value;

	}

	/**
	 * Filter add_metadata for Ghost Meta.
	 *
	 * @param null|bool $check      Whether to allow adding metadata for the given type.
	 * @param int       $object_id  Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
	 * @param bool      $unique     Whether the specified meta key should be unique
	 *                              for the object. Optional. Default false.
	 *
	 * @return int|false The meta ID on success, false on failure.
	 */
	public function add_metadata( $check, $object_id, $meta_key, $meta_value, $unique = false ) {

		$_meta_key = $meta_key;

		$meta_info = $this->get_meta_info_from_key( $meta_key );
		$meta_type = $meta_info['type'];
		$meta_key  = $meta_info['key'];

		$meta = $this->get_ghost_meta( $meta_type, $object_id );

		$_meta_value = $meta_value;
		$meta_value  = maybe_serialize( $meta_value );

		if ( ! isset( $meta[ $meta_key ] ) ) {
			$meta[ $meta_key ] = array();
		}

		$meta[ $meta_key ][] = $meta_value;

		$check = $this->update_ghost_meta( $meta_type, $object_id, $meta );

		if ( false !== $check ) {
			$check = 1;
		}

		$mid = 0;

		/**
		 * Fires immediately after meta of a specific type is added.
		 *
		 * @since 1.0
		 *
		 * @param int    $mid        The meta ID after successful update.
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		do_action( 'added_' . $this->meta_type . '_meta', $mid, $object_id, $_meta_key, $_meta_value );

		return $check;

	}

	/**
	 * Filter update_metadata for Ghost Meta.
	 *
	 * @param null|bool $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
	 * @param mixed     $prev_value Optional. If specified, only update existing
	 *                              metadata entries with the specified value.
	 *                              Otherwise, update all entries.
	 *
	 * @return bool True on successful update, false on failure.
	 */
	public function update_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {

		$_meta_key = $meta_key;

		$meta_info = $this->get_meta_info_from_key( $meta_key );
		$meta_type = $meta_info['type'];
		$meta_key  = $meta_info['key'];

		$meta = $this->get_ghost_meta( $meta_type, $object_id );

		$_meta_value = $meta_value;
		$meta_value  = maybe_serialize( $meta_value );

		if ( ! isset( $meta[ $meta_key ] ) ) {
			$meta[ $meta_key ] = array();
		}

		$changed = false;

		if ( $prev_value ) {
			$prev_value = maybe_serialize( $prev_value );

			// Update meta values if they match $prev_value
			foreach ( $meta[ $meta_key ] as $prev_meta_k => $prev_meta_v ) {
				if ( $prev_value == $prev_meta_v ) {
					if ( is_bool( $prev_meta_v ) && (boolean) $prev_value === $prev_meta_v ) {
						// Strict matching for booleans
						$meta[ $meta_key ][ $prev_meta_k ] = $meta_value;

						$changed = true;
					} elseif ( is_int( $prev_meta_v ) && (int) $prev_value === $prev_meta_v ) {
						// Strict matching for integers
						$meta[ $meta_key ][ $prev_meta_k ] = $meta_value;

						$changed = true;
					} else {
						// Loose matching like MySQL would do by default
						$meta[ $meta_key ][ $prev_meta_k ] = $meta_value;

						$changed = true;
					}
				}
			}
		} elseif ( 1 !== count( $meta[ $meta_key ] ) || $meta_value !== $meta[ $meta_key ][0] ) {
			// Handle changes only if the value does not match
			$changed = true;

			// Update default is to remove all meta values and set as one single value
			$meta[ $meta_key ] = array(
				$meta_value
			);
		}

		$check = false;

		if ( $changed ) {
			$check = $this->update_ghost_meta( $meta_type, $object_id, $meta );

			if ( false !== $check ) {
				$check = 1;
			}
		}

		$meta_id = 0;

		/**
		 * Fires immediately after updating metadata of a specific type.
		 *
		 * @since 1.0
		 *
		 * @param int    $meta_id    ID of updated metadata entry. (not currently used)
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		do_action( 'updated_' . $this->meta_type . '_meta', $meta_id, $object_id, $_meta_key, $_meta_value );

		return $check;

	}

	/**
	 * Filter delete_metadata for Ghost Meta.
	 *
	 * @param null|bool $delete     Whether to allow metadata deletion of the given type.
	 * @param int       $object_id  Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
	 * @param bool      $delete_all Whether to delete the matching metadata entries
	 *                              for all objects, ignoring the specified $object_id.
	 *                              Default false.
	 *
	 * @return bool True on successful delete, false on failure.
	 */
	public function delete_metadata( $delete, $object_id, $meta_key, $meta_value, $delete_all ) {

		$_meta_key = $meta_key;

		$meta_info = $this->get_meta_info_from_key( $meta_key );
		$meta_type = $meta_info['type'];
		$meta_key  = $meta_info['key'];

		$meta = $this->get_ghost_meta( $meta_type, $object_id );

		$delete  = false;
		$changed = false;

		$_meta_value = $meta_value;

		$meta_ids = array();

		if ( $delete_all ) {
			// Handle deleting all values in ghost meta for this key
			/**
			 * @var $wpdb wpdb
			 */
			global $wpdb;

			$table       = _get_meta_table( $meta_type );
			$type_column = sanitize_key( $meta_type . '_id' );

			if ( ! $table ) {
				return false;
			}

			// Build like value so we only get ghost meta for objects that have something that matches
			$like_value = sprintf(
				'%%s:%d:%s%%', // Example: %s:7:example%
				mb_strlen( $meta_key ),
				$wpdb->esc_like( $meta_key )
			);

			// Get all objects in this meta
			$object_ids_sql = $wpdb->prepare(
				"
					SELECT {$type_column}
					FROM {$table}
					WHERE
						meta_key = %s
						AND meta_value LIKE %s
				",
				$this->meta_key,
				$like_value
			);

			$object_ids = $wpdb->get_col( $object_ids_sql );

			foreach ( $object_ids as $o_id ) {
				$check_deleted = $this->delete_metadata( null, $o_id, $_meta_key, $meta_value, false );

				if ( $check_deleted ) {
					$delete = 1;
				}
			}
		} elseif ( isset( $meta[ $meta_key ] ) ) {
			if ( '' !== $meta_value && null !== $meta_value && false !== $meta_value ) {
				// Handle deleting only values that match
				$meta_value = maybe_serialize( $meta_value );

				// Update meta values if they match $prev_value
				foreach ( $meta[ $meta_key ] as $prev_meta_k => $prev_meta_v ) {
					if ( $meta_value == $prev_meta_v ) {
						if ( is_bool( $prev_meta_v ) && (boolean) $meta_value === $prev_meta_v ) {
							// Strict matching for booleans
							unset( $meta[ $meta_key ][ $prev_meta_k ] );

							$changed = true;
						} elseif ( is_int( $prev_meta_v ) && (int) $meta_value === $prev_meta_v ) {
							// Strict matching for integers
							unset( $meta[ $meta_key ][ $prev_meta_k ] );

							$changed = true;
						} else {
							// Loose matching like MySQL would do by default
							unset( $meta[ $meta_key ][ $prev_meta_k ] );

							$changed = true;
						}
					}
				}

				// Rekey the values
				if ( $changed ) {
					$meta[ $meta_key ] = array_values( $meta[ $meta_key ] );
				}
			} else {
				// Remove the whole array of data
				unset( $meta[ $meta_key ] );

				$changed = true;
			}
		}

		if ( $changed ) {
			$delete = $this->update_ghost_meta( $meta_type, $object_id, $meta );

			if ( false !== $delete ) {
				$delete = 1;
			}
		}

		/**
		 * Fires immediately after deleting Ghost metadata.
		 *
		 * @since 1.0
		 *
		 * @param array  $meta_ids   An array of deleted metadata entry IDs. (not currently used)
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		do_action( 'deleted_' . $this->meta_type . '_meta', $meta_ids, $object_id, $_meta_key, $_meta_value );

		return $delete;

	}

	/**
	 * Hook into ElasticPress post arguments to add our ghost meta, just as if it were normal meta.
	 *
	 * @param array $post_args Post arguments to send to Elasticsearch.
	 * @param int   $post_id   Post ID.
	 *
	 * @return array Post arguments to send to Elasticsearch.
	 */
	public function ep_post_sync_args( $post_args, $post_id ) {

		$meta = $this->get_ghost_meta( 'post', $post_id );

		if ( ! empty( $meta ) ) {
			// Merge ghost meta values
			$post_args['post_meta'] = array_merge( $post_args['post_meta'], $meta );
		}

		return $post_args;

	}

	/**
	 * Get Ghost Meta from the object.
	 *
	 * @param string $meta_type Meta type.
	 * @param int    $object_id Object ID.
	 *
	 * @return array Ghost meta
	 */
	protected function get_ghost_meta( $meta_type, $object_id ) {

		$meta = get_metadata( $meta_type, $object_id, $this->meta_key, true );

		if ( empty( $meta ) || ! is_array( $meta ) ) {
			$meta = array();
		}

		return $meta;

	}

	/**
	 * Update Ghost Meta for the object.
	 *
	 * @param string $meta_type Meta type.
	 * @param int    $object_id Object ID.
	 * @param array  $meta      Ghost meta.
	 *
	 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	protected function update_ghost_meta( $meta_type, $object_id, $meta ) {

		return update_metadata( $meta_type, $object_id, $this->meta_key, $meta );

	}

	/**
	 * Get meta info from a meta key.
	 *
	 * Examples: some_meta_value, post.some_meta_value, user.some_meta_value
	 *
	 * @param string $meta_key Meta key
	 *
	 * @return array Meta info
	 */
	protected function get_meta_info_from_key( $meta_key ) {

		$meta_info = array(
			'type' => 'post',
			'key'  => $meta_key,
		);

		if ( false !== strpos( $meta_key, '.' ) ) {
			$meta_key_data = explode( '.', $meta_key );

			if ( 2 === count( $meta_key_data ) ) {
				$meta_info['type'] = $meta_key_data[0];
				$meta_info['key']  = $meta_key_data[1];
			}
		}

		return $meta_info;

	}

}