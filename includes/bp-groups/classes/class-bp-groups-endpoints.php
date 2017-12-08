<?php
defined( 'ABSPATH' ) || exit;

/**
 * Groups endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Groups_Controller extends WP_REST_Controller {

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
	 * Register the routes.
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
			'schema' => array( $this, 'get_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array(
						'default' => 'view',
					) ),
				),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
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
				'id' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'creator_id' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the user that created the group.', 'buddypress' ),
					'type'        => 'integer',
				),

				'name' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the group.', 'buddypress' ),
					'type'        => 'string',
				),

				'slug' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The URL-friendly slug for the group.', 'buddypress' ),
					'type'        => 'integer',
				),

				'link' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The permalink to this object on the site.', 'buddypress' ),
					'format'      => 'url',
					'type'        => 'string',
				),

				'description' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The description of the group.', 'buddypress' ),
					'type'        => 'string',
				),

				'status' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The status of the group.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array( 'public', 'private', 'hidden' ),
				),

				'enable_forum' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the group has a forum or not.', 'buddypress' ),
					'type'        => 'boolean',
				),

				'date_created' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the group was created, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),

				'admins' => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Group administrators.', 'buddypress' ),
					'type'        => 'array',
				),

				'mods' => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Group administrators.', 'buddypress' ),
					'type'        => 'array',
				),

				'total_member_count' => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Count of all group members.', 'buddypress' ),
					'type'        => 'integer',
				),

				'last_activity' => array(
					'context'     => array( 'edit' ),
					'description' => __( "The date the group was last active, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),

				'avatar_urls'  => array(
					'description' => __( 'Avatar URLs for the resource.' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => array(
						'thumb' => array(
							'description' => __( 'Thumbnail-sized avatar URL.' ),
							'type'        => 'string',
							'format'      => 'uri',
							'context'     => array( 'embed', 'view', 'edit' ),
						),
						'full' => array(
							'description' => __( 'Full-sized avatar URL.' ),
							'type'        => 'string',
							'format'      => 'uri',
							'context'     => array( 'embed', 'view', 'edit' ),
						),
					),
				),
			),
		);

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
		$params = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['type'] = array(
			'description'       => __( 'Shorthand for certain orderby/order combinations', 'buddypress' ),
			'type'              => 'string',
			'default'           => null,
			'enum'              => array( 'active', 'newest', 'alphabetical', 'random', 'popular', 'most-forum-topics', 'most-forum-posts' ),
			'sanitize_callback' => 'rest_validate_request_arg',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddypress' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => array( 'asc', 'desc' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['orderby'] = array(
			'description'       => __( 'Order groups by which attribute.', 'buddypress' ),
			'type'              => 'string',
			'default'           => 'date_created',
			'enum'              => array( 'date_created', 'last_activity', 'total_member_count', 'name', 'random' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Pass a user_id to limit to only groups that this user is a member of.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes groups with specific IDs.', 'buddypress' ),
			'type'              => 'array',
			'default'           => false,
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'buddypress' ),
			'type'              => 'array',
			'default'           => false,
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
			'description'       => __( 'Limit results set to groups of a certain type.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'enum'              => bp_groups_get_group_types(),
			'validate_callback' => 'rest_validate_request_arg',
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

		// @todo: how to handle this?
		$params['meta_query'] = array( // WPCS: slow query ok.
			'description'       => __( 'Perform a meta query.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
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

	/**
	 * Retrieve groups.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of activity object data.
	 */
	public function get_items( $request ) {

		$args = array(
			'type'               => $request['type'],
			'order'              => $request['order'],
			'fields'             => $request['fields'],
			'orderby'            => $request['orderby'],
			'user_id'            => $request['user_id'],
			'include'            => $request['include'],
			'exclude'            => $request['exclude'],
			'search_terms'       => $request['search'],
			'group_type'         => $request['group_type'],
			'group_type__in'     => $request['group_type__in'],
			'group_type__not_in' => $request['group_type__not_in'],
			'meta_query'         => $request['meta_query'], // WPCS: slow query ok.
			'show_hidden'        => $request['show_hidden'],
			'per_page'           => $request['per_page'],
			'page'               => $request['page'],
			'populate_extras'    => false,
			'update_meta_cache'  => true,
		);

		$retval = array();
		$groups = groups_get_groups( $args );
		foreach ( $groups as $group ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $group, $request )
			);
		}

		return rest_ensure_response( $retval );
	}

	/**
	 * Retrieve group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {
		$group_id = (int) $request['id'];

		$group = groups_get_group( array(
			'group_id'          => $group_id,
			'load_users'        => false,
			'populate_extras'   => false,
		) );

		// Prevent non-members from seeing hidden groups.
		if ( 'hidden' === $group->status && ( ! bp_current_user_can( 'bp_moderate' ) && ! groups_is_user_member( bp_loggedin_user_id(), $group->id ) ) ) {
			// Unset the group ID to ensure our error condition fires.
			$group->id = 0;
		} else {
			$retval = $this->prepare_item_for_response( $group, $request );
		}

		if ( empty( $group_id ) || empty( $group->id ) ) {
			return new WP_Error( 'bp_rest_invalid_group_id', __( 'Invalid resource id.' ), array(
				'status' => 404,
			) );
		}

		return rest_ensure_response( $retval );
	}

	/**
	 * Check if a given request has access to get information about a specific group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request. Full data about the request.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to group items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request. Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		// Only bp_moderators and logged in users (viewing their own groups) can see hidden groups.
		if ( ! empty( $request['show_hidden'] ) && ( ! bp_current_user_can( 'bp_moderate' ) &&
			! ( ! empty( $request['user_id'] ) && bp_loggedin_user_id() === $request['user_id'] ) )
		) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you cannot view hidden groups.' ), array(
				'status' => rest_authorization_required_code(),
			) );
		}

		if ( 'edit' === $request['context'] && ! bp_current_user_can( 'bp_moderate' ) ) {
			return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you cannot view this resource with edit context.' ), array(
				'status' => rest_authorization_required_code(),
			) );
		}

		return true;
	}

	/**
	 * Prepares group data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $item Group data.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param boolean         $is_raw Optional, not used. Defaults to false.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request, $is_raw = false ) {
		$data = array(
			'avatar_urls'  => array(),
			'creator_id'   => bp_get_group_creator_id( $item ),
			'date_created' => $this->prepare_date_response( $item->date_created ),
			'decription'   => bp_get_group_description( $item ),
			'enable_forum' => bp_group_is_forum_enabled( $item ),
			'id'           => $item->id,
			'link'         => bp_get_group_permalink( $item ),
			'name'         => bp_get_group_name( $item ),
			'slug'         => bp_get_group_slug( $item ),
			'status'       => bp_get_group_status( $item ),
			'admins'       => array(),
			'mods'         => array(),
			'total_member_count' => null,
			'last_activity' => null,
		);

		// Avatars.
		$data['avatar_urls']['thumb'] = bp_core_fetch_avatar( array(
			'html'    => false,
			'object'  => 'group',
			'item_id' => $item->id,
			'type'    => 'thumb',
		) );

		$data['avatar_urls']['full'] = bp_core_fetch_avatar( array(
			'html'    => false,
			'object'  => 'group',
			'item_id' => $item->id,
			'type'    => 'full',
		) );

		$context = ! empty( $request['context'] )
			? $request['context']
			: 'view';

		// If this is the 'edit' context, fill in more details--similar to "populate_extras". Correct approach?
		if ( 'edit' === $context ) {
			$data['total_member_count'] = groups_get_groupmeta( $item->id, 'total_member_count' );
			$data['last_activity']      = $this->prepare_date_response( groups_get_groupmeta( $item->id, 'last_activity' ) );

			// Add admins and moderators to their respective arrays.
			$admin_mods = groups_get_group_members( array(
				'group_id' => $item->id,
				'group_role' => array(
					'admin',
					'mod',
				),
			) );

			foreach ( (array) $admin_mods['members'] as $user ) {
				if ( ! empty( $user->is_admin ) ) {
					$data['admins'][] = $user;
				} else {
					$data['mods'][] = $user;
				}
			}
		}

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $item ) );

		/**
		 * Filter a group value returned from the API.
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Full details about the request. Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_buddypress_group_value', $response, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array $item Group.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $item ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );

		// Entity meta.
		$links = array(
			'self' => array(
				'href' => rest_url( $base . $item->id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
		);

		return $links;
	}

	/**
	 * Convert the input date to RFC3339 format.
	 *
	 * @since 0.1.0
	 *
	 * @param string      $date_gmt Date GMT format.
	 * @param string|null $date Optional. Date object.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	protected function prepare_date_response( $date_gmt, $date = null ) {
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Clean up group_type__in input.
	 *
	 * @param string $value Comma-separated list of group types.
	 *
	 * @return array|null
	 */
	public function sanitize_group_types( $value ) {
		if ( ! empty( $value ) ) {
			$types            = explode( ',', $value );
			$registered_types = bp_groups_get_group_types();
			$valid_types = array_intersect( $types, $registered_types );

			if ( ! empty( $valid_types ) ) {
				return $valid_types;
			} else {
				return null;
			}
		}
		return $value;
	}

	/**
	 * Validate group_type__in input.
	 *
	 * @param  mixed            $value
	 * @param  WP_REST_Request  $request
	 * @param  string           $param
	 *
	 * @return WP_Error|boolean
	 */
	public function validate_group_types( $value, $request, $param ) {
		if ( ! empty( $value ) ) {
			$types = explode( ',', $value );
			$registered_types = bp_groups_get_group_types();
			foreach ( $types as $type ) {
				if ( ! in_array( $type, $registered_types, true ) ) {
					return new WP_Error( 'rest_invalid_group_type', sprintf( __( 'The group type you provided, %s, is not one of %s.' ), $type, implode( ', ', $registered_types ) ) );
				}
			}
		}
		return true;
	}
}
