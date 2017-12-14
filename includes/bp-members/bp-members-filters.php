<?php
/**
 * BP REST: Member Filter
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replace the standard WP "author" link with a link to the BuddyPress user domain.
 *
 * @since 0.1.0
 *
 * @param WP_REST_Response $response  The response object.
 * @param object           $user      User object used to create response.
 * @param WP_REST_Request  $request   Request object.
 */
function bp_rest_filter_user_link( $response, $user, $request ) {
	if ( $user->ID ) {
		$response->data['link'] = bp_core_get_user_domain( $user->ID );
	}

	return $response;
}
add_filter( 'rest_prepare_user', 'bp_rest_filter_user_link', 10, 3 );
