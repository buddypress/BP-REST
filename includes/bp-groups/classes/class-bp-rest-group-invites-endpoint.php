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
		$this->namespace              = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base              = buddypress()->groups->id . '/invites';
		$this->groups_endpoint        = new BP_REST_Groups_Endpoint();
		$this->group_members_endpoint = new BP_REST_Group_Membership_Endpoint();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),

				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<invite_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
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
		$args = array(
			'item_id'      => $request['group_id'],
			'user_id'      => $request['user_id'],
			'inviter_id'   => $request['inviter_id'],
			'invite_sent'  => $request['invite_sent'],
			'per_page'     => $request['per_page'],
			'page'         => $request['page'],
		);

		// If the query is not restricted by group, user or inviter, limit it to the current user, if not an admin.
		if ( ! $args['item_id'] && ! $args['user_id'] && ! $args['inviter_id'] && ! bp_current_user_can( 'bp_moderate' ) ) {
			$args['user_id'] = bp_loggedin_user_id();
		}

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
		 * @param WP_REST_Response $response      The response data.
		 * @param WP_REST_Request  $request       The request sent to the API.
		 */
		do_action( 'bp_rest_group_invites_get_items', $invites_data, $response, $request );

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
		$retval  = true;
		$user_id = bp_loggedin_user_id();
		$group   = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$inviter = bp_rest_get_user( $request['inviter_id'] );

		// If the query is not restricted by group or user, limit it to the current user, if not an admin.
		if ( ! $request['group_id'] && ! $request['user_id'] && ! bp_current_user_can( 'bp_moderate' ) ) {
			$request['user_id'] = $user_id;
		}
		$user = bp_rest_get_user( $request['user_id'] );


		if ( ! $user_id ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);

			/**
			 * Users can see invitations if they
			 * - are a site admin
			 * - are a group admin of the subject group (group_id must be specified)
			 * - are the invite recipient (user_id must be specified)
			 * - are the inviter (inviter_id must be specified)
			 * So, the request must be scoped if the user is not a site admin.
			 */
		} else if ( $request['group_id'] && ! $group instanceof BP_Groups_Group ) {
			$retval = new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		// If a user ID has been passed, check that it is valid.
		} else if ( $request['user_id'] && ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		// If an inviter ID has been passed, check that it is valid.
		} else if ( $request['inviter_id'] && ! $inviter instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		// Site administrators can do anything. Otherwise, the user must manage the subject group or be the requester.
		} else if ( bp_current_user_can( 'bp_moderate' )
				|| $request['group_id'] && $this->can_see( $request['group_id'] )
				|| $request['user_id'] === $user_id
				|| $request['inviter_id'] === $user_id
			) {
			// Do nothing.
		} else {
			$retval = new WP_Error(
				'bp_rest_group_invites_not_allowed',
				__( 'Sorry, you are not allowed to fetch group invitations with those arguments.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Filter the group invites `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request can continue.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_invites_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Fetch a specific group invitation by ID.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$invites   = groups_get_invites( array( 'id' => $request['invite_id'] ) );
		$invite    = current( $invites );
		$retval    = $this->prepare_response_for_collection(
			$this->prepare_item_for_response( $invite, $request )
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a membership request is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Invitation     $invite      Invitation object.
		 * @param WP_REST_Response  $response    The response data.
		 * @param WP_REST_Request   $request     The request sent to the API.
		 */
		do_action( 'bp_rest_group_invite_get_item', $invite, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to fetch group invitation.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$user_id   = bp_loggedin_user_id();
		$invites   = groups_get_invites( array( 'id' => $request['invite_id'] ) );
		if ( $invites ) {
			$invite = current( $invites );
			/**
			 * Users can see a specific invitation if they
			 * - are a site admin
			 * - are a group admin of the subject group
			 * - are the invite recipient
			 * - are the inviter
			 */
			if ( bp_current_user_can( 'bp_moderate' ) ) {
				// Nothing to do.
			} else if ( ! $user_id ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			} else if ( ! $this->can_see( $invite->item_id )
						&& ! in_array( $user_id, array( $invite->user_id, $invite->inviter_id ), true )
						) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to fetch an invitation.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		} else {
			$retval = new WP_Error(
				'bp_rest_group_invite_invalid_id',
				__( 'Invalid group invitation id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}
		$retval = true;

		/**
		 * Filter the group membership request `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request can continue.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_invites_get_item_permissions_check', $retval, $request );
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
		$inviter_id_arg = $request['inviter_id'] ? $request['inviter_id'] : bp_loggedin_user_id();
		$group          = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$user           = bp_rest_get_user( $request['user_id'] );
		$inviter        = bp_rest_get_user( $inviter_id_arg );

		$invite_id = groups_invite_user(
			array(
				'user_id'     => $user->ID,
				'group_id'    => $group->id,
				'inviter_id'  => $inviter->ID,
				'send_invite' => isset( $request['invite_sent'] ) ? (boolean) $request['invite_sent'] : 1,
				'content'     => $request['message'],
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
		$inviter_id_arg = $request['inviter_id'] ? $request['inviter_id'] : bp_loggedin_user_id();
		$group          = $this->groups_endpoint->get_group_object( $request['group_id']  );
		$user           = bp_rest_get_user( $request['user_id'] );
		$inviter        = bp_rest_get_user( $inviter_id_arg );
		$retval         = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to create an invitation.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		} else if ( empty( $group->id ) ) {
			return new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		} else if ( ( empty( $user->ID ) || empty( $inviter->ID ) || $user->ID === $inviter->ID ) ) {
			return new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		// Only a site admin or the user herself can extend invites.
		} else if ( $inviter_id_arg !== bp_loggedin_user_id() && ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to create the invitation as requested.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		// Is this user able to extend this specific invitation?
		} else {
			$group_inv_mgr = new BP_Groups_Invitation_Manager();
			if ( ! $group_inv_mgr->allow_invitation( array(
				'user_id'    => $request['user_id'],
				'item_id'    => $request['group_id'],
				'inviter_id' => $inviter_id_arg
			) ) ) {
				$retval = new WP_Error(
					'bp_rest_group_invite_creation_disallowed',
					__( 'Sorry, you are not allowed to create the invitation as requested.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		}

		/**
		 * Filter the group invites `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request can continue.
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
		$user_id = bp_loggedin_user_id();
		$invites = groups_get_invites( array( 'id' => $request['invite_id'] ) );
		$invite  = current( $invites );

		$accept = groups_accept_invite( $invite->user_id, $invite->item_id );
		if ( ! $accept ) {
			return new WP_Error(
				'bp_rest_group_invite_cannot_accept',
				__( 'Could not accept group invitation.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$accepted_member = new BP_Groups_Member( $invite->user_id, $invite->item_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->group_members_endpoint->prepare_item_for_response( $accepted_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );
		$group    = $this->groups_endpoint->get_group_object( $invite->item_id  );

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
		$retval  = true;
		$user_id = bp_loggedin_user_id();
		$invites = groups_get_invites( array( 'id' => $request['invite_id'] ) );
		if ( ! $user_id ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		} else if ( $invites ) {
			$invite = current( $invites );
			// Only the invitee or a site admin should be able to accept an invitation, and one must exist.
			if ( bp_current_user_can( 'bp_moderate' ) ) {
				// Nothing to do.
			} else if ( $user_id !== $invite->user_id	) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to update the invitation as requested.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		} else {
			$retval = new WP_Error(
				'bp_rest_group_invite_invalid_id',
				__( 'Invalid group invitation id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Filter the group invites `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request can continue.
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
		$user_id   = bp_loggedin_user_id();
		$invites   = groups_get_invites( array( 'id' => $request['invite_id'] ) );
		$invite    = current( $invites );
		$deleted   = groups_delete_invite( $invite->user_id, $invite->item_id, $invite->inviter_id );
		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_group_invite_cannot_delete',
				__( 'Could not delete group invitation.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$deleted_invite = new BP_Invitation( $request['invite_id'] );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $deleted_invite, $request )
			),
		);

		$response = rest_ensure_response( $retval );
		$user     = bp_rest_get_user( $invite->user_id );
		$group    = $this->groups_endpoint->get_group_object( $invite->item_id  );

		/**
		 * Fires after a group invite is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user            Subject user.
		 * @param BP_Groups_Group  $group           The group object.
		 * @param WP_REST_Response $response        The response data.
		 * @param WP_REST_Request  $request         The request sent to the API.
		 */
		do_action( 'bp_rest_group_invites_delete_item', $user, $group, $response, $request );

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
		$retval  = true;
		$user_id = bp_loggedin_user_id();
		$invites = groups_get_invites( array( 'id' => $request['invite_id'] ) );
		if ( ! $user_id ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		} else if ( $invites ) {
			$invite = current( $invites );
			// The inviter, the invitee, group admins, and site admins can all delete invites.
			if ( bp_current_user_can( 'bp_moderate' ) ) {
				// Nothing to do.
			} else if ( ! in_array( $user_id, array( $invite->user_id, $invite->inviter_id ), true )
					&& ! groups_is_user_admin( $user_id, $invite->item_id )
				) {
				$retval = new WP_Error(
					'bp_rest_delete_invite_disallowed',
					__( 'Sorry, you are not allowed to delete the invitation as requested.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		} else {
			$retval = new WP_Error(
				'bp_rest_group_invite_invalid_id',
				__( 'Invalid group invitation id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Filter the group invites `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request can continue.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_invites_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares group invitation data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Invitation   $invite  Invite object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $invite, $request ) {
		$data = array(
			'id'            => $invite->id,
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
		 * @param BP_Invitation    $invite   The invite object.
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
		$url  = $base . $invite->id;

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
				'id'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID for the BP_Invitation object.', 'buddypress' ),
					'type'        => 'integer',
				),
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
							'context'     => array( 'view', 'edit' ),
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
			'required'          => false,
			'default'           => 0,
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
			'default'           => 0,
			'type'              => 'integer',
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
