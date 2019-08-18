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
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->notifications->id;
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
				'args'   => array(
					'id' => array(
						'description' => __( 'The unique numeric ID for the notification.', 'buddypress' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
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
	 * Retrieve notifications.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'user_id'           => $request['user_id'],
			'item_id'           => $request['item_id'],
			'secondary_item_id' => $request['secondary_item_id'],
			'component_name'    => $request['component_name'],
			'component_action'  => $request['component_action'],
			'order_by'          => $request['order_by'],
			'sort_order'        => strtoupper( $request['sort_order'] ),
			'is_new'            => $request['is_new'],
			'page'              => $request['page'],
			'per_page'          => $request['per_page'],
		);

		if ( empty( $request['component_action'] ) ) {
			$args['component_action'] = false;
		}

		if ( empty( $request['component_name'] ) ) {
			$args['component_name'] = false;
		}

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
		do_action( 'bp_rest_notifications_get_items', $notifications, $response, $request );

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
		$retval = true;

		if ( ! is_user_logged_in() || ( bp_loggedin_user_id() !== $request['user_id'] && ! $this->can_see() ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to see the notifications.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the notifications `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_notifications_get_items_permissions_check', $retval, $request );
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
		do_action( 'bp_rest_notifications_get_item', $notification, $response, $request );

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
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to see the notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$notification = $this->get_notification_object( $request );

		if ( true === $retval && is_null( $notification->item_id ) ) {
			$retval = new WP_Error(
				'bp_rest_notification_invalid_id',
				__( 'Invalid notification id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! $this->can_see( $notification->id ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you cannot view this notification.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the notifications `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_notifications_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Create a notification.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		// Setting context.
		$request->set_param( 'context', 'edit' );

		$notification_id = bp_notifications_add_notification( $this->prepare_item_for_database( $request ) );

		if ( ! is_numeric( $notification_id ) ) {
			return new WP_Error(
				'bp_rest_user_cannot_create_notification',
				__( 'Cannot create new notification.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$notification  = $this->get_notification_object( $notification_id );
		$fields_update = $this->update_additional_fields_for_object( $notification, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

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
		do_action( 'bp_rest_notifications_create_item', $notification, $response, $request );

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
		$retval = $this->get_items_permissions_check( $request );

		/**
		 * Filter the notifications `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_notifications_create_item_permissions_check', $retval, $request );
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
		// Setting context.
		$request->set_param( 'context', 'edit' );

		$notification = $this->get_notification_object( $request );

		if ( $request['is_new'] === $notification->is_new ) {
			return new WP_Error(
				'bp_rest_user_cannot_update_notification_status',
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
			return new WP_Error(
				'bp_rest_user_cannot_update_notification',
				__( 'Cannot update the status of this notification.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Make sure to update the status of the notification.
		$notification = $this->prepare_item_for_database( $request );

		// Update additional fields.
		$fields_update = $this->update_additional_fields_for_object( $notification, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
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
		 * @param BP_Notifications_Notification $notification The updated notification.
		 * @param WP_REST_Response            $response    The response data.
		 * @param WP_REST_Request             $request     The request sent to the API.
		 */
		do_action( 'bp_rest_notifications_update_item', $notification, $response, $request );

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
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the notifications `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_notifications_update_item_permissions_check', $retval, $request );
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
		// Setting context.
		$request->set_param( 'context', 'edit' );

		// Get the notification before it's deleted.
		$notification = $this->get_notification_object( $request );
		$previous     = $this->prepare_item_for_response( $notification, $request );

		if ( ! BP_Notifications_Notification::delete( array( 'id' => $notification->id ) ) ) {
			return new WP_Error(
				'bp_rest_notification_invalid_id',
				__( 'Invalid notification id.', 'buddypress' ),
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
		 * Fires after a notification is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Notifications_Notification $notification The deleted notification.
		 * @param WP_REST_Response              $response     The response data.
		 * @param WP_REST_Request               $request      The request sent to the API.
		 */
		do_action( 'bp_rest_notifications_delete_item', $notification, $response, $request );

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
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the notifications `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_notifications_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares notification data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Notifications_Notification $notification Notification data.
	 * @param WP_REST_Request               $request     Full details about the request.
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
			'is_new'            => $notification->is_new,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// @todo add prepare_links
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
		return apply_filters( 'bp_rest_notifications_prepare_value', $response, $request, $notification );
	}

	/**
	 * Prepare a notification for create or update.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass
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
		} elseif ( isset( $notification->user_id ) ) {
			$prepared_notification->user_id = $notification->user_id;
		} else {
			$prepared_notification->user_id = bp_loggedin_user_id();
		}

		if ( ! empty( $schema['properties']['item_id'] ) && isset( $request['item_id'] ) ) {
			$prepared_notification->item_id = $request['item_id'];
		} elseif ( isset( $notification->item_id ) ) {
			$prepared_notification->item_id = $notification->item_id;
		}

		if ( ! empty( $schema['properties']['secondary_item_id'] ) && isset( $request['secondary_item_id'] ) ) {
			$prepared_notification->secondary_item_id = $request['secondary_item_id'];
		} elseif ( isset( $notification->secondary_item_id ) ) {
			$prepared_notification->secondary_item_id = $notification->secondary_item_id;
		}

		if ( ! empty( $schema['properties']['component'] ) && isset( $request['component'] ) ) {
			$prepared_notification->component_name = $request['component'];
		} elseif ( isset( $notification->component_name ) ) {
			$prepared_notification->component_name = $notification->component_name;
		}

		if ( ! empty( $schema['properties']['action'] ) && isset( $request['action'] ) ) {
			$prepared_notification->component_action = $request['action'];
		} elseif ( isset( $notification->component_action ) ) {
			$prepared_notification->component_action = $notification->component_action;
		}

		if ( ! empty( $schema['properties']['is_new'] ) && isset( $request['is_new'] ) ) {
			$prepared_notification->is_new = $request['is_new'];
		} elseif ( isset( $notification->is_new ) ) {
			$prepared_notification->is_new = $notification->is_new;
		}

		if ( ! empty( $schema['properties']['date'] ) && isset( $request['date'] ) ) {
			$prepared_notification->date_notified = $request['date'];
		} elseif ( isset( $notification->date_notified ) ) {
			$prepared_notification->date_notified = $notification->date_notified;
		}

		/**
		 * Filters a notification before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_notification An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'bp_rest_notifications_pre_insert_value', $prepared_notification, $request );
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

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $base . $notification->id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $notification->user_id ) ),
				'embeddable' => true,
			),
		);

		/**
		 * Filter links prepared for the REST response.
		 *
		 * @since 0.1.0
		 *
		 * @param array                       $links       The prepared links of the REST response.
		 * @param BP_Notifications_Notification $notification Notification object.
		 */
		return apply_filters( 'bp_rest_notifications_prepare_links', $links, $notification );
	}

	/**
	 * Can this user see the notification?
	 *
	 * @since 0.1.0
	 *
	 * @param int $notification_id Notification ID.
	 * @return bool
	 */
	protected function can_see( $notification_id = 0 ) {

		// Check notification access.
		if ( ! empty( $notification_id ) && (bool) BP_Notifications_Notification::check_access( bp_loggedin_user_id(), $notification_id ) ) {
			return true;
		}

		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		return false;
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
	 * Select the item schema arguments needed for the EDITABLE method.
	 *
	 * @since 0.1.0
	 *
	 * @param string $method Optional. HTTP method of the request.
	 * @return array Endpoint arguments.
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
		$args = WP_REST_Controller::get_endpoint_args_for_item_schema( $method );
		$key  = 'get_item';

		if ( WP_REST_Server::EDITABLE === $method ) {
			$key = 'update_item';

			// Only switching the is_new property can be achieved.
			$args                      = array_intersect_key( $args, array( 'is_new' => true ) );
			$args['is_new']['default'] = 0;
		} elseif ( WP_REST_Server::CREATABLE === $method ) {
			$key = 'create_item';
		} elseif ( WP_REST_Server::DELETABLE === $method ) {
			$key = 'delete_item';
		}

		/**
		 * Filters the method query arguments.
		 *
		 * @since 0.1.0
		 *
		 * @param array  $args   Query arguments.
		 * @param string $method HTTP method of the request.
		 */
		return apply_filters( "bp_rest_notifications_{$key}_query_arguments", $args, $method );
	}

	/**
	 * Get the notification schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_notifications',
			'type'       => 'object',
			'properties' => array(
				'id'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the notification.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'user_id'           => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the user the notification is addressed to.', 'buddypress' ),
					'type'        => 'integer',
					'default'     => bp_loggedin_user_id(),
				),
				'item_id'           => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the item associated with the notification.', 'buddypress' ),
					'type'        => 'integer',
				),
				'secondary_item_id' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the secondary item associated with the notification.', 'buddypress' ),
					'type'        => 'integer',
				),
				'component'         => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the BuddyPress component the notification relates to.', 'buddypress' ),
					'type'        => 'string',
				),
				'action'            => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the component\'s action the notification is about.', 'buddypress' ),
					'type'        => 'string',
				),
				'date'              => array(
					'description' => __( 'The date the notification was created, in the site\'s timezone.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'is_new'            => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether it\'s a new notification or not.', 'buddypress' ),
					'type'        => 'integer',
					'default'     => 1,
				),
			),
		);

		/**
		 * Filters the notifications schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_notification_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for the notifications collections.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		// Remove the search argument.
		unset( $params['search'] );

		$params['order_by'] = array(
			'description'       => __( 'Name of the field to order according to.', 'buddypress' ),
			'default'           => 'id',
			'type'              => 'string',
			'enum'              => array( 'id', 'date_notified', 'item_id', 'secondary_item_id', 'component_name', 'component_action' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['sort_order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddypress' ),
			'default'           => 'ASC',
			'type'              => 'string',
			'enum'              => array( 'ASC', 'DESC' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['component_name'] = array(
			'description'       => __( 'Limit result set to notifications associated with a specific component', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['component_action'] = array(
			'description'       => __( 'Limit result set to notifications associated with a specific component\'s action name.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Limit result set to notifications addressed to a specific user.', 'buddypress' ),
			'default'           => bp_loggedin_user_id(),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['item_id'] = array(
			'description'       => __( 'Limit result set to notifications associated with a specific item ID.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['secondary_item_id'] = array(
			'description'       => __( 'Limit result set to notifications associated with a specific secondary item ID.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['is_new'] = array(
			'description'       => __( 'Limit result set to items from specific states.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_notifications_collection_params', $params );
	}
}
