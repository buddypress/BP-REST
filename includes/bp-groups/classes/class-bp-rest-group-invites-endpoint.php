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
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user_id>[\d]+)',
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
		$args = array(
			'item_id'      => isset( $request['group_id'] ) ? $request['group_id'] : false,
			'user_id'      => isset( $request['user_id'] ) ? $request['user_id'] : false,
			'inviter_id'   => ! empty( $request['inviter_id'] ) ? $request['inviter_id'] : false,
			'invite_sent'  => isset( $request['invite_sent'] ) ? $request['invite_sent'] : 'sent',
			'per_page'     => $request['per_page'],
			'page'         => $request['page'],
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
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);

		} else {
			$user_id        = bp_loggedin_user_id();
			$group_id_arg   = isset( $request['group_id'] ) ? $request['group_id'] : false;
			$user_id_arg    = isset( $request['user_id'] ) ? $request['user_id'] : false;
			$inviter_id_arg = isset( $request['inviter_id'] ) ? $request['inviter_id'] : false;

			/**
			 * Users can see invitations if they
			 * - are a site admin
			 * - are a group admin of the subject group (group_id must be specified)
			 * - are the invite recipient (user_id must be specified)
			 * - are the inviter (inviter_id must be specified)
			 * So, the request must be scoped if the user is not a site admin.
			 */
			if ( bp_current_user_can( 'bp_moderate' )
				|| $group_id_arg && $this->can_see( $group_id_arg )
				|| $user_id_arg === $user_id
				|| $inviter_id_arg === $user_id
			) {
				$retval = true;
			} else {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to fetch group invitations with those arguments.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
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
	 * Fetch a specific group invitation by ID.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		// Get membership.
		$invites = groups_get_invites( array( 'id' => $request['invite_id'] ) );

		if ( $invites ) {
			$invite = current( $invites );
			$retval = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $invite, $request )
			);
		} else {
			$invite = new BP_Invitation( 0 );
			$retval = new WP_Error(
				'bp_rest_group_invite_invalid_id',
				__( 'Invalid group invitation id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

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
		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to see the group invitations.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		} else {
			$user_id      = bp_loggedin_user_id();
			$this->invite = new BP_Invitation( absint( $request['invite_id'] ) );

			if ( is_null( $this->invite->user_id ) || is_null( $this->invite->item_id ) ) {
				$retval = new WP_Error(
					'bp_rest_group_invitation_invalid_invite',
					__( 'Invalid invitation.', 'buddypress' ),
					array(
						'status' => 404,
					)
				);

			/**
			 * Users can see a specific invitation if they
			 * - are a site admin
			 * - are a group admin of the subject group
			 * - are the invite recipient
			 * - are the inviter
			 */
			} else if ( bp_current_user_can( 'bp_moderate' )
					|| $this->can_see( $this->invite->item_id )
					|| in_array( $user_id, array( $this->invite->user_id, $this->invite->inviter_id ) )
				) {
				$retval = true;
			} else {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to fetch an invitation.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		/**
		 * Filter the group membership request `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
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
		$group   = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$user    = bp_rest_get_user( $request['user_id'] );
		$inviter = bp_rest_get_user( $request['inviter_id'] );

		if ( empty( $group->id ) ) {
			return new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

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
		$group_id_arg   = isset( $request['group_id'] ) ? $request['group_id'] : false;
		$user_id_arg    = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : false;
		$inviter_id_arg = isset( $request['inviter_id'] ) ? absint( $request['inviter_id'] ) : false;
		$retval         = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to create an invitation.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
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
				'user_id'    => $user_id_arg,
				'item_id'    => $group_id_arg,
				'inviter_id' => $inviter_id_arg
			) ) ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to create the invitation as requested.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

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
		// Only the invitee or a site admin should be able to accept an invitation, and one must exist.
		$group_id = isset( $request['group_id'] ) ? $request['group_id'] : false;
		$user_id  = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : false;
		$group    = $this->groups_endpoint->get_group_object( $group_id );
		$user     = bp_rest_get_user( $user_id );

		if ( ! $user instanceof WP_User  ) {
			return new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $group instanceof BP_Groups_Group ) {
			return new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! groups_check_user_has_invite( $user->ID, $group->id ) ) {
			return new WP_Error(
				'bp_rest_no_invitation_exists',
				__( 'No invitation exists matching your parameters.', 'buddypress' ),
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
				$this->group_members_endpoint->prepare_item_for_response( $accepted_member, $request )
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
		$retval = true;

		// Only the invitee or a site admin should be able to accept an invitation, and one must exist.
		$group_id = isset( $request['group_id'] ) ? $request['group_id'] : false;
		$user_id  = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : false;

		if ( $user_id !== bp_loggedin_user_id() && ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to update the invitation as requested.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

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
		$args = array(
			'item_id'      => isset( $request['group_id'] ) ? $request['group_id'] : false,
			'user_id'      => isset( $request['user_id'] ) ? $request['user_id'] : false,
			'inviter_id'   => ! empty( $request['inviter_id'] ) ? $request['inviter_id'] : false,
			'invite_sent'  => isset( $request['invite_sent'] ) ? $request['invite_sent'] : 'sent',
			'per_page'     => $request['per_page'],
			'page'         => $request['page'],
		);

		$group = $this->groups_endpoint->get_group_object( $args['item_id'] );
		$user  = bp_rest_get_user( $args['user_id'] );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $group instanceof BP_Groups_Group ) {
			return new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( $args['inviter_id'] ) {
			$inviter = bp_rest_get_user( $args['inviter_id'] );
			if ( ! $inviter instanceof WP_User ) {
				return new WP_Error(
					'bp_rest_inviter_invalid_id',
					__( 'Invalid inviter id.', 'buddypress' ),
					array(
						'status' => 404,
					)
				);
			}
		}

		// Get invites--just sending a user ID and group ID may return more invites than this user can delete.
		$deleted      = false;
		$invites_data = groups_get_invites( $args );

		foreach ( $invites_data as $invite ) {
			if ( bp_current_user_can( 'bp_moderate' )
				|| in_array( bp_loggedin_user_id(), array( $invite->user_id, $invite->inviter_id ), true ) ) {
				$deleted = groups_delete_invite( $invite->user_id, $invite->item_id, $invite->inviter_id );
			}
		}

		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_group_invite_cannot_delete',
				__( 'Could not delete group invitation.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$deleted_invite = new BP_Invitation( 0 );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $deleted_invite, $request )
			),
		);

		$response = rest_ensure_response( $retval );

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
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to delete the invitation as requested.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);

			// We need to find the actual invitation to make this check.
			$args = array(
				'item_id'      => isset( $request['group_id'] ) ? $request['group_id'] : false,
				'user_id'      => isset( $request['user_id'] ) ? $request['user_id'] : false,
				'inviter_id'   => ! empty( $request['inviter_id'] ) ? $request['inviter_id'] : false,
				'invite_sent'  => isset( $request['invite_sent'] ) ? $request['invite_sent'] : 'sent',
				'per_page'     => $request['per_page'],
				'page'         => $request['page'],
			);

			// Get invites.
			$invites_data = groups_get_invites( $args );

			foreach ( $invites_data as $invite ) {
				if ( in_array( bp_loggedin_user_id(), array( $invite->user_id, $invite->inviter_id ), true ) ) {
					$retval = true;
					break;
				}
			}
		}

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
			'required'          => false,
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
