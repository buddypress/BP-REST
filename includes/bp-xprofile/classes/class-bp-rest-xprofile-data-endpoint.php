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
 * Use /xprofile/data
 * Use /xprofile/data/{id}
 *
 * @since 0.1.0
 */
class BP_REST_XProfile_Data_Endpoint extends WP_REST_Controller {

	/**
	 * XProfile Fields Class.
	 *
	 * @since 0.1.0
	 *
	 * @param $field_endpoint BP_REST_XProfile_Fields_Endpoint
	 */
	protected $field_endpoint;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace      = 'buddypress/v1';
		$this->rest_base      = buddypress()->profile->id . '/data';
		$this->field_endpoint = new BP_REST_XProfile_Fields_Endpoint();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_item_params(),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( true ),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Set, save, XProfile data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$field = $this->get_xprofile_field_object( $request['field_id'] );

		if ( empty( $field->id ) ) {
			return new WP_Error( 'rest_invalid_field_id',
				__( 'Invalid field id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$user  = bp_rest_get_user( $request['user_id'] );
		$value = $request['value'];

		if ( 'checkbox' === $field->type ) {
			$value = explode( ',', $value );
		}

		$updated = xprofile_set_field_data( $field->id, $user->ID, $value );

		if ( ! $updated ) {
			return new WP_Error( 'rest_user_cannot_save_xprofile_data',
				__( 'Cannot save XProfile data.', 'buddypress' ),
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
		 * Fires after a XProfile data is added via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field  $field      The field object.
		 * @param WP_User           $user      The user object.
		 * @param mixed             $value     The field data added.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_data_create_item', $field, $value, $response, $request );

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
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to save XProfile data.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		if ( empty( $user->ID ) ) {
			return new WP_Error( 'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_see( $user->ID ) ) {
			return new WP_Error( 'rest_user_cannot_view_field_data',
				__( 'Sorry, you cannot save XProfile field data.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Delete users's XProfile data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$field = $this->get_xprofile_field_object( $request['field_id'] );

		if ( empty( $field->id ) ) {
			return new WP_Error( 'bp_rest_invalid_field_id',
				__( 'Invalid field id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		$field_data = new BP_XProfile_ProfileData( $field->id, $user->ID );

		if ( ! $field_data->delete() ) {
			return new WP_Error( 'bp_rest_xprofile_data_cannot_delete',
				__( 'Could not delete XProfile data.', 'buddypress' ),
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
		 * Fires after a XProfile data is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field  $field      Deleted field object.
		 * @param WP_User           $user      User object.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_data_delete_item', $field, $user, $response, $request );

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
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Prepares XProfile data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param  BP_XProfile_Field $field    XProfile field object.
	 * @param  WP_REST_Request   $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $field, $request ) {
		$data = array(
			'field_id'     => $field->data->field_id,
			'user_id'      => $field->data->user_id,
			'value'        => xprofile_get_field_data( $field->data->field_id, $field->data->user_id ),
			'last_updated' => bp_rest_prepare_date_response( $field->data->last_updated ),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $field->data->field_id ) );

		/**
		 * Filter the XProfile data response returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 * @param BP_XProfile_Field  $field     XProfile field object.
		 */
		return apply_filters( 'bp_rest_xprofile_data_prepare_value', $response, $request, $field );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $field_id XProfile field id.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $field_id ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $field_id;

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
	 * Get XProfile field object.
	 *
	 * @since 0.1.0
	 *
	 * @param int $field_id Field id.
	 * @return BP_XProfile_Field
	 */
	public function get_xprofile_field_object( $field_id ) {
		return $this->field_endpoint->get_xprofile_field_object( $field_id );
	}

	/**
	 * Can this user see the XProfile data?
	 *
	 * @since 0.1.0
	 *
	 * @param int $field_user_id User ID of the field.
	 * @return boolean
	 */
	protected function can_see( $field_user_id ) {
		$user_id = bp_loggedin_user_id();
		$retval  = false;

		// Moderators can as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		// Field owners also can.
		if ( $user_id === $field_user_id ) {
			$retval = true;
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $retval  Return value.
		 * @param int  $user_id User ID.
		 */
		return apply_filters( 'bp_rest_xprofile_data_can_see', $retval, $user_id );
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
			'title'      => 'xprofile_data',
			'type'       => 'object',
			'properties' => array(
				'field_id'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the field the data is from.', 'buddypress' ),
					'type'        => 'integer',
				),

				'user_id'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of user the field data is from.', 'buddypress' ),
					'type'        => 'integer',
				),

				'value'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The value of the field data.', 'buddypress' ),
					'type'        => 'integer',
				),

				'last_updated'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The date the field data was clast updated, in the site\'s timezone.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
			),
		);

		return $schema;
	}

	/**
	 * Get the query params for a XProfile data.
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

		$params['field_id'] = array(
			'description'       => __( 'The ID of the field that data is from.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
