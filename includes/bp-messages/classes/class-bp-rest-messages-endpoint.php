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
					'args'                => array(
						'thread_id'  => array(
							'description'       => __( 'Leave empty to init a new thread. Specify the thread ID to reply to an existing one.', 'buddypress' ),
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'sender_id'  => array(
							'description'       => __( 'The user ID of the sender. Defaults to the logged in user.', 'buddypress' ),
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'content'    => array(
							'description'       => __( 'Content of the message/reply.', 'buddypress' ),
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'recipients' => array(
							'description'       => __( 'Recipients of the message/reply.', 'buddypress' ),
							'required'          => false,
							'type'              => 'array',
							'items'             => array( 'type' => 'integer' ),
							'sanitize_callback' => 'wp_parse_id_list',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
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

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_messages_get_items_query_args', $args, $request );

		// Include the meta_query for starred messages.
		if ( 'starred' === $args['box'] ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'starred_by_user',
					'value' => $args['user_id'],
				),
			);
		}

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
				'bp_rest_member_invalid_id',
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
					'status' => 500,
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
				'bp_rest_message_invalid_id',
				__( 'Invalid message id.', 'buddypress' ),
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

		$result = bp_messages_star_set_action( array(
			'user_id'    => $user_id,
			'message_id' => $message->id,
			'action'     => $action,
		) );

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
		$data = $this->prepare_message_for_response( $message, $request );

		return rest_ensure_response( $data );
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
		$retval     = true;
		$thread_id  = messages_get_message_thread_id( $request['id'] );

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
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		$thread = $this->get_thread_object( $request['id'] );

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

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $thread, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a thread is deleted via the REST API.
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
	 * @param object The Message object.
	 * @param WP_REST_Request $request Full details about the request.
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
			'message' => array(
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
		 * @param array           $data    The message value for the REST response.
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_messages_prepare_message_value', $data, $request );
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
			'recipients'     => $thread->recipients,
			'messages'       => array(),
		);

		// Loop through messages to prepare them for the response.
		foreach ( $thread->messages as $message ) {
			$data['messages'][] = $this->prepare_message_for_response( $message, $request );
		}

		// Pluck starred message ids.
		$data['starred_message_ids'] = array_keys( array_filter( wp_list_pluck( $data['messages'], 'is_starred', 'id' ) ) );

		// @todo Set user avatar, user name, and user links for recipients.
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		/**
		 * Filter a thread value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response   $response Response generated by the request.
		 * @param WP_REST_Request    $request  Request used to generate the response.
		 * @param BP_Messages_Thread $thread The thread object.
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

		if ( ! empty( $schema['properties']['content'] ) && ! empty( $thread->last_message_content ) ) {
			$prepared_thread->last_message_content = $thread->last_message_content;
		} else {
			$prepared_thread->content = $request['content'];
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
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'bp_rest_messages_pre_insert_value', $prepared_thread, $request );
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
	 * Get the BP Messages schema, conforming to JSON Schema.
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
				'id'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'message_id'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the message.', 'buddypress' ),
					'type'        => 'integer',
				),
				'last_sender_id' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of last sender.', 'buddypress' ),
					'type'        => 'integer',
				),
				'subject'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Title of the object.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Title of the object, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'Title of the object, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'excerpt'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Summary of the object.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Summary for the object, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'HTML summary for the object, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'message'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Content of the object.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'      => array(
							'description' => __( 'Content for the object, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered' => array(
							'description' => __( 'HTML content for the object, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
				'date'           => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the object was published, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'unread_count'   => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Total count of unread messages.', 'buddypress' ),
					'type'        => 'integer',
				),
				'sender_ids'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The user IDs of all messages in the message thread.', 'buddypress' ),
					'type'        => 'array',
				),
				'recipients'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Recipient objects in the thread', 'buddypress' ),
					'type'        => 'array',
				),
				'messages'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'List of messages.', 'buddypress' ),
					'type'        => 'array',
				),
				'starred_message_ids' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'List of starred message ids.', 'buddypress' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'integer',
					),
					'default'     => array(),
				),
			),
		);

		/**
		 * Filters the messages schema.
		 *
		 * @since 0.1.0
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_messages_schema', $this->add_additional_fields_schema( $schema ) );
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
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
