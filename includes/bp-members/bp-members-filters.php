<?php
/**
 * BP REST: Member Filters/Hooks
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Removes `has_published_posts` from the query args so even users who have not
 * published content are returned by the request.
 *
 * @see https://developer.wordpress.org/reference/classes/wp_user_query/
 *
 * @since 0.1.0
 *
 * @param array           $prepared_args Array of arguments for WP_User_Query.
 * @param WP_REST_Request $request       The current request.
 * @return array
 */
function bp_remove_has_published_posts_from_wp_api_user_query( $prepared_args, $request ) {
	$namespace = bp_rest_namespace() . '/' . bp_rest_version() . '/members';

	if ( 0 !== strpos( $request->get_route(), $namespace ) ) {
		return $prepared_args;
	}

	unset( $prepared_args['has_published_posts'] );

	return $prepared_args;
}
add_filter( 'rest_user_query', 'bp_remove_has_published_posts_from_wp_api_user_query', 10, 2 );
