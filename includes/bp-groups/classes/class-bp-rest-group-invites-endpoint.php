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
	 * Reuse some parts of the BP_REST_Groups_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @param object BP_REST_Groups_Endpoint
	 */
	protected $groups_endpoint;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace       = 'buddypress/v1';
		$this->rest_base       = 'group/invites';
		$this->groups_endpoint = new BP_REST_Groups_Endpoint();
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

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
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
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		$args = array(
			'group_id'     => $group->id,
			'per_page'     => $request['per_page'],
			'page'         => $request['page'],
			'is_confirmed' => $request['is_confirmed'],
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

		$retval = array();
		foreach ( $invites_data as $invited_user ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $invited_user, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $invite_query->total_users, $args['per_page'] );

		/**
		 * Fires after a list of group invites are fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $invites_data  Invited users from the group.
		 * @param BP_Groups_Group  $group         The group object.
		 * @param WP_REST_Response $response      The response data.
		 * @param WP_REST_Request  $request       The request sent to the API.
		 */
		do_action( 'bp_rest_group_invites_get_items', $invites_data, $group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to group invitations.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		if ( ! $group ) {
			return new WP_Error( 'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_see( $group->id ) ) {
			return new WP_Error( 'bp_rest_user_cannot_view_group_invite',
				__( 'Sorry, you are not allowed to list the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Delete a group invitation.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$user  = $this->get_user( $request['id'] );

		if ( empty( $user->ID ) ) {
			return new WP_Error( 'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$request->set_param( 'context', 'edit' );

		$retval = groups_delete_invite( $user->ID, $group->id );

		if ( ! $retval ) {
			return new WP_Error( 'bp_rest_group_invite_cannot_delete',
				__( 'Could not delete group invitation.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $user, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group invite is deleted via the REST API.
		 *
		 * @since 0.1.0

		 * @param WP_REST_Response   $response The response data.
		 * @param WP_REST_Request    $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_invites_delete_item', $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a group invitation.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to delete this group invite.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		if ( ! $group ) {
			return new WP_Error( 'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_see( $group->id ) ) {
			return new WP_Error( 'bp_rest_user_cannot_delete_group_invite',
				__( 'Sorry, you are not allowed to delete this group invite.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Prepares group invitation data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $user    Invited user object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $user, $request ) {
		$data = array(
			'user_id'       => $user->ID,
			'invite_sent'   => $user->invite_sent,
			'inviter_id'    => $user->inviter_id,
			'is_confirmed'  => $user->is_confirmed,
			'membership_id' => $user->membership_id,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $user ) );

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
	 * @param stdClass $user Invited user object.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $user ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $user->ID;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $user->ID ) ),
				'embeddable' => true,
			),
		);

		return $links;
	}

	/**
	 * Get the user, if the ID is valid.
	 *
	 * Method is public to be used in unit tests as well.
	 *
	 * @since 0.1.0
	 *
	 * @param int $id Supplied ID.
	 * @return WP_User|boolean
	 */
	public function get_user( $id ) {

		if ( (int) $id <= 0 ) {
			return false;
		}

		$user = get_userdata( (int) $id );
		if ( empty( $user ) || ! $user->exists() ) {
			return false;
		}

		return $user;
	}

	/**
	 * Check access.
	 *
	 * @param int $group_id Group ID.
	 * @return boolean
	 */
	protected function can_see( $group_id ) {

		// Site administrators can do anything.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		$loggedin_user_id = bp_loggedin_user_id();
		if ( ! groups_is_user_admin( $loggedin_user_id, $group_id ) && ! groups_is_user_mod( $loggedin_user_id, $group_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the group invite schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'invite',
			'type'       => 'object',
			'properties' => array(
				'user_id'            => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID for the user object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'invite_sent'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Date on which the invite was sent.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'inviter_id'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID of the user who made the invite.', 'buddypress' ),
					'type'        => 'integer',
				),
				'membership_id'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID of the membership.', 'buddypress' ),
					'type'        => 'integer',
				),
				'is_confirmed'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Status of the invite.', 'buddypress' ),
					'type'        => 'boolean',
				),
			)
		);

		return $schema;
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

		$params['is_confirmed'] = array(
			'description'       => __( 'Limit result set to (un)confirmed invites.', 'buddypress' ),
			'default'           => false,
			'type'              => 'bollean',
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
