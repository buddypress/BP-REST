<?php
/**
 * BP REST: BP_REST_Group_Membership_Request_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Group Membership Request Endpoint.
 *
 * Use /groups/{group_id}/membership-request
 * Use /groups/membership-request/{request_id}
 * Use /groups/{group_id}/membership-request/{user_id}
 *
 * @since 0.1.0
 */
class BP_REST_Group_Membership_Request_Endpoint extends WP_REST_Controller {

	/**
	 * Reuse some parts of the BP_REST_Groups_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_REST_Groups_Endpoint
	 */
	protected $groups_endpoint;

	/**
	 * Reuse some parts of the BP_REST_Group_Invites_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_REST_Group_Invites_Endpoint
	 */
	protected $invites_endpoint;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace           = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base           = buddypress()->groups->id . '/membership-requests';
		$this->groups_endpoint     = new BP_REST_Groups_Endpoint();
		$this->invites_endpoint    = new BP_REST_Group_Invites_Endpoint();
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
			'/' . $this->rest_base . '/(?P<request_id>[\d]+)',
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
	 * Fetch pending group membership requests.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$args = array(
			'item_id'  => isset( $request['group_id'] ) ? $request['group_id'] : false,
			'user_id'  => isset( $request['user_id'] ) ? $request['user_id'] : false,
			'per_page' => isset( $request['per_page'] ) ? $request['per_page'] : 10,
			'page'     => isset( $request['page'] ) ? $request['page'] : 1,
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_group_membership_request_get_items_query_args', $args, $request );

		// If a group ID has been passed, check that it is valid.
		if ( $args['item_id'] ) {
			$group = $this->groups_endpoint->get_group_object( $args['item_id'] );

			if ( ! $group instanceof BP_Groups_Group ) {
				return new WP_Error(
					'bp_rest_group_invalid_id',
					__( 'Invalid group id.', 'buddypress' ),
					array(
						'status' => 404,
					)
				);
			}
		}

		// If a user ID has been passed, check that it is valid.
		if ( $args['user_id'] ) {
			$user = bp_rest_get_user( $args['user_id'] );

			if ( ! $user instanceof WP_User ) {
				$retval = new WP_Error(
					'bp_rest_member_invalid_id',
					__( 'Invalid member id.', 'buddypress' ),
					array(
						'status' => 404,
					)
				);
			}
		}
		$memreqs = groups_get_requests( $args );

		$retval = array();
		foreach ( $memreqs as $memreq ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->invites_endpoint->prepare_item_for_response( $memreq, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, count( $memreqs ), $args['per_page'] );

		/**
		 * Fires after a list of group membership request is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array of BP_Invitations $memreqs      List of membership requests.
		 * @param WP_REST_Response        $response     The response data.
		 * @param WP_REST_Request         $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_get_items', $memreqs, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to fetch group membership requests.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$retval       = true;
		$user_id      = bp_loggedin_user_id();
		$group_id_arg = isset( $request['group_id'] ) ? $request['group_id'] : false;
		$user_id_arg  = isset( $request['user_id'] ) ? $request['user_id'] : false;

		// Site administrators can do anything.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			// Do nothing.
		} else if ( ! $user_id ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to view membership requests.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		} else {
			/**
			 * If attempting to fetch the membership requests for a group,
			 * the group must exist and the current user must be a group admin.
			 */
			if ( $group_id_arg ) {
				$group = $this->groups_endpoint->get_group_object( $group_id_arg );

				if ( ! groups_is_user_admin( $user_id, $group->id ) ) {
					$retval = new WP_Error(
						'bp_rest_authorization_required',
						__( 'Sorry, you are not allowed to view membership requests.', 'buddypress' ),
						array(
							'status' => rest_authorization_required_code(),
						)
					);
				}
			}

			/**
			 * If attempting to fetch the membership requests for a user,
			 * the subject user should be the current user.
			 */

			if ( true === $retval && $user_id_arg ) {
				$user = bp_rest_get_user( $user_id_arg );

				if ( true === $retval && $user_id !== $user_id_arg ) {
					$retval = new WP_Error(
						'bp_rest_authorization_required',
						__( 'Sorry, you are not allowed to view membership requests.', 'buddypress' ),
						array(
							'status' => rest_authorization_required_code(),
						)
					);
				}
			}
		}

		/**
		 * Filter the `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_membership_request_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Fetch a sepcific pending group membership request by ID.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$request_id = isset( $request['request_id'] ) ? absint( $request['request_id'] ) : 0;

		// Get membership.
		$memreqs = groups_get_requests( array( 'id' => $request_id ) );

		if ( $memreqs ) {
			$memreq = current( $memreqs );
			$retval = $this->prepare_response_for_collection(
				$this->invites_endpoint->prepare_item_for_response( $memreq, $request )
			);
		} else {
			$memreq = new BP_Invitation( 0 );
			$retval = new WP_Error(
				'bp_rest_group_membership_request_invalid_id',
				__( 'Invalid group membership request id.', 'buddypress' ),
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
		 * @param BP_Invitation     $membership  Membership object.
		 * @param WP_REST_Response  $response    The response data.
		 * @param WP_REST_Request   $request     The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_get_item', $memreq, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to fetch group membership request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$retval  = true;
		$user_id = bp_loggedin_user_id();
		$request_id = isset( $request['request_id'] ) ? absint( $request['request_id'] ) : 0;

		// Site admins can view anything.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			// Do nothing.
		} else if ( ! $user_id ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to get a membership.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		} else {
			// Fetch the request
			$requests = groups_get_requests( array( 'id' => $request_id ) );
			if ( ! $requests ) {
				$retval = new WP_Error(
					'bp_rest_group_membership_request_invalid_membership',
					__( 'Invalid membership request.', 'buddypress' ),
					array(
						'status' => 404,
					)
				);
			} else {
				$request = current( $requests );

				// Group admins can see requests to their group, and the requester can see her requests.
				if ( $user_id !== $request->user_id && ! groups_is_user_admin( $user_id, $request->item_id ) ) {
					$retval = new WP_Error(
						'bp_rest_authorization_required',
						__( 'Sorry, you are not allowed to view a membership request.', 'buddypress' ),
						array(
							'status' => rest_authorization_required_code(),
						)
					);
				}

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
		return apply_filters( 'bp_rest_group_membership_request_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Request membership to a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$group_id_arg = isset( $request['group_id'] ) ? absint( $request['group_id'] ) : false;
		$user_id_arg  = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : false;
		$user         = bp_rest_get_user( $user_id_arg );
		$group        = $this->groups_endpoint->get_group_object( $group_id_arg );

		// Check for valid user.
		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'bp_rest_group_member_invalid_id',
				__( 'Invalid user id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Check for valid group.
		if ( ! $group instanceof BP_Groups_Group ) {
			return new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Avoid duplicate requests.
		if ( groups_check_for_membership_request( $user->ID, $group->id ) ) {
			return new WP_Error(
				'bp_rest_group_membership_duplicate_request',
				__( 'There is already a request to this member.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Avoid general failures.
		$group_inv_mgr = new BP_Groups_Invitation_Manager();
		if ( ! $group_inv_mgr->allow_request( array(
			'user_id'    => $user->ID,
			'item_id'    => $group->id,
		) ) ) {
			return new WP_Error(
				'bp_rest_group_member_request_disallowed',
				__( 'Sorry, you are not allowed to create the invitation as requested.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$request_id = groups_send_membership_request( array( 'group_id' => $group->id, 'user_id' => $user->ID ) );

		if ( ! $request_id ) {
			return new WP_Error(
				'bp_rest_group_membership_request_failed',
				__( 'Could not send membership request to this group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$invite = new BP_Invitation( $request_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->invites_endpoint->prepare_item_for_response( $invite, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group membership request is made via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user         The user.
		 * @param BP_Invitation    $invite       The invitation object.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_create_item', $user, $invite, $group, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to make a group membership request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$user_id     = bp_loggedin_user_id();
		$user_id_arg = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : false;
		$retval       = true;

		// Site administrators can do anything.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			// Don't stop the rock.
		// User must be logged in.
		} else if ( ! $user_id ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to create a membership request.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		// Normal users can only extend invitations on their own behalf.
		} else if ( $user_id !== $user_id_arg ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'User may not extend requests on behalf of another user.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the group membership request `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_membership_request_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Accept or reject a pending group membership request.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$group_id_arg = isset( $request['group_id'] ) ? absint( $request['group_id'] ) : false;
		$user_id_arg  = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : false;
		$user         = bp_rest_get_user( $user_id_arg );
		$group        = $this->groups_endpoint->get_group_object( $group_id_arg );

		// Check for valid user.
		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'bp_rest_group_member_invalid_id',
				__( 'Invalid user id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Check for valid group.
		if ( ! $group instanceof BP_Groups_Group ) {
			return new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Check for valid membership request.
		if ( ! groups_check_for_membership_request( $user->ID, $group->id ) ) {
			return new WP_Error(
				'bp_rest_group_membership_update_no_request',
				__( 'There is no membership request from this member to this group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$invite_id = groups_accept_membership_request( false, $user->ID, $group->id );
		if ( ! $invite_id ) {
			return new WP_Error(
				'bp_rest_group_membership_request_acceptance_failed',
				__( 'There was an error accepting the membership request.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// @TODO: Return the user membership?
		$invite = new BP_Invitation( $invite_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->invites_endpoint->prepare_item_for_response( $invite, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group membership request is accepted/rejected via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user         The user.
		 * @param BP_Invitation    $invite       The invitation object.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_update_item', $user, $invite, $group, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to accept a group membership request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$group_id_arg = isset( $request['group_id'] ) ? absint( $request['group_id'] ) : false;
		$user_id      = bp_loggedin_user_id();
		$retval       = true;

		if ( bp_current_user_can( 'bp_moderate' ) ) {
			// Do nothing.
		} else if ( ! $user_id ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to make an update.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		} else if ( ! groups_is_user_admin( $user_id, $group_id_arg ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'User is not allowed to approve membership requests to this group.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the group membership request `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_membership_request_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Reject a pending group membership request.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$user_id_arg  = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : 0;
		$user         = bp_rest_get_user( $user_id_arg );
		$group_id_arg = isset( $request['group_id'] ) ? absint( $request['group_id'] ) : 0;
		$group        = $this->groups_endpoint->get_group_object( $group_id_arg );

		// Check for valid user.
		if ( ! $user instanceof WP_User ) {
			return new WP_Error(
				'bp_rest_group_member_invalid_id',
				__( 'Invalid user id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Check for valid group.
		if ( ! $group instanceof BP_Groups_Group ) {
			return new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Check for valid membership request.
		if ( ! groups_check_for_membership_request( $user->ID, $group->id ) ) {
			return new WP_Error(
				'bp_rest_group_membership_update_no_request',
				__( 'There is no membership request from this member to this group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$success = groups_reject_membership_request( false, $user->ID, $group->id );
		if ( ! $success ) {
			return new WP_Error(
				'bp_rest_group_membership_request_rejection_failed',
				__( 'There was an error rejecting the membership request.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$invite = new BP_Invitation( 0 );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->invites_endpoint->prepare_item_for_response( $invite, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group membership request is rejected via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user         The user.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_delete_item', $user, $group, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to reject a group membership request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$user_id      = bp_loggedin_user_id();
		$group_id_arg = isset( $request['group_id'] ) ? absint( $request['group_id'] ) : 0;
		$group        = $this->groups_endpoint->get_group_object( $group_id_arg );
		$user_id_arg  = isset( $request['user_id'] ) ? absint( $request['user_id'] ) : 0;
		$allow        = true;

		// Site admins can delete requests.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			// Do nothing.
		// User must be logged in.
		} else if ( ! $user_id ) {
			$allow = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to make an update.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		// User must either be the requesting user or a group admin of the subject group.
		} else if ( $user_id !== $user_id_arg && ! groups_is_user_admin( $user_id, $group_id_arg ) ) {
			$allow = new WP_Error(
				'bp_rest_authorization_required',
				__( 'User is not allowed to delete membership requests with these parameters.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the group membership request `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $allow   Whether the request may proceed.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_membership_request_delete_item_permissions_check', $allow, $request );
	}

	/**
	 * Get the group membership request schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {

		// Get schema from the membership endpoint.
		$schema = $this->invites_endpoint->get_item_schema();

		// Set title to this endpoint.
		$schema['title'] = 'bp_group_membership_request';

		/**
		 * Filters the group membership request schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_group_membership_request_schema', $this->add_additional_fields_schema( $schema ) );
	}
}
