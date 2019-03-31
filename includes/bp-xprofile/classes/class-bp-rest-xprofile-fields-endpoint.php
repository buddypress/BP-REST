<?php
/**
 * BP REST: BP_REST_XProfile_Fields_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * XProfile Fields endpoints.
 *
 * Use /xprofile/fields
 * Use /xprofile/fields/{id}
 *
 * @since 0.1.0
 */
class BP_REST_XProfile_Fields_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->profile->id . '/fields';
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
					'args'                => $this->create_update_item_params(),
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
					'args'                => array(
						'user_id' => array(
							'description'       => __( 'Required if you want to load a specific user\'s data.', 'buddypress' ),
							'default'           => bp_loggedin_user_id(),
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->create_update_item_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'delete_data' => array(
							'description'       => __( 'Required if you want to delete user\'s data for the field.', 'buddypress' ),
							'default'           => false,
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieve XProfile fields.
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
			'fetch_field_data'       => $request['fetch_field_data'],
			'fetch_visibility_level' => $request['fetch_visibility_level'],
			'exclude_groups'         => $request['exclude_groups'],
			'exclude_fields'         => $request['exclude_fields'],
			'update_meta_cache'      => $request['update_meta_cache'],
			'fetch_fields'           => true,
		);

		if ( empty( $request['member_type'] ) ) {
			$args['member_type'] = false;
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_xprofile_fields_get_items_query_args', $args, $request );

		// Actually, query it.
		$field_groups = bp_xprofile_get_groups( $args );

		$retval = array();
		foreach ( $field_groups as $group ) {
			foreach ( $group->fields as $field ) {
				$retval[] = $this->prepare_response_for_collection(
					$this->prepare_item_for_response( $field, $request )
				);
			}
		}

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a list of field are fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $field_groups Fetched field groups.
		 * @param WP_REST_Response $response     The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_fields_get_items', $field_groups, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to XProfile fields.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {

		/**
		 * Filter the XProfile fields `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool            $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_fields_get_items_permissions_check', true, $request );
	}

	/**
	 * Retrieve single XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$profile_field_id = (int) $request['id'];

		$field = $this->get_xprofile_field_object( $profile_field_id );

		if ( empty( $profile_field_id ) || empty( $field->id ) ) {
			return new WP_Error(
				'bp_rest_xprofile_field_invalid_id',
				__( 'Invalid field id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! empty( $request['user_id'] ) ) {
			$field->data = new stdClass();

			// Ensure that the requester is allowed to see this field.
			$hidden_user_fields = bp_xprofile_get_hidden_fields_for_user( $request['user_id'] );

			$field->data->value = in_array( $profile_field_id, $hidden_user_fields, true )
				? __( 'Value suppressed.', 'buddypress' )
				: xprofile_get_field_data( $profile_field_id, $request['user_id'] );

			// Set 'fetch_field_data' to true so that the data is included in the response.
			$request['fetch_field_data'] = true;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after XProfile field is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field $field    Fetched field object.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_fields_get_item', $field, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get information about a specific XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {

		/**
		 * Filter the XProfile fields `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool            $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_fields_get_item_permissions_check', true, $request );
	}

	/**
	 * Create a XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$args = array(
			'field_group_id'    => $request['field_group_id'],
			'parent_id'         => $request['parent_id'],
			'type'              => $request['type'],
			'name'              => $request['name'],
			'description'       => $request['description'],
			'is_required'       => $request['required'],
			'can_delete'        => $request['can_delete'],
			'order_by'          => $request['order_by'],
			'is_default_option' => $request['is_default_option'],
			'option_order'      => $request['option_order'],
			'field_order'       => $request['field_order'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_xprofile_fields_create_item_query_args', $args, $request );

		$field_id = xprofile_insert_field( $args );
		if ( ! $field_id ) {
			return new WP_Error(
				'bp_rest_user_cannot_create_xprofile_field',
				__( 'Cannot create new XProfile field.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$field = $this->get_xprofile_field_object( $field_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a XProfile field is created via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field $field     Created field object.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_fields_create_item', $field, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to create a XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to create a XProfile field.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( true === $retval && ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = new WP_Error(
				'bp_rest_user_cannot_create_field',
				__( 'Sorry, you are not allowed to create a XProfile field.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the XProfile fields `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_fields_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Update a XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$args = array(
			'field_id'          => $request['id'],
			'field_group_id'    => $request['field_group_id'],
			'parent_id'         => $request['parent_id'],
			'type'              => $request['type'],
			'name'              => $request['name'],
			'description'       => $request['description'],
			'is_required'       => $request['required'],
			'can_delete'        => $request['can_delete'],
			'order_by'          => $request['order_by'],
			'is_default_option' => $request['is_default_option'],
			'option_order'      => $request['option_order'],
			'field_order'       => $request['field_order'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_xprofile_fields_update_item_query_args', $args, $request );

		$field_id = xprofile_insert_field( $args );
		if ( ! $field_id ) {
			return new WP_Error(
				'bp_rest_user_cannot_update_xprofile_field',
				__( 'Cannot update XProfile field.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$field = $this->get_xprofile_field_object( $field_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a XProfile field is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field  $field      Updated field object.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_fields_update_item', $field, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		$retval = $this->delete_item_permissions_check( $request );

		/**
		 * Filter the XProfile fields `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_fields_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete a XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$field   = new BP_XProfile_Field( (int) $request['id'] );
		$deleted = $field->delete( $request['delete_data'] );

		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_xprofile_field_cannot_delete',
				__( 'Could not delete XProfile field.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a XProfile field is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field $field     Deleted field object.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_fields_delete_item', $field, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this field.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$field = $this->get_xprofile_field_object( $request );

		if ( true === $retval && empty( $field->id ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_field_id',
				__( 'Invalid field id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this field.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the XProfile fields `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_fields_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares single XProfile field data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $field   XProfile field object.
	 * @param WP_REST_Request   $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $field, $request ) {
		$data = $this->assemble_response_data( $field, $request );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $field ) );

		/**
		 * Filter the XProfile field returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 * @param BP_XProfile_Field  $field     XProfile field object.
		 */
		return apply_filters( 'bp_rest_xprofile_fields_prepare_value', $response, $request, $field );
	}

	/**
	 * Assembles single XProfile field data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $field   XProfile field object.
	 * @param WP_REST_Request   $request Full data about the request.
	 * @return array
	 */
	public function assemble_response_data( $field, $request ) {
		$data = array(
			'id'                => (int) $field->id,
			'group_id'          => (int) $field->group_id,
			'parent_id'         => (int) $field->parent_id,
			'type'              => $field->type,
			'name'              => $field->name,
			'description'       => array(
				'raw'      => $field->description,
				'rendered' => apply_filters( 'bp_get_the_profile_field_description', $field->description ),
			),
			'is_required'       => (bool) $field->is_required,
			'can_delete'        => (bool) $field->can_delete,
			'field_order'       => (int) $field->field_order,
			'option_order'      => (int) $field->option_order,
			'order_by'          => $field->order_by,
			'is_default_option' => (bool) $field->is_default_option,
		);

		if ( ! empty( $request['fetch_visibility_level'] ) ) {
			$data['visibility_level'] = $field->visibility_level;
		}

		if ( ! empty( $request['fetch_field_data'] ) ) {
			if ( isset( $field->data->id ) ) {
				$data['data']['id'] = $field->data->id;
			}
			$data['data']['value'] = maybe_unserialize( $field->data->value );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		return $data;
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $field XProfile field object.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $field ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $base . $field->id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
		);

		return $links;
	}

	/**
	 * Get XProfile field object.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return BP_XProfile_Field|string XProfile field object.
	 */
	public function get_xprofile_field_object( $request ) {
		$field_id = is_numeric( $request ) ? $request : (int) $request['id'];

		$field = xprofile_get_field( $field_id );

		if ( empty( $field ) ) {
			return '';
		}

		return $field;
	}

	/**
	 * Get the XProfile field schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => esc_html__( 'XProfile Fields', 'buddypress' ),
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'group_id'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the group the field is part of.', 'buddypress' ),
					'type'        => 'integer',
				),
				'parent_id'         => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the field parent.', 'buddypress' ),
					'type'        => 'integer',
				),
				'type'              => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The type of field, like checkbox or select.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
				'name'              => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the profile field group.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'description'       => array(
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
				'is_required'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the profile field must have a value.', 'buddypress' ),
					'type'        => 'boolean',
				),
				'can_delete'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the profile field can be deleted or not.', 'buddypress' ),
					'type'        => 'boolean',
				),
				'field_order'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The order of the field.', 'buddypress' ),
					'type'        => 'integer',
				),
				'option_order'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The order of the field\'s options.', 'buddypress' ),
					'type'        => 'integer',
				),
				'order_by'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'How the field\'s options are ordered.', 'buddypress' ),
					'type'        => 'string',
				),
				'is_default_option' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the option is the default option.', 'buddypress' ),
					'type'        => 'boolean',
				),
				'visibility_level'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Who may see the saved value for this field.', 'buddypress' ),
					'type'        => 'string',
				),
				'data'              => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The saved value for this field.', 'buddypress' ),
					'type'        => 'array',
				),
			),
		);

		/**
		 * Filters the xprofile fields schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_xprofile_fields_schema', $schema );
	}

	/**
	 * Get the query params for XProfile fields.
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

	/**
	 * Get the query params for a XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function create_update_item_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'edit';

		$params['name'] = array(
			'description'       => __( 'Name of the object.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['description'] = array(
			'description'       => __( 'Description of the object.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Type of the object.', 'buddypress' ),
			'type'              => 'string',
			'enum'              => buddypress()->profile->field_types,
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['parent_id'] = array(
			'description'       => __( 'Parent ID of object.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['is_required'] = array(
			'description'       => __( 'Requirement of the object.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['can_delete'] = array(
			'description'       => __( 'Can the object be deleted.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['is_default_option'] = array(
			'description'       => __( 'Option of the default field.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['field_group_id'] = array(
			'description'       => __( 'ID of the group you want to add the object.', 'buddypress' ),
			'required'          => true,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
