<?php
/**
 * BP REST: BP_REST_Messages_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Messages endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Messages_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->messages->id;
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

		// Attention: (?P<id>[\d]+) is the placeholder for **Thread** ID, not the Message ID one.
		$thread_endpoint = '/' . $this->rest_base . '/(?P<id>[\d]+)';

		register_rest_route(
			$this->namespace,
			$thread_endpoint,
			array(
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
						'message_id' => array(
							'description'       => __( 'By default the latest message of the thread will be updated. Specify this message ID to edit another message of the thread.', 'buddypress' ),
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
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

		// Register the starred route.
		if ( bp_is_active( 'messages', 'star' ) ) {
			// Attention: (?P<id>[\d]+) is the placeholder for **Message** ID, not the Thread ID one.
			$starred_endpoint = '/' . $this->rest_base . '/' . bp_get_messages_starred_slug() . '/(?P<id>[\d]+)';

			register_rest_route(
				$this->namespace,
				$starred_endpoint,
				array(
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $this, 'update_starred' ),
						'permission_callback' => array( $this, 'update_starred_permissions_check' ),
					),
					'schema' => array( $this, 'get_item_schema' ),
				)
			);
		}
	}

	/**
	 * Select the item schema arguments needed for the CREATABLE, EDITABLE and DELETABLE methods.
	 *
	 * @since 0.1.0
	 *
	 * @param string $method Optional. HTTP method of the request.
	 * @return array Endpoint arguments.
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
		$args = WP_REST_Controller::get_endpoint_args_for_item_schema( $method );

		if ( WP_REST_Server::CREATABLE === $method ) {
			// Add the sender_id argument.
			$args['sender_id'] = array(
				'description'       => __( 'The user ID of the Message sender.', 'buddypress' ),
				'required'          => false,
				'default'           => bp_loggedin_user_id(),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			);

			// Edit subject's properties.
			$args['subject']['type']        = 'string';
			$args['subject']['default']     = $args['subject']['properties']['rendered']['default'];
			$args['subject']['description'] = __( 'Subject of the Message initializing the Thread.', 'buddypress' );

			// Edit message's properties.
			$args['message']['type']        = 'string';
			$args['message']['description'] = __( 'Content of the Message initializing the Thread.', 'buddypress' );

			// Edit recipients properties
			$args['recipients']['items']             = array( 'type' => 'integer' );
			$args['recipients']['sanitize_callback'] = 'wp_parse_id_list';
			$args['recipients']['validate_callback'] = 'rest_validate_request_arg';
			$args['recipients']['description']       =  __( 'The list of the recipients user IDs of the Message.', 'buddypress' );

			// Remove unused properties for this transport method.
			unset( $args['subject']['properties'], $args['message']['properties'] );
		}

		return $args;
	}

	/**
	 * Retrieve threads.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'user_id'      => $request['user_id'],
			'box'          => $request['box'],
			'type'         => $request['type'],
			'page'         => $request['page'],
			'per_page'     => $request['per_page'],
			'search_terms' => $request['search'],
		);

		// Include the meta_query for starred messages.
		if ( 'starred' === $args['box'] ) {
			$args['meta_query'] = array( // phpcs:ignore
				array(
					'key'   => 'starred_by_user',
					'value' => $args['user_id'],
				),
			);
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_messages_get_items_query_args', $args, $request );

		// Actually, query it.
		$messages_box = new BP_Messages_Box_Template( $args );

		$retval = array();
		foreach ( (array) $messages_box->threads as $thread ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $thread, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $messages_box->total_thread_count, $args['per_page'] );

		/**
		 * Fires after a thread is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Messages_Box_Template  $messages_box Fetched thread.
		 * @param WP_REST_Response          $response     The response data.
		 * @param WP_REST_Request           $request      The request sent to the API.
		 */
		do_action( 'bp_rest_messages_get_items', $messages_box, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to thread items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to see the messages.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );

		if ( true === $retval && ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && (int) bp_loggedin_user_id() !== $user->ID && ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you cannot view the messages.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the messages `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_messages_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Get a single thread.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$thread = $this->get_thread_object( $request['id'] );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $thread, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a thread is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Messages_Thread $thread  Thread object.
		 * @param WP_REST_Response   $retval  The response data.
		 * @param WP_REST_Request    $request The request sent to the API.
		 */
		do_action( 'bp_rest_messages_get_item', $thread, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to a thread item.
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
				__( 'Sorry, you are not allowed to see this thread.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$thread = $this->get_thread_object( $request['id'] );

		if ( true === $retval && empty( $thread->thread_id ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Sorry, this thread does not exist.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			$id = messages_check_thread_access( $thread->thread_id );
			if ( true === $retval && is_null( $id ) ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to see this thread.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}

			if ( true === $retval ) {
				$retval = true;
			}
		}

		/**
		 * Filter the messages `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_messages_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Create message.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		$prepared_thread = $this->prepare_item_for_database( $request );

		if ( ! isset( $prepared_thread->recipients ) || ! $prepared_thread->recipients ) {
			return new WP_Error(
				'bp_rest_messages_missing_recipients',
				__( 'Please provide some recipients for your message or reply.', 'buddypress' ),
				array(
					'status' => 400,
				)
			);
		}

		// Create message.
		$thread_id = messages_new_message( $prepared_thread );

		if ( ! $thread_id ) {
			return new WP_Error(
				'bp_rest_messages_create_failed',
				__( 'There was an error trying to create the message.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Make sure to get the newest message to update REST Additional fields.
		$thread        = $this->get_thread_object( $thread_id );
		$last_message  = wp_list_filter( $thread->messages, array( 'id' => $thread->last_message_id ) );
		$last_message  = reset( $last_message );
		$fields_update = $this->update_additional_fields_for_object( $last_message, $request );

		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $thread, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a message is created via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Messages_Thread $thread  Thread object.
		 * @param WP_REST_Response   $retval  The response data.
		 * @param WP_REST_Request    $request The request sent to the API.
		 */
		do_action( 'bp_rest_messages_create_item', $thread, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to create a message.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to create a message.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the messages `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_messages_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Update one of the messages of the thread.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		// Get the thread.
		$thread = $this->get_thread_object( $request['id'] );
		$error  = new WP_Error(
			'bp_rest_messages_update_failed',
			__( 'There was an error trying to update the message.', 'buddypress' ),
			array(
				'status' => 500,
			)
		);

		if ( ! $thread->thread_id ) {
			return $error;
		}

		// By default use the last message.
		$message_id = $thread->last_message_id;
		if ( $request['message_id'] ) {
			$message_id = $request['message_id'];
		}

		$updated_message = wp_list_filter( $thread->messages, array( 'id' => $message_id ) );
		$updated_message = reset( $updated_message );

		/**
		 * Filter here to allow more users to edit the message meta (eg: the recipients).
		 *
		 * @since 0.1.0
		 *
		 * @param boolean             $value           Whether the user can edit the message meta.
		 *                                             By default: only the sender and a community moderator can.
		 * @param BP_Messages_Message $updated_message The updated message object.
		 * @param WP_REST_Request     $request         The request sent to the API.
		 */
		$can_edit_item_meta = apply_filters(
			'bp_rest_messages_can_edit_item_meta',
			bp_loggedin_user_id() === $updated_message->sender_id || bp_current_user_can( 'bp_moderate' ),
			$updated_message,
			$request
		);

		// The message must exist in the thread, and the logged in user must be the sender.
		if ( ! isset( $updated_message->id ) || ! $updated_message->id || ! $can_edit_item_meta ) {
			return $error;
		}

		$fields_update = $this->update_additional_fields_for_object( $updated_message, $request );
		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $thread, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a message is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Messages_Message $updated_message The updated message.
		 * @param WP_REST_Response    $response        The response data.
		 * @param WP_REST_Request     $request         The request sent to the API.
		 */
		do_action( 'bp_rest_messages_update_item', $updated_message, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a message.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the message `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_messages_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Adds or removes the message from the current user's starred box.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_starred( $request ) {
		$message = $this->get_message_object( $request['id'] );

		if ( empty( $message->id ) ) {
			return new WP_Error(
				'bp_rest_invalid_id',
				__( 'Sorry, this message does not exist.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$user_id = bp_loggedin_user_id();
		$result  = false;
		$action  = 'star';
		$info    = __( 'Sorry, you cannot add the message to your starred box.', 'buddypress' );

		if ( bp_messages_is_message_starred( $message->id, $user_id ) ) {
			$action = 'unstar';
			$info   = __( 'Sorry, you cannot remove the message from your starred box.', 'buddypress' );
		}

		$result = bp_messages_star_set_action(
			array(
				'user_id'    => $user_id,
				'message_id' => $message->id,
				'action'     => $action,
			)
		);

		if ( ! $result ) {
			return new WP_Error(
				'bp_rest_user_cannot_update_starred_message',
				$info,
				array(
					'status' => 500,
				)
			);
		}

		// Prepare the message for the REST response.
		$data = array(
			$this->prepare_response_for_collection(
				$this->prepare_message_for_response( $message, $request )
			),
		);

		$response = rest_ensure_response( $data );

		/**
		 * Fires after a message is starred/unstarred via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Messages_Message $message  Message object.
		 * @param string              $action   Informs about the update performed.
		 *                                      Possible values are `star` or `unstar`.
		 * @param WP_REST_Response    $response The response data.
		 * @param WP_REST_Request     $request  The request sent to the API.
		 */
		do_action( 'bp_rest_message_update_starred_item', $message, $action, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update user starred messages.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_starred_permissions_check( $request ) {
		$retval    = true;
		$thread_id = messages_get_message_thread_id( $request['id'] );

		if ( ! is_user_logged_in() || ! messages_check_thread_access( $thread_id ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to star/unstar messages.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the message `update_starred` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_messages_update_starred_permissions_check', $retval, $request );
	}

	/**
	 * Delete a thread.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		// Get the thread before it's deleted.
		$thread   = $this->get_thread_object( $request['id'] );
		$previous = $this->prepare_item_for_response( $thread, $request );

		$user_id = bp_loggedin_user_id();
		if ( ! empty( $request['user_id'] ) ) {
			$user_id = $request['user_id'];
		}

		// Delete a thread.
		if ( ! messages_delete_thread( $thread->thread_id, $user_id ) ) {
			return new WP_Error(
				'bp_rest_messages_delete_thread_failed',
				__( 'There was an error trying to delete a thread.', 'buddypress' ),
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
		 * Fires after a thread is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Messages_Thread $thread   Thread object.
		 * @param WP_REST_Response   $response The response data.
		 * @param WP_REST_Request    $request  The request sent to the API.
		 */
		do_action( 'bp_rest_messages_delete_item', $thread, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a thread.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the thread `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_messages_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares message data for the REST response.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Messages_Message $message The Message object.
	 * @param WP_REST_Request     $request Full details about the request.
	 * @return array The Message data for the REST response.
	 */
	public function prepare_message_for_response( $message, $request ) {
		$data = array(
			'id'        => (int) $message->id,
			'thread_id' => (int) $message->thread_id,
			'sender_id' => (int) $message->sender_id,
			'subject'   => array(
				'raw'      => $message->subject,
				'rendered' => apply_filters( 'bp_get_message_thread_subject', wp_staticize_emoji( $message->subject ) ),
			),
			'message'   => array(
				'raw'      => $message->message,
				'rendered' => apply_filters( 'bp_get_the_thread_message_content', wp_staticize_emoji( $message->message ) ),
			),
			'date_sent' => bp_rest_prepare_date_response( $message->date_sent ),
		);

		if ( bp_is_active( 'messages', 'star' ) ) {
			$user_id = bp_loggedin_user_id();

			if ( isset( $request['user_id'] ) && $request['user_id'] ) {
				$user_id = (int) $request['user_id'];
			}

			$data['is_starred'] = bp_messages_is_message_starred( $data['id'], $user_id );
		}

		// Add REST Fields (BP Messages meta) data.
		$data = $this->add_additional_fields_to_object( $data, $request );

		/**
		 * Filter a message value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array               $data    The message value for the REST response.
		 * @param BP_Messages_Message $message The Message object.
		 * @param WP_REST_Request     $request Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_message_prepare_value', $data, $message, $request );
	}

	/**
	 * Prepares recipient data for the REST response.
	 *
	 * @since 0.1.0
	 *
	 * @param object          $recipient The recipient object.
	 * @param WP_REST_Request $request   Full details about the request.
	 * @return array                     The recipient data for the REST response.
	 */
	public function prepare_recipient_for_response( $recipient, $request ) {
		$data = array(
			'id'        => (int) $recipient->id,
			'user_id'   => (int) $recipient->user_id,
			'user_link' => esc_url( bp_core_get_user_domain( $recipient->user_id ) ),
		);

		// Fetch the user avatar urls (Full & thumb).
		if ( true === buddypress()->avatar->show_avatars ) {
			foreach ( array( 'full', 'thumb' ) as $type ) {
				$data['user_avatars'][ $type ] = bp_core_fetch_avatar(
					array(
						'item_id' => $recipient->user_id,
						'html'    => false,
						'type'    => $type,
					)
				);
			}
		}

		$data = array_merge(
			$data,
			array(
				'thread_id'    => (int) $recipient->thread_id,
				'unread_count' => (int) $recipient->unread_count,
				'sender_only'  => (int) $recipient->sender_only,
				'is_deleted'   => (int) $recipient->is_deleted,
			)
		);

		/**
		 * Filter a recipient value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $data      The recipient value for the REST response.
		 * @param object          $recipient The recipient object.
		 * @param WP_REST_Request $request   Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_messages_prepare_recipient_value', $data, $recipient, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Messages_Thread $thread  Thread object.
	 * @return array Links for the given thread.
	 */
	protected function prepare_links( $thread ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $base . $thread->thread_id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
		);

		// Add star links for each message of the thread.
		if ( bp_is_active( 'messages', 'star' ) ) {
			$starred_base = $base . bp_get_messages_starred_slug() . '/';

			foreach ( $thread->messages as $message ) {
				$links[ $message->id ] = array(
					'href' => rest_url( $starred_base . $message->id ),
				);
			}
		}

		/**
		 * Filter links prepared for the REST response.
		 *
		 * @since 0.1.0
		 *
		 * @param array              $links   The prepared links of the REST response.
		 * @param BP_Messages_Thread $thread  Thread object.
		 */
		return apply_filters( 'bp_rest_messages_prepare_links', $links, $thread );
	}

	/**
	 * Prepares thread data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Messages_Thread $thread  Thread object.
	 * @param WP_REST_Request    $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $thread, $request ) {
		$excerpt = '';
		if ( isset( $thread->last_message_content ) ) {
			$excerpt = wp_strip_all_tags( bp_create_excerpt( $thread->last_message_content, 75 ) );
		}

		$data = array(
			'id'             => $thread->thread_id,
			'message_id'     => $thread->last_message_id,
			'last_sender_id' => $thread->last_sender_id,
			'subject'        => array(
				'raw'      => $thread->last_message_subject,
				'rendered' => apply_filters( 'bp_get_message_thread_subject', wp_staticize_emoji( $thread->last_message_subject ) ),
			),
			'excerpt'        => array(
				'raw'      => $excerpt,
				'rendered' => apply_filters( 'bp_get_message_thread_excerpt', $excerpt ),
			),
			'message'        => array(
				'raw'      => $thread->last_message_content,
				'rendered' => apply_filters( 'bp_get_message_thread_content', wp_staticize_emoji( $thread->last_message_content ) ),
			),
			'date'           => bp_rest_prepare_date_response( $thread->last_message_date ),
			'unread_count'   => ! empty( $thread->unread_count ) ? $thread->unread_count : 0,
			'sender_ids'     => $thread->sender_ids,
			'recipients'     => array(),
			'messages'       => array(),
		);

		// Loop through messages to prepare them for the response.
		foreach ( $thread->messages as $message ) {
			$data['messages'][] = $this->prepare_message_for_response( $message, $request );
		}

		// Loop through recipients to prepare them for the response.
		foreach ( $thread->recipients as $recipient ) {
			$data['recipients'][ $recipient->user_id ] = $this->prepare_recipient_for_response( $recipient, $request );
		}

		// Pluck starred message ids.
		$data['starred_message_ids'] = array_keys( array_filter( wp_list_pluck( $data['messages'], 'is_starred', 'id' ) ) );

		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $thread ) );

		/**
		 * Filter a thread value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response   $response Response generated by the request.
		 * @param WP_REST_Request    $request  Request used to generate the response.
		 * @param BP_Messages_Thread $thread   The thread object.
		 */
		return apply_filters( 'bp_rest_messages_prepare_value', $response, $request, $thread );
	}

	/**
	 * Prepare a message/thread for create or update.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_thread = new stdClass();
		$schema          = $this->get_item_schema();

		// By default, let's init a new Thread.
		$prepared_thread->thread_id = false;

		// If it's a reply, get the parent thread.
		if ( $request['thread_id'] ) {
			$thread = $this->get_thread_object( $request['thread_id'] );

			if ( ! empty( $schema['properties']['id'] ) && ! empty( $thread->thread_id ) ) {
				$prepared_thread->thread_id = $thread->thread_id;
			}
		}

		// Defaults to current user.
		$prepared_thread->sender_id = bp_loggedin_user_id();
		if ( $request['sender_id'] ) {
			$prepared_thread->sender_id = $request['sender_id'];
		} elseif ( ! empty( $schema['properties']['last_sender_id'] ) && ! empty( $thread->sender_id ) ) {
			$prepared_thread->sender_id = $thread->sender_id;
		}

		// Note to self: the content was not one of the schema properties
		if ( ! empty( $schema['properties']['message'] ) && ! empty( $thread->last_message_content ) ) {
			$prepared_thread->last_message_content = $thread->last_message_content;
		} else {
			$prepared_thread->content = $request['message'];
		}

		if ( ! empty( $schema['properties']['subject'] ) && ! empty( $thread->last_message_subject ) ) {
			$prepared_thread->last_message_subject = $thread->last_message_subject;
		} elseif ( ! empty( $request['subject'] ) ) {
			$prepared_thread->subject = $request['subject'];
		} else {
			$prepared_thread->subject = false;
		}

		if ( ! empty( $request['recipients'] ) ) {
			$prepared_thread->recipients = $request['recipients'];
		} elseif ( isset( $thread->recipients ) && $thread->recipients ) {
			$prepared_thread->recipients = wp_parse_id_list( wp_list_pluck( $thread->recipients, 'user_id' ) );
		}

		/**
		 * Filters a thread before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_thread An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request         Request object.
		 */
		return apply_filters( 'bp_rest_messages_pre_save_value', $prepared_thread, $request );
	}

	/**
	 * Get thread object.
	 *
	 * @since 0.1.0
	 *
	 * @param int $thread_id Thread ID.
	 * @return BP_Messages_Thread
	 */
	public function get_thread_object( $thread_id ) {
		return new BP_Messages_Thread( $thread_id );
	}

	/**
	 * Get the message object thanks to its ID.
	 *
	 * @since 0.1.0
	 *
	 * @param int $message_id Message ID.
	 * @return BP_Messages_Message
	 */
	public function get_message_object( $message_id ) {
		return new BP_Messages_Message( $message_id );
	}

	/**
	 * Get the message schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_messages',
			'type'       => 'object',
			'properties' => array(
				'id'                  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the Thread.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'message_id'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the latest message of the Thread.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'last_sender_id'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of latest sender of the Thread.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'subject'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Title of the latest message of the Thread.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Title of the latest message of the Thread, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
							'default'     => __( 'No Subject', 'buddypress' ),
						),
						'rendered' => array(
							'description' => __( 'Title of the latest message of the Thread, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
							'default'     => __( 'No Subject', 'buddypress' ),
						),
					),
				),
				'excerpt'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Summary of the latest message of the Thread.', 'buddypress' ),
					'type'        => 'object',
					'readonly'    => true,
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Summary for the latest message of the Thread, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'HTML summary for the latest message of the Thread, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
						),
					),
				),
				'message'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Content of the latest message of the Thread.', 'buddypress' ),
					'type'        => 'object',
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Content for the latest message of the Thread, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'HTML content for the latest message of the Thread, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'date'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the latest message of the Thread, in the site's timezone.", 'buddypress' ),
					'readonly'    => true,
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'unread_count'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Total count of unread messages into the Thread for the requested user.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'sender_ids'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The list of user IDs for all messages in the Thread.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
				),
				'recipients'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The list of recipient User Objects involved into the Thread.', 'buddypress' ),
					'type'        => 'array',
					'required'    => true,
					'items'       => array(
						'type' => 'object',
					),
				),
				'messages'            => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'List of message objects for the thread.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'array',
					'items'       => array(
						'type' => 'object',
					),
				),
				'starred_message_ids' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'List of starred message ids.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
					'default'     => array(),
				),
			),
		);

		/**
		 * Filters the message schema.
		 *
		 * @since 0.1.0
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_message_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for Messages collections.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';
		$boxes                        = array( 'sentbox', 'inbox' );

		if ( bp_is_active( 'messages', 'star' ) ) {
			$boxes[] = 'starred';
		}

		$params['box'] = array(
			'description'       => __( 'Filter the result by box.', 'buddypress' ),
			'default'           => 'inbox',
			'type'              => 'string',
			'enum'              => $boxes,
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Filter the result by thread status.', 'buddypress' ),
			'default'           => 'all',
			'type'              => 'string',
			'enum'              => array( 'all', 'read', 'unread' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Limit result to messages created by a specific user.', 'buddypress' ),
			'default'           => bp_loggedin_user_id(),
			'type'              => 'integer',
			'required'          => true,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_messages_collection_params', $params );
	}
}
