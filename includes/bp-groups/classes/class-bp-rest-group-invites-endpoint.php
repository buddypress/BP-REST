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
 * Use /groups/{group_id}/invites
 * Use /groups/{group_id}/invites/{user_id}
 *
 * @since 0.1.0
 */
class BP_REST_Group_Invites_Endpoint extends WP_REST_Controller {

	/**
	 * Reuse some parts of the BP_REST_Groups_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_REST_Groups_Endpoint
	 */
	protected $groups_endpoint;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace       = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base       = buddypress()->groups->id;
		$this->groups_endpoint = new BP_REST_Groups_Endpoint();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<group_id>[\d]+)/invites',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<group_id>[\d]+)/invites/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieve group invitations.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		$args = array(
			'item_id'      => $group->id,
			'user_id'      => isset( $request['user_id'] ) ? $request['user_id'] : false,
			// 'inviter_id'   => isset( $request['inviter_id'] ) ? $request['inviter_id'] : false,
			'invite_sent'  => isset( $request['invite_sent'] ) ? $request['invite_sent'] : 'sent',
			'per_page'     => $request['per_page'],
			'page'         => $request['page'],
		);
// return rest_ensure_response( $args );
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
		$invites_data = groups_get_invites( $args );

		$retval = array();
		foreach ( $invites_data as $invitation ) {
			if ( $invitation instanceof BP_Invitation ) {
				$retval[] = $this->prepare_response_for_collection(
					$this->prepare_item_for_response( $invitation, $request )
				);
			}
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, count( $invites_data ), $args['per_page'] );

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
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );
		if ( true === $retval && ! $group instanceof BP_Groups_Group ) {
			$retval = new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! $this->can_see( $group->id ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the group invites `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_invites_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Invite a member to a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$group   = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$user    = bp_rest_get_user( $request['user_id'] );
		$inviter = bp_rest_get_user( $request['inviter_id'] );

		if ( ( empty( $user->ID ) || empty( $inviter->ID ) || $user->ID === $inviter->ID ) ) {
			return new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$invite_id = groups_invite_user(
			array(
				'user_id'     => $user->ID,
				'group_id'    => $group->id,
				'inviter_id'  => $inviter->ID,
				'send_invite' => isset( $request['send_invite'] ) ? (boolean) $request['send_invite'] : 1,
			)
		);

		if ( ! $invite_id ) {
			return new WP_Error(
				'bp_rest_group_invite_cannot_invite',
				__( 'Could not invite member to the group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$invite = new BP_Invitation( $invite_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $invite, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a member is invited to a group via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Invitation    $invite         The invitation object.
		 * @param WP_User          $user           The invited user.
		 * @param WP_User          $inviter        The inviter user.
		 * @param BP_Groups_Group  $group          The group object.
		 * @param WP_REST_Response $response       The response data.
		 * @param WP_REST_Request  $request        The request sent to the API.
		 */
		do_action( 'bp_rest_group_invites_create_item', $invite, $user, $inviter, $group, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to invite a member to a group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$retval = $this->get_items_permissions_check( $request );

		/**
		 * Filter the group invites `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_invites_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Accept a group invitation.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$user  = bp_rest_get_user( $request['user_id'] );

		if ( empty( $user->ID ) ) {
			return new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$accept = groups_accept_invite( $user->ID, $group->id );
		if ( ! $accept ) {
			return new WP_Error(
				'bp_rest_group_invite_cannot_accept',
				__( 'Could not accept group invitation.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$accepted_member = new BP_Groups_Member( $user->ID, $group->id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $accepted_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group invite is accepted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Groups_Member $accepted_member Accepted group member.
		 * @param BP_Groups_Group  $group           The group object.
		 * @param WP_REST_Response $response        The response data.
		 * @param WP_REST_Request  $request         The request sent to the API.
		 */
		do_action( 'bp_rest_group_invites_update_item', $accepted_member, $group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to accept a group invitation.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$retval = $this->get_items_permissions_check( $request );

		/**
		 * Filter the group invites `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_invites_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Remove (reject/delete) a group invitation.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$user  = bp_rest_get_user( $request['user_id'] );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$deleted = groups_delete_invite( $user->ID, $group->id );
		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_group_invite_cannot_delete',
				__( 'Could not delete group invitation.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$deleted_member = new BP_Groups_Member( $user->ID, $group->id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $deleted_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group invite is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Groups_Member $deleted_member  Deleted group member.
		 * @param BP_Groups_Group  $group           The group object.
		 * @param WP_REST_Response $response        The response data.
		 * @param WP_REST_Request  $request         The request sent to the API.
		 */
		do_action( 'bp_rest_group_invites_delete_item', $deleted_member, $group, $response, $request );

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
		$retval = $this->get_items_permissions_check( $request );

		/**
		 * Filter the group invites `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_invites_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares group invitation data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $invite  Invite object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $invite, $request ) {
		$data = array(
			'user_id'       => $invite->user_id,
			'invite_sent'   => $invite->invite_sent,
			'inviter_id'    => $invite->inviter_id,
			'group_id'      => $invite->item_id,
			'date_modified' => $invite->date_modified,
			'type'          => $invite->type,
			'message'       => array(
				'raw'      => $invite->content,
				'rendered' => apply_filters( 'the_content', $invite->content ),
			),
		);

		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $invite ) );

		/**
		 * Filter a group invite value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 * @param stdClass         $invite   The invite object.
		 */
		return apply_filters( 'bp_rest_group_invites_prepare_value', $response, $request, $invite );
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
		$url  = $base . $invite->item_id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $invite->user_id ) ),
				'embeddable' => true,
			),
		);

		/**
		 * Filter links prepared for the REST response.
		 *
		 * @since 0.1.0
		 *
		 * @param array    $links  The prepared links of the REST response.
		 * @param stdClass $invite Invite object.
		 */
		return apply_filters( 'bp_rest_group_invites_prepare_links', $links, $invite );
	}

	/**
	 * Check access.
	 *
	 * @param int $group_id Group ID.
	 * @return bool
	 */
	protected function can_see( $group_id ) {
		$user_id = bp_loggedin_user_id();

		if ( ! groups_is_user_admin( $user_id, $group_id ) && ! groups_is_user_mod( $user_id, $group_id ) ) {
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
			'title'      => 'bp_group_invites',
			'type'       => 'object',
			'properties' => array(
				'user_id'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID for the user object.', 'buddypress' ),
					'type'        => 'integer',
				),
				'invite_sent'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the invite has been sent to the invitee.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'boolean',
				),
				'inviter_id'   => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID of the user who made the invite.', 'buddypress' ),
					'type'        => 'integer',
				),
				'group_id'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID of the group to which the user has been invited.', 'buddypress' ),
					'type'        => 'integer',
				),
				'date_modified' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the object was created or last updated, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'type'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Invitation or request.', 'buddypress' ),
					'type'        => 'string',
				),
				'message'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Content of the object.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null,
						'validate_callback' => null,
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Content for the object, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'HTML content for the object, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),

			),
		);

		/**
		 * Filters the group invites schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_group_invites_schema', $this->add_additional_fields_schema( $schema ) );
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

		$params['group_id']   = array(
			'description'       => __( 'ID of the group to limit results to.', 'buddypress' ),
			'required'          => true,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id']    = array(
			'description'       => __( 'Return only invitations extended to this user.', 'buddypress' ),
			'required'          => false,
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['inviter_id'] = array(
			'description'       => __( 'Return only invitations extended by this user.', 'buddypress' ),
			'required'          => false,
			'default'           => false,
			// 'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['invite_sent'] = array(
			'description'       => __( 'Limit result set to invites that have been sent, not sent, or include all.', 'buddypress' ),
			'default'           => 'sent',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_group_invites_collection_params', $params );
	}
}
