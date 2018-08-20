<?php
/**
 * BP REST: BP_REST_Activity_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activity endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Activity_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'buddypress/v1';
		$this->rest_base = buddypress()->activity->id;
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
	 * Retrieve activities.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of activities object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'exclude'           => $request['exclude'],
			'in'                => $request['include'],
			'page'              => $request['page'],
			'per_page'          => $request['per_page'],
			'search_terms'      => $request['search'],
			'sort'              => $request['order'],
			'spam'              => 'spam' === $request['status'] ? 'spam_only' : 'ham_only',
			'display_comments'  => $request['display_comments'],
			'count_total'       => true,
			'fields'            => 'all',
			'show_hidden'       => false,
			'update_meta_cache' => true,
			'filter'            => false,
		);

		if ( isset( $request['after'] ) ) {
			$args['since'] = $request['after'];
		}

		if ( isset( $request['user'] ) ) {
			$args['filter']['user_id'] = $request['user'];
		}

		if ( isset( $request['component'] ) ) {
			$args['filter']['object'] = $request['component'];
		}

		if ( isset( $request['type'] ) ) {
			$args['filter']['action'] = $request['type'];
		}

		if ( isset( $request['primary_id'] ) ) {
			$args['filter']['primary_id'] = $request['primary_id'];
		}

		if ( isset( $request['secondary_id'] ) ) {
			$args['filter']['secondary_id'] = $request['secondary_id'];
		}

		if ( $args['in'] ) {
			$args['count_total'] = false;
		}

		if ( $this->show_hidden( $request['component'], $request['primary_id'] ) ) {
			$args['show_hidden'] = true;
		}

		$activities = bp_activity_get( $args );

		$retval = array();
		foreach ( $activities['activities'] as $activity ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			);
		}

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after a list of activities is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activities Fetched activities.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_activity_get_items', $activities, $retval, $request );

		return $retval;
	}

	/**
	 * Retrieve an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {
		$activity = $this->get_activity_object( $request );

		// Prevent non-members from seeing hidden activity.
		if ( ! $this->show_hidden( $activity->component, $activity->item_id ) ) {
			return new WP_Error( 'bp_rest_invalid_activity',
				__( 'Invalid activity id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			),
		);

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activity Fetched activity.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_activity_get_item', $activity, $retval, $request );

		return $retval;
	}

	/**
	 * Check if a given request has access to get information about a specific activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! $this->can_see( $request ) ) {
			return new WP_Error( 'rest_user_cannot_view_activity',
				__( 'Sorry, you cannot view the activities.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! $this->can_see( $request, true ) ) {
			return new WP_Error( 'rest_forbidden_context',
				__( 'Sorry, you cannot view this resource with edit context.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Check if a given request has access to activity items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {

		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to see the activities.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Create an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function create_item( $request ) {
		$prepared_activity = $this->prepare_item_for_database( $request );
		$prime             = $request['prime_association'];
		$id                = $request['id'];
		$parent            = $request['parent'];
		$type              = $request['type'];
		$activity_id       = 0;

		if ( ( 'activity_update' === $type ) && bp_is_active( 'groups' ) && ! is_null( $prime ) ) {
			$activity_id = groups_post_update( $prepared_activity );
		} elseif ( ( 'activity_comment' === $type ) && ! is_null( $id ) && ! is_null( $parent ) ) {
			$activity_id = bp_activity_new_comment( $prepared_activity );
		} else {
			$activity_id = bp_activity_post_update( $prepared_activity );
		}

		if ( ! is_numeric( $activity_id ) ) {
			return new WP_Error( 'rest_user_cannot_create_activity',
				__( 'Cannot create new activity.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$activity = bp_activity_get( array(
			'in'               => $activity_id,
			'display_comments' => 'stream',
		) );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity['activities'][0], $request )
			),
		);

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is created via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activity The created activity.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_activity_create_item', $activity, $retval, $request );

		return $retval;
	}

	/**
	 * Checks if a given request has access to create an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to create activities.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		// Bail early.
		if ( empty( $request['content'] ) ) {
			return new WP_Error( 'rest_create_activity_empty_content',
				__( 'Please, enter some content.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$acc = $request['prime_association'];

		if ( ( 'activity_update' === $request['type'] ) && bp_is_active( 'groups' ) && ! is_null( $acc ) ) {
			if ( ! $this->show_hidden( 'groups', $acc ) ) {
				return new WP_Error( 'rest_user_cannot_create_activity',
					__( 'Sorry, you are not allowed to create activity to this group.', 'buddypress' ),
					array(
						'status' => 500,
					)
				);
			}
		}

		return true;
	}

	/**
	 * Update an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {

		// Bail early.
		if ( empty( $request['content'] ) ) {
			return new WP_Error( 'rest_update_activity_empty_content',
				__( 'Please, enter some content.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$activity_id = bp_activity_add( $this->prepare_item_for_database( $request ) );

		if ( ! is_numeric( $activity_id ) ) {
			return new WP_Error( 'rest_user_cannot_update_activity',
				__( 'Cannot update existing activity.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$activity = $this->get_activity_object( $activity_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			),
		);

		$retval = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activity The updated activity.
		 * @param WP_REST_Response $retval   The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_activity_update_item', $activity, $retval, $request );

		return $retval;
	}

	/**
	 * Check if a given request has access to update an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {

		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to update this activity.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$activity = $this->get_activity_object( $request );

		if ( empty( $activity->id ) ) {
			return new WP_Error( 'rest_activity_invalid_id',
				__( 'Invalid activity id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// If activity author does not match logged_in user, block access.
		if ( bp_loggedin_user_id() !== $activity->user_id ) {
			return new WP_Error( 'rest_activity_cannot_update',
				__( 'You are not allowed to update this activity.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Delete an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$activity = $this->get_activity_object( $request );

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $activity, $request );

		if ( 'activity_comment' === $activity->type ) {
			$retval = bp_activity_delete_comment( $activity->item_id, $activity->id );
		} else {
			$retval = bp_activity_delete( array(
				'id' => $activity->id,
			) );
		}

		if ( ! $retval ) {
			return new WP_Error( 'rest_activity_cannot_delete',
				__( 'Could not delete the activity.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Fires after an activity is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $activity The deleted activity.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'rest_activity_delete_item', $activity, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {

		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to delete this activity.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$activity = $this->get_activity_object( $request );

		if ( empty( $activity->id ) ) {
			return new WP_Error( 'rest_activity_invalid_id',
				__( 'Invalid activity id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! bp_activity_user_can_delete( $activity ) ) {
			return new WP_Error( 'rest_user_cannot_delete_activity',
				__( 'Sorry, you cannot delete the activity.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Prepares activity data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $activity Activity data.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param boolean         $is_raw Optional, not used. Defaults to false.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $activity, $request, $is_raw = false ) {
		$data = array(
			'user'                  => $activity->user_id,
			'component'             => $activity->component,
			'content'               => $activity->content,
			'date'                  => $this->prepare_date_response( $activity->date_recorded ),
			'id'                    => $activity->id,
			'link'                  => bp_activity_get_permalink( $activity->id ),
			'parent'                => 'activity_comment' === $activity->type ? $activity->item_id : 0,
			'prime_association'     => $activity->item_id,
			'secondary_association' => $activity->secondary_item_id,
			'status'                => $activity->is_spam ? 'spam' : 'published',
			'title'                 => $activity->action,
			'type'                  => $activity->type,
		);

		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['comments'] ) && 'threaded' === $request['display_comments'] ) {
			$data['comments'] = $this->prepare_activity_comments( $activity->children, $request );
		}

		if ( ! empty( $schema['properties']['user_avatar'] ) ) {
			$data['user_avatar'] = array(
				'full'  => bp_core_fetch_avatar( array(
					'item_id' => $activity->user_id,
					'html'    => false,
					'type'    => 'full',
				) ),

				'thumb' => bp_core_fetch_avatar( array(
					'item_id' => $activity->user_id,
					'html'    => false,
				) ),
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $activity ) );

		/**
		 * Filter an activity value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_buddypress_activity_value', $response, $request );
	}

	/**
	 * Prepare activity comments.
	 *
	 * @since 0.1.0
	 *
	 * @param  object          $comments Comments.
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return array           An array of activity comments.
	 */
	protected function prepare_activity_comments( $comments, $request ) {
		$data = array();

		if ( empty( $comments ) ) {
			return $data;
		}

		foreach ( $comments as $comment ) {
			$data[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $comment, $request )
			);
		}

		return $data;
	}

	/**
	 * Prepare an activity for create or update.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_activity = new stdClass();
		$schema            = $this->get_item_schema();
		$activity          = $this->get_activity_object( $request );

		if ( ! empty( $schema['properties']['id'] ) && ! empty( $activity->id ) ) {
			if ( 'activity_comment' === $request['type'] ) {
				$prepared_activity->activity_id = $activity->id;
			} else {
				$prepared_activity->id         = $activity->id;
				$prepared_activity->component  = buddypress()->activity->id;
				$prepared_activity->error_type = 'wp_error';
			}
		}

		// Comment parent.
		if ( ! empty( $schema['properties']['parent'] ) && ( 'activity_comment' === $request['type'] ) && isset( $request['parent'] ) ) {
			$prepared_activity->parent_id = $request['parent'];
		}

		// Group ID.
		if ( ! empty( $schema['properties']['prime_association'] ) && ( 'activity_update' === $request['type'] ) && isset( $request['prime_association'] ) ) {
			$prepared_activity->group_id = (int) $request['prime_association'];
		}

		// Activity type.
		if ( ! empty( $schema['properties']['type'] ) && isset( $request['type'] ) ) {
			$prepared_activity->type = $request['type'];
		}

		// Activity content.
		if ( ! empty( $schema['properties']['content'] ) && isset( $request['content'] ) ) {
			$prepared_activity->content = $request['content'];
		}

		/**
		 * Filters an activity before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_activity An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'rest_pre_insert_buddypress_activity_value', $prepared_activity, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array $activity Activity.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $activity ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $activity->id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href' => rest_url( sprintf( '/wp/v2/users/%d', $activity->user_id ) ),
			),
		);

		if ( 'activity_comment' === $activity->type ) {
			$links['up'] = array(
				'href' => rest_url( $url ),
			);
		}

		return $links;
	}

	/**
	 * Can this user see the activity?
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @param  boolean         $edit Edit fallback.
	 * @return boolean
	 */
	protected function can_see( $request, $edit = false ) {
		$user_id  = bp_loggedin_user_id();
		$activity = $this->get_activity_object( $request );
		$retval   = false;

		// Fix for edit content.
		if ( $edit && 'edit' === $request['context'] && ! bp_current_user_can( 'bp_moderate' ) ) {
			return false;
		}

		if ( bp_activity_user_can_read( $activity, $user_id ) ) {
			$retval = true;
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool                 $retval   Return value.
		 * @param int                  $user_id  User ID.
		 * @param BP_Activity_Activity $activity Activity object.
		 */
		return apply_filters( 'rest_activity_endpoint_can_see', $retval, $user_id, $activity );
	}

	/**
	 * Show hidden activity?
	 *
	 * @since 0.1.0
	 *
	 * @param  string $component Group component.
	 * @param  int    $group_id  Group ID.
	 * @return boolean
	 */
	protected function show_hidden( $component, $group_id ) {
		$user_id = bp_loggedin_user_id();
		$retval  = false;

		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		// If activity is from a group, do an extra cap check.
		if ( bp_is_active( 'groups' ) && 'groups' === $component ) {
			$retval = true;

			// User is a member of the group.
			if ( (bool) groups_is_user_member( $user_id, $group_id ) ) {
				$retval = true;
			}

			// Group admins and mods have access as well.
			if ( groups_is_user_admin( $user_id, $group_id ) || groups_is_user_mod( $user_id, $group_id ) ) {
				$retval = true;
			}
		}

		return (bool) $retval;
	}

	/**
	 * Convert the input date to RFC3339 format.
	 *
	 * @since 0.1.0
	 *
	 * @param string      $date_gmt Date GMT format.
	 * @param string|null $date Optional. Date object.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	public function prepare_date_response( $date_gmt, $date = null ) {
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		return mysql_to_rfc3339( $date_gmt );
	}

	/**
	 * Get activity object.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return object An activity object.
	 */
	public function get_activity_object( $request ) {
		$activity_id = is_numeric( $request ) ? $request : (int) $request['id'];

		$activity = bp_activity_get_specific( array(
			'activity_ids'     => array( $activity_id ),
			'display_comments' => true,
		) );

		if ( is_array( $activity ) && isset( $activity['activities'][0] ) ) {
			return $activity['activities'][0];
		}

		return '';
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
			'title'      => 'activity',
			'type'       => 'object',

			'properties' => array(
				'id'                    => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'prime_association'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object primarily associated with this one.', 'buddypress' ),
					'type'        => 'integer',
				),

				'secondary_association' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'buddypress' ),
					'type'        => 'integer',
				),

				'user'                  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID for the creator of the object.', 'buddypress' ),
					'type'        => 'integer',
				),

				'link'                  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The permalink to this object on the site.', 'buddypress' ),
					'format'      => 'url',
					'type'        => 'string',
				),

				'component'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The BuddyPress component the object relates to.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array_keys( bp_core_get_components() ),
				),

				'type'                  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The activity type of the object.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array_keys( bp_activity_get_types() ),
				),

				'title'                 => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML title of the object.', 'buddypress' ),
					'type'        => 'string',
				),

				'content'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML content of the object.', 'buddypress' ),
					'type'        => 'string',
				),

				'date'                  => array(
					'description' => __( "The date the object was published, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),

				'status'                => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the object has been marked as spam or not.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array( 'published', 'spam' ),
				),

				'parent'                => array(
					'description' => __( 'The ID of the parent of the object.', 'buddypress' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),

				'comments'              => array(
					'description' => __( 'The ID for the parent of the object.', 'buddypress' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		// Avatars.
		if ( true === buddypress()->avatar->show_avatars ) {
			$avatar_properties = array();

			$avatar_properties['full'] = array(
				/* translators: Full image size for the member Avatar */
				'description' => sprintf( __( 'Avatar URL with full image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_full_width() ), number_format_i18n( bp_core_avatar_full_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$avatar_properties['thumb'] = array(
				/* translators: Thumb imaze size for the member Avatar */
				'description' => sprintf( __( 'Avatar URL with thumb image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_thumb_width() ), number_format_i18n( bp_core_avatar_thumb_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$schema['properties']['user_avatar'] = array(
				'description' => __( 'Avatar URLs for the member.', 'buddypress' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

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
		$params = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes specific IDs.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddypress' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => array( 'asc', 'desc' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['after'] = array(
			'description'       => __( 'Limit result set to items published after a given ISO8601 compliant date.', 'buddypress' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['per_page'] = array(
			'description'       => __( 'Maximum number of results returned per result set.', 'buddypress' ),
			'default'           => 20,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['page'] = array(
			'description'       => __( 'Offset the result set by a specific number of pages of results.', 'buddypress' ),
			'default'           => 1,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user'] = array(
			'description'       => __( 'Limit result set to items created by specific users.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['status'] = array(
			'default'           => 'published',
			'description'       => __( 'Limit result set to items with a specific status.', 'buddypress' ),
			'type'              => 'string',
			'enum'              => array( 'published', 'spam' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['primary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific prime assocation.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['secondary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific secondary assocation.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['component'] = array(
			'description'       => __( 'Limit result set to items with a specific BuddyPress component.', 'buddypress' ),
			'type'              => 'string',
			'enum'              => array_keys( bp_core_get_components() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Limit result set to items with a specific activity type.', 'buddypress' ),
			'type'              => 'string',
			'enum'              => array_keys( bp_activity_get_types() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['search'] = array(
			'description'       => __( 'Limit result set to items that match this search query.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['display_comments'] = array(
			'description'       => __( 'False for no comments. stream for within stream display, threaded for below each activity item..', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
