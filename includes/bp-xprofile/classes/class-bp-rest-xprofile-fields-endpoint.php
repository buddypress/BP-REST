<?php
/**
 * BP REST: BP_REST_XProfile_Fields_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * XProfile fields endpoints.
 *
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
		$this->namespace = 'buddypress/v1';
		$this->rest_base = buddypress()->profile->id . '/fields';
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => $this->get_item_params(),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Retrieve single XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {
		$profile_field_id = (int) $request['id'];

		$field = $this->get_xprofile_field_object( $profile_field_id );

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

		if ( empty( $profile_field_id ) || empty( $field->id ) ) {
			return new WP_Error( 'rest_xprofile_field_invalid_id',
				__( 'Invalid field id.', 'buddypress' ),
				array(
					'status' => 404,
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
		 * Fires after XProfile field is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field $field    Fetched field.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'rest_xprofile_field_get_item', $field, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get information about a specific field.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you need to be logged in to get a XProfile field.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! $this->can_see() ) {
			return new WP_Error( 'rest_user_cannot_view_xprofile_field',
				__( 'Sorry, you cannot view this XProfile field.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Prepares single XProfile field data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $field   XProfile field.
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
		 * @param BP_XProfile_Field $field    XProfile field object.
		 */
		return apply_filters( 'rest_xprofile_field_prepare_value', $response, $request, $field );
	}

	/**
	 * Assembles single XProfile field data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $item    XProfile field data.
	 * @param WP_REST_Request   $request Full data about the request.
	 * @return array
	 */
	public function assemble_response_data( $item, $request ) {
		$data = array(
			'id'                => (int) $item->id,
			'group_id'          => (int) $item->group_id,
			'parent_id'         => (int) $item->parent_id,
			'type'              => $item->type,
			'name'              => $item->name,
			'description'       => $item->description,
			'is_required'       => (bool) $item->is_required,
			'can_delete'        => (bool) $item->can_delete,
			'field_order'       => (int) $item->field_order,
			'option_order'      => (int) $item->option_order,
			'order_by'          => $item->order_by,
			'is_default_option' => (bool) $item->is_default_option,
		);

		if ( ! empty( $request['fetch_visibility_level'] ) ) {
			$data['visibility_level'] = $item->visibility_level;
		}

		if ( ! empty( $request['fetch_field_data'] ) ) {
			if ( isset( $item->data->id ) ) {
				$data['data']['id'] = $item->data->id;
			}
			$data['data']['value'] = maybe_unserialize( $item->data->value );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		return $data;
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $item XProfile field.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $item ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $item->id;

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
	 * Can this user see the XProfile field?
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $field XProfile field.
	 * @return boolean
	 */
	protected function can_see( $field = null ) {
		$user_id = bp_loggedin_user_id();
		$retval  = false;

		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool              retval          Return value.
		 * @param int               $user_id        User ID.
		 * @param BP_XProfile_Field $field XProfile Field.
		 */
		return apply_filters( 'rest_xprofile_field_can_see', $retval, $user_id, $field );
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
			'title'      => 'xprofile_fields',
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
						'sanitize_callback' => 'sanitize_text_field',
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
					'description' => __( 'The description of the profile field group.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
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

		return $schema;
	}

	/**
	 * Get the query params for collections of XProfile fields.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		return $params;
	}

	/**
	 * Get the query params for a XProfile field.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['user_id'] = array(
			'description'       => __( 'Required if you want to load a specific user\'s data.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => bp_loggedin_user_id(),
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
