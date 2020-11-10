<?php
/**
 * BP REST: BP_REST_XProfile_Data_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * XProfile Data endpoints.
 *
 * Use /xprofile/{field_id}/data/{user_id}
 *
 * @since 0.1.0
 */
class BP_REST_XProfile_Data_Endpoint extends WP_REST_Controller {

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
		$this->rest_base       = buddypress()->profile->id;
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
			'/' . $this->rest_base . '/(?P<field_id>[\d]+)/data/(?P<user_id>[\d]+)',
			array(
				'args'   => array(
					'field_id' => array(
						'description' => __( 'The ID of the field the data is from.', 'buddypress' ),
						'required'    => true,
						'type'        => 'integer',
					),
					'user_id'  => array(
						'description' => __( 'The ID of user the field data is from.', 'buddypress' ),
						'required'    => true,
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'value' => array(
							'description' => __( 'The value(s) for the field data.', 'buddypress' ),
							'required'    => true,
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
							),
						),
					),
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
	 * Retrieve single XProfile field data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		// Get Field data.
		$field_data = $this->get_xprofile_field_data_object( $request->get_param( 'field_id' ), $request->get_param( 'user_id' ) );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field_data, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires before a XProfile data is retrieved via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_ProfileData $field_data The field data object.
		 * @param WP_REST_Response      $response  The response data.
		 * @param WP_REST_Request       $request   The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_data_get_item', $field_data, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get users's data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;

		// Check the field exists.
		$field = $this->get_xprofile_field_object( $request->get_param( 'field_id' ) );

		if ( empty( $field->id ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid field ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Check the requested user exists.
		if ( true === $retval && ! bp_rest_get_user( $request->get_param( 'user_id' ) ) ) {
			$retval = new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Check the user can view this field value.
		$hidden_user_fields = bp_xprofile_get_hidden_fields_for_user( $request->get_param( 'user_id' ) );

		if ( true === $retval && in_array( $field->id, $hidden_user_fields, true ) ) {
			$retval = new WP_Error(
				'bp_rest_hidden_profile_field',
				__( 'Sorry, the profile field value is not viewable for this user.', 'buddypress' ),
				array(
					'status' => 403,
				)
			);
		}

		/**
		 * Filter the XProfile data `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_data_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Save XProfile data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		// Setting context.
		$request->set_param( 'context', 'edit' );

		$field = $this->get_xprofile_field_object( $request->get_param( 'field_id' ) );

		if ( empty( $field->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid field ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$user  = bp_rest_get_user( $request->get_param( 'user_id' ) );
		$value = $request->get_param( 'value' );

		/**
		 * For field types not supporting multiple values, join values in case
		 * the submitted value was not an array.
		 */
		if ( ! $field->type_obj->supports_multiple_defaults ) {
			$value = implode( ' ', (array) $value );
		} else {
			$value = preg_split( '/[,]+/', $value );
		}

		if ( ! xprofile_set_field_data( $field->id, $user->ID, $value ) ) {
			return new WP_Error(
				'rest_user_cannot_save_xprofile_data',
				__( 'Cannot save XProfile data.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Get Field data.
		$field_data = $this->get_xprofile_field_data_object( $field->id, $user->ID );

		// Create Additional fields.
		$fields_update = $this->update_additional_fields_for_object( $field_data, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field_data, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a XProfile data is saved via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field       $field      The field object.
		 * @param BP_XProfile_ProfileData $field_data The field data object.
		 * @param WP_User                 $user      The user object.
		 * @param WP_REST_Response        $response  The response data.
		 * @param WP_REST_Request         $request   The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_data_save_item', $field, $field_data, $user, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to save XProfile field data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to save XProfile data.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request->get_param( 'user_id' ) );

		if ( true === $retval && ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid member ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! $this->can_see( $user->ID ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you cannot save XProfile field data.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the XProfile data `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_data_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete user's XProfile data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		// Setting context.
		$request->set_param( 'context', 'edit' );

		$field = $this->get_xprofile_field_object( $request->get_param( 'field_id' ) );

		if ( empty( $field->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid field ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$user = bp_rest_get_user( $request->get_param( 'user_id' ) );

		// Get the field data before it's deleted.
		$field_data = $this->get_xprofile_field_data_object( $field->id, $user->ID );

		// Set empty for the response.
		$field_data->value = '';
		$previous          = $this->prepare_item_for_response( $field_data, $request );

		if ( false === $field_data->delete() ) {
			return new WP_Error(
				'bp_rest_xprofile_data_cannot_delete',
				__( 'Could not delete XProfile data.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Build the response.
		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		/**
		 * Fires after a XProfile data is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field       $field       Deleted field object.
		 * @param BP_XProfile_ProfileData  $field_data  Deleted field data object.
		 * @param WP_User                $user       User object.
		 * @param WP_REST_Response       $response   The response data.
		 * @param WP_REST_Request        $request    The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_data_delete_item', $field, $field_data, $user, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete users's data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->update_item_permissions_check( $request );

		/**
		 * Filter the XProfile data `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_data_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares XProfile data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param  BP_XProfile_ProfileData $field_data XProfile field data object.
	 * @param  WP_REST_Request         $request   Full data about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $field_data, $request ) {
		$data = array(
			'id'           => $field_data->id,
			'field_id'     => $field_data->field_id,
			'user_id'      => $field_data->user_id,
			'value'        => array(
				'raw'          => $field_data->value,
				'unserialized' => $this->fields_endpoint->get_profile_field_unserialized_value( $field_data->value ),
				'rendered'     => $this->fields_endpoint->get_profile_field_rendered_value( $field_data->value, $field_data->field_id ),
			),
			'last_updated' => bp_rest_prepare_date_response( $field_data->last_updated ),
		);

		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $field_data ) );

		/**
		 * Filter the XProfile data response returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response      $response  The response data.
		 * @param WP_REST_Request       $request   Request used to generate the response.
		 * @param BP_XProfile_ProfileData $field_data XProfile field data object.
		 */
		return apply_filters( 'bp_rest_xprofile_data_prepare_value', $response, $request, $field_data );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_ProfileData $field_data XProfile field data object.
	 * @return array
	 */
	protected function prepare_links( $field_data ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );

		// Entity meta.
		$links = array(
			'self' => array(
				'href' => rest_url( $base . $field_data->field_id ),
			),
			'user' => array(
				'href'       => rest_url( bp_rest_get_user_url( $field_data->user_id ) ),
				'embeddable' => true,
			),
		);

		/**
		 * Filter links prepared for the REST response.
		 *
		 * @since 0.1.0
		 *
		 * @param array                 $links     The prepared links of the REST response.
		 * @param BP_XProfile_ProfileData $field_data XProfile field data object.
		 */
		return apply_filters( 'bp_rest_xprofile_data_prepare_links', $links, $field_data );
	}

	/**
	 * Get XProfile field object.
	 *
	 * @since 0.1.0
	 *
	 * @param int $field_id Field id.
	 * @return BP_XProfile_Field
	 */
	public function get_xprofile_field_object( $field_id ) {
		return $this->fields_endpoint->get_xprofile_field_object( $field_id );
	}

	/**
	 * Get XProfile field data object.
	 *
	 * @since 0.1.0
	 *
	 * @param int $field_id Field id.
	 * @param int $user_id User id.
	 * @return BP_XProfile_ProfileData
	 */
	public function get_xprofile_field_data_object( $field_id, $user_id ) {
		return new BP_XProfile_ProfileData( $field_id, $user_id );
	}

	/**
	 * Can this user see the XProfile data?
	 *
	 * @since 0.1.0
	 *
	 * @param int $field_user_id User ID of the field.
	 * @return bool
	 */
	protected function can_see( $field_user_id ) {
		return ( bp_current_user_can( 'bp_moderate' ) || bp_loggedin_user_id() === $field_user_id );
	}

	/**
	 * Get the XProfile data schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_xprofile_data',
			'type'       => 'object',
			'properties' => array(
				'id'           => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the profile data.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'field_id'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the field the data is from.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'user_id'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the user the field data is from.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'value'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The value of the field data.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null,
						'validate_callback' => null,
					),
					'properties'  => array(
						'raw'          => array(
							'description' => __( 'Value for the field, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'unserialized' => array(
							'description' => __( 'Unserialized value for the field, regular string will be casted as array.', 'buddypress' ),
							'type'        => 'array',
							'context'     => array( 'view', 'edit' ),
							'items'       => array(
								'type' => 'string',
							),
							'readonly'    => true,
						),
						'rendered'     => array(
							'description' => __( 'HTML value for the field, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'last_updated' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The date the field data was last updated, in the site\'s timezone.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
			),
		);

		/**
		 * Filters the xprofile data schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_xprofile_data_schema', $this->add_additional_fields_schema( $schema ) );
	}
}
