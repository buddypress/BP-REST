<?php
/**
 * BP REST: BP_REST_Friends_Endpoint class
 *
 * @package BuddyPress
 * @since 6.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Friendship endpoints.
 *
 * /friends/
 * /friends/{id}
 *
 * @since 6.0.0
 */
class BP_REST_Friends_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->friends->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 6.0.0
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
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Identifier for the friendship.', 'buddypress' ),
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
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'edit',
							)
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'edit',
							)
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieve friendships.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'user_id'           => $request['user_id'],
			'id'                => $request['id'],
			'initiator_user_id' => $request['initiator_id'],
			'friend_user_id'    => $request['friend_id'],
			'is_confirmed'      => $request['is_confirmed'],
			'order_by'          => $request['order_by'],
			'sort_order'        => $request['order'],
			'page'              => $request['page'],
			'per_page'          => $request['per_page'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 6.0.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_friends_get_items_query_args', $args, $request );

		// Check if user is valid.
		$user = get_user_by( 'id', $args['user_id'] );
		if ( ! $user ) {
			return new WP_Error(
				'bp_rest_friends_get_items_user_failed',
				__( 'There was a problem confirming if user is a valid one.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Actually, query it.
		$friendships = BP_Friends_Friendship::get_friendships( $user->ID, $args );

		$retval = array();
		foreach ( (array) $friendships as $friend ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $friend, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, count( $friendships ), $args['per_page'] );

		/**
		 * Fires after friendships are fetched via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param array            $friendships Fetched friendships.
		 * @param WP_REST_Response $response    The response data.
		 * @param WP_REST_Request  $request     The request sent to the API.
		 */
		do_action( 'bp_rest_friends_get_items', $friendships, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to friendship items.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the friends `get_items` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_friends_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Retrieve single friendship.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {

		// Get friendship object.
		$friendship = $this->get_friendship_object( $request['id'] );

		if ( ! $friendship || empty( $friendship->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid friendship ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $friendship, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires before a friendship is retrieved via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Friends_Friendship $friendship  The friendship object.
		 * @param WP_REST_Response      $response    The response data.
		 * @param WP_REST_Request       $request     The request sent to the API.
		 */
		do_action( 'bp_rest_friends_get_item', $friendship, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get a friendship.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the friendship `get_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_friends_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Create a new friendship.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$request->set_param( 'context', 'edit' );

		$initiator_id = get_user_by( 'id', $request['initiator_id'] );
		$friend_id    = get_user_by( 'id', $request['friend_id'] );

		// Check if users are valid.
		if ( ! $initiator_id || ! $friend_id ) {
			return new WP_Error(
				'bp_rest_friends_create_item_failed',
				__( 'There was a problem confirming if user is a valid one.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Adding friendship.
		if ( ! friends_add_friend( $initiator_id->ID, $friend_id->ID, $request['force'] ) ) {
			return new WP_Error(
				'bp_rest_friends_create_item_failed',
				__( 'There was an error trying to create the friendship.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Get friendship.
		$friendship = $this->get_friendship_object(
			BP_Friends_Friendship::get_friendship_id( $initiator_id->ID, $friend_id->ID )
		);

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $friendship, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a friendship is created via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Friends_Friendship $friendship The friendship object.
		 * @param WP_REST_Response      $retval     The response data.
		 * @param WP_REST_Request       $request    The request sent to the API.
		 */
		do_action( 'bp_rest_friends_create_item', $friendship, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to create a friendship.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the friends `create_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_friends_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Update friendship.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$request->set_param( 'context', 'edit' );

		// Get friendship object.
		$friendship = $this->get_friendship_object( $request['id'] );

		if ( ! $friendship || empty( $friendship->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid friendship ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Accept friendship.
		if ( ! friends_accept_friendship( $friendship->id ) ) {
			return new WP_Error(
				'bp_rest_friends_cannot_update_item',
				__( 'Could not update friendship.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $friendship, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a friendship is updated via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Friends_Friendship $friendship Friendship object.
		 * @param WP_REST_Response      $response   The response data.
		 * @param WP_REST_Request       $request    The request sent to the API.
		 */
		do_action( 'bp_rest_friends_update_item', $friendship, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a friendship.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function update_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the friendship `update_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_friends_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Reject/withdraw friendship.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$request->set_param( 'context', 'edit' );

		// Get friendship object.
		$friendship = $this->get_friendship_object( $request['id'] );

		if ( ! $friendship || empty( $friendship->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid friendship ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$user_id  = bp_loggedin_user_id();
		$previous = $this->prepare_item_for_response( $friendship, $request );

		/**
		 * If this change is being initiated by the initiator,
		 * use the `reject` function.
		 *
		 * This is the user who requested the friendship, and is doing the withdrawing.
		 */
		if ( $user_id === $friendship->initiator_user_id ) {
			$deleted = friends_withdraw_friendship( $friendship->initiator_user_id, $friendship->friend_user_id );
		} else {
			/**
			 * Otherwise, this change is being initiated by the user, friend,
			 * who received the friendship reject.
			 */
			$deleted = friends_reject_friendship( $friendship->id );
		}

		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_friends_cannot_delete_item',
				__( 'Could not delete friendship.', 'buddypress' ),
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
		 * Fires after a friendship is deleted via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Friends_Friendship $friendship Friendship object.
		 * @param WP_REST_Response      $response   The response data.
		 * @param WP_REST_Request       $request    The request sent to the API.
		 */
		do_action( 'bp_rest_friends_delete_item', $friendship, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a friendship.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the friendship `delete_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_friends_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares friendship data for return as an object.
	 *
	 * @since 6.0.0
	 *
	 * @param BP_Friends_Friendship $friendship Friendship object.
	 * @param WP_REST_Request       $request    Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $friendship, $request ) {
		$data = array(
			'id'           => $friendship->id,
			'initiator_id' => $friendship->initiator_user_id,
			'friend_id'    => $friendship->friend_user_id,
			'is_confirmed' => $friendship->is_confirmed,
			'date_created' => bp_rest_prepare_date_response( $friendship->date_created ),
		);

		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		/**
		 * Filter a friendship value returned from the API.
		 *
		 * @since 6.0.0
		 *
		 * @param WP_REST_Response      $response   Response generated by the request.
		 * @param WP_REST_Request       $request    Request used to generate the response.
		 * @param BP_Friends_Friendship $friendship The friendship object.
		 */
		return apply_filters( 'bp_rest_friends_prepare_value', $response, $request, $friendship );
	}

	/**
	 * Get friendship object.
	 *
	 * @since 6.0.0
	 *
	 * @param int $friendship_id Friendship ID.
	 *
	 * @return BP_Friends_Friendship
	 */
	public function get_friendship_object( $friendship_id ) {
		return new BP_Friends_Friendship( $friendship_id );
	}

	/**
	 * Get the friends schema, conforming to JSON Schema.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_friends',
			'type'       => 'object',
			'properties' => array(
				'id'           => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the friendship.', 'buddypress' ),
					'type'        => 'integer',
				),
				'initiator_id' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'User ID of the friendship initiator.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'friend_id'    => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'User ID of the `friend` - the one invited to the friendship', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'is_confirmed' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Has the friendship been confirmed/accepted', 'buddypress' ),
					'type'        => 'integer',
					'readonly'    => true,
				),
				'date_created' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the friendship was created, in the site's timezone.", 'buddypress' ),
					'readonly'    => true,
					'type'        => 'string',
					'format'      => 'date-time',
				),
			),
		);

		/**
		 * Filters the friends schema.
		 *
		 * @since 6.0.0
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_friends_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for friends collections.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		unset( $params['search'] );

		$params['user_id'] = array(
			'description'       => __( 'ID of the user whose friends are being retrieved.', 'buddypress' ),
			'default'           => bp_loggedin_user_id(),
			'type'              => 'integer',
			'required'          => true,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['is_confirmed'] = array(
			'description'       => __( 'Wether the friendship has been accepted.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['id'] = array(
			'description'       => __( 'ID of specific friendship to retrieve.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['initiator_id'] = array(
			'description'       => __( 'ID of friendship initiator.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['friend_id'] = array(
			'description'       => __( 'ID of specific friendship to retrieve.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['order_by'] = array(
			'description'       => __( 'Column name to order the results by.', 'buddypress' ),
			'default'           => 'date_created',
			'type'              => 'string',
			'enum'              => array( 'date_created', 'initiator_user_id', 'friend_user_id', 'id' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddypress' ),
			'default'           => 'desc',
			'type'              => 'string',
			'enum'              => array( 'asc', 'desc' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_friends_collection_params', $params );
	}
}
