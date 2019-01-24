<?php
/**
 * BP REST: BP_REST_Notifications_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notifications endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Notifications_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'buddypress/v1';
		$this->rest_base = buddypress()->notifications->id;
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
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( true ),
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
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( false ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Retrieve notifications.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response List of notifications object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'user_id'           => $request['user_id'],
			'item_id'           => $request['item_id'],
			'secondary_item_id' => $request['secondary_item_id'],
			'component_name'    => $request['component_name'],
			'component_action'  => $request['component_action'],
			'is_new'            => $request['is_new'],
			'search_terms'      => $request['search'],
			'date_query'        => $request['date'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_notifications_get_items_query_args', $args, $request );

		// Actually, query it.
		$notifications = BP_Notifications_Notification::get( $args );

		$retval = array();
		foreach ( $notifications as $notification ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $notification, $request )
			);
		}

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after notifications are fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $notifications Fetched notifications.
		 * @param WP_REST_Response $response      The response data.
		 * @param WP_REST_Request  $request       The request sent to the API.
		 */
		do_action( 'bp_rest_notification_get_items', $notifications, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to notification items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to see the notifications.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! $this->can_see() ) {
			return new WP_Error( 'bp_rest_user_cannot_view_notifications',
				__( 'Sorry, you cannot view the notifications.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Retrieve a notification.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$notification = $this->get_notification_object( $request );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $notification, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a notification is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Notifications_Notification $notification Fetched notification.
		 * @param WP_REST_Response              $response     The response data.
		 * @param WP_REST_Request               $request      The request sent to the API.
		 */
		do_action( 'bp_rest_notification_get_item', $notification, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get information about a specific notification.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to see the notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$notification = $this->get_notification_object( $request );

		if ( empty( $notification->id ) ) {
			return new WP_Error( 'bp_rest_notification_invalid_id',
				__( 'Invalid notification id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_see( $notification->id ) ) {
			return new WP_Error( 'bp_rest_user_cannot_view_notification',
				__( 'Sorry, you cannot view this notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Create a notification.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Request|WP_Error
	 */
	public function create_item( $request ) {
		$notification_id = bp_notifications_add_notification( $this->prepare_item_for_database( $request ) );

		if ( ! is_numeric( $notification_id ) ) {
			return new WP_Error( 'bp_rest_user_cannot_create_notification',
				__( 'Cannot create new notification.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$notification = $this->get_notification_object( $notification_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $notification, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a notification is created via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Notifications_Notification  $notification The created notification.
		 * @param WP_REST_Response               $response     The response data.
		 * @param WP_REST_Request                $request      The request sent to the API.
		 */
		do_action( 'bp_rest_notification_create_item', $notification, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to create a notification.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to create a notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! $this->can_see() ) {
			return new WP_Error( 'bp_rest_user_cannot_create_notification',
				__( 'Sorry, you cannot create a notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Update a notification.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$notification = $this->get_notification_object( $request );

		if ( $request['is_new'] === $notification->is_new ) {
			return new WP_Error( 'bp_rest_user_cannot_update_notification_status',
				__( 'Notification is already with the status you are trying to update into.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$updated = BP_Notifications_Notification::update(
			array( 'is_new' => $request['is_new'] ),
			array( 'id' => $notification->id )
		);

		if ( ! (bool) $updated ) {
			return new WP_Error( 'bp_rest_user_cannot_update_notification',
				__( 'Cannot update the status of this notification.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $notification, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a notification is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Notifications_Notification $notification The updated activity.
		 * @param WP_REST_Response              $response     The response data.
		 * @param WP_REST_Request               $request      The request sent to the API.
		 */
		do_action( 'bp_rest_notification_update_item', $notification, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a notification.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to update a notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$notification = $this->get_notification_object( $request );

		if ( empty( $notification->user_id ) ) {
			return new WP_Error( 'bp_rest_notification_invalid_id',
				__( 'Invalid notification id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_see( $notification->id ) ) {
			return new WP_Error( 'bp_rest_user_cannot_update_notification',
				__( 'Sorry, you are not allowed to update this this notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Delete a notification.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$notification = $this->get_notification_object( $request );

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $notification, $request );

		if ( ! BP_Notifications_Notification::delete( array( 'id' => $notification->id ) ) ) {
			return new WP_Error( 'bp_rest_notification_invalid_id',
				__( 'Invalid notification id.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Fires after a notification is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Notifications_Notification $notification The deleted notification.
		 * @param WP_REST_Response              $response     The response data.
		 * @param WP_REST_Request               $request      The request sent to the API.
		 */
		do_action( 'bp_rest_notification_delete_item', $notification, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a notification.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to delete a notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$notification = $this->get_notification_object( $request );

		if ( ! $this->can_see( $notification->id ) ) {
			return new WP_Error( 'bp_rest_user_cannot_delete_notification',
				__( 'Sorry, you cannot delete this notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Prepares notification data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Notifications_Notification $notification Notification data.
	 * @param WP_REST_Request               $request      Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $notification, $request ) {
		$data = array(
			'id'                => $notification->id,
			'user_id'           => $notification->user_id,
			'item_id'           => $notification->item_id,
			'secondary_item_id' => $notification->secondary_item_id,
			'component'         => $notification->component_name,
			'action'            => $notification->component_action,
			'date'              => bp_rest_prepare_date_response( $notification->date_notified ),
			'unread'            => $notification->is_new,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		/**
		 * Filter a notification value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response            $response    The response data.
		 * @param WP_REST_Request             $request     Request used to generate the response.
		 * @param BP_Notifications_Notification $notification Notification object.
		 */
		return apply_filters( 'bp_rest_notification_prepare_value', $response, $request, $notification );
	}

	/**
	 * Prepare a notification for create or update.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_notification = new stdClass();
		$schema                = $this->get_item_schema();
		$notification          = $this->get_notification_object( $request );

		if ( ! empty( $schema['properties']['id'] ) && ! empty( $notification->id ) ) {
			$prepared_notification->id = $notification->id;
		}

		if ( ! empty( $schema['properties']['user_id'] ) && isset( $request['user_id'] ) ) {
			$prepared_notification->user_id = (int) $request['user_id'];
		} else {
			$prepared_notification->user_id = get_current_user_id();
		}

		if ( ! empty( $schema['properties']['item_id'] ) && isset( $request['item_id'] ) ) {
			$prepared_notification->item_id = $request['item_id'];
		}

		if ( ! empty( $schema['properties']['secondary_item_id'] ) && isset( $request['secondary_item_id'] ) ) {
			$prepared_notification->secondary_item_id = $request['secondary_item_id'];
		}

		if ( ! empty( $schema['properties']['component'] ) && isset( $request['component'] ) ) {
			$prepared_notification->component_name = $request['component'];
		}

		if ( ! empty( $schema['properties']['action'] ) && isset( $request['action'] ) ) {
			$prepared_notification->component_action = $request['action'];
		}

		if ( ! empty( $schema['properties']['unread'] ) && isset( $request['unread'] ) ) {
			$prepared_notification->is_new = $request['unread'];
		}

		if ( ! empty( $schema['properties']['date'] ) && isset( $request['date'] ) ) {
			$prepared_notification->date_notified = $request['date'];
		}

		/**
		 * Filters a notification before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_notification An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'bp_rest_notification_pre_insert_value', $prepared_notification, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Notifications_Notification $notification Notification item.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $notification ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $notification->id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $notification->user_id ) ),
				'embeddable' => true,
			),
		);

		return $links;
	}

	/**
	 * Can this user see the notification?
	 *
	 * @since 0.1.0
	 *
	 * @param int $notification_id Notification ID.
	 * @return boolean
	 */
	protected function can_see( $notification_id = 0 ) {
		$user_id = bp_loggedin_user_id();
		$retval  = false;

		// Check notification access.
		if ( ! empty( $notification_id ) && (bool) BP_Notifications_Notification::check_access( $user_id, $notification_id ) ) {
			$retval = true;
		}

		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $retval          Return value.
		 * @param int  $user_id         User ID.
		 * @param int  $notification_id Notification ID.
		 */
		return apply_filters( 'bp_rest_notification_can_see', $retval, $user_id, $notification_id );
	}

	/**
	 * Get notification object.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return BP_Notifications_Notification|string A notification object.
	 */
	public function get_notification_object( $request ) {
		$notification_id = is_numeric( $request ) ? $request : (int) $request['id'];

		$notification = bp_notifications_get_notification( $notification_id );

		if ( empty( $notification->id ) ) {
			return '';
		}

		return $notification;
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
			'title'      => 'notification',
			'type'       => 'object',
			'properties' => array(
				'id'                    => array(
					'context'     => array( 'view', 'embed', 'edit' ),
					'description' => __( 'The notification ID.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'item_id'     => array(
					'context'     => array( 'view', 'embed', 'edit' ),
					'description' => __( 'The ID of the item associated with the notification.', 'buddypress' ),
					'type'        => 'integer',
				),
				'secondary_item_id'     => array(
					'context'     => array( 'view', 'embed', 'edit' ),
					'description' => __( 'The ID of the secondary item associated with the notification.', 'buddypress' ),
					'type'        => 'integer',
				),
				'user_id'                    => array(
					'context'     => array( 'view', 'embed', 'edit' ),
					'description' => __( 'The ID of the user the notification is associated with.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'component'               => array(
					'context'     => array( 'view', 'embed', 'edit' ),
					'description' => __( 'The name of the component that the notification is for.', 'buddypress' ),
					'type'        => 'string',
				),
				'action'               => array(
					'context'     => array( 'view', 'embed', 'edit' ),
					'description' => __( 'The component action which the notification is related to.', 'buddypress' ),
					'type'        => 'string',
				),
				'date'                  => array(
					'description' => __( "The date the notification was created, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'embed', 'edit' ),
				),
				'unread'                => array(
					'context'     => array( 'view', 'embed', 'edit' ),
					'description' => __( 'The status of the notification.', 'buddypress' ),
					'type'        => 'integer',
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

		$params['component_action'] = array(
			'description'       => __( 'Limit result set to items from a specific actions.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['component_name'] = array(
			'description'       => __( 'Limit result set to items from a specific component.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Limit result set to items created by a specific user.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => bp_loggedin_user_id(),
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['item_id'] = array(
			'description'       => __( 'Limit result set to items with a specific item id.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => '',
			'sanitize_callback' => 'absint',
		);

		$params['secondary_item_id'] = array(
			'description'       => __( 'Limit result set to items with a secondary item id.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => '',
			'sanitize_callback' => 'absint',
		);

		$params['is_new'] = array(
			'description'       => __( 'Limit result set to items from specific states.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['search'] = array(
			'description'       => __( 'Limit result set to items that match this search query.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['date'] = array(
			'description'       => __( 'Limit result set to items published before or after a given ISO8601 compliant date.', 'buddypress' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
