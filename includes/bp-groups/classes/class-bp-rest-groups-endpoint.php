<?php
/**
 * BP REST: BP_REST_Groups_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Groups endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Groups_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->groups->id;
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
	 * Retrieve groups.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request List of groups object data.
	 */
	public function get_items( $request ) {
		$args = array(
			'type'               => $request['type'],
			'order'              => $request['order'],
			'fields'             => $request['fields'],
			'orderby'            => $request['orderby'],
			'user_id'            => $request['user_id'],
			'include'            => $request['include'],
			'parent_id'          => $request['parent_id'],
			'exclude'            => $request['exclude'],
			'search_terms'       => $request['search'],
			'meta_query'         => $request['meta'], // WPCS: slow query ok.
			'group_type'         => $request['group_type'],
			'group_type__in'     => $request['group_type__in'],
			'group_type__not_in' => $request['group_type__not_in'],
			'show_hidden'        => $request['show_hidden'],
			'per_page'           => $request['per_page'],
			'status'             => $request['status'],
			'page'               => $request['page'],
		);

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_groups_get_items_query_args', $args, $request );

		// Actually, query it.
		$groups = groups_get_groups( $args );

		$retval = array();
		foreach ( $groups['groups'] as $group ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $group, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $groups['total'], $args['per_page'] );

		/**
		 * Fires after a list of groups is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $groups   Fetched groups.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_get_items', $groups, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to group items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! $this->can_see_hidden_groups( $request ) ) {
			return new WP_Error( 'bp_rest_user_cannot_view_hidden_groups',
				__( 'Sorry, you cannot view hidden groups.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Retrieve a group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {
		$group = $this->get_group_object( $request );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $group, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Groups_Group  $group    Fetched group.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_get_item', $group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get information about a specific group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		$group = $this->get_group_object( $request );

		if ( empty( $group->id ) ) {
			return new WP_Error( 'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_see( $group ) ) {
			return new WP_Error( 'bp_rest_user_cannot_view_group',
				__( 'Sorry, you cannot view the group.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Create a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function create_item( $request ) {

		// If no group name.
		if ( empty( $request['name'] ) ) {
			return new WP_Error( 'bp_rest_create_group_empty_name',
				__( 'Please, enter the name of group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$group_id = groups_create_group( $this->prepare_item_for_database( $request ) );

		if ( ! is_numeric( $group_id ) ) {
			return new WP_Error( 'bp_rest_user_cannot_create_group',
				__( 'Cannot create new group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$group = $this->get_group_object( $group_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $group, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group is created via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Groups_Group  $group    The created group.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_create_item', $group, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to create a group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to create groups.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( ! bp_user_can_create_groups() ) {
			return new WP_Error( 'bp_rest_user_cannot_create_groups',
				__( 'Sorry, you cannot create groups.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Update a group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$group_id = groups_create_group( $this->prepare_item_for_database( $request ) );

		// If the update was fired but returned an error,
		// send a custom error to the api.
		if ( ! is_numeric( $group_id ) ) {
			return new WP_Error( 'bp_rest_user_cannot_update_group',
				__( 'Cannot update existing group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$group = $this->get_group_object( $group_id );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $group, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group is updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Groups_Group  $group    The updated group.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_update_item', $group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to update a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {

		// Bail early.
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to update this group.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$group = $this->get_group_object( $request );

		if ( empty( $group->id ) ) {
			return new WP_Error( 'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// If group author does not match logged_in user, block update.
		if ( ! $this->can_user_delete_or_update( $group ) ) {
			return new WP_Error( 'bp_rest_group_cannot_update',
				__( 'Sorry, you are not allowed to update this group.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Delete a group.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$group = $this->get_group_object( $request );

		$request->set_param( 'context', 'edit' );

		$response = $this->prepare_item_for_response( $group, $request );

		if ( ! groups_delete_group( $group->id ) ) {
			return new WP_Error( 'bp_rest_group_cannot_delete',
				__( 'Could not delete the group.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Fires after a group is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param object           $group    The deleted group.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_group_delete_item', $group, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to delete this group.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$group = $this->get_group_object( $request );

		if ( empty( $group->id ) ) {
			return new WP_Error( 'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_user_delete_or_update( $group ) ) {
			return new WP_Error( 'bp_rest_user_cannot_delete_group',
				__( 'Sorry, you are not allowed to delete this group.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Prepares group data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Groups_Group $item     Group object.
	 * @param WP_REST_Request $request  Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data = array(
			'id'                 => $item->id,
			'creator_id'         => bp_get_group_creator_id( $item ),
			'parent_id'          => $item->parent_id,
			'date_created'       => bp_rest_prepare_date_response( $item->date_created ),
			'description'        => array(
				'raw'      => $item->description,
				'rendered' => bp_get_group_description( $item ),
			),
			'enable_forum'       => bp_group_is_forum_enabled( $item ),
			'link'               => bp_get_group_permalink( $item ),
			'name'               => bp_get_group_name( $item ),
			'slug'               => bp_get_group_slug( $item ),
			'status'             => bp_get_group_status( $item ),
			'avatar_urls'        => array(),
			'admins'             => array(),
			'mods'               => array(),
			'total_member_count' => null,
			'last_activity'      => null,
		);

		// Avatars.
		$data['avatar_urls']['thumb'] = bp_core_fetch_avatar( array(
			'html'    => false,
			'object'  => 'group',
			'item_id' => $item->id,
			'type'    => 'thumb',
		) );

		$data['avatar_urls']['full'] = bp_core_fetch_avatar( array(
			'html'    => false,
			'object'  => 'group',
			'item_id' => $item->id,
			'type'    => 'full',
		) );

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		// If this is the 'edit' context, fill in more details--similar to "populate_extras".
		if ( 'edit' === $context ) {
			$data['total_member_count'] = groups_get_groupmeta( $item->id, 'total_member_count' );
			$data['last_activity']      = bp_rest_prepare_date_response( groups_get_groupmeta( $item->id, 'last_activity' ) );

			// Add admins and moderators to their respective arrays.
			$admin_mods = groups_get_group_members( array(
				'group_id'   => $item->id,
				'group_role' => array(
					'admin',
					'mod',
				),
			) );

			foreach ( (array) $admin_mods['members'] as $user ) {
				if ( ! empty( $user->is_admin ) ) {
					$data['admins'][] = $user;
				} else {
					$data['mods'][] = $user;
				}
			}
		}

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $item ) );

		/**
		 * Filter a group value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_group_prepare_value', $response, $request );
	}

	/**
	 * Prepare a group for create or update.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Object or WP_Error.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_group = new stdClass();
		$schema         = $this->get_item_schema();
		$group          = $this->get_group_object( $request );

		// Group ID.
		if ( ! empty( $schema['properties']['id'] ) && ! empty( $group->id ) ) {
			$prepared_group->group_id = $group->id;
		}

		// Group Creator ID.
		if ( ! empty( $schema['properties']['creator_id'] ) && isset( $request['user_id'] ) ) {
			$prepared_group->creator_id = (int) $request['user_id'];
		} else {
			$prepared_group->creator_id = get_current_user_id();
		}

		// Group Slug.
		if ( ! empty( $schema['properties']['slug'] ) && isset( $request['slug'] ) ) {
			$prepared_group->slug = $request['slug'];
		}

		// Group Name.
		if ( ! empty( $schema['properties']['name'] ) && isset( $request['name'] ) ) {
			$prepared_group->name = $request['name'];
		}

		// Group description.
		if ( ! empty( $schema['properties']['description'] ) && isset( $request['description'] ) ) {
			if ( is_string( $request['description'] ) ) {
				$prepared_group->description = $request['description'];
			} elseif ( isset( $request['description']['raw'] ) ) {
				$prepared_group->description = $request['description']['raw'];
			}
		}

		// Group status.
		if ( ! empty( $schema['properties']['status'] ) && isset( $request['status'] ) ) {
			$prepared_group->status = $request['status'];
		}

		// Group Forum Enabled.
		if ( ! empty( $schema['properties']['enable_forum'] ) && isset( $request['enable_forum'] ) ) {
			$prepared_group->enable_forum = (bool) $request['enable_forum'];
		}

		// Group Parent ID.
		if ( ! empty( $schema['properties']['parent_id'] ) && isset( $request['parent_id'] ) ) {
			$prepared_group->parent_id = $request['parent_id'];
		}

		/**
		 * Filters a group before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_group An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request        Request object.
		 */
		return apply_filters( 'bp_rest_group_pre_insert_value', $prepared_group, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param BP_Groups_Group $group Group item.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $group ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );
		$url  = $base . $group->id;

		// Entity meta.
		$links = array(
			'self'       => array(
				'href' => rest_url( $url ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'user'       => array(
				'href'       => rest_url( bp_rest_get_user_url( $group->creator_id ) ),
				'embeddable' => true,
			),
		);

		return $links;
	}

	/**
	 * Can a user see a group?
	 *
	 * @since 0.1.0
	 *
	 * @param  BP_Groups_Group $group Group object.
	 * @return boolean
	 */
	protected function can_see( $group ) {
		$retval  = false;
		$user_id = bp_loggedin_user_id();

		// If it is not a hidden/private group, user can see it.
		if ( 'public' === $group->status ) {
			$retval = true;
		} else {

			// User is a member of the group.
			if ( groups_is_user_member( $user_id, $group->id ) ) {
				$retval = true;
			}

			// Moderators.
			if ( bp_current_user_can( 'bp_moderate' ) ) {
				$retval = true;
			}
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool            $retval  Return value.
		 * @param int             $user_id User id.
		 * @param BP_Groups_Group $group   BP_Groups_Group object.
		 */
		return (bool) apply_filters( 'bp_rest_group_can_see', $retval, $user_id, $group );
	}

	/**
	 * See if user can delete or update a group.
	 *
	 * @since 0.1.0
	 *
	 * @param  BP_Groups_Group $group Group item.
	 * @return bool
	 */
	protected function can_user_delete_or_update( $group ) {

		if ( bp_current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		if ( get_current_user_id() === $group->creator_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Can this user see hidden groups?
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	protected function can_see_hidden_groups( $request ) {
		if ( $request['show_hidden'] ) {
			if ( bp_current_user_can( 'bp_moderate' ) ) {
				return true;
			}

			if ( is_user_logged_in() && isset( $request['user_id'] ) && absint( $request['user_id'] ) === bp_loggedin_user_id() ) {
				return true;
			}

			return false;
		}

		return true;
	}

	/**
	 * Get group object.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|BP_Groups_Group
	 */
	public function get_group_object( $request ) {
		if ( is_numeric( $request ) ) {
			$group_id = $request;
		} elseif ( isset( $request['group_id'] ) ) {
			$group_id = (int) $request['group_id'];
		} else {
			$group_id = (int) $request['id'];
		}

		$group = groups_get_group( $group_id );

		if ( empty( $group ) || empty( $group->id ) ) {
			return false;
		}

		return $group;
	}

	/**
	 * Clean up group_type__in input.
	 *
	 * @since 0.1.0
	 *
	 * @param string $value Comma-separated list of group types.
	 * @return array|null
	 */
	public function sanitize_group_types( $value ) {

		// Bail early.
		if ( empty( $value ) ) {
			return null;
		}

		$types       = explode( ',', $value );
		$valid_types = array_intersect( $types, bp_groups_get_group_types() );

		return empty( $valid_types ) ? null : $valid_types;
	}

	/**
	 * Validate group_type__in input.
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed           $value mixed value.
	 * @param  WP_REST_Request $request Full details about the request.
	 * @param  string          $param string.
	 *
	 * @return WP_Error|bool
	 */
	public function validate_group_types( $value, $request, $param ) {

		// Bail early.
		if ( empty( $value ) ) {
			return true;
		}

		$types            = explode( ',', $value );
		$registered_types = bp_groups_get_group_types();
		foreach ( $types as $type ) {
			if ( ! in_array( $type, $registered_types, true ) ) {
				/* translators: %1$s and %2$s is replaced with the registered types */
				return new WP_Error( 'bp_rest_invalid_group_type', sprintf( __( 'The group type you provided, %1$s, is not one of %2$s.', 'buddypress' ), $type, implode( ', ', $registered_types ) ) );
			}
		}
	}

	/**
	 * Get the group schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'group',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'creator_id'         => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of the user that created the group.', 'buddypress' ),
					'type'        => 'integer',
				),

				'name'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the group.', 'buddypress' ),
					'type'        => 'string',
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),

				'slug'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The URL-friendly slug for the group.', 'buddypress' ),
					'type'        => 'string',
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_title',
					),
				),

				'link'               => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The permalink to this object on the site.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),

				'description'        => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The description of the group.', 'buddypress' ),
					'type'        => 'object',
					'arg_options' => array(
						'sanitize_callback' => null, // Note: sanitization implemented in self::prepare_item_for_database().
						'validate_callback' => null, // Note: validation implemented in self::prepare_item_for_database().
					),
					'properties'  => array(
						'raw'       => array(
							'description' => __( 'Content for the group, as it exists in the database.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'edit' ),
						),
						'rendered'  => array(
							'description' => __( 'HTML content for the group, transformed for display.', 'buddypress' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),

				'status'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The status of the group.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array( 'public', 'private', 'hidden' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),

				'enable_forum'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the group has a forum or not.', 'buddypress' ),
					'type'        => 'boolean',
				),

				'parent_id'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'ID of the parent group.', 'buddypress' ),
					'type'        => 'integer',
				),

				'date_created'       => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( "The date the group was created, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),

				'admins'             => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Group administrators.', 'buddypress' ),
					'type'        => 'array',
				),

				'mods'               => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Group moderators.', 'buddypress' ),
					'type'        => 'array',
				),

				'total_member_count' => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Count of all group members.', 'buddypress' ),
					'type'        => 'integer',
				),

				'last_activity'      => array(
					'context'     => array( 'edit' ),
					'description' => __( "The date the group was last active, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
				),
			),
		);

		// Avatars.
		if ( true === buddypress()->avatar->show_avatars ) {
			$avatar_properties = array();

			$avatar_properties['full'] = array(
				/* translators: Full image size for the group Avatar */
				'description' => sprintf( __( 'Avatar URL with full image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_full_width() ), number_format_i18n( bp_core_avatar_full_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$avatar_properties['thumb'] = array(
				/* translators: Thumb imaze size for the group Avatar */
				'description' => sprintf( __( 'Avatar URL with thumb image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_thumb_width() ), number_format_i18n( bp_core_avatar_thumb_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$schema['properties']['avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the group.', 'buddypress' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		return $schema;
	}

	/**
	 * Get the query params for collections of groups.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['type'] = array(
			'description'       => __( 'Shorthand for certain orderby/order combinations.', 'buddypress' ),
			'default'           => 'active',
			'type'              => 'string',
			'enum'              => array( 'active', 'newest', 'alphabetical', 'random', 'popular', 'most-forum-topics', 'most-forum-posts' ),
			'sanitize_callback' => 'rest_validate_request_arg',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddypress' ),
			'default'           => 'desc',
			'type'              => 'string',
			'enum'              => array( 'asc', 'desc' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['status'] = array(
			'description'       => __( 'Group statuses to limit results to.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['orderby'] = array(
			'description'       => __( 'Order groups by which attribute.', 'buddypress' ),
			'default'           => 'date_created',
			'type'              => 'string',
			'enum'              => array( 'date_created', 'last_activity', 'total_member_count', 'name', 'random' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Pass a user_id to limit to only groups that this user is a member of.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['parent_id'] = array(
			'description'       => __( 'Get groups that are children of the specified group(s) ids.', 'buddypress' ),
			'default'           => null,
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['meta'] = array(
			'description'       => __( 'Get groups based on their meta data information.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes groups with specific IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['search'] = array(
			'description'       => __( 'Limit results set to items that match this search query.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['group_type'] = array(
			'description'       => __( 'Limit results set to a certain type.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'enum'              => bp_groups_get_group_types(),
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['group_type__in'] = array(
			'description'       => __( 'Limit results set to groups of certain types.', 'buddypress' ),
			'default'           => '',
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['group_type__not_in'] = array(
			'description'       => __( 'Exclude groups of certain types.', 'buddypress' ),
			'default'           => '',
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_group_types' ),
			'validate_callback' => array( $this, 'validate_group_types' ),
		);

		$params['enable_forum'] = array(
			'description'       => __( 'Whether the group should have a forum enabled.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['show_hidden'] = array(
			'description'       => __( 'Whether results should include hidden groups.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
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

		return $params;
	}
}
