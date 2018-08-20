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
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}
	/**
	 * Retrieve notifications.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request Notification object data.
	 */
	public function get_items( $request ) {
		$notifications = bp_notifications_get_notifications_for_user( bp_loggedin_user_id(), $format = 'object' );
		$retval = array();
		foreach ( $notifications as $notification ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $notification, $request )
			);
		}
		$retval = rest_ensure_response( $retval );
		/**
		 * Fires after a notification is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $notifications Fetched notification.
		 * @param WP_REST_Response $retval       The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'rest_notifications_get_items', $notifications, $retval, $request );
		return $retval;
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
		// Bail early.
		if ( ! $this->can_see() ) {
			return new WP_Error( 'rest_user_cannot_view_notifications',
				__( 'Sorry, you cannot view the notifications.', 'buddypress' ),
				array(
					'status' => 500,
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
	 * @param stdClass        $notification Notification data.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $notification, $request ) {
		$data = array(
			'id'                    => $notification->id,
			'user_id'     => $notification->user_id,
			'prime_association' => $notification->item_id,
			'secondary_association'               => $notification->secondary_item_id,
			'component'               => $notification->component_name,
			'action'                  => $notification->component_action,
			'date'                  => $notification->date_notified,
			'unread'                => $notification->is_new,
			'content'            => $notification->content,
			'href'              => $notification->href,
		);
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );
		/**
		 * Filter a notification value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_buddypress_notification_value', $response, $request );
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
		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}
		// Check notification access.
		if ( ! empty( $notification_id ) && notifications_check_notification_access( $notification_id, $user_id ) ) {
			$retval = true;
		}
		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool   $retval Return value.
		 * @param int    $user_id User ID.
		 */
		return apply_filters( 'rest_notification_endpoint_can_see', $retval, $user_id );
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
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The notification ID.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'prime_association'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the item associated with the notification.', 'buddypress' ),
					'type'        => 'integer',
				),
				'secondary_association'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the secondary item associated with the notification.', 'buddypress' ),
					'type'        => 'integer',
				),
				'user_id'                    => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the user the notification is associated with.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'component'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the component that the notification is for.', 'buddypress' ),
					'type'        => 'string',
				),
				'action'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The component action which the notification is related to.', 'buddypress' ),
					'type'        => 'string',
				),
				'date'                  => array(
					'description' => __( "The date  the notification was created, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'unread'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'buddypress' ),
					'type'        => 'integer',
				),
				'content'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Content to display.', 'buddypress' ),
					'type'        => 'string',
				),
				'href'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Target href of the notification.', 'buddypress' ),
					'type'        => 'string',
				),

			),
		);
		return $schema;
	}
}
