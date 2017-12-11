<?php
/**
 * BP REST: BP_REST_Activity_Controller class
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
class BP_REST_Activity_Controller extends WP_REST_Controller {

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
	 * Register the plugin routes.
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
			'schema' => array( $this, 'get_item_schema' ),
		) );
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
			),
		);

		if ( get_option( 'show_avatars' ) ) {
			$avatar_properties = array();

			$avatar_sizes = rest_get_avatar_sizes();
			foreach ( $avatar_sizes as $size ) {
				$avatar_properties[ $size ] = array(
					/* translators: %d: avatar image size in pixels */
					'description' => sprintf( __( 'Avatar URL with image size of %d pixels.' ), $size ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
				);
			}

			$schema['properties']['user_avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the object user.' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit', 'embed' ),
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
			'default'           => false,
			'type'              => 'boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}

	/**
	 * Retrieve activities.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of activity object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'exclude'           => $request['exclude'],
			'in'                => $request['include'],
			'page'              => $request['page'],
			'per_page'          => $request['per_page'],
			'primary_id'        => $request['primary_id'],
			'search_terms'      => $request['search'],
			'secondary_id'      => $request['secondary_id'],
			'sort'              => $request['order'],
			'spam'              => 'spam' === $request['status'] ? 'spam_only' : 'ham_only',
			'user_id'           => $request['user'],
			'display_comments'  => $request['display_comments'],

			// Set optimised defaults.
			'count_total'       => true,
			'fields'            => 'all',
			'show_hidden'       => false,
			'update_meta_cache' => true,
		);

		if ( isset( $request['after'] ) ) {
			$args['since'] = $request['after'];
		}

		if ( isset( $request['component'] ) ) {
			if ( ! isset( $args['filter'] ) ) {
				$args['filter'] = array(
					'object' => $request['component'],
				);
			} else {
				$args['filter']['object'] = $request['component'];
			}
		}

		if ( isset( $request['type'] ) ) {
			if ( ! isset( $args['filter'] ) ) {
				$args['filter'] = array(
					'action' => $request['type'],
				);
			} else {
				$args['filter']['action'] = $request['type'];
			}
		}

		if ( $args['in'] ) {
			$args['count_total'] = false;
		}

		if ( $this->show_hidden( $request['component'], $request['primary_id'] ) ) {
			$args['show_hidden'] = true;
		}

		$retval     = array();
		$activities = bp_activity_get( $args );

		foreach ( $activities['activities'] as $activity ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			);
		}

		return rest_ensure_response( $retval );
	}

	/**
	 * Retrieve activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {
		$activity = bp_activity_get( array(
			'in' => (int) $request['id'],
		) );

		$activity = $activity['activities'][0];

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

		return rest_ensure_response( $retval );
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
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to activity items.
	 *
	 * @todo Handle private activities etc.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		return $this->can_see( $request );
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

		if ( ! empty( $schema['properties']['user_avatar_urls'] ) ) {
			$data['user_avatar_urls'] = rest_get_avatar_urls( $activity->user_email );
		}

		$context = ! empty( $request['context'] )
			? $request['context']
			: 'view';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $activity ) );

		/**
		 * Filter an activity value returned from the API.
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_buddypress_activity_value', $response, $request );
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
				'href' => rest_url( '/wp/v2/users/' . $activity->user_id ),
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
	 * @return boolean
	 */
	protected function can_see( $request ) {
		$user_id = bp_loggedin_user_id();

		// Admins can see it all.
		if ( is_super_admin( $user_id ) ) {
			return true;
		}

		$retval = true;

		$activity = bp_activity_get( array(
			'in' => (int) $request['id'],
		) );

		$activity = $activity['activities'][0];

		$bp = buddypress();

		// If activity is from a group, do an extra cap check.
		if ( isset( $bp->groups->id ) && $activity->component === $bp->groups->id ) {

			// Activity is from a group, but groups is currently disabled.
			if ( ! bp_is_active( 'groups' ) ) {
				return false;
			}

			// Check to see if the user has access to the activity's parent group.
			$group = groups_get_group( $activity->item_id );
			if ( $group ) {
				$retval = $group->user_has_access;
			}
		}

		// If activity author does not match logged_in user, block access.
		if ( true === $retval && $user_id !== $activity->user_id ) {
			$retval = false;
		}

		// Community moderators can see it.
		if ( ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = false;
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $retval
		 */
		return apply_filters( 'rest_activity_endpoint_can_see', $retval );
	}

	/**
	 * Show hidden activity?
	 *
	 * @since 0.1.0
	 *
	 * @param  string $component  Group component.
	 * @param  int    $id Primary ID.
	 * @return boolean
	 */
	protected function show_hidden( $component, $id ) {
		// Bail early.
		if ( 'groups' !== $component ) {
			return false;
		}

		$retval = false;

		// Moderators as well.
		if ( bp_current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		if ( (bool) groups_is_user_member( get_current_user_id(), $id ) ) {
			$retval = true;
		}

		return $retval;
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
	protected function prepare_date_response( $date_gmt, $date = null ) {
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		if ( '0000-00-00 00:00:00' === $date_gmt ) {
			return null;
		}

		return mysql_to_rfc3339( $date_gmt );
	}
}
