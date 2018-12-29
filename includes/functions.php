<?php
/**
 * BP REST: common functions.
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get user url with new.
 *
 * @todo Update members path to the filterable one.
 *
 * @since 0.1.0
 *
 * @param  int $user_id User ID.
 * @return string
 */
function bp_rest_get_user_url( $user_id ) {
	return sprintf( '/buddypress/v1/members/%d', $user_id );
}

/**
 * Set headers to let the Client Script be aware of the pagination.
 *
 * @since 0.1.0
 *
 * @param  WP_REST_Response $response The response data.
 * @param  integer          $total    The total number of found items.
 * @param  integer          $per_page The number of items per page of results.
 * @return WP_REST_Response $response The response data.
 */
function bp_rest_response_add_total_headers( WP_REST_Response $response, $total = 0, $per_page = 0 ) {
	if ( ! $total || ! $per_page ) {
		return $response;
	}

	$total_items = (int) $total;
	$max_pages   = ceil( $total_items / (int) $per_page );

	$response->header( 'X-WP-Total', $total_items );
	$response->header( 'X-WP-TotalPages', (int) $max_pages );

	return $response;
}

/**
 * Convert the input date to RFC3339 format.
 *
 * @since 0.1.0
 *
 * @param string      $date_gmt Date GMT format.
 * @param string|null $date Optional. Date object.
 * @return string|null ISO8601/RFC3339 formatted datetime.
 */
function bp_rest_prepare_date_response( $date_gmt, $date = null ) {
	if ( isset( $date ) ) {
		return mysql_to_rfc3339( $date );
	}

	if ( '0000-00-00 00:00:00' === $date_gmt ) {
		return null;
	}

	return mysql_to_rfc3339( $date_gmt );
}

/**
 * Clean up member_type input.
 *
 * @since 0.1.0
 *
 * @param string $value Comma-separated list of group types.
 *
 * @return array|null
 */
function bp_rest_sanitize_member_types( $value ) {
	if ( ! empty( $value ) ) {
		$types              = explode( ',', $value );
		$registered_types   = bp_get_member_types();
		$registered_types[] = 'any';
		$valid_types        = array_intersect( $types, $registered_types );

		return ( ! empty( $valid_types ) ) ? $valid_types : null;
	}
	return $value;
}

/**
 * Validate member_type input.
 *
 * @since 0.1.0
 *
 * @param  mixed           $value   Mixed value.
 * @param  WP_REST_Request $request Full details about the request.
 * @param  string          $param   String.
 *
 * @return WP_Error|boolean
 */
function bp_rest_validate_member_types( $value, $request, $param ) {
	if ( ! empty( $value ) ) {
		$types            = explode( ',', $value );
		$registered_types = bp_get_member_types();

		// Add the special value.
		$registered_types[] = 'any';
		foreach ( $types as $type ) {
			if ( ! in_array( $type, $registered_types, true ) ) {
				/* translators: %1$s and %2$s is replaced with the registered types */
				return new WP_Error( 'rest_invalid_group_type', sprintf( __( 'The member type you provided, %$1s, is not one of %$2s.' ), $type, implode( ', ', $registered_types ) ) );
			}
		}
	}

	return true;
}
