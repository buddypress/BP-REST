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
		$this->namespace              = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base              = buddypress()->groups->id . '/membership-requests';
		$this->groups_endpoint        = new BP_REST_Groups_Endpoint();
		$this->group_members_endpoint = new BP_REST_Group_Membership_Endpoint();
		$this->invites_endpoint       = new BP_REST_Group_Invites_Endpoint();
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
			'/' . $this->rest_base . '/(?P<request_id>[\d]+)',
			array(
				'args'   => array(
					'request_id' => array(
						'description' => __( 'A unique numeric ID for the group membership request.', 'buddypress' ),
						'type'        => 'integer',
					),
				),
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
	 * Fetch pending group membership requests.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$args = array(
			'item_id'  => $request['group_id'],
			'user_id'  => $request['user_id'],
			'per_page' => $request['per_page'],
			'page'     => $request['page'],
		);

		// If the query is not restricted by group or user, limit it to the current user, if not an admin.
		if ( ! $args['item_id'] && ! $args['user_id'] && ! bp_current_user_can( 'bp_moderate' ) ) {
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
		$args = apply_filters( 'bp_rest_group_membership_request_get_items_query_args', $args, $request );

		$memreqs = groups_get_requests( $args );

		$retval = array();
		foreach ( $memreqs as $memreq ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $memreq, $request )
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
		$retval      = true;
		$user_id     = bp_loggedin_user_id();
		$user_id_arg = $request['user_id'];
 		$group       = $this->groups_endpoint->get_group_object( $request['group_id'] );

		// If the query is not restricted by group or user, limit it to the current user, if not an admin.
		if ( ! $request['group_id'] && ! $request['user_id'] && ! bp_current_user_can( 'bp_moderate' ) ) {
			$user_id_arg = $user_id;
		}
		$user = bp_rest_get_user( $user_id_arg );

		if ( ! $user_id ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to view membership requests.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		// If a group ID has been passed, check that it is valid.
		} else if ( $request['group_id'] && ! $group instanceof BP_Groups_Group ) {
			$retval = new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		// If a user ID has been passed, check that it is valid.
		} else if ( $user_id_arg && ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		// Site administrators can do anything. Otherwise, the user must manage the subject group or be the requester.
		} else if ( bp_current_user_can( 'bp_moderate' )
				|| $request['group_id'] && groups_is_user_admin( $user_id, $request['group_id'] )
				|| $user_id_arg === $user_id ) {
				// Do nothing.
		} else {
			$retval = new WP_Error(
				'bp_rest_group_membership_requests_cannot_get_items',
				__( 'Sorry, you are not allowed to view membership requests.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Filter the `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request can continue.
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
		$memreq = $this->fetch_single_invite( array( 'id' => $request['request_id'] ) );
		$retval = $this->prepare_response_for_collection(
			$this->prepare_item_for_response( $memreq, $request )
		);

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
		$memreq  = $this->fetch_single_invite( array( 'id' => $request['request_id'] ) );
		if ( $memreq ) {
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
			} else if ( $user_id !== $request->user_id && ! groups_is_user_admin( $user_id, $request->item_id ) ) {
				$retval = new WP_Error(
					'bp_rest_group_membership_requests_cannot_get_item',
					__( 'Sorry, you are not allowed to view a membership request.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		} else {
			$retval = new WP_Error(
				'bp_rest_group_membership_request_invalid_id',
				__( 'Invalid group membership request id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Filter the group membership request `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request can continue.
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
		$user_id_arg  = $request['user_id'] ? $request['user_id'] : bp_loggedin_user_id();
		$user         = bp_rest_get_user( $user_id_arg );
		$group        = $this->groups_endpoint->get_group_object( $request['group_id'] );

		// Avoid duplicate requests.
		if ( groups_check_for_membership_request( $user->ID, $group->id ) ) {
			return new WP_Error(
				'bp_rest_group_membership_request_duplicate_request',
				__( 'There is already a request to this member.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$request_id = groups_send_membership_request( array(
			'group_id' => $group->id,
			'user_id'  => $user->ID,
			'content'  => $request['message'],
		) );

		if ( ! $request_id ) {
			return new WP_Error(
				'bp_rest_group_membership_request_cannot_create_item',
				__( 'Could not send membership request to this group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$invite = new BP_Invitation( $request_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $invite, $request )
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
		$retval      = true;
		$user_id     = bp_loggedin_user_id();
		$user_id_arg = $request['user_id'] ? $request['user_id'] : bp_loggedin_user_id();
		$user        = bp_rest_get_user( $user_id_arg );
		$group       = $this->groups_endpoint->get_group_object( $request['group_id'] );

		// Check for valid user.
		if ( ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_group_member_invalid_id',
				__( 'Invalid user id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		// Check for valid group.
		} else if ( ! $group instanceof BP_Groups_Group ) {
			$retval = new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		// Site administrators can do anything.
		} else if ( bp_current_user_can( 'bp_moderate' ) ) {
			// Do nothing.
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
				'bp_rest_group_membership_request_cannot_create_item',
				__( 'User may not extend requests on behalf of another user.', 'buddypress' ),
				array(
					'status' => 500,
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
		$memreq = $this->fetch_single_invite( array( 'id' => $request['request_id'] ) );
		$success = groups_accept_membership_request( false, $memreq->user_id, $memreq->item_id );
		if ( ! $success ) {
			return new WP_Error(
				'bp_rest_group_member_request_cannot_update_item',
				__( 'There was an error accepting the membership request.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$g_member = new BP_Groups_Member( $memreq->user_id, $memreq->item_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->group_members_endpoint->prepare_item_for_response( $g_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );
		$group    = $this->groups_endpoint->get_group_object( $memreq->item_id  );

		/**
		 * Fires after a group membership request is accepted/rejected via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Groups_Member $g_member     The groups member object.
  		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_update_item', $g_member, $group, $response, $request );

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
		$retval  = true;
		$user_id = bp_loggedin_user_id();
		$memreq  = $this->fetch_single_invite( array( 'id' => $request['request_id'] ) );
		if ( $memreq ) {
			// Site admins can do anything.
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
			} else if ( ! groups_is_user_admin( $user_id, $request->item_id ) ) {
				$retval = new WP_Error(
					'bp_rest_group_member_request_cannot_update_item',
					__( 'User is not allowed to approve membership requests to this group.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		} else {
			$retval = new WP_Error(
				'bp_rest_group_membership_request_invalid_id',
				__( 'Invalid group membership request id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Filter the group membership request `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request can continue.
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
		$memreq  = $this->fetch_single_invite( array( 'id' => $request['request_id'] ) );
		$success = groups_reject_membership_request( false, $memreq->user_id, $memreq->item_id );
		if ( ! $success ) {
			return new WP_Error(
				'bp_rest_group_membership_request_cannot_delete_item',
				__( 'There was an error rejecting the membership request.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$invite = new BP_Invitation( $request['request_id'] );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $invite, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		$user = bp_rest_get_user( $memreq->user_id );
		$group = $this->groups_endpoint->get_group_object( $memreq->item_id );

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
		$retval  = true;
		$user_id = bp_loggedin_user_id();
		$memreq  = $this->fetch_single_invite( array( 'id' => $request['request_id'] ) );
		if ( $memreq ) {
			// Site admins can view anything.
			if ( bp_current_user_can( 'bp_moderate' ) ) {
				// Do nothing.
			} else if ( ! $user_id ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you need to be logged in to delete a request.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			} else if ( $user_id !== $request->user_id && ! groups_is_user_admin( $user_id, $request->item_id ) ) {
				$retval = new WP_Error(
					'bp_rest_group_membership_request_cannot_delete_item',
					__( 'User is not allowed to delete this membership request.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		} else {
			$retval = new WP_Error(
				'bp_rest_group_membership_request_invalid_id',
				__( 'Invalid group membership request id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Filter the group membership request `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Whether the request may proceed.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_membership_request_delete_item_permissions_check', $retval, $request );
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
			'date_modified' => bp_rest_prepare_date_response( $invite->date_modified ),
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
		return apply_filters( 'bp_rest_group_membership_requests_prepare_value', $response, $request, $invite );
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
			'description'       => __( 'Return only requests sent by this user.', 'buddypress' ),
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

	/**
	 * Helper function to fetch a single group invite.
	 *
	 * @since 0.1.0
	 *
	 * @return BP_Invitation|bool $memreq Membership request if found, false otherwise.
	 */
	public function fetch_single_invite( $args = array() ) {
		$memreqs = groups_get_requests( $args );
		if ( $memreqs ) {
			return current( $memreqs );
		} else {
			return false;
		}
	}
}
