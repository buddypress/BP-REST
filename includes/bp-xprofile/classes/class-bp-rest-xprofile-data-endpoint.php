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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
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
			return new WP_Error(
				'rest_invalid_field_id',
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

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field_data, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a XProfile data is added via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_XProfile_Field      $field      The field object.
		 * @param BP_XProfile_ProfileData $field_data The field data object.
		 * @param WP_User               $user      The user object.
		 * @param WP_REST_Response      $response  The response data.
		 * @param WP_REST_Request       $request   The request sent to the API.
		 */
		do_action( 'bp_rest_xprofile_data_create_item', $field, $field_data, $user, $response, $request );

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

		$user = bp_rest_get_user( $request['user_id'] );

		if ( true === $retval && ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
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
		 * Filter the XProfile data `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_xprofile_data_create_item_permissions_check', $retval, $request );
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
		$field = $this->get_xprofile_field_object( $request['field_id'] );

		if ( empty( $field->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_field_id',
				__( 'Invalid field id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		$field_data = $this->get_xprofile_field_data_object( $field->id, $user->ID );

		if ( ! $field_data->delete() ) {
			return new WP_Error(
				'bp_rest_xprofile_data_cannot_delete',
				__( 'Could not delete XProfile data.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Set empty for the response.
		$field_data->value = '';

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $field_data, $request )
			),
		);

		$response = rest_ensure_response( $retval );

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
		$retval = $this->create_item_permissions_check( $request );

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
			'field_id'     => $field_data->field_id,
			'user_id'      => $field_data->user_id,
			'value'        => $field_data->value,
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
		$url  = $base . $field_data->field_id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $field_data->user_id ) ),
				'embeddable' => true,
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

		// Moderators can as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		// Field owners also can.
		if ( bp_loggedin_user_id() === $field_user_id ) {
			return true;
		}

		return false;
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
			'title'      => esc_html__( 'XProfile Data', 'buddypress' ),
			'type'       => 'object',
			'properties' => array(
				'field_id'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the field the data is from.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'user_id'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of user the field data is from.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'value'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The value of the field data.', 'buddypress' ),
					'type'        => 'integer',
				),
				'last_updated' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The date the field data was clast updated, in the site\'s timezone.', 'buddypress' ),
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
		return apply_filters( 'bp_rest_xprofile_data_schema', $schema );
	}
}
