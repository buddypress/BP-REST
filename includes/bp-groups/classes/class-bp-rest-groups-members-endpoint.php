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
class BP_REST_Groups_Members_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'buddypress/v1';
		$this->rest_base = buddypress()->groups->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {

		$members_endpoint = '/' . $this->rest_base . '/members';

		register_rest_route( $this->namespace, $members_endpoint, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );

		register_rest_route( $this->namespace, $members_endpoint . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( false ),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Retrieve group memberships.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of group members object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'group_id'            => $request['group_id'],
			'group_role'          => $request['roles'],
			'exclude_admins_mods' => true,

			/*

			'per_page'           => $request['per_page'],
			'page'               => $request['page'],
			'exclude'            => $request['exclude'],
			'search_terms'       => $request['search'],

			'exclude_banned'      => true,
			'type'                => 'last_joined', */
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
		$response = bp_rest_response_add_total_headers( $response, $members['total'], $args['per_page'] );

		/**
		 * Fires after a list of group members is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $members  Fetched members.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_groups_members_get_items', $members, $response, $request );

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

		// $group_id = groups_create_group( $this->prepare_item_for_database( $request ) );

		if ( ! is_numeric( $retval ) ) {
			return new WP_Error( 'bp_rest_user_cannot_update_group_member',
				__( 'Cannot update existing group member.', 'buddypress' ),
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

		/**
		 * Fires after a group member is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_User          $member   The updated member.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_member_update_item', $member, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
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

		$member = $this->get_user( $request );

		if ( empty( $member->id ) ) {
			return new WP_Error( 'bp_rest_group_member_invalid_id',
				__( 'Invalid group member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_user_delete_or_update( $member ) ) {
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
	 * Prepare a group for
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_group = new stdClass();
		$schema         = $this->get_item_schema();
		$member         = $this->get_user( $request );

		// Member ID.
		if ( ! empty( $schema['properties']['id'] ) && ! empty( $member->ID ) ) {
			$prepared_group->id = $member->ID;
		}

		/**
		 * Filters a group member before it is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_member An object prepared for updating the database.
		 * @param WP_REST_Request $request         Request object.
		 */
		return apply_filters( 'bp_rest_group_member_pre_insert_value', $prepared_member, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User $member User object.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $member ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $member->ID;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $member->ID ) ),
				'embeddable' => true,
			),
		);

		return $links;
	}

	/**
	 * See if user can update a group member.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_User $member User object.
	 * @return bool
	 */
	protected function can_user_delete_or_update( $member ) {
		return ( bp_current_user_can( 'bp_moderate' ) || get_current_user_id() === $member->ID );
	}

	/**
	 * Get the user, if the ID is valid.
	 *
	 * @since 0.1.0
	 *
	 * @param int $id Supplied ID.
	 * @return WP_User|boolean
	 */
	protected function get_user( $id ) {

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
	 * Clean up group_type__in input.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value Comma-separated list of group types.
	 * @return array|null
	 */
	public function sanitize_group_types( $value ) {

		// Bail early.
		if ( empty( $value ) ) {
			return null;
		}

		$types       = explode( ',', $value );
		$valid_types = array_intersect( $types, bp_groups_get_group_types() );

		return empty( $valid_types ) ? null : $valid_types;
	}

	/**
	 * Validate group_type__in input.
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed           $value mixed value.
	 * @param  WP_REST_Request $request Full details about the request.
	 * @param  string          $param string.
	 *
	 * @return WP_Error|bool
	 */
	public function validate_group_types( $value, $request, $param ) {

		// Bail early.
		if ( empty( $value ) ) {
			return true;
		}

		$types            = explode( ',', $value );
		$registered_types = bp_groups_get_group_types();
		foreach ( $types as $type ) {
			if ( ! in_array( $type, $registered_types, true ) ) {
				/* translators: %1$s and %2$s is replaced with the registered types */
				return new WP_Error( 'bp_rest_invalid_group_type', sprintf( __( 'The group type you provided, %1$s, is not one of %2$s.', 'buddypress' ), $type, implode( ', ', $registered_types ) ) );
			}
		}
	}

	/**
	 * Get the group schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'group',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'creator_id'         => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the user that created the group.', 'buddypress' ),
					'type'        => 'integer',
				),

				'name'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the group.', 'buddypress' ),
					'type'        => 'string',
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),

				'slug'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The URL-friendly slug for the group.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_title',
					),
				),

				'link'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The permalink to this object on the site.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),

				'description'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The description of the group.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'       => array(
							'description' => __( 'Content for the group, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered'  => array(
							'description' => __( 'HTML content for the group, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),

				'status'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The status of the group.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array( 'public', 'private', 'hidden' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),

				'enable_forum'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the group has a forum or not.', 'buddypress' ),
					'type'        => 'boolean',
				),

				'parent_id'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID of the parent group.', 'buddypress' ),
					'type'        => 'integer',
				),

				'date_created'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the group was created, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),

				'admins'             => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Group administrators.', 'buddypress' ),
					'type'        => 'array',
				),

				'mods'               => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Group moderators.', 'buddypress' ),
					'type'        => 'array',
				),

				'total_member_count' => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Count of all group members.', 'buddypress' ),
					'type'        => 'integer',
				),

				'last_activity'      => array(
					'context'     => array( 'edit' ),
					'description' => __( "The date the group was last active, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
			),
		);

		// Avatars.
		if ( true === buddypress()->avatar->show_avatars ) {
			$avatar_properties = array();

			$avatar_properties['full'] = array(
				/* translators: Full image size for the group Avatar */
				'description' => sprintf( __( 'Avatar URL with full image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_full_width() ), number_format_i18n( bp_core_avatar_full_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$avatar_properties['thumb'] = array(
				/* translators: Thumb imaze size for the group Avatar */
				'description' => sprintf( __( 'Avatar URL with thumb image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_thumb_width() ), number_format_i18n( bp_core_avatar_thumb_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$schema['properties']['avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the group.', 'buddypress' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		return $schema;
	}

	/**
	 * Get the query params for collections of groups.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['type'] = array(
			'description'       => __( 'Shorthand for certain orderby/order combinations.', 'buddypress' ),
			'default'           => 'active',
			'type'              => 'string',
			'enum'              => array( 'active', 'newest', 'alphabetical', 'random', 'popular', 'most-forum-topics', 'most-forum-posts' ),
			'sanitize_callback' => 'rest_validate_request_arg',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddypress' ),
			'default'           => 'desc',
			'type'              => 'string',
			'enum'              => array( 'asc', 'desc' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['status'] = array(
			'description'       => __( 'Group statuses to limit results to.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['orderby'] = array(
			'description'       => __( 'Order groups by which attribute.', 'buddypress' ),
			'default'           => 'date_created',
			'type'              => 'string',
			'enum'              => array( 'date_created', 'last_activity', 'total_member_count', 'name', 'random' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Pass a user_id to limit to only groups that this user is a member of.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['parent_id'] = array(
			'description'       => __( 'Get groups that are children of the specified group(s) ids.', 'buddypress' ),
			'default'           => null,
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['meta'] = array(
			'description'       => __( 'Get groups based on their meta data information.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes groups with specific IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'buddypress' ),
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

		$params['group_type'] = array(
			'description'       => __( 'Limit results set to a certain type.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'enum'              => bp_groups_get_group_types(),
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['group_type__in'] = array(
			'description'       => __( 'Limit results set to groups of certain types.', 'buddypress' ),
			'default'           => '',
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['group_type__not_in'] = array(
			'description'       => __( 'Exclude groups of certain types.', 'buddypress' ),
			'default'           => '',
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['enable_forum'] = array(
			'description'       => __( 'Whether the group should have a forum enabled.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['show_hidden'] = array(
			'description'       => __( 'Whether results should include hidden groups.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
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
