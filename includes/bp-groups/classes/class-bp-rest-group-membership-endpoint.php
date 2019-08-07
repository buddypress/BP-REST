<?php
/**
 * BP REST: BP_REST_Group_Membership_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Group membership endpoints.
 *
 * Use /groups/{group_id}/members
 * Use /groups/{group_id}/members/{user_id}
 *
 * @since 0.1.0
 */
class BP_REST_Group_Membership_Endpoint extends WP_REST_Controller {

	/**
	 * Reuse some parts of the BP_REST_Groups_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_REST_Groups_Endpoint
	 */
	protected $groups_endpoint;

	/**
	 * Reuse some parts of the BP_REST_Members_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_REST_Members_Endpoint
	 */
	protected $members_endpoint;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace        = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base        = buddypress()->groups->id;
		$this->groups_endpoint  = new BP_REST_Groups_Endpoint();
		$this->members_endpoint = new BP_REST_Members_Endpoint();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<group_id>[\d]+)/members',
			array(
				'args'   => array(
					'group_id' => array(
						'description' => __( 'A unique numeric ID for the Group.', 'buddypress' ),
						'type'        => 'integer',
					),
				),
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
			'/' . $this->rest_base . '/(?P<group_id>[\d]+)/members/(?P<user_id>[\d]+)',
			array(
				'args'   => array(
					'group_id' => array(
						'description' => __( 'A unique numeric ID for the Group.', 'buddypress' ),
						'type'        => 'integer',
					),
					'user_id'  => array(
						'description' => __( 'A unique numeric ID for the Group Member.', 'buddypress' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_method( WP_REST_Server::CREATABLE ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_method( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_method( WP_REST_Server::DELETABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * GET arguments for the endpoint's CREATABLE, EDITABLE & DELETABLE methods.
	 *
	 * @since 0.1.0
	 *
	 * @param string $method Optional. HTTP method of the request.
	 * @return array Endpoint arguments.
	 */
	public function get_endpoint_args_for_method( $method = WP_REST_Server::CREATABLE ) {
		$filer_key = 'get_item';

		$args = array(
			'context' => $this->get_context_param(
				array(
					'default' => 'edit',
				)
			),
		);

		if ( WP_REST_Server::CREATABLE === $method || WP_REST_Server::EDITABLE === $method ) {
			$filer_key   = 'create_item';
			$group_roles = array_diff( array_keys( bp_groups_get_group_roles() ), array( 'banned' ) );

			$args['role'] = array(
				'description'       => __( 'Group role to assign the user to.', 'buddypress' ),
				'default'           => 'member',
				'type'              => 'string',
				'enum'              => $group_roles,
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			);

			if ( WP_REST_Server::EDITABLE === $method ) {
				$filer_key = 'update_item';

				$args['action'] = array(
					'description'       => __( 'Action used to update a group member.', 'buddypress' ),
					'default'           => 'promote',
					'type'              => 'string',
					'enum'              => array( 'promote', 'demote', 'ban', 'unban' ),
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => 'rest_validate_request_arg',
				);
			}
		} elseif ( WP_REST_Server::DELETABLE === $method ) {
			$filer_key = 'delete_item';
		}

		/**
		 * Filters the method query arguments.
		 *
		 * @since 0.1.0
		 *
		 * @param array $args Query arguments.
		 */
		return apply_filters( "bp_rest_group_members_{$filer_key}_query_arguments", $args );
	}

	/**
	 * Retrieve group members.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		$args = array(
			'group_id'            => $group->id,
			'group_role'          => $request['roles'],
			'type'                => $request['status'],
			'per_page'            => $request['per_page'],
			'page'                => $request['page'],
			'search_terms'        => $request['search'],
			'exclude'             => $request['exclude'],
			'exclude_admins_mods' => (bool) $request['exclude_admins'],
			'exclude_banned'      => (bool) $request['exclude_banned'],
		);

		if ( empty( $args['exclude'] ) ) {
			$args['exclude'] = false;
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_group_members_get_items_query_args', $args, $request );

		// Get our members.
		$members = groups_get_group_members( $args );

		$retval = array();
		foreach ( $members['members'] as $member ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $member, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $members['count'], $args['per_page'] );

		/**
		 * Fires after a list of group members are fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $members  Fetched group members.
		 * @param BP_Groups_Group  $group    The group object.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_members_get_items', $members, $group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to group members.
	 *
	 * We are using the same permissions check done on group access.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$retval = $this->groups_endpoint->get_item_permissions_check( $request );

		/**
		 * Filter the group members `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_members_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Add member to a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$user  = bp_rest_get_user( $request['user_id'] );
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		if ( ! $request['context'] || 'view' === $request['context'] ) {
			if ( ! groups_join_group( $user->ID, $group->id ) ) {
				return new WP_Error(
					'bp_rest_group_member_failed_to_join',
					__( 'Could not join the group.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}

			// Set the group member.
			$group_member = new BP_Groups_Member( $user->ID, $group->id );
		} else {
			$role         = $request['role'];
			$group_id     = $group->id;
			$group_member = new BP_Groups_Member( $user->ID, $group_id );

			// Add member to the group.
			$group_member->group_id      = $group_id;
			$group_member->user_id       = $user->ID;
			$group_member->is_admin      = 0;
			$group_member->date_modified = bp_core_current_time();
			$group_member->is_confirmed  = 1;
			$saved                       = $group_member->save();

			if ( ! $saved ) {
				return new WP_Error(
					'bp_rest_group_member_failed_to_join',
					__( 'Could not add member to the group.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}

			// If new role set, promote it too.
			if ( $saved && 'member' !== $role ) {
				// Make sure to update the group role.
				if ( groups_promote_member( $user->ID, $group_id, $role ) ) {
					$group_member = new BP_Groups_Member( $user->ID, $group_id );
				}
			}
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $group_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a member is added to a group via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user         The user.
		 * @param BP_Groups_Member $group_member The group member object.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_members_create_item', $user, $group_member, $group, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to join a group.
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
				__( 'Sorry, you need to be logged in to join a group.', 'buddypress' ),
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

		// Site administrators can do anything.
		if ( true === $retval && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {

			$loggedin_user_id = bp_loggedin_user_id();
			if ( $loggedin_user_id === $user->ID && 'view' === $request['context'] ) {

				// Users may only freely join public groups.
				if ( true === $retval && (
					! bp_current_user_can( 'groups_join_group', array( 'group_id' => $group->id ) )
					|| groups_is_user_member( $loggedin_user_id, $group->id ) // As soon as they are not already members.
					|| groups_is_user_banned( $loggedin_user_id, $group->id ) // And as soon as they are not banned from it.
				) ) {
					$retval = new WP_Error(
						'bp_rest_group_member_failed_to_join',
						__( 'Could not join the group.', 'buddypress' ),
						array(
							'status' => 500,
						)
					);
				}

				if ( true === $retval ) {
					$retval = true;
				}
			} else {
				$retval = false;
			}
		}

		/**
		 * Filter the group members `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_members_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Update user status on a group (add, remove, promote, demote or ban).
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$user         = bp_rest_get_user( $request['user_id'] );
		$group        = $this->groups_endpoint->get_group_object( $request['group_id'] );
		$action       = $request['action'];
		$role         = $request['role'];
		$group_id     = $group->id;
		$group_member = new BP_Groups_Member( $user->ID, $group_id );

		if ( 'promote' === $action ) {
			if ( ! $group_member->promote( $role ) ) {
				return new WP_Error(
					'bp_rest_group_member_failed_to_promote',
					__( 'Could not promote member.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		} elseif ( 'demote' === $action && 'member' !== $role ) {
			if ( ! $group_member->promote( $role ) ) {
				return new WP_Error(
					'bp_rest_group_member_failed_to_demote',
					__( 'Could not demote member.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		} elseif ( in_array( $action, [ 'demote', 'ban', 'unban' ], true ) ) {
			if ( ! $group_member->$action() ) {
				return new WP_Error(
					'bp_rest_group_member_failed_to_' . $action,
					sprintf(
						/* translators: %1$s is replaced with the action */
						__( 'Could not %s member from the group.', 'buddypress' ),
						esc_attr( $action )
					),
					array(
						'status' => 500,
					)
				);
			}
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $group_member, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group member is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user         The updated member.
		 * @param BP_Groups_Member $group_member The group member object.
		 * @param BP_Groups_Group  $group        The group object.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_group_members_update_item', $user, $group_member, $group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a group member.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
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

		// Site administrators can do anything.
		if ( true === $retval && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {

			$loggedin_user_id = bp_loggedin_user_id();
			if ( true === $retval && in_array( $request['action'], [ 'ban', 'unban', 'promote', 'demote' ], true ) ) {
				if ( ! groups_is_user_admin( $loggedin_user_id, $group->id ) && ! groups_is_user_mod( $loggedin_user_id, $group->id ) ) {
					$retval = new WP_Error(
						'bp_rest_group_member_cannot_' . $request['action'],
						sprintf(
							/* translators: %1$s is replaced with the action. */
							__( 'Sorry, you are not allowed to %s this group member.', 'buddypress' ),
							esc_attr( $request['action'] )
						),
						array(
							'status' => rest_authorization_required_code(),
						)
					);
				} else {
					$retval = true;
				}
			}
		}

		/**
		 * Filter the group members `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_members_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete a group membership.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$member  = new BP_Groups_Member( $request['user_id'], $request['group_id'] );
		$removed = $member->remove();

		if ( ! $removed ) {
			return new WP_Error(
				'bp_rest_group_member_failed_to_remove',
				__( 'Could not remove member from this group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $member, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		$user  = bp_rest_get_user( $request['user_id'] );
		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		/**
		 * Fires after a group member is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user     The updated member.
		 * @param BP_Groups_Member $member   The group member object.
		 * @param BP_Groups_Group  $group    The group object.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_members_delete_item', $user, $member, $group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a group member.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to view a group membership.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		if ( true === $retval && ! $user instanceof WP_User ) {
			return new WP_Error(
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

		// Site administrators can do anything.
		if ( true === $retval && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} elseif ( true === $retval ) {

			$loggedin_user_id = bp_loggedin_user_id();

			if ( $user->ID !== $loggedin_user_id ) {
				if ( true === $retval && ! groups_is_user_admin( $loggedin_user_id, $group->id ) && ! groups_is_user_mod( $loggedin_user_id, $group->id ) ) {
					$retval = new WP_Error(
						'bp_rest_authorization_required',
						__( 'Sorry, you need to be logged in to view a group membership.', 'buddypress' ),
						array(
							'status' => rest_authorization_required_code(),
						)
					);
				}
			} else {
				// Special case for self-removal: don't allow if it'd leave a group with no admins.
				$user             = bp_rest_get_user( $request['user_id'] );
				$group            = $this->groups_endpoint->get_group_object( $request['group_id'] );
				$loggedin_user_id = bp_loggedin_user_id();

				$group_admins = groups_get_group_admins( $group->id );
				if ( true === $retval && 1 === count( $group_admins ) && $loggedin_user_id === $group_admins[0]->user_id && $user->ID === $loggedin_user_id ) {
					$retval = new WP_Error(
						'bp_rest_authorization_required',
						__( 'Sorry, you need to be logged in to view a group membership.', 'buddypress' ),
						array(
							'status' => rest_authorization_required_code(),
						)
					);
				}
			}
		}

		/**
		 * Filter the group members `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_group_members_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares group member data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Groups_Member $group_member Group member object.
	 * @param WP_REST_Request  $request      Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $group_member, $request ) {
		$user        = bp_rest_get_user( $group_member->user_id );
		$context     = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$member_data = $this->members_endpoint->user_data( $user, $context );

		// Merge both info.
		$data = array_merge(
			$member_data,
			array(
				'is_mod'        => (bool) $group_member->is_mod,
				'is_admin'      => (bool) $group_member->is_admin,
				'is_banned'     => (bool) $group_member->is_banned,
				'is_confirmed'  => (bool) $group_member->is_confirmed,
				'date_modified' => bp_rest_prepare_date_response( $group_member->date_modified ),
			)
		);

		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $user ) );

		/**
		 * Filter a group member value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response      The response data.
		 * @param WP_REST_Request  $request       Request used to generate the response.
		 * @param BP_Groups_Member $group_member  Group member object.
		 */
		return apply_filters( 'bp_rest_group_members_prepare_value', $response, $request, $group_member );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User $user User object.
	 * @return array
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
		);

		/**
		 * Filter links prepared for the REST response.
		 *
		 * @since 0.1.0
		 *
		 * @param array   $links The prepared links of the REST response.
		 * @param WP_User $user  User object.
		 */
		return apply_filters( 'bp_rest_group_members_prepare_links', $links, $user );
	}

	/**
	 * Get the group member schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {

		// Get schema from members.
		$schema = $this->members_endpoint->get_item_schema();

		// Set title to this endpoint.
		$schema['title'] = 'bp_group_members';

		$schema['properties']['is_mod'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( '`1` if this member is a Group moderator, `0` otherwise.', 'buddypress' ),
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
		);

		$schema['properties']['is_banned'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( '`1` if this member has been banned from the Group, `0` otherwise.', 'buddypress' ),
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
		);

		$schema['properties']['is_admin'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( '`1` if this member is a Group administrator, `0` otherwise.', 'buddypress' ),
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
		);

		$schema['properties']['is_confirmed'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( '`1` if the membership of this user has been confirmed, `0` otherwise.', 'buddypress' ),
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
		);

		$schema['properties']['date_modified'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( "The date of the last time the membership of this user was modified, in the site's timezone.", 'buddypress' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		/**
		 * Filters the group membership schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_group_members_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for collections of group memberships.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';
		$statuses                     = array( 'last_joined', 'first_joined', 'alphabetical' );

		if ( bp_is_active( 'activity' ) ) {
			$statuses[] = 'group_activity';
		}

		$params['status'] = array(
			'description'       => __( 'Sort the order of results by the status of the group members.', 'buddypress' ),
			'default'           => 'last_joined',
			'type'              => 'string',
			'enum'              => $statuses,
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['roles'] = array(
			'description'       => __( 'Ensure result set includes specific Group roles.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array(
				'type' => 'string',
				'enum' => array_keys( bp_groups_get_group_roles() ),
			),
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific member IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude_admins'] = array(
			'description'       => __( 'Whether results should exclude group admins and mods.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude_banned'] = array(
			'description'       => __( 'Whether results should exclude banned group members.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_group_members_collection_params', $params );
	}
}
