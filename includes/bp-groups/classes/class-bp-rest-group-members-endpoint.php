<?php
/**
 * BP REST: BP_REST_Groups_Members_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Group members endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Group_Members_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'buddypress/v1';
		$this->rest_base = buddypress()->groups->id . '/members';
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
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_item_params(),
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
			'group_id'            => $group_id,
			'group_role'          => $request['roles'],
			'type'                => $request['status'],
			'per_page'            => $request['per_page'],
			'page'                => $request['page'],
			'search_terms'        => $request['search'],
			'exclude'             => $request['exclude'],
			'exclude_admins_mods' => (bool) $request['exclude_admins'],
			'exclude_banned'      => (bool) $request['exclude_banned'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_groups_members_get_items_query_args', $args, $request );

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
		 * @param int              $group_id The group id.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_groups_members_get_items', $members, $group_id, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to group members.
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
	 * Update user status on a group (add, remove, promote, demote or ban).
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {

		$user = $this->get_user( $request['id'] );

		if ( empty( $user->ID ) ) {
			return new WP_Error( 'bp_rest_group_member_invalid_id',
				__( 'Invalid group member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$group_id = $this->get_group_id( $request['group_id'] );

		if ( ! $group_id ) {
			return new WP_Error( 'bp_rest_invalid_group_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$action = $request['action'];
		$role   = $request['role'];

		if ( empty( $action ) ) {
			return new WP_Error( 'bp_rest_invalid_group_member_update_action',
				__( 'You must choose a group member update action.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( 'join' === $action ) {

			// Add member to the group.
			$joined = groups_join_group( $group_id, $user->ID );

			if ( ! $joined ) {
				return new WP_Error( 'bp_rest_group_member_failed_to_join',
					__( 'Could not add user to the group.', 'buddypress' ),
					array(
						'status' => 404,
					)
				);
			}

			// If new role set, promote it too.
			if ( 'member' !== $role ) {
				groups_promote_member( $user->ID, $group_id, $role );
			}
		} elseif ( 'promote' === $action ) {
			$promoted_member = new BP_Groups_Member( $user->ID, $group_id );

			if ( ! $promoted_member->promote( $role ) ) {
				return new WP_Error( 'bp_rest_group_member_failed_to_promote',
					__( 'Could not promote user from the group.', 'buddypress' ),
					array(
						'status' => 404,
					)
				);
			}
		} elseif ( in_array( $action, [ 'remove', 'demote', 'ban', 'unban' ], true ) ) {
			$updated_member = new BP_Groups_Member( $user->ID, $group_id );

			if ( ! $updated_member->$action() ) {
				return new WP_Error( 'bp_rest_group_member_failed_to_' . $action,
					printf( __( 'Could not %s user from the group.', 'buddypress' ), $action ),
					array(
						'status' => 404,
					)
				);
			}
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $user, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group member is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $user     The updated member.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_member_update_item', $user, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function update_item_permissions_check( $request ) {

		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to update this group member.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! bp_current_user_can( 'bp_moderate' ) ) {
			return new WP_Error( 'bp_rest_group_member_cannot_update',
				__( 'Sorry, you are not allowed to update this group member.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Prepares group member data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User         $user     User object.
	 * @param WP_REST_Request $request  Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $user, $request ) {

		$members_endpoint = new BP_REST_Members_Endpoint();

		$data = $members_endpoint->user_data( $user );

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

		if ( is_multisite() && ! is_user_member_of_blog( $user->ID ) ) {
			return false;
		}

		return $user;
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
	 * Get the group member schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$members_endpoint = new BP_REST_Members_Endpoint();

		return $members_endpoint->get_item_schema();
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
			'sanitize_callback' => 'rest_validate_request_arg',
		);

		$params['roles'] = array(
			'description'       => __( 'Ensure result set includes specific group roles.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['search'] = array(
			'description'       => __( 'Limit results set to items that match this search query.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
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
	public function get_item_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['id'] = array(
			'description'       => __( 'ID of the member.', 'buddypress' ),
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
			'sanitize_callback' => 'rest_validate_request_arg',
		);

		$params['role'] = array(
			'description'       => __( 'Member role to update him into.', 'buddypress' ),
			'default'           => 'member',
			'type'              => 'string',
			'enum'              => array( 'member', 'mod', 'admin' ),
			'sanitize_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
