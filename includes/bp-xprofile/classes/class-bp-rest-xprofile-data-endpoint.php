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
	 * @var $field_class BP_REST_XProfile_Fields_Endpoint
	 */
	protected $field_class;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace   = 'buddypress/v1';
		$this->rest_base   = buddypress()->profile->id . '/data';
		$this->field_class = new BP_REST_XProfile_Fields_Endpoint();
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
			'schema' => array( $this, 'get_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => $this->get_item_params(),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => $this->get_item_params(),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Retrieve single XProfile field data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {

		// Fallback.
		$user_id = bp_loggedin_user_id();
		if ( isset( $request['user-id'] ) ) {
			$user_id = (int) $request['user-id'];
		}

		$data = xprofile_get_field_data( $request['id'], $user_id );

		// if ( ! empty( $request['field-id'] ) ) {
		// } else {
		// 	$data           = \BP_XProfile_ProfileData::get_all_for_user( $request['user-id'] );
		// 	$formatted_data = array();

		// 	foreach ( $data as $field_name => $field_data ) {
		// 		// Omit WP core fields.
		// 		if ( ! is_array( $field_data ) ) {
		// 			continue;
		// 		}

		// 		$_field_data = maybe_unserialize( $field_data['field_data'] );
		// 		$_field_data = wp_json_encode( $_field_data );

		// 		$formatted_data[] = array(
		// 			'field_id'   => $field_data['field_id'],
		// 			'field_name' => $field_name,
		// 			'value'      => $_field_data,
		// 		);
		// 	}

		// 	$data = $formatted_data;
		// }


		// $retval = array(
		// 	$this->prepare_response_for_collection(
		// 		$this->prepare_item_for_response( $data, $request )
		// 	),
		// );

		// $response = rest_ensure_response( $retval );

		/**
		 * Fires after a user's XProfile data is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array             $data     Fetched user data.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		// do_action( 'rest_xprofile_data_get_item', $data, $response, $request );

		return $data;
	}

	/**
	 * Check if a given request has access to get a user's XProfile data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you need to be logged in to view this user\'s XProfile data.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! $this->can_see() ) {
			return new WP_Error( 'rest_user_cannot_view_xprofile_data',
				__( 'Sorry, you cannot view this users\'s XProfile data.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Set XProfile data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$field = $this->field_class->get_xprofile_field_object( (int) $request['field-id'] );

		$updated = xprofile_set_field_data( $field->id, $request['user-id'], $request['value'] );

		if ( ! $updated ) {
			return new WP_Error( 'rest_user_cannot_create_xprofile_data',
				__( 'Cannot add XProfile data.', 'buddypress' ),
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
		 * @param BP_XProfile_Field $field     The field object the data was set.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 * @param mixed             $value     The field data added.
		 */
		do_action( 'rest_xprofile_data_create_item', $field, $response, $request, $value );

		return $response;
	}

	/**
	 * Check if a given request has access to add XProfile field data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to add XProfile data.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$field = $this->field_class->get_xprofile_field_object( $request['field-id'] );

		if ( empty( $field->id ) ) {
			return new WP_Error( 'rest_invalid_field_id',
				__( 'Invalid field id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_see() ) {
			return new WP_Error( 'rest_user_cannot_create_field_data',
				__( 'Sorry, you cannot set XProfile data.', 'buddypress' ),
				array(
					'status' => 500,
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
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		// if ( isset( $request['delete-all'] ) ) {
		// 	$deleted = xprofile_remove_data( $request['user-id'] );

		$field = $this->field_class->get_xprofile_field_object( $request['field-id'] );

		$request->set_param( 'context', 'edit' );

		if ( ! xprofile_delete_field_data( $field->id, $user->ID ) ) {
			return new WP_Error( 'rest_xprofile_data_cannot_delete',
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
		 * @param BP_XProfile_Field $field     Deleted field object.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'rest_xprofile_data_delete_item', $field, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete users's data.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this XProfile data.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! isset( $request['field-id'] ) ) { // && ! isset( $request['delete-all'] )
			return new WP_Error( 'rest_xprofile_data_required_field_missing',
				__( 'Either field-id must be provided.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		if ( ! $this->can_see() ) {
			return new WP_Error( 'rest_user_cannot_delete_xprofile_data',
				__( 'Sorry, you cannot delete this user\'s data.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Prepares XProfile data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param array           $data    XProfile data.
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $data, $request ) {

		var_dump( $data );

		// $field    = $this->field_class->get_xprofile_field_object( $data['field-id'] );
		// $response = rest_ensure_response( $data );
		// $response->add_links( $this->prepare_links( $field ) );

		/**
		 * Filter the XProfile data returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 * @param array             $data     XProfile data.
		 * @param BP_XProfile_Field $field    XProfile field object.
		 */
		return apply_filters( 'rest_xprofile_field_data_prepare_value', $data, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_XProfile_Field $field XProfile field object.
	 *
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $field ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $field->id;

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
	 * Can this user see the XProfile data?
	 *
	 * @since 0.1.0
	 *
	 * @return boolean
	 */
	protected function can_see() {
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
		 * @param bool $retval  Return value.
		 * @param int  $user_id User ID.
		 */
		return apply_filters( 'rest_xprofile_data_can_see', $retval, $user_id );
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
				'id'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'field-id'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the field the data is from.', 'buddypress' ),
					'type'        => 'integer',
				),

				'user-id'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of user the field data is from.', 'buddypress' ),
					'type'        => 'integer',
				),

				'value'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The value of the field data.', 'buddypress' ),
					'type'        => 'integer',
				),

				'last-updated'          => array(
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

		$params['user-id'] = array(
			'description'       => __( 'Required if you want to load a specific user\'s data.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => bp_loggedin_user_id(),
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['field-id'] = array(
			'description'       => __( 'The ID of the field that data is from.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		// $params['delete-all'] = array(
		// 	'description'       => __( 'Option to delete all data from a specific user.', 'buddypress' ),
		// 	'type'              => 'boolean',
		// 	'default'           => false,
		// 	'validate_callback' => 'rest_validate_request_arg',
		// );

		return $params;
	}
}
