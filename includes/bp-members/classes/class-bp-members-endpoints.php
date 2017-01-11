<?php

defined('ABSPATH') || exit;

/**
 * Members endpoints.
 * @todo Group Members Integration ?
 *
 * @since 0.1.0
 */
class BP_REST_Members_Controller extends WP_REST_Controller {

    protected $member_type;

    public function __construct($member_type = false) {
        $this->namespace = 'buddypress/v1';
        $this->rest_base = buddypress()->members->id;

        if ($member_type) {
            // Member type support for member endpoint.
            $this->member_type = $member_type;
            $obj = bp_get_member_type_object($member_type);
            $this->rest_base = (isset($obj->rest_base) && !empty($obj->rest_base)) ? $obj->rest_base : $member_type;
        }

        //@todo member meta field registeration..
    }

    /**
     * Register the plugin routes.
     *
     * @since 0.1.0
     */
    public function register_routes() {

        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => $this->get_collection_params(),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));
    }

    /**
     * Get the plugin schema, conforming to JSON Schema.
     *
     * @since 0.1.0
     *
     * @return array
     */
    public function get_item_schema() {

        // @todo: Make sure to write schema.

        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'activity',
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'context' => array('view', 'edit'),
                    'description' => __('A unique alphanumeric ID for the object.', 'buddypress'),
                    'readonly' => true,
                    'type' => 'integer',
                ),
                'username'    => array(
                    'description' => __( 'Login name for the resource.', 'buddypress' ),
                    'type'        => 'string',
                    'context'     => array( 'edit' ),
                    'required'    => true,
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_user',
                    ),
                ),
                'name'        => array(
                    'description' => __( 'Display name for the resource.' ),
                    'type'        => 'string',
                    'context'     => array( 'embed', 'view', 'edit' ),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
                'fullname'  => array(
                    'description' => __( 'Full First name for the resource.' ),
                    'type'        => 'string',
                    'context'     => array( 'edit' ),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
                'registered_date' => array(
                    'description' => __( 'Registration date for the resource.' ),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => array( 'edit' ),
                    'readonly'    => true,
                ),
                'nickname'    => array(
                    'description' => __( 'The nickname for the resource.' ),
                    'type'        => 'string',
                    'context'     => array( 'edit' ),
                    'arg_options' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
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
        $params = parent::get_collection_params();
        $params['context']['default'] = 'view';

        $params['exclude'] = array(
            'description' => __('Ensure result set excludes specific IDs.', 'buddypress'),
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => 'wp_parse_id_list',
        );

        $params['include'] = array(
            'description' => __('Ensure result set includes specific IDs.', 'buddypress'),
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => 'wp_parse_id_list',
        );

        $params['type'] = array(
            'description' => __('Determines sort order. Select from newest, active, online, random, popular, alphabetical.', 'buddypress'),
            'type' => 'string',
            'default' => 'newest',
            'enum' => array('newest', 'active', 'online', 'random', 'popular', 'alphabetical'),
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['per_page'] = array(
            'description' => __('Maximum number of results returned per result set.', 'buddypress'),
            'default' => 20,
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['page'] = array(
            'description' => __('Offset the result set by a specific number of pages of results.', 'buddypress'),
            'default' => 1,
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'validate_callback' => 'rest_validate_request_arg',
        );

        $params['search'] = array(
            'description' => __('Limit result set to items that match this search query.', 'buddypress'),
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => 'rest_validate_request_arg',
        );

        if (bp_is_active('friends')) { // only availble when friends component is activate
            $params['user_id'] = array(
                'description' => __('Limit result by connections of specific user.', 'buddypress'),
                'type' => 'integer',
                'default' => 0,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
            );
        }

        return $params;
    }

    /**
     * Retrieve members.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Request List of activity object data.
     */
    public function get_items($request) {
        global $bp, $wpdb;

        $args = array(
            'type' => $request['type'],
            'user_id' => false,
            'search_terms' => $request['search'],
            'member_type' => '',
            'per_page' => $request['per_page'],
            'page' => $request['page'],
            'populate_extras' => true,
            'meta_key' => false, // Limit to users who have this piece of usermeta.
            'meta_value' => false,
            'count_total' => 'count_query'
        );

        if(!empty($request['exclude'])) {
            $args['exclude'] = $request['exclude'];
        }

        if(!empty($request['include'])) {
            $args['include'] = $request['include'];
        }

        if ($this->member_type != "") {
            $args['member_type'] = $this->member_type;
        }

        if (!empty($request['user_id'])) {
            $args['user_id'] = $request['user_id'];
        }

        $retval = array();

        $members = new BP_User_Query($args);

        foreach ($members->results as $member) {

            //@todo: Passing the member object into prepare response

            $retval[] = $this->prepare_response_for_collection(
                    $this->prepare_item_for_response($member, $request)
            );
        }

        return rest_ensure_response($retval);
    }

    /**
     * Retrieve member.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
     */
    public function get_item($request) {

        // @todo: Member singlular query logics

        $members = array();

        $retval = array(
            $this->prepare_response_for_collection(
                $this->prepare_item_for_response($members, $request)
            )
        );

        return rest_ensure_response($retval);
    }

    /**
     * Check if a given request has access to get information about a specific member.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return bool
     */
    public function get_item_permissions_check($request) {
        return $this->get_items_permissions_check($request);
    }

    /**
     * Check if a given request has access to member items.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_items_permissions_check($request) {
        // @todo: have to look what permission we can check here.
        return true;
    }

    /**
     * Prepares member data for return as an object.
     *
     * @since 0.1.0
     *
     * @param stdClass $activity Activity data.
     * @param WP_REST_Request $request
     * @param boolean $is_raw Optional, not used. Defaults to false.
     * @return WP_REST_Response
     */
    public function prepare_item_for_response($member, $request, $is_raw = false) {

        //@todo define the data structure of rest single object.

        $data = array(
            //"id" => $member->ID,
        );

        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data = $this->add_additional_fields_to_object($data, $request);
        $data = $this->filter_response_by_context($data, $context);

        $response = rest_ensure_response($data);
        $response->add_links($this->prepare_links($activity));

        /**
         * Filter an member value returned from the API.
         *
         * @param array           $response
         * @param WP_REST_Request $request Request used to generate the response.
         */
        return apply_filters('rest_prepare_buddypress_member_value', $response, $request);
    }

    /**
     * Prepare links for the request.
     *
     * @since 0.1.0
     *
     * @param array $member Member.
     * @return array Links for the given plugin.
     */
    protected function prepare_links($member) {
        $base = sprintf('/%s/%s/', $this->namespace, $this->rest_base);

        // Entity meta.
        $links = array(
            'self' => array(
                'href' => rest_url($base . $member->id), //@todo: Need to test
            ),
            'collection' => array(
                'href' => rest_url($base),
            )
        );

        return $links;
    }

    /**
     * Convert the input date to RFC3339 format.
     *
     * @param string $date_gmt
     * @param string|null $date Optional. Date object.
     * @return string|null ISO8601/RFC3339 formatted datetime.
     */
    protected function prepare_date_response($date_gmt, $date = null) {
        if (isset($date)) {
            return mysql_to_rfc3339($date);
        }

        if ($date_gmt === '0000-00-00 00:00:00') {
            return null;
        }

        return mysql_to_rfc3339($date_gmt);
    }

}
