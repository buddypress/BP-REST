<?php
/**
 * BP REST: BP_REST_Signup_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Signup endpoints.
 *
 * Use /signup/{id}
 * Use /signup/activate/{id}
 *
 * @since 0.1.0
 */
class BP_REST_Signup_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = 'signup';
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description'       => __( 'A unique numeric ID for the signup.', 'buddypress' ),
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'edit' ) ),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		// Register the activate route.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/activate/(?P<id>[\w-]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Identifier for the signup. Can be a signup ID, an email address, or a user_login.', 'buddypress' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'activate_item' ),
					'permission_callback' => array( $this, 'activate_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'edit' ) ),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieve single signup.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		// Get signup.
		$signup = $this->get_signup_object( $request['id'] );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $signup, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires before a signup is retrieved via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Signup         $signup    The signup object.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'bp_rest_signup_get_item', $signup, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get a signup.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;
		$signup = $this->get_signup_object( $request['id'] );

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to perfom this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( true === $retval && empty( $signup ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid signup id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not authorized to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the signup `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_signup_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete a signup.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		// Setting context.
		$request->set_param( 'context', 'edit' );

		// Get the signup before it's deleted.
		$signup   = $this->get_signup_object( $request['id'] );
		$previous = $this->prepare_item_for_response( $signup, $request );
		$deleted  = BP_Signup::delete( array( $signup->id ) );

		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_signup_cannot_delete',
				__( 'Could not delete the signup.', 'buddypress' ),
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
		 * Fires after a signup is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Signup        $signup   The deleted signup.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_signup_delete_item', $signup, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a signup.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the signup `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_signup_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Activate a signup.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_item( $request ) {
		// Setting context.
		$request->set_param( 'context', 'edit' );

		// Get the signup.
		$signup    = $this->get_signup_object( $request['id'] );
		$activated = bp_core_activate_signup( $signup->activation_key );

		if ( ! $activated ) {
			return new WP_Error(
				'bp_rest_signup_activate_fail',
				__( 'Fail to activate the signup.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $signup, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a signup is activated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Signup        $signup   The activated signup.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_signup_activate_item', $signup, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to activate a signup.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function activate_item_permissions_check( $request ) {
		$retval = true;
		$signup = $this->get_signup_object( $request['id'] );

		if ( empty( $signup ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid signup id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Filter the signup `activate_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_signup_activate_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares signup to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param  BP_Signup       $signup  Signup object.
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $signup, $request ) {
		$data = array(
			'id'         => $signup->id,
			'user_login' => $signup->user_login,
			'user_name'  => $signup->user_name,
			'registered' => bp_rest_prepare_date_response( $signup->registered ),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		if ( 'edit' === $context ) {
			$data['activation_key'] = $signup->activation_key;
			$data['user_email']     = $signup->user_email;
		}

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		// @todo add prepare_links
		$response = rest_ensure_response( $data );

		/**
		 * Filter the signup response returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 * @param BP_Signup         $signup   Signup object.
		 */
		return apply_filters( 'bp_rest_signup_prepare_value', $response, $request, $signup );
	}

	/**
	 * Get signup object.
	 *
	 * @since 0.1.0
	 *
	 * @param int $identifier Signup identifier.
	 * @return BP_Signup|bool
	 */
	public function get_signup_object( $identifier ) {
		if ( is_numeric( $identifier ) ) {
			$signup_args['include'] = array( intval( $identifier ) );
		} elseif ( is_email( $identifier ) ) {
			$signup_args['usersearch'] = $identifier;
		} else {
			$signup_args['user_login'] = $identifier;
		}

		// Get signups.
		$signups = \BP_Signup::get( $signup_args );

		if ( ! empty( $signups['signups'] ) ) {
			return reset( $signups['signups'] );
		}

		return false;
	}

	/**
	 * Get the signup schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_signup',
			'type'       => 'object',
			'properties' => array(
				'id'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the signup.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'user_login'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The username of the user the signup is for.', 'buddypress' ),
					'required'    => true,
					'type'        => 'string',
					'readonly'    => true,
				),
				'user_name'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The full name of the user the signup is for.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'user_email'     => array(
					'context'     => array( 'edit' ),
					'description' => __( 'The email for the user the signup is for.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'activation_key' => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Activation key of the signup.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'registered'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The registered date for the user, in the site\'s timezone.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
					'format'      => 'date-time',
				),
			),
		);

		/**
		 * Filters the signup schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_signup_schema', $this->add_additional_fields_schema( $schema ) );
	}
}
