<?php
namespace WPMeta;

/**
 * Function register a new model's data to the global models variable.
 *
 * @param [string] $prefix Unique prefix to add to each model key.
 * @param [array]  $model An Associative array of model info.
 * @param [string] $name Unique Model Name. Should Be similar to the post type name.
 * @return void
 */
function register_model( $prefix, $model, $name ) {
	global $wp_models;

	$wp_models[ $name ] = array_map(
		function ( $data ) use ( $prefix ) {
				$data['key'] = sprintf( '%s_%s', $prefix, $data['key'] );
				return $data;
		}, $model
	);
}

/**
 * Function will fetch a model from the wp_models global. If a key is passed then this
 * function will return the model data for that specific key.
 *
 * @param [string]           $name Model name.
 * @param [boolean | string] $key false | Name or key of model info you want to retrieve.
 * @return mixed $key = false then function returns an associative array for the whole model,
 *               If a key is passed then you will get an associative array of data for just that key,
 *               If the model is not found, then the function will return false.
 */
function get_model( $name, $key = false ) {
	global $wp_models;

	if ( isset( $wp_models[ $name ] ) ) {
		return get_meta_key_value( $wp_models[ $name ], $key );
	}
	return false;
}

/**
 * Helper function which makes it easy to get a post meta key by its short
 * name defined in the model.
 *
 * @param [array]  $keys An array of all model keys.
 * @param [string] $key An model key you want.
 * @return array|string array if no $key is passed, or the model key if a $key is passed.
 */
function get_meta_key_value( $keys, $key ) {
	return ( $key ) ? $keys[ $key ]['key'] : $keys;
}

/**
 * Helper function which uses the model data to add that data to the REST API response. Items
 * added to the response allow you to fetch and update those values via the API. If you set
 * a model item's show_in_rest to true then this function will add it to the rest API.
 *
 * @param [array]        $model All the registered model keys.
 * @param [type]         $post_type The post type you want to process.
 * @param boolean|string $modify_keys optional string which can be used to modify the key name
 * which will show up in the rest api response. i.e passing crossfield_team will convert
 * crossfield_team_bio into just bio in the rest api response.
 * @return void
 */
function register_model_meta( $model, $post_type, $modify_keys = false, $type = 'post' ) {

	foreach ( $model as $item ) {
		if ( ! isset( $item['show_in_rest'] ) || false === $item['show_in_rest'] ) {
			continue;
		}

		$get_func = function( $object ) use ( $item, $type ) {
			$metadata = get_type_meta( $type, $object['id'], $item['key'] );

			if ( isset( $item['get_cb'] ) && is_callable( $item['get_cb'] ) ) {
				$metadata = call_user_func_array( $item['get_cb'], [ $metadata ] );
			}

			return $metadata;
		};

		if ( $modify_keys ) {
			$item['key'] = str_replace( sprintf( '%s_', $modify_keys ), '', $item['key'] );
		}

		$update_func = function( $value, $object ) use ( $item, $type ) {
			$sanitization_cb = get_sanitization_cb( $item );
			$result          = call_user_func_array( $sanitization_cb, [ $value ] );

			if ( isset( $item['update_cb'] ) && is_callable( $item['update_cb'] ) ) {
				return call_user_func_array( $item['update_cb'], [ $item, $object->ID, $result ] );
			}
			return update_type_meta( $type, $object->ID, $item['key'], $result );
		};

		register_rest_field(
			$post_type,
			$item['key'],
			array(
				'get_callback'    => $get_func,
				'update_callback' => $update_func,
				'schema'          => null,
			)
		);
	}

}

/**
 * Helper function used by register_model_meta to sanitize post fields which it
 * adds to the rest response.
 *
 * @param [array] $item An item from the model.
 * @return string The string name of the sanitization function to use when update a post
 * meta item via the rest api.
 */
function get_sanitization_cb( $item ) {
	if ( isset( $item['sanitization_cb'] ) && is_callable( $item['sanitization_cb'] ) ) {
		return $item['sanitization_cb'];
	}

	switch ( $item['type'] ) {
		case 'text':
			return 'sanitize_text_field';
		case 'email':
			return 'sanitize_email';
		case 'textarea':
			return 'sanitize_textarea_field';
		case 'number':
			return 'absint';
		case 'color':
			return 'sanitize_hex_color';
		default:
			return 'sanitize_text_field';
	}
}

/**
 * Wrapper function which determines which type of meta we need based on the input type.
 *
 * @param [string]     $type Object Type Name. (post, user, or term).
 * @param [int|string] $object_id The Object ID.
 * @param [string]     $key The meta key to retrieve.
 * @return mixed
 */
function get_type_meta( $type, $object_id, $key ) {
	if ( 'post' === $type ) {
		return get_post_meta( $object_id, $key, true );
	} elseif ( 'user' === $type ) {
		return get_user_meta( $object_id, $key, true );
	} elseif ( 'term' === $type ) {
		return get_term_meta( $object_id, $key, true );
	}
	return '';
}

/**
 * Wrapper function which handles updating the right meta by type.
 *
 * @param [string]     $type Object Type Name. (post, user, or term).
 * @param [int|string] $object_id The Object ID.
 * @param [string]     $key The meta key to retrieve.
 * @param [mixed]      $result The Data to save.
 * @return boolean
 */
function update_type_meta( $type, $object_id, $key, $result ) {
	if ( 'post' === $type ) {
		return update_post_meta( $object_id, $key, $result );
	} elseif ( 'user' === $type ) {
		return update_user_meta( $object_id, $key, $result );
	} elseif ( 'term' === $type ) {
		return update_term_meta( $object_id, $key, $result );
	}
	return true;
}
