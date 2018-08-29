<?php
/**
 * BP REST: common functions.
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

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
