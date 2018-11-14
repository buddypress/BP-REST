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
		$this->namespace      = 'buddypress/v1';
		$this->rest_base      = buddypress()->activity->id;
		$this->user_favorites = array();
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

		$activity_endpoint = '/' . $this->rest_base . '/(?P<id>[\d]+)';

		register_rest_route( $this->namespace, $activity_endpoint, array(
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

		// Register the favorite route.
		register_rest_route( $this->namespace, $activity_endpoint . '/favorite', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_favorite' ),
				'permission_callback' => array( $this, 'update_favorite_permissions_check' ),
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
	 * @return WP_REST_Response List of activities response data.
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

		$item_id = 0;
		if ( isset( $request['primary_id'] ) ) {
			$item_id                      = $request['primary_id'];
			$args['filter']['primary_id'] = $item_id;
		}

		if ( isset( $request['secondary_id'] ) ) {
			$args['filter']['secondary_id'] = $request['secondary_id'];
		}

		if ( $args['in'] ) {
			$args['count_total'] = false;
		}

		if ( $this->show_hidden( $request['component'], $item_id ) ) {
			$args['show_hidden'] = true;
		}

		$activities = bp_activity_get( $args );

		$retval = array();
		foreach ( $activities['activities'] as $activity ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $activities['total'], $args['per_page'] );

		/**
		 * Fires after a list of activities is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $activities Fetched activities.
		 * @param WP_REST_Response $response   The response data.
		 * @param WP_REST_Request  $request    The request sent to the API.
		 */
		do_action( 'rest_activity_get_items', $activities, $response, $request );

		return $response;
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
		if ( ! $this->get_user_permission_check() ) {
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

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Activity_Activity $activity Fetched activity.
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
		 */
		do_action( 'rest_activity_get_item', $activity, $response, $request );

		return $response;
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
		if ( ! $this->get_user_permission_check() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to see the activity.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

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
	 * Create an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function create_item( $request ) {
		$prepared_activity = $this->prepare_item_for_database( $request );
		$type              = $request['type'];
		$prime             = $request['prime_association'];
		$activity_id       = 0;

		// Post a regular activity update.
		if ( 'activity_update' === $type ) {
			if ( bp_is_active( 'groups' ) && ! is_null( $prime ) ) {
				$activity_id = groups_post_update( $prepared_activity );
			} else {
				$activity_id = bp_activity_post_update( $prepared_activity );
			}

			// Post an activity comment.
		} elseif ( ( 'activity_comment' === $type ) && ! is_null( $request['id'] ) && ! is_null( $request['parent'] ) ) {

			// ID of the root activity item.
			if ( ! empty( $schema['properties']['prime_association'] ) && isset( $prime ) ) {
				$prepared_activity->activity_id = (int) $prime;
			}

			// ID of a parent comment.
			if ( ! empty( $schema['properties']['secondary_association'] ) && isset( $request['secondary_association'] ) ) {
				$prepared_activity->parent_id = (int) $request['secondary_association'];
			}

			$activity_id = bp_activity_new_comment( $prepared_activity );

			// Otherwise add an activity.
		} else {
			$activity_id = bp_activity_add( $prepared_activity );
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
			'show_hidden'      => $request['hidden'],
		) );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity['activities'][0], $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is created via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Activity_Activity $activity The created activity.
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
		 */
		do_action( 'rest_activity_create_item', $activity, $response, $request );

		return $response;
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
		if ( ! $this->get_user_permission_check() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you are not allowed to create activities.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		// Bail early, if no content.
		if ( empty( $request['content'] ) ) {
			return new WP_Error( 'rest_create_activity_empty_content',
				__( 'Please, enter some content.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$item_id   = $request['prime_association'];
		$component = $request['component'];

		if ( bp_is_active( 'groups' ) && buddypress()->groups->id === $component && ! is_null( $item_id ) ) {
			if ( ! $this->show_hidden( $component, $item_id ) ) {
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

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after an activity is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Activity_Activity $activity The updated activity.
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
		 */
		do_action( 'rest_activity_update_item', $activity, $response, $request );

		return $response;
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
		if ( ! $this->get_user_permission_check() ) {
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

		if ( ! bp_activity_user_can_delete( $activity ) ) {
			return new WP_Error( 'rest_activity_cannot_update',
				__( 'Sorry, you are not allowed to update this activity.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		if ( empty( $request['content'] ) ) {
			return new WP_Error( 'rest_update_activity_empty_content',
				__( 'Please, enter some content.', 'buddypress' ),
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
		 * @param BP_Activity_Activity $activity The deleted activity.
		 * @param WP_REST_Response     $response The response data.
		 * @param WP_REST_Request      $request  The request sent to the API.
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
		if ( ! $this->get_user_permission_check() ) {
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
	 * Adds or removes the activity from the current user's favorites.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_favorite( $request ) {
		$activity = $this->get_activity_object( $request );
		$user_id  = get_current_user_id();

		$result = false;
		if ( in_array( $activity->id, $this->user_favorites, true ) ) {
			$result  = bp_activity_remove_user_favorite( $activity->id, $user_id );
			$message = __( 'Sorry, you cannot remove the activity from your favorites.', 'buddypress' );

			// Update the user favorites, removing the activity ID.
			$this->user_favorites = array_diff( $this->user_favorites, array( $activity->id ) );
		} else {
			$result  = bp_activity_add_user_favorite( $activity->id, $user_id );
			$message = __( 'Sorry, you cannot add the activity to your favorites.', 'buddypress' );

			// Update the user favorites, adding the activity ID.
			$this->user_favorites[] = (int) $activity->id;
		}

		if ( ! $result ) {
			return new WP_Error( 'rest_user_cannot_update_activity_favorite',
				$message,
				array(
					'status' => 500,
				)
			);
		}

		// Prepare the response now the user favorites has been updated.
		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after user favorited activities has been updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Activity_Activity $activity       The updated activity.
		 * @param array                $user_favorites The updated user favorites.
		 * @param WP_REST_Response     $response       The response data.
		 * @param WP_REST_Request      $request        The request sent to the API.
		 */
		do_action( 'rest_activity_update_favorite', $activity, $this->user_favorites, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update user favorites.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_favorite_permissions_check( $request ) {
		if ( ! $this->get_user_permission_check() ) {
			return new WP_Error( 'rest_authorization_required',
				__( 'Sorry, you need to be logged in to update your favorites.', 'buddypress' ),
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

		if ( ! bp_activity_can_favorite() ) {
			return new WP_Error( 'rest_activity_cannot_favorite',
				__( 'Sorry, Activity cannot be favorited.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		return true;
	}

	/**
	 * Renders the content of an activity.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Activity_Activity $activity Activity data.
	 * @return string The rendered activity content.
	 */
	public function render_item( $activity ) {
		$rendered = '';

		if ( empty( $activity->content ) ) {
			return $rendered;
		}

		// Do not truncate activities.
		add_filter( 'bp_activity_maybe_truncate_entry', '__return_false' );

		if ( 'activity_comment' === $activity->type ) {
			$rendered = apply_filters( 'bp_get_activity_content', $activity->content );
		} else {
			$activities_template = null;

			if ( isset( $GLOBALS['activities_template'] ) ) {
				$activities_template = $GLOBALS['activities_template'];
			}

			// Set the `activities_template` global for the current activity.
			$GLOBALS['activities_template']           = new stdClass();
			$GLOBALS['activities_template']->activity = $activity;

			// Set up activity oEmbed cache.
			bp_activity_embed();

			$rendered = apply_filters( 'bp_get_activity_content_body', $activity->content );

			// Restore the `activities_template` global.
			$GLOBALS['activities_template'] = $activities_template;
		}

		// Restore the filter to truncate activities.
		remove_filter( 'bp_activity_maybe_truncate_entry', '__return_false' );

		return $rendered;
	}

	/**
	 * Prepares activity data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Activity_Activity $activity Activity data.
	 * @param WP_REST_Request      $request  Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $activity, $request ) {
		$data = array(
			'user'                  => $activity->user_id,
			'component'             => $activity->component,
			'content'               => array(
				'raw'      => $activity->content,
				'rendered' => $this->render_item( $activity ),
			),
			'date'                  => bp_rest_prepare_date_response( $activity->date_recorded ),
			'id'                    => $activity->id,
			'link'                  => bp_activity_get_permalink( $activity->id ),
			'parent'                => 'activity_comment' === $activity->type ? $activity->item_id : 0,
			'prime_association'     => $activity->item_id,
			'secondary_association' => $activity->secondary_item_id,
			'status'                => $activity->is_spam ? 'spam' : 'published',
			'title'                 => $activity->action,
			'type'                  => $activity->type,
			'favorited'             => in_array( $activity->id, $this->user_favorites, true ),
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
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $activity ) );

		/**
		 * Filter an activity value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response     $response The Response data.
		 * @param WP_REST_Request      $request  Request used to generate the response.
		 * @param BP_Activity_Activity $activity The activity object.
		 */
		return apply_filters( 'rest_activity_prepare_value', $response, $request, $activity );
	}

	/**
	 * Prepare activity comments.
	 *
	 * @since 0.1.0
	 *
	 * @param  array           $comments Comments.
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

		/**
		 * Filter activity comments returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $data     An array of activity comments.
		 * @param array           $comments Comments.
		 * @param WP_REST_Request $request  Request used to generate the response.
		 */
		return apply_filters( 'rest_activity_prepare_comments', $data, $comments, $request );
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
			$prepared_activity->id = $activity->id;

			if ( 'activity_comment' !== $request['type'] ) {
				$prepared_activity->error_type = 'wp_error';
			}
		}

		// Activity author ID.
		if ( ! empty( $schema['properties']['user'] ) && isset( $request['user'] ) ) {
			$prepared_activity->user_id = (int) $request['user'];
		} else {
			$prepared_activity->user_id = get_current_user_id();
		}

		// Activity component.
		if ( ! empty( $schema['properties']['component'] ) && isset( $request['component'] ) ) {
			$prepared_activity->component = $request['component'];
		} else {
			$prepared_activity->component = buddypress()->activity->id;
		}

		// Activity Item ID.
		if ( ! empty( $schema['properties']['prime_association'] ) && isset( $request['prime_association'] ) ) {
			$item_id = (int) $request['prime_association'];

			// Set the group ID of the activity.
			if ( bp_is_active( 'groups' ) && isset( $prepared_activity->component ) && buddypress()->groups->id === $prepared_activity->component ) {
				$prepared_activity->group_id = $item_id;

				// Use a generic item ID for other components.
			} else {
				$prepared_activity->item_id = $item_id;
			}
		}

		// Secondary Item ID.
		if ( ! empty( $schema['properties']['secondary_association'] ) && isset( $request['secondary_association'] ) ) {
			$prepared_activity->secondary_item_id = (int) $request['secondary_association'];
		}

		// Activity type.
		if ( ! empty( $schema['properties']['type'] ) && isset( $request['type'] ) ) {
			$prepared_activity->type = $request['type'];
		}

		// Activity content.
		if ( ! empty( $schema['properties']['content'] ) && isset( $request['content'] ) ) {
			if ( is_string( $request['content'] ) ) {
				$prepared_activity->content = $request['content'];
			} elseif ( isset( $request['content']['raw'] ) ) {
				$prepared_activity->content = $request['content']['raw'];
			}
		}

		// Activity Sitewide visibility.
		if ( ! empty( $schema['properties']['hidden'] ) && isset( $request['hidden'] ) ) {
			$prepared_activity->hide_sitewide = (bool) $request['hidden'];
		}

		/**
		 * Filters an activity before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_activity An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'rest_activity_pre_insert_value', $prepared_activity, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param object $activity Activity object.
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
				'href'       => rest_url( sprintf( '/wp/v2/users/%d', $activity->user_id ) ),
				'embeddable' => true,
			),
		);

		if ( 'activity_comment' === $activity->type ) {
			$links['up'] = array(
				'href' => rest_url( $url ),
			);
		}

		if ( bp_activity_can_favorite() ) {
			$links['favorite'] = array(
				'href' => rest_url( $url . '/favorite' ),
			);
		}

		return $links;
	}

	/**
	 * Checks if the current user is logged in and set their favorites.
	 *
	 * @since 0.1.0
	 *
	 * @return boolean True if the user is logged in. False otherwise.
	 */
	protected function get_user_permission_check() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Current user favorites.
		$user_favorites       = bp_activity_get_user_favorites( get_current_user_id() );
		$this->user_favorites = array_filter( wp_parse_id_list( $user_favorites ) );

		return true;
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
		$user_id  = get_current_user_id();
		$activity = $this->get_activity_object( $request );
		$retval   = false;

		// Fix for edit context.
		if ( $edit && 'edit' === $request['context'] && bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			if ( bp_activity_user_can_read( $activity, $user_id ) ) {
				$retval = true;
			}
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool                 $retval   Return value.
		 * @param int                  $user_id  User ID.
		 * @param BP_Activity_Activity $activity Activity object.
		 * @param WP_REST_Request      $request  Request.
		 */
		return (bool) apply_filters( 'rest_activity_can_see', $retval, $user_id, $activity, $request );
	}

	/**
	 * Show hidden activity?
	 *
	 * @since 0.1.0
	 *
	 * @param  string $component The activity component.
	 * @param  int    $item_id   The activity item ID.
	 * @return boolean
	 */
	protected function show_hidden( $component, $item_id ) {
		$user_id = get_current_user_id();
		$retval  = false;

		// If activity is from a group, do an extra cap check.
		if ( ! $retval && ! empty( $item_id ) && bp_is_active( $component ) && buddypress()->groups->id === $component ) {
			// Group admins and mods have access as well.
			if ( groups_is_user_admin( $user_id, $item_id ) || groups_is_user_mod( $user_id, $item_id ) ) {
				$retval = true;

				// User is a member of the group.
			} elseif ( (bool) groups_is_user_member( $user_id, $item_id ) ) {
				$retval = true;
			}
		}

		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		/**
		 * Filter here to edit the `show_hidden` activity param for the given component/item_id.
		 *
		 * @since 0.1.0
		 *
		 * @param boolean $retval    True to include hidden activities. False otherwise.
		 * @param integer $user_id   The current user ID.
		 * @param string  $component The activity component.
		 * @param integer $item_id   The activity item ID.
		 */
		return (bool) apply_filters( 'rest_activity_show_hidden', $retval, $user_id, $component, $item_id );
	}

	/**
	 * Get activity object.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return BP_Activity_Activity|string An activity object.
	 */
	public function get_activity_object( $request ) {
		$activity_id = is_numeric( $request ) ? $request : (int) $request['id'];

		$activity = bp_activity_get_specific( array(
			'activity_ids'     => array( $activity_id ),
			'display_comments' => true,
		) );

		if ( is_array( $activity ) && ! empty( $activity['activities'][0] ) ) {
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
					'format'      => 'uri',
					'type'        => 'string',
					'readonly'    => true,
				),

				'component'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The active BuddyPress component the object relates to.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array_keys( buddypress()->active_components ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
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
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),

				'content'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML content of the object.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'       => array(
							'description' => __( 'Content for the object, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered'  => array(
							'description' => __( 'HTML content for the object, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
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
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
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

				'hidden'                => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Whether the activity object should be sitewide hidden or not.', 'buddypress' ),
					'type'        => 'boolean',
				),

				'favorited'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the activity object has been favorited by the current user.', 'buddypress' ),
					'type'        => 'boolean',
					'readonly'    => true,
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
		$params                       = parent::get_collection_params();
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
			'sanitize_callback' => 'sanitize_key',
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
			'description'       => __( 'Limit result set to items created by a specific user.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['status'] = array(
			'description'       => __( 'Limit result set to items with a specific status.', 'buddypress' ),
			'default'           => 'published',
			'type'              => 'string',
			'enum'              => array( 'published', 'spam' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['primary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific prime association.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => '',
			'sanitize_callback' => 'absint',
		);

		$params['secondary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific secondary association.', 'buddypress' ),
			'type'              => 'integer',
			'default'           => '',
			'sanitize_callback' => 'absint',
		);

		$params['component'] = array(
			'description'       => __( 'Limit result set to items with a specific active BuddyPress component.', 'buddypress' ),
			'type'              => 'string',
			'enum'              => array_keys( buddypress()->active_components ),
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
			'description'       => __( 'False for no comments. stream for within stream display, threaded for below each activity item.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
