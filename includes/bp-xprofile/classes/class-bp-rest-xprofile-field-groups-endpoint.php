<?php
/**
 * BP REST: BP_REST_XProfile_Field_Groups_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * XProfile Field Groups Endpoints.
 *
 * Use /xprofile/groups
 * Use /xprofile/groups/{id}
 *
 * @since 0.1.0
 */
class BP_REST_XProfile_Field_Groups_Endpoint extends WP_REST_Controller {

	/**
	 * XProfile Fields Class.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_REST_XProfile_Fields_Endpoint
	 */
	protected $fields_endpoint;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace       = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base       = buddypress()->profile->id . '/groups';
		$this->fields_endpoint = new BP_REST_XProfile_Fields_Endpoint();
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
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
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
	 * Retrieve XProfile groups.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'profile_group_id'       => $request['profile_group_id'],
			'user_id'                => $request['user_id'],
			'member_type'            => $request['member_type'],
			'hide_empty_groups'      => $request['hide_empty_groups'],
			'hide_empty_fields'      => $request['hide_empty_fields'],
			'fetch_fields'           => $request['fetch_fields'],
			'fetch_field_data'       => $request['fetch_field_data'],
			'fetch_visibility_level' => $request['fetch_visibility_level'],
			'exclude_groups'         => $request['exclude_groups'],
			'exclude_fields'         => $request['exclude_fields'],
			'update_meta_cache'      => $request['update_meta_cache'],
		);

		if ( empty( $request['member_type'] ) ) {
			$args['member_type'] = null;
		}

		if ( empty( $request['exclude_fields'] ) ) {
			$args['exclude_fields'] = false;
		}

		if ( empty( $request['exclude_groups'] ) ) {
			$args['exclude_groups'] = false;
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_xprofile_field_groups_get_items_query_args', $args, $request );

		// Actually, query it.
		$field_groups = bp_xprofile_get_groups( $args );

		$retval = array();
		foreach ( (array) $field_groups as $item ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $item, $request )
			);
		}

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a list of field groups are fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $field_groups Fetched field groups.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_field_groups_get_items', $field_groups, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to XProfile field groups items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {

		/**
		 * Filter the XProfile fields groups `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool            $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_field_groups_get_items_permissions_check', true, $request );
	}

	/**
	 * Retrieve single XProfile field group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$field_group = $this->get_xprofile_field_group_object( $request );

		if ( empty( $field_group->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_field_group_id',
				__( 'Invalid field group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field_group, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a field group is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Group $field_group Fetched field group.
		 * @param WP_REST_Response  $response    The response data.
		 * @param WP_REST_Request   $request     The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_field_groups_get_item', $field_group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get information about a specific field group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$retval = $this->get_items_permissions_check( $request );

		/**
		 * Filter the XProfile fields groups `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_field_groups_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Create a XProfile field group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$args = array(
			'name'        => $request['name'],
			'description' => $request['description'],
			'can_delete'  => $request['can_delete'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_xprofile_field_groups_create_item_query_args', $args, $request );

		if ( empty( $args['name'] ) ) {
			return new WP_Error(
				'bp_rest_required_param_missing',
				__( 'Required param missing.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$group_id = xprofile_insert_field_group( $args );

		if ( ! $group_id ) {
			return new WP_Error(
				'bp_rest_user_cannot_create_xprofile_field_group',
				__( 'Cannot create new XProfile field group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$field_group = $this->get_xprofile_field_group_object( $group_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field_group, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a XProfile field group is created via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Group $field_group Created field group object.
		 * @param WP_REST_Response  $response    The response data.
		 * @param WP_REST_Request   $request     The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_field_groups_create_item', $field_group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to create a XProfile field group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		$retval = true;

		if ( ! ( is_user_logged_in() && bp_current_user_can( 'bp_moderate' ) ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to view this XProfile field group.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the XProfile fields groups `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_field_groups_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Update a XProfile field group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$field_group = $this->get_xprofile_field_group_object( $request );

		if ( empty( $field_group->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_field_group_id',
				__( 'Invalid field group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$args = array(
			'field_group_id' => $field_group->id,
			'name'           => $request['name'],
			'description'    => $request['description'],
			'can_delete'     => $request['can_delete'],
		);

		$group_id = xprofile_insert_field_group( $args );

		if ( ! $group_id ) {
			return new WP_Error(
				'bp_rest_user_cannot_update_xprofile_field_group',
				__( 'Cannot update XProfile field group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$field_group = $this->get_xprofile_field_group_object( $group_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field_group, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a XProfile field group is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Group $field_group Updated field group object.
		 * @param WP_REST_Response  $response    The response data.
		 * @param WP_REST_Request   $request     The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_field_groups_update_item', $field_group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to create a XProfile field group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		$retval = $this->create_item_permissions_check( $request );

		/**
		 * Filter the XProfile fields groups `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_field_groups_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete a XProfile field group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$field_group = $this->get_xprofile_field_group_object( $request );

		if ( empty( $field_group->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_field_group_id',
				__( 'Invalid field group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! xprofile_delete_field_group( $field_group->id ) ) {
			return new WP_Error(
				'bp_rest_xprofile_field_group_cannot_delete',
				__( 'Could not delete XProfile field group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field_group, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a field group is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Group $field_group Deleted field group.
		 * @param WP_REST_Response  $response    The response data.
		 * @param WP_REST_Request   $request     The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_field_groups_delete_item', $field_group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a field group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->create_item_permissions_check( $request );

		/**
		 * Filter the XProfile fields groups `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_field_groups_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares single XProfile field group data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Group $group   XProfile field group data.
	 * @param WP_REST_Request   $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $group, $request ) {
		$data = array(
			'id'          => (int) $group->id,
			'name'        => $group->name,
			'description' => array(
				'raw'      => $group->description,
				'rendered' => apply_filters( 'bp_get_the_profile_field_description', $group->description ),
			),
			'group_order' => (int) $group->group_order,
			'can_delete'  => (bool) $group->can_delete,
		);

		// If the fields have been requested, we populate them.
		if ( $request['fetch_fields'] ) {
			$data['fields'] = array();

			foreach ( $group->fields as $field ) {
				$data['fields'][] = $this->fields_endpoint->assemble_response_data( $field, $request );
			}
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $group ) );

		/**
		 * Filter the XProfile field group returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 * @param BP_XProfile_Group  $group    XProfile field group.
		 */
		return apply_filters( 'bp_rest_xprofile_field_groups_prepare_value', $response, $request, $group );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Group $group XProfile field group.
	 * @return array
	 */
	protected function prepare_links( $group ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $base . $group->id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
		);

		return $links;
	}

	/**
	 * Get XProfile field group object.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return BP_XProfile_Group|string XProfile field group object.
	 */
	public function get_xprofile_field_group_object( $request ) {
		$profile_group_id = is_numeric( $request ) ? $request : (int) $request['id'];

		$args = array(
			'profile_group_id'       => $profile_group_id,
			'user_id'                => $request['user_id'],
			'member_type'            => $request['member_type'],
			'hide_empty_fields'      => $request['hide_empty_fields'],
			'fetch_fields'           => $request['fetch_fields'],
			'fetch_field_data'       => $request['fetch_field_data'],
			'fetch_visibility_level' => $request['fetch_visibility_level'],
			'exclude_fields'         => $request['exclude_fields'],
			'update_meta_cache'      => $request['update_meta_cache'],
		);

		if ( empty( $request['member_type'] ) ) {
			$args['member_type'] = null;
		}

		if ( empty( $request['exclude_fields'] ) ) {
			$args['exclude_fields'] = false;
		}

		$field_group = current( bp_xprofile_get_groups( $args ) );

		if ( empty( $field_group->id ) ) {
			return '';
		}

		return $field_group;
	}

	/**
	 * Get the XProfile field group schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => esc_html__( 'XProfile Field Group', 'buddypress' ),
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'name'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the XProfile field group.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'description' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The description of the object.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
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
				'group_order' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The order of the group.', 'buddypress' ),
					'type'        => 'integer',
				),
				'can_delete'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the XProfile field group can be deleted or not.', 'buddypress' ),
					'type'        => 'boolean',
				),
				'fields'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The fields associated with this field group.', 'buddypress' ),
					'type'        => 'array',
				),
			),
		);

		/**
		 * Filters the xprofile field group schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_xprofile_field_group_schema', $schema );
	}

	/**
	 * Get the query params for XProfile field groups.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['profile_group_id'] = array(
			'description'       => __( 'ID of the field group that have fields.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['hide_empty_groups'] = array(
			'description'       => __( 'True to hide groups that do not have any fields.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Required if you want to load a specific user\'s data.', 'buddypress' ),
			'default'           => bp_loggedin_user_id(),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['member_type'] = array(
			'description'       => __( 'Limit fields by those restricted to a given member type, or array of member types. If `$user_id` is provided, the value of `$member_type` will be overridden by the member types of the provided user. The special value of \'any\' will return only those fields that are unrestricted by member type - i.e., those applicable to any type.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'string' ),
			'sanitize_callback' => 'bp_rest_sanitize_member_types',
			'validate_callback' => 'bp_rest_validate_member_types',
		);

		$params['hide_empty_groups'] = array(
			'description'       => __( 'True to hide field groups where the user has not provided data.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['hide_empty_fields'] = array(
			'description'       => __( 'True to hide fields where the user has not provided data.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['fetch_fields'] = array(
			'description'       => __( 'Whether to fetch the fields for each group.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['fetch_field_data'] = array(
			'description'       => __( 'Whether to fetch data for each field. Requires a $user_id.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['fetch_visibility_level'] = array(
			'description'       => __( 'Whether to fetch the visibility level for each field.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude_groups'] = array(
			'description'       => __( 'Ensure result set excludes specific profile field groups.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude_fields'] = array(
			'description'       => __( 'Ensure result set excludes specific profile fields.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'string' ),
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['update_meta_cache'] = array(
			'description'       => __( 'Whether to pre-fetch xprofilemeta for all retrieved groups, fields, and data.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
