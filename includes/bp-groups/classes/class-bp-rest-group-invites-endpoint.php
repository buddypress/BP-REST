<?php
/**
 * BP REST: BP_REST_Group_Invites_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Group Invites endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Group_Invites_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'buddypress/v1';
		$this->rest_base = '/group/invites';
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Retrieve group invitations.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of group invites.
	 */
	public function get_items( $request ) {
		$group_id = $this->get_group_id( $request['group_id'] );

		if ( ! $group_id ) {
			return new WP_Error( 'bp_rest_invalid_group_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$args = array(
			'group_id'     => $group_id,
			'user_id'      => $request['user_id'],
			'per_page'     => $request['per_page'],
			'is_confirmed' => false,
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_group_invites_get_items_query_args', $args, $request );

		// Get invites.
		$invite_query = new BP_Group_Member_Query( $args );
		$invites_data = $invite_query->results;
		$invite_count = count( array_values( wp_list_pluck( $invite_data, 'ID' ) ) );

		$retval = array();
		foreach ( $invites_data as $invite ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $invite, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $invite_count, $args['per_page'] );

		/**
		 * Fires after a list of group invites are fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $invites_data  Fetched group invites.
		 * @param int              $group_id      The group id.
		 * @param WP_REST_Response $response      The response data.
		 * @param WP_REST_Request  $request       The request sent to the API.
		 */
		do_action( 'bp_rest_group_invites_get_items', $invites_data, $group_id, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to group invitations.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Prepares group invitation data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $invite   Invite object.
	 * @param WP_REST_Request $request  Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $invite, $request ) {

		$data = array(
			'user' => array(
				'profile_data'   => '',
				'email'         => '',
				'user_url'      => '',
				'user_link'     => '',
				'last_active'   => '',
				'total_groups'  => '',
				'total_friends' => '',
				'total_blogs'   => ''
			),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $invite ) );

		/**
		 * Filter a group invite value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_group_invite_prepare_value', $response, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass $invite Invite object.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $invite ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $invite->ID;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $invite->user->ID ) ),
				'embeddable' => true,
			),
		);

		return $links;
	}

	/**
	 * Get a group ID from its identifier.
	 *
	 * @since 0.1.0
	 *
	 * @param int $group_id Group ID.
	 * @return int|bool
	 */
	protected function get_group_id( $group_id ) {

		// Get group object.
		$group_obj = groups_get_group( array(
			'group_id' => $group_id,
		) );

		if ( empty( $group_obj->id ) ) {
			return false;
		}

		return intval( $group_obj->id );
	}

	/**
	 * Get the group invite schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
	}

	/**
	 * Get the query params for collections of group invites.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['group_id'] = array(
			'description'       => __( 'ID of the group to limit results to.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['per_page'] = array(
			'description'       => __( 'Maximum number of results returned per result set.', 'buddypress' ),
			'default'           => 20,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['page'] = array(
			'description'       => __( 'Offset the result set by a specific number of pages of results.', 'buddypress' ),
			'default'           => 1,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
