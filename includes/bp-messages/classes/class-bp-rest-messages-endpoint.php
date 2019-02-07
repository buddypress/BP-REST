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
	 * Retrieve threads.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request Thread object data.
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

		// Actually, query it.
		$messages_box = new BP_Messages_Box_Template( $args );

		$retval = array();
		foreach ( $messages_box->threads as $thread ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $thread, $request )
			);
		}

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after a thread is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $messages_box Fetched thread.
		 * @param WP_REST_Response $retval       The response data.
		 * @param WP_REST_Request  $request      The request sent to the API.
		 */
		do_action( 'bp_rest_messages_get_items', $messages_box, $retval, $request );

		return $retval;
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
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to see the messages.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! $this->can_see() ) {
			return new WP_Error( 'bp_rest_user_cannot_view_messages',
				__( 'Sorry, you cannot view the messages.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Prepares thread data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $thread Thread data.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $thread, $request ) {
		$data = array(
			'id'                => $thread->thread_id,
			'primary_item_id'   => $thread->last_message_id,
			'secondary_item_id' => $thread->last_sender_id,
			'subject'           => $thread->last_message_subject,
			'message'           => $thread->last_message_content,
			'date'              => $thread->last_message_date,
			'unread'            => ! empty( $thread->unread_count ) ? $thread->unread_count : 0,
			'sender_ids'        => $thread->sender_ids,
			'messages'          => $thread->messages,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $activity ) );

		/**
		 * Filter a thread value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_prepare_buddypress_message_value', $response, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array $thread Thread.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $thread ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $thread->thread_id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $thread->last_sender_id ) ),
				'embeddable' => true,
			),
		);

		return $links;
	}

	/**
	 * Can this user see the message?
	 *
	 * @since 0.1.0
	 *
	 * @param int $thread_id Thread ID.
	 * @return boolean
	 */
	protected function can_see( $thread_id = 0 ) {
		$user_id = bp_loggedin_user_id();
		$retval  = false;

		// Check thread access.
		if ( ! empty( $thread_id ) && messages_check_thread_access( $thread_id, $user_id ) ) {
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
		 * @param bool  $retval     Return value.
		 * @param int   $user_id    User ID.
		 * @param int   $thread_id  Thread ID.
		 */
		return (bool) apply_filters( 'bp_rest_messages_can_see', $retval, $user_id, $thread_id );
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
			'title'      => 'thread',
			'type'       => 'object',
			'properties' => array(
				'id'              => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'primary_item_id' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object primarily associated with this one.', 'buddypress' ),
					'type'        => 'integer',
				),
				'secondary_item_id' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'buddypress' ),
					'type'        => 'integer',
				),
				'subject'         => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML title of the object.', 'buddypress' ),
					'type'        => 'string',
				),
				'message'         => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML content of the object.', 'buddypress' ),
					'type'        => 'string',
				),
				'date'            => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the object was published, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
				'unread'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'buddypress' ),
					'type'        => 'integer',
				),
				'sender_ids'      => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'IDs of the senders in the thread', 'buddypress' ),
					'type'        => 'array',
				),
				'messages'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Childrens of the object.', 'buddypress' ),
					'type'        => 'array',
				),
			),
		);

		return $schema;
	}

	/**
	 * Get the query params for collections of plugins.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['box'] = array(
			'description'       => __( 'Filter the result by box.', 'buddypress' ),
			'default'           => 'sentbox',
			'type'              => 'string',
			'enum'              => array( 'notices', 'sentbox', 'inbox' ),
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
