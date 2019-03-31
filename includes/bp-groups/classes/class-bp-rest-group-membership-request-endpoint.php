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
 * Use /groups/membership-request/{membership_id}
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
	 * Reuse some parts of the BP_REST_Group_Membership_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_REST_Group_Membership_Endpoint
	 */
	protected $membership_endpoint;

	/**
	 * Membership slug.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $membership_slug;

	/**
	 * Membership object.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_Groups_Member
	 */
	protected $membership;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace           = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base           = buddypress()->groups->id;
		$this->membership_slug     = 'membership-request';
		$this->groups_endpoint     = new BP_REST_Groups_Endpoint();
		$this->membership_endpoint = new BP_REST_Group_Membership_Endpoint();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		$base = $this->rest_base . '/(?P<group_id>[\d]+)/' . $this->membership_slug;

		register_rest_route(
			$this->namespace,
			'/' . $base,
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
			'/' . $this->rest_base . '/' . $this->membership_slug . '/(?P<membership_id>[\d]+)',
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
			'/' . $base . '/(?P<user_id>[\d]+)',
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
			'per_page' => $request['per_page'],
			'group_id' => $request['group_id'],
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

		$group    = $this->groups_endpoint->get_group_object( $args['group_id'] );
		$user_ids = BP_Groups_Member::get_all_membership_request_user_ids( $group->id );

		$retval = array();
		foreach ( $user_ids as $user_id ) {
			$group_member = new BP_Groups_Member( $user_id, $group->id );

			$retval[] = $this->prepare_response_for_collection(
				$this->membership_endpoint->prepare_item_for_response( $group_member, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, count( $user_ids ), $args['per_page'] );

		/**
		 * Fires after a list of group membership request is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $user_ids     List of membership requests.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_get_items', $user_ids, $group, $response, $request );

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
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in view membership requests.', 'buddypress' ),
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

		// Site administrators can do anything.
		if ( true === $retval && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			if ( true === $retval && ! groups_is_user_admin( bp_loggedin_user_id(), $group->id ) ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to view membership requests.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}

			if ( true === $retval ) {
				$retval = true;
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
	 * Fetch pending group membership request.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		// Get membership.
		$membership = $this->membership;

		$retval = $this->prepare_response_for_collection(
			$this->membership_endpoint->prepare_item_for_response( $membership, $request )
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a membership request is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Groups_Member  $membership  Membership object.
		 * @param WP_REST_Response  $response    The response data.
		 * @param WP_REST_Request   $request     The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_get_item', $membership, $response, $request );

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
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to get a membership.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user_id          = bp_loggedin_user_id();
		$this->membership = new BP_Groups_Member( false, false, absint( $request['membership_id'] ) );

		if ( true === $retval && is_null( $this->membership->user_id ) && is_null( $this->membership->group_id ) ) {
			$retval = new WP_Error(
				'bp_rest_group_membership_request_invalid_membership',
				__( 'Invalid membership request.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Site administrators can do anything.
		if ( true === $retval && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			if ( true === $retval && ! groups_is_user_admin( $user_id, $this->membership->group_id ) ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to get a membership.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}

			if ( true === $retval ) {
				$retval = true;
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
		$user     = bp_rest_get_user( $request['user_id'] );
		$group    = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$group_id = $group->id;

		$group_member                = new BP_Groups_Member();
		$group_member->group_id      = $group_id;
		$group_member->user_id       = $user->ID;
		$group_member->inviter_id    = 0;
		$group_member->is_admin      = 0;
		$group_member->user_title    = '';
		$group_member->date_modified = bp_core_current_time();
		$group_member->is_confirmed  = 0;

		if ( ! $group_member->save() ) {
			return new WP_Error(
				'bp_rest_group_membership_request_failed',
				__( 'Could not send membership request to this group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$admins = groups_get_group_admins( $group_id );

		// Now send the email notification.
		for ( $i = 0, $count = count( $admins ); $i < $count; ++$i ) {
			groups_notification_new_membership_request( $user->ID, $admins[ $i ]->user_id, $group_id, $group_member->id );
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->membership_endpoint->prepare_item_for_response( $group_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group membership request is made via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user         The user.
		 * @param BP_Groups_Member $group_member The group member object.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_create_item', $user, $group_member, $group, $response, $request );

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
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to create a membership request.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		if ( true === $retval && ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_group_member_invalid_id',
				__( 'Invalid group member id.', 'buddypress' ),
				array(
					'status' => 404,
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

		if ( true === $retval && groups_check_for_membership_request( $user->ID, $group->id ) && groups_check_user_has_invite( $user->ID, $group->id ) ) {
			$retval = new WP_Error(
				'bp_rest_group_membership_duplicate_request',
				__( 'There is already a request to this member.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Site administrators can do anything.
		if ( true === $retval && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			if ( true === $retval && ( groups_is_user_member( $user->ID, $group->id ) || groups_is_user_banned( $user->ID, $group->id ) ) ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'User is not allowed to have request membership issued to this group.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}

			if ( true === $retval ) {
				$retval = true;
			}
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
		$user  = bp_rest_get_user( $request['user_id'] );
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		if ( ! groups_accept_membership_request( false, $user->ID, $group->id ) ) {
			return new WP_Error(
				'bp_rest_group_membership_request_acceptance_failed',
				__( 'There was an error accepting the membership request.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$group_member = new BP_Groups_Member( $user->ID, $group->id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->membership_endpoint->prepare_item_for_response( $group_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group membership request is accepted/rejected via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user         The user.
		 * @param BP_Groups_Member $group_member The group member object.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_update_item', $user, $group_member, $group, $response, $request );

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
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to make an update.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		if ( true === $retval && ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_group_member_invalid_id',
				__( 'Invalid group member id.', 'buddypress' ),
				array(
					'status' => 404,
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

		if ( true === $retval && ! groups_check_for_membership_request( $user->ID, $group->id ) ) {
			$retval = new WP_Error(
				'bp_rest_group_membership_update_no_request',
				__( 'There is no membership request to this member.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Site administrators can do anything.
		if ( true === $retval && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			if ( true === $retval && ! groups_is_user_admin( $user->ID, $group->id ) ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'User is not allowed to have request membership issued to this group.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}

			if ( true === $retval ) {
				$retval = true;
			}
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
		$user  = bp_rest_get_user( $request['user_id'] );
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		if ( ! groups_reject_membership_request( false, $user->ID, $group->id ) ) {
			return new WP_Error(
				'bp_rest_group_membership_request_rejection_failed',
				__( 'There was an error rejecting the membership request.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$group_member = new BP_Groups_Member( $user->ID, $group->id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->membership_endpoint->prepare_item_for_response( $group_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group membership request is rejected via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user         The user.
		 * @param BP_Groups_Member $group_member The group member object.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_membership_request_delete_item', $user, $group_member, $group, $response, $request );

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
		$retval = $this->update_item_permissions_check( $request );

		/**
		 * Filter the group membership request `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_membership_request_delete_item_permissions_check', $retval, $request );
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
		$schema = $this->membership_endpoint->get_item_schema();

		// Set title to this endpoint.
		$schema['title'] = __( 'Group Membership Request', 'buddypress' );

		/**
		 * Filters the group membership request schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_group_membership_request_schema', $schema );
	}
}
