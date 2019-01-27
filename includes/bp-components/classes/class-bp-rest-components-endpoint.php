<?php
/**
 * BP REST: BP_REST_Components_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Components endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Components_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'buddypress/v1';
		$this->rest_base = 'components';
	}

	/**
	 * Register the component routes.
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
	}

	/**
	 * Retrieve components.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'type'     => $request['type'],
			'status'   => $request['status'],
			'per_page' => $request['per_page'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_components_get_items_query_args', $args, $request );

		$type = $args['type'];

		// Get all components based on type.
		$components = bp_core_get_components( $type );

		// Active components.
		$active_components = apply_filters( 'bp_active_components', bp_get_option( 'bp-active-components' ) );

		// Core component is always active.
		if ( 'optional' !== $type ) {
			$active_components['core'] = $components['core'];
		}

		// Inactive components.
		$inactive_components = array_diff( array_keys( $components ), array_keys( $active_components ) );

		$current_components = array();
		switch ( $args['status'] ) {
			case 'all':
				foreach ( $components as $name => $labels ) {
					$current_components[] = array(
						'name'        => $name,
						'status'      => $this->verify_component_status( $name ),
						'title'       => $labels['title'],
						'description' => $labels['description'],
					);
				}
				break;

			case 'active':
				foreach ( array_keys( $active_components ) as $component ) {
					$info = $components[ $component ];
					$current_components[] = array(
						'name'        => $component,
						'status'      => __( 'active', 'buddypress' ),
						'title'       => $info['title'],
						'description' => $info['description'],
					);
				}
				break;

			case 'inactive':
				foreach ( $inactive_components as $component ) {
					$info = $components[ $component ];
					$current_components[] = array(
						'name'        => $component,
						'status'      => __( 'inactive', 'buddypress' ),
						'title'       => $info['title'],
						'description' => $info['description'],
					);
				}
				break;
		}

		$retval = array();
		foreach ( $current_components as $component ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $component, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, count( $current_components ), $args['per_page'] );

		/**
		 * Fires after a list of components is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $current_components Fetched components.
		 * @param WP_REST_Response $response           The response data.
		 * @param WP_REST_Request  $request            The request sent to the API.
		 */
		do_action( 'bp_rest_components_get_items', $current_components, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to list components.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to list components.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! bp_current_user_can( 'bp_moderate' ) ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you do not have access to list components.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Prepares component data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param array           $component Component.
	 * @param WP_REST_Request $request   Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $component, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $component, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		/**
		 * Filter a component value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response  The Response data.
		 * @param WP_REST_Request  $request   Request used to generate the response.
		 * @param array            $component The component and its values.
		 */
		return apply_filters( 'bp_rest_component_prepare_value', $response, $request, $component );
	}

	/**
	 * Verify Component Status.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Component id.
	 * @return string
	 */
	protected function verify_component_status( $id ) {
		$active = __( 'active', 'buddypress' );

		if ( 'core' === $id ) {
			return $active;
		}

		return ( bp_is_active( $id ) ) ? $active : __( 'inactive', 'buddypress' );
	}

	/**
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'component',
			'type'       => 'object',
			'properties' => array(
				'name'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Name of the object.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),

				'status'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the object is active or inactive.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),

				'title'                 => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML title of the object.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),

				'description'                 => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML description of the object.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		);

		return $schema;
	}

	/**
	 * Get the query params for collections.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['status'] = array(
			'description'       => __( 'Limit result set to items with a specific status.', 'buddypress' ),
			'default'           => 'all',
			'type'              => 'string',
			'enum'              => array( 'all', 'active', 'inactive' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Limit result set to items with a specific type.', 'buddypress' ),
			'default'           => 'all',
			'type'              => 'string',
			'enum'              => array( 'all', 'optional', 'retired', 'required' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
