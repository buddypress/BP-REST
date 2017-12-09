<?php
defined('ABSPATH') || exit;

/**
 * Activity endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Activity_Controller extends WP_REST_Controller
{

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct()
    {
        $this->namespace = 'buddypress/v1';
        $this->rest_base = buddypress()->activity->id;
    }

    /**
     * Register the plugin routes.
     *
     * @since 0.1.0
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
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
                'args'                => $this->get_endpoint_args_for_item_schema(true),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ));

        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'                => array(
                    'context' => $this->get_context_param(array(
                        'default' => 'view',
                    )),
                ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ));
    }

    /**
     * Get the plugin schema, conforming to JSON Schema.
     *
     * @since 0.1.0
     *
     * @return array
     */
    public function get_item_schema()
    {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'activity',
            'type'       => 'object',

            'properties' => array(
                'id' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('A unique alphanumeric ID for the object.', 'buddypress'),
                    'readonly'    => true,
                    'type'        => 'integer',
                ),

                'prime_association' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('The ID of some other object primarily associated with this one.', 'buddypress'),
                    'type'        => 'integer',
                ),

                'secondary_association' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('The ID of some other object also associated with this one.', 'buddypress'),
                    'type'        => 'integer',
                ),

                'author' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('The ID for the creator of the object.', 'buddypress'),
                    'type'        => 'integer',
                ),

                'link' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('The permalink to this object on the site.', 'buddypress'),
                    'format'      => 'url',
                    'type'        => 'string',
                ),

                'component' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('The BuddyPress component the object relates to.', 'buddypress'),
                    'type'        => 'string',
                    'enum'        => array_keys(bp_core_get_components()),
                ),

                'type' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('The activity type of the object.', 'buddypress'),
                    'type'        => 'string',
                    'enum'        => array_keys(bp_activity_get_types()),
                ),

                'title' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('HTML title of the object.', 'buddypress'),
                    'type'        => 'string',
                ),

                'content' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('HTML content of the object.', 'buddypress'),
                    'type'        => 'string',
                ),

                'date' => array(
                    'description' => __("The date the object was published, in the site's timezone.", 'buddypress'),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => array( 'view', 'edit' ),
                ),

                'status' => array(
                    'context'     => array( 'view', 'edit' ),
                    'description' => __('Whether the object has been marked as spam or not.', 'buddypress'),
                    'type'        => 'string',
                    'enum'        => array( 'published', 'spam' ),
                ),

                'parent' => array(
                    'description'  => __('The ID of the parent of the object.', 'buddypress'),
                    'type'         => 'integer',
                    'context'      => array( 'view', 'edit' ),
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
    public function get_collection_params()
    {
        $params = parent::get_collection_params();
        $params['context']['default'] = 'view';

        $params['exclude'] = array(
            'description'       => __('Ensure result set excludes specific IDs.', 'buddypress'),
            'type'              => 'array',
            'default'           => array(),
            'sanitize_callback' => 'wp_parse_id_list',
        );

        $params['include'] = array(
            'description'       => __('Ensure result set includes specific IDs.', 'buddypress'),
            'type'              => 'array',
            'default'           => array(),
            'sanitize_callback' => 'wp_parse_id_list',
        );

        $params['order'] = array(
            'description'       => __('Order sort attribute ascending or descending.', 'buddypress'),
            'type'              => 'string',
            'default'           => 'desc',
            'enum'              => array( 'asc', 'desc' ),
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['after'] = array(
            'description'       => __('Limit result set to items published after a given ISO8601 compliant date.', 'buddypress'),
            'type'              => 'string',
            'format'            => 'date-time',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['per_page'] = array(
            'description'       => __('Maximum number of results returned per result set.', 'buddypress'),
            'default'           => 20,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['page'] = array(
            'description'       => __('Offset the result set by a specific number of pages of results.', 'buddypress'),
            'default'           => 1,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['author'] = array(
            'description'       => __('Limit result set to items created by specific authors.', 'buddypress'),
            'type'              => 'array',
            'default'           => array(),
            'sanitize_callback' => 'wp_parse_id_list',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['status'] = array(
            'default'           => 'published',
            'description'       => __('Limit result set to items with a specific status.', 'buddypress'),
            'type'              => 'string',
            'enum'              => array( 'published', 'spam' ),
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['primary_id'] = array(
            'description'       => __('Limit result set to items with a specific prime assocation.', 'buddypress'),
            'type'              => 'array',
            'default'           => array(),
            'sanitize_callback' => 'wp_parse_id_list',
        );

        $params['secondary_id'] = array(
            'description'       => __('Limit result set to items with a specific secondary assocation.', 'buddypress'),
            'type'              => 'array',
            'default'           => array(),
            'sanitize_callback' => 'wp_parse_id_list',
        );

        $params['component'] = array(
            'description'       => __('Limit result set to items with a specific BuddyPress component.', 'buddypress'),
            'type'              => 'string',
            'enum'              => array_keys(bp_core_get_components()),
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['type'] = array(
            'description'       => __('Limit result set to items with a specific activity type.', 'buddypress'),
            'type'              => 'string',
            'enum'              => array_keys(bp_activity_get_types()),
            'sanitize_callback' => 'sanitize_key',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['search'] = array(
            'description'       => __('Limit result set to items that match this search query.', 'buddypress'),
            'default'           => '',
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
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
    public function get_items($request)
    {
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
            'user_id'           => $request['author'],

            // Set optimised defaults.
            'count_total'       => true,
            'fields'            => 'all',
            'show_hidden'       => false,
            'update_meta_cache' => true,
        );

        if (isset($request['after'])) {
            $args['since'] = $request['after'];
        }

        if (isset($request['component'])) {
            if (! isset($args['filter'])) {
                $args['filter'] = array(
                    'object' => $request['component'],
                );
            } else {
                $args['filter']['object'] = $request['component'];
            }
        }

        if (isset($request['type'])) {
            if (! isset($args['filter'])) {
                $args['filter'] = array(
                    'action' => $request['type'],
                );
            } else {
                $args['filter']['action'] = $request['type'];
            }
        }

        if ($args['in']) {
            $args['count_total'] = false;
        }

        // Override certain options for security.
        // @TODO: Verify and confirm this show_hidden logic, and check core for other edge cases.
        if ('groups' === $request['component'] &&
            (
                groups_is_user_member(get_current_user_id(), $request['primary_id']) ||
                bp_current_user_can('bp_moderate')
            )
        ) {
            $args['show_hidden'] = true;
        }

        $retval     = array();
        $activities = bp_activity_get($args);

        foreach ($activities['activities'] as $activity) {
            $retval[] = $this->prepare_response_for_collection(
                $this->prepare_item_for_response($activity, $request)
            );
        }

        return rest_ensure_response($retval);
    }

    /**
     * Retrieve activity.
     *
     * @todo Query logic, permissions, other parameters that might need to be set. etc.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
     */
    public function get_item($request)
    {
        $activity = bp_activity_get(array(
            'in' => (int) $request['id'],
        ));

        $retval = array(
            $this->prepare_response_for_collection(
                $this->prepare_item_for_response($activity['activities'][0], $request)
            ),
        );

        return rest_ensure_response($retval);
    }

    /**
     * Create activity.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
     */
    public function create_item($request)
    {
        if (empty($request['content'])) {
            return new WP_Error('rest_empty_content', __('Please enter some content to post.', 'buddypress'), array( 'status' => 500 ));
        }

        $prepared_activity = $this->prepare_item_for_database($request);

        $activity_id = 0;
        if (
            ($request['type'] == 'activity_update') &&
            ! empty($request['prime_association']) &&
            bp_is_active('groups')
        ) {
            $activity_id = groups_post_update($prepared_activity);
        } elseif (
            ($request['type'] == 'activity_comment') &&
            bp_is_active('activity') &&
            ! empty($request['secondary_association']) &&
            ! empty($request['prime_association']) &&
            is_numeric($request['secondary_association']) &&
            is_numeric($request['prime_association'])
        ) {
            $activity_id = bp_activity_new_comment($prepared_activity);
        } else {
            $activity_id = bp_activity_post_update($prepared_activity);
        }

        if (! is_numeric($activity_id)) {
            return new WP_Error('rest_cant_create', __('Cannot create new activity.', 'buddypress'), array( 'status' => 500 ));
        } elseif (is_wp_error($activity_id)) {
            return $activity_id;
        }

        $activity = bp_activity_get(array(
            'in' => $activity_id,
            'display_comments' => 'stream',
        ));

        $retval = array( $this->prepare_response_for_collection(
            $this->prepare_item_for_response($activity['activities'][0], $request)
        ) );

        return rest_ensure_response($retval);
    }

    /**
     * Check if a given request has access to get information about a specific activity.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_item_permissions_check($request)
    {
        return $this->get_items_permissions_check($request);
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
    public function get_items_permissions_check($request)
    {
        return $this->can_see($request);
    }

    /**
     * Checks if a given request has access to create an activity.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
     */
    public function create_item_permissions_check($request)
    {
        if (! is_user_logged_in()) {
            return new WP_Error('rest_authorization_required', __('Sorry, you are not allowed to create activities as this user.', 'buddypress'), array( 'status' => rest_authorization_required_code() ));
        }

        if (
            ($request['type'] == 'activity_update') &&
            ! empty($request['prime_association']) &&
            bp_is_active('groups')
        ) {
            if (!bp_current_user_can('bp_moderate') && !groups_is_user_member(bp_loggedin_user_id(), $request['prime_association'])) {
                return new WP_Error('rest_cannot_create', __('Sorry, you are not allowed to create activity to this group as this user.', 'buddypress'), array( 'status' => 500 ));
            }
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
    public function prepare_item_for_response($activity, $request, $is_raw = false)
    {
        $data = array(
            'author'                => $activity->user_id,
            'component'             => $activity->component,
            'content'               => $activity->content,
            'date'                  => $this->prepare_date_response($activity->date_recorded),
            'id'                    => $activity->id,
            'link'                  => bp_activity_get_permalink($activity->id),
            'parent'                => 'activity_comment' === $activity->type ? $activity->item_id : 0,
            'prime_association'     => $activity->item_id,
            'secondary_association' => $activity->secondary_item_id,
            'status'                => $activity->is_spam ? 'spam' : 'published',
            'title'                 => $activity->action,
            'type'                  => $activity->type,
        );

        $context = ! empty($request['context'])
            ? $request['context']
            : 'view';

        $data = $this->add_additional_fields_to_object($data, $request);
        $data = $this->filter_response_by_context($data, $context);

        $response = rest_ensure_response($data);
        $response->add_links($this->prepare_links($activity));

        /**
         * Filter an activity value returned from the API.
         *
         * @param array           $response
         * @param WP_REST_Request $request Request used to generate the response.
         */
        return apply_filters('rest_prepare_buddypress_activity_value', $response, $request);
    }

    /**
     * Prepares a single activity for create or update.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request Request object.
     * @return stdClass|WP_Error Post object or WP_Error.
     */
    protected function prepare_item_for_database($request)
    {
        $prepared_activity = new stdClass();

        $schema = $this->get_item_schema();

        // Prime association.
        if (! empty($schema['properties']['prime_association']) && isset($request['prime_association'])) {
            if ($request['type'] == 'activity_update') {
                $prepared_activity->group_id = $request['prime_association'];
            } else {
                $prepared_activity->activity_id = $request['prime_association'];
            }
        }

        // Secondary association.
        if (! empty($schema['properties']['secondary_association']) && isset($request['secondary_association'])) {
            $prepared_activity->parent_id = $request['secondary_association'];
        }

        // Activity type.
        if (! empty($schema['properties']['type']) && isset($request['type'])) {
            $prepared_activity->type = $request['type'];
        }

        // Activity content.
        if (! empty($schema['properties']['content']) && isset($request['content'])) {
            $prepared_activity->content = $request['content'];
        }

        /**
         * Filters an activity before it is inserted via the REST API.
         *
         *
         * @since 0.1.0
         *
         * @param stdClass        $prepared_activity An object representing a single post prepared for inserting or updating the database.
         * @param WP_REST_Request $request Request object.
         */
        return apply_filters("rest_pre_insert_buddypress_activity_value", $prepared_activity, $request);
    }

    /**
     * Prepare links for the request.
     *
     * @since 0.1.0
     *
     * @param array $activity Activity.
     * @return array Links for the given plugin.
     */
    protected function prepare_links($activity)
    {
        $base = sprintf('/%s/%s/', $this->namespace, $this->rest_base);

        // Entity meta.
        $links = array(
            'self' => array(
                'href' => rest_url($base . $activity->id),
            ),
            'collection' => array(
                'href' => rest_url($base),
            ),
            'author' => array(
                'href' => rest_url('/wp/v2/users/' . $activity->user_id),
            ),
        );

        if ('activity_comment' === $activity->type) {
            $links['up'] = array(
                'href' => rest_url($base . $activity->item_id),
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
    protected function can_see($request)
    {
        $retval = true;

        $activity = bp_activity_get(array(
            'in' => (int) $request['id'],
        ));

        $activity = $activity['activities'][0];

        $bp = buddypress();

        // If activity is from a group, do an extra cap check.
        if (isset($bp->groups->id) && $activity->component === $bp->groups->id) {

            // Activity is from a group, but groups is currently disabled.
            if (! bp_is_active('groups')) {
                return false;
            }

            // Check to see if the user has access to to the activity's parent group.
            $group = groups_get_group($activity->item_id);
            if ($group) {
                $retval = $group->user_has_access;
            }
        }

        // If activity author does not match logged_in user, block access.
        if (true === $retval && bp_loggedin_user_id() !== $activity->user_id) {
            $retval = false;
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
    protected function prepare_date_response($date_gmt, $date = null)
    {
        if (isset($date)) {
            return mysql_to_rfc3339($date);
        }

        if ('0000-00-00 00:00:00' === $date_gmt) {
            return null;
        }

        return mysql_to_rfc3339($date_gmt);
    }
}
