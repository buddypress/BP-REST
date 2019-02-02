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
	 * @var object
	 */
	protected $groups_endpoint;

	/**
	 * Reuse some parts of the BP_REST_Members_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var object
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
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<group_id>[\d]+)/members', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<group_id>[\d]+)/members/(?P<user_id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_update_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => $this->get_update_collection_params(),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Retrieve group members.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of group members.
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
		return $this->groups_endpoint->get_item_permissions_check( $request );
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

		// Add member to the group.
		if ( 'join' === $action ) {
			$group_member->group_id     = $group_id;
			$group_member->user_id      = $user->ID;
			$group_member->is_admin     = 0;
			$group_member->date_modified = bp_core_current_time();
			$group_member->is_confirmed  = 1;
			$saved                      = $group_member->save();

			if ( ! $saved ) {
				return new WP_Error( 'bp_rest_group_member_failed_to_join',
					__( 'Could not add member to the group.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}

			// If new role set, promote it too.
			if ( $saved && 'member' !== $role ) {
				groups_promote_member( $user->ID, $group_id, $role );
			}
		} elseif ( 'promote' === $action ) {
			if ( ! $group_member->promote( $role ) ) {
				return new WP_Error( 'bp_rest_group_member_failed_to_promote',
					__( 'Could not promote member.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		} elseif ( in_array( $action, [ 'remove', 'demote', 'ban', 'unban' ], true ) ) {
			if ( ! $group_member->$action() ) {
				return new WP_Error( 'bp_rest_group_member_failed_to_' . $action,
					sprintf( __( 'Could not %s member from the group.', 'buddypress' ), esc_attr( $action ) ),
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
		do_action( 'bp_rest_group_member_update_item', $user, $group_member, $group, $response, $request );

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
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to make an update.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		if ( empty( $user->ID ) ) {
			return new WP_Error( 'bp_rest_group_member_invalid_id',
				__( 'Invalid group member id.', 'buddypress' ),
				array(
					'status' => 404,
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

		// Site administrators can do anything.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		$loggedin_user_id = bp_loggedin_user_id();

		if ( $loggedin_user_id === $user->ID ) {
			// Case 1: User is making a self-request.
			switch ( $request['action'] ) {
				case 'join' :
					// Users may only freely join public groups. @todo Private requests.
					if ( 'public' !== $group->status && ! groups_is_user_member( $loggedin_user_id, $group->id ) ) {
						return new WP_Error( 'bp_rest_group_member_cannot_join',
							__( 'Sorry, you are not allowed to join this group.', 'buddypress' ),
							array(
								'status' => rest_authorization_required_code(),
							)
						);
					} else {
						return true;
					}

				default :
					return false;
			}

		} else {
			// Case 2: User is making a request about another user.
			switch ( $request['action'] ) {
				case 'ban' :
				case 'unban' :
				case 'promote' :
				case 'demote' :
					if ( ! groups_is_user_admin( $loggedin_user_id, $group->id ) && ! groups_is_user_mod( $loggedin_user_id, $group->id ) ) {
						return new WP_Error( 'bp_rest_group_member_cannot_' . $request['action'],
							sprintf( __( 'Sorry, you are not allowed to %s this group member.', 'buddypress' ), esc_attr( $request['action'] ) ),
							array(
								'status' => rest_authorization_required_code(),
							)
						);
					} else {
						return true;
					}

				default :
					return false;
			}
		}
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
			return new WP_Error( 'bp_rest_group_member_failed_to_remove',
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
		do_action( 'bp_rest_group_member_delete_item', $user, $member, $group, $response, $request );

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
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to delete a group membership.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		if ( empty( $user->ID ) ) {
			return new WP_Error( 'bp_rest_group_member_invalid_id',
				__( 'Invalid group member id.', 'buddypress' ),
				array(
					'status' => 404,
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

		// Site administrators can do anything.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		$loggedin_user_id = bp_loggedin_user_id();

		if ( $user->ID !== $loggedin_user_id ) {
			if ( ! groups_is_user_admin( $loggedin_user_id, $group->id ) && ! groups_is_user_mod( $loggedin_user_id, $group->id ) ) {
				return new WP_Error( 'bp_rest_group_member_cannot_remove',
					__( 'Sorry, you are not allowed to remove this group member.', 'buddypress' ),
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
			if ( 1 === count( $group_admins ) && $loggedin_user_id === $group_admins[0]->user_id && $user->ID === $loggedin_user_id ) {
				return new WP_Error( 'bp_rest_group_member_cannot_remove',
					__( 'Sorry, you are not allowed to leave this group.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		return true;
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
		$member_data = $this->members_endpoint->user_data( $user );

		// Merge both info.
		$data = array_merge( $member_data, array(
			'is_mod'       => (bool) $group_member->is_mod,
			'is_admin'     => (bool) $group_member->is_admin,
			'is_banned'    => (bool) $group_member->is_banned,
			'is_confirmed'  => (bool) $group_member->is_confirmed,
			'date_modified' => bp_rest_prepare_date_response( $group_member->date_modified ),
		) );

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $user ) );

		/**
		 * Filter a group member value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_group_member_prepare_value', $response, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User $user User object.
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
		);

		return $links;
	}

	/**
	 * Get the group member schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = $this->members_endpoint->get_item_schema();

		$schema['properties']['is_mod'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( 'Is this user a mod of the group?', 'buddypress' ),
			'type'        => 'integer',
		);

		$schema['properties']['is_banned'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( 'Is this user banned from the group?', 'buddypress' ),
			'type'        => 'integer',
		);

		$schema['properties']['is_admin'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( 'Is this user an admin of the group?', 'buddypress' ),
			'type'        => 'integer',
		);

		$schema['properties']['is_confirmed'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( 'Is this user membership confirmed?', 'buddypress' ),
			'type'        => 'integer',
		);

		$schema['properties']['date_modified'] = array(
			'context'     => array( 'view', 'edit' ),
			'description' => __( "The date of the last time this user was modified, in the site's timezone.", 'buddypress' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections of group members.
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

		$params['status'] = array(
			'description'       => __( 'Sort the order of results by the status of the group members.', 'buddypress' ),
			'default'           => 'last_joined',
			'type'              => 'string',
			'enum'              => array( 'last_joined', 'first_joined' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['roles'] = array(
			'description'       => __( 'Ensure result set includes specific group roles.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
		);

		$params['search'] = array(
			'description'       => __( 'Limit results set to items that match this search query.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific member IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['exclude_admins'] = array(
			'description'       => __( 'Whether results should exclude group admins and mods.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude_banned'] = array(
			'description'       => __( 'Whether results should exclude banned group members.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}

	/**
	 * Get the query params for a group member update.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_update_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['user_id'] = array(
			'description'       => __( 'ID of the group member.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['group_id'] = array(
			'description'       => __( 'ID of the group.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['action'] = array(
			'description'       => __( 'Action used to update a member.', 'buddypress' ),
			'default'           => 'join',
			'type'              => 'string',
			'enum'              => array( 'join', 'remove', 'promote', 'demote', 'ban', 'unban' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['role'] = array(
			'description'       => __( 'Member role to update him into.', 'buddypress' ),
			'default'           => 'member',
			'type'              => 'string',
			'enum'              => array( 'member', 'mod', 'admin' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
