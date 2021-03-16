<?php
/**
 * BP REST: BP_REST_Members_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * BuddyPress Members endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Members_Endpoint extends WP_REST_Users_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = 'members';
	}

	/**
	 * Retrieve users.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'type'           => $request['type'],
			'user_id'        => $request['user_id'],
			'user_ids'       => $request['user_ids'],
			'xprofile_query' => $request['xprofile'],
			'include'        => $request['include'],
			'exclude'        => $request['exclude'],
			'member_type'    => $request['member_type'],
			'search_terms'   => $request['search'],
			'per_page'       => $request['per_page'],
			'page'           => $request['page'],
		);

		if ( empty( $request['user_ids'] ) ) {
			$args['user_ids'] = false;
		}

		if ( empty( $request['exclude'] ) ) {
			$args['exclude'] = false;
		}

		if ( empty( $request['include'] ) ) {
			$args['include'] = false;
		}

		if ( empty( $request['xprofile'] ) ) {
			$args['xprofile_query'] = false;
		}

		if ( empty( $request['member_type'] ) ) {
			$args['member_type'] = '';
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_members_get_items_query_args', $args, $request );

		// Actually, query it.
		$member_query = new BP_User_Query( $args );
		$members      = array_values( $member_query->results );

		$retval = array();
		foreach ( $members as $member ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $member, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $member_query->total_users, $args['per_page'] );

		/**
		 * Fires after a list of members is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $members  Fetched members.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_members_get_items', $members, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to get all users.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ) {

		/**
		 * Filter the members `get_items` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool            $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_members_get_items_permissions_check', true, $request );
	}

	/**
	 * Checks if a given request has access to read a user.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to view members.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['id'] );

		if ( true === $retval && ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && get_current_user_id() === $user->ID ) {
			$retval = true;
		} elseif ( true === $retval && 'edit' === $request['context'] && ! current_user_can( 'list_users' ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not allowed to view members.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the members `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_members_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Checks if a given request has access create members.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$retval = parent::create_item_permissions_check( $request );

		/**
		 * Filter or override the members `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_members_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Check if a given request has access to update a member.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$error = new WP_Error(
			'bp_rest_authorization_required',
			__( 'Sorry, you are not allowed to perform this action.', 'buddypress' ),
			array(
				'status' => rest_authorization_required_code(),
			)
		);
		$retval = $error;

		$user             = bp_rest_get_user( $request['id'] );
		$member_type_edit = isset( $request['member_type'] );

		if ( ! $user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		} else {
			$action = 'delete';

			if ( 'DELETE' !== $request->get_method() ) {
				$action = 'update';
			}

			if ( get_current_user_id() === $user->ID ) {
				if ( $member_type_edit && ! bp_current_user_can( 'bp_moderate' ) ) {
					$retval = $error;
				} else {
					$retval = parent::update_item_permissions_check( $request );
				}
			} elseif ( ! $this->can_manage_member( $user, $action ) ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
					__( 'Sorry, you are not allowed to view members.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			} else {
				$retval = true;
			}
		}

		/**
		 * Filter the members `update_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param true|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_members_update_item_permissions_check', $retval, $request );
	}

	/**
	 * Check if a given request has access to delete a member.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->update_item_permissions_check( $request );

		/**
		 * Filter the members `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_members_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Deleting the current user is not implemented into this endpoint.
	 *
	 * This action is specific to the User Settings endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error                WP_Error object to inform it's not implemented.
	 */
	public function delete_current_item_permissions_check( $request ) {
		return new WP_Error(
			'bp_rest_invalid_method',
			/* translators: %s: transport method name */
			sprintf( __( '\'%s\' Transport Method not implemented.', 'buddypress' ), $request->get_method() ),
			array(
				'status' => 405,
			)
		);
	}

	/**
	 * Deleting the current user is not implemented into this endpoint.
	 *
	 * This action is specific to the User Settings endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error                WP_Error to inform it's not implemented.
	 */
	public function delete_current_item( $request ) {
		return new WP_Error(
			'bp_rest_invalid_method',
			/* translators: %s: transport method name */
			sprintf( __( '\'%s\' Transport method not implemented.', 'buddypress' ), $request->get_method() ),
			array(
				'status' => 405,
			)
		);
	}

	/**
	 * Prepares a single user output for response.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User         $user    User object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $user, $request ) {
		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data     = $this->user_data( $user, $context );
		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $user ) );

		/**
		 * Filters user data returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_REST_Request  $request  The request object.
		 * @param WP_User          $user     WP_User object.
		 */
		return apply_filters( 'bp_rest_members_prepare_value', $response, $request, $user );
	}

	/**
	 * Method to facilitate fetching of user data.
	 *
	 * This was abstracted to be used in other BuddyPress endpoints.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User $user    User object.
	 * @param string  $context The context of the request. Defaults to 'view'.
	 * @return array
	 */
	public function user_data( $user, $context = 'view' ) {
		$data = array(
			'id'                 => $user->ID,
			'name'               => $user->display_name,
			'user_login'         => $user->user_login,
			'link'               => bp_core_get_user_domain( $user->ID, $user->user_nicename, $user->user_login ),
			'member_types'       => bp_get_member_type( $user->ID, false ),
			'roles'              => array(),
			'capabilities'       => array(),
			'extra_capabilities' => array(),
			'registered_date'    => '',
			'xprofile'           => $this->xprofile_data( $user->ID ),
			'friendship_status' => false,
		);

		// Check if user is friends with current logged in user.
		if ( bp_is_active( 'friends' ) && get_current_user_id() !== $user->ID ) {
			$data['friendship_status'] = ( 'is_friend' === friends_check_friendship_status( get_current_user_id(), $user->ID ) );
		}

		if ( 'edit' === $context ) {
			$data['registered_date']    = bp_rest_prepare_date_response( $user->data->user_registered );
			$data['roles']              = (array) array_values( $user->roles );
			$data['capabilities']       = (array) array_keys( $user->allcaps );
			$data['extra_capabilities'] = (array) array_keys( $user->caps );
		}

		// The name used for that user in @-mentions.
		if ( bp_is_active( 'activity' ) ) {
			$data['mention_name'] = bp_activity_get_user_mentionname( $user->ID );
		}

		// Get item schema.
		$schema = $this->get_item_schema();

		// Avatars.
		if ( ! empty( $schema['properties']['avatar_urls'] ) ) {
			$data['avatar_urls'] = array(
				'full'  => bp_core_fetch_avatar(
					array(
						'item_id' => $user->ID,
						'html'    => false,
						'type'    => 'full',
					)
				),
				'thumb' => bp_core_fetch_avatar(
					array(
						'item_id' => $user->ID,
						'html'    => false,
						'type'    => 'thumb',
					)
				),
			);
		}

		// Fallback.
		if ( false === $data['member_types'] ) {
			$data['member_types'] = array();
		}

		return $data;
	}

	/**
	 * Prepares a single user for creation or update.
	 *
	 * @todo Improve sanitization and schema verification.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_user = parent::prepare_item_for_database( $request );

		// The parent class uses username instead of user_login.
		if ( ! isset( $prepared_user->user_login ) && isset( $request['user_login'] ) ) {
			$prepared_user->user_login = $request['user_login'];
		}

		// Set member type.
		if ( isset( $prepared_user->ID ) && isset( $request['member_type'] ) ) {

			// Append on update. Add on creation.
			$append = WP_REST_Server::EDITABLE === $request->get_method();

			bp_set_member_type( $prepared_user->ID, $request['member_type'], $append );
		}

		/**
		 * Filters an user object before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_user An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( 'bp_rest_members_pre_insert_value', $prepared_user, $request );
	}

	/**
	 * Get XProfile info from the user.
	 *
	 * @since 0.1.0
	 *
	 * @param  int $user_id User ID.
	 * @return array
	 */
	protected function xprofile_data( $user_id ) {
		$data = array();

		// Get XProfile groups, only if the component is active.
		if ( bp_is_active( 'xprofile' ) ) {
			$fields_endpoint = new BP_REST_XProfile_Fields_Endpoint();

			$groups = bp_xprofile_get_groups(
				array(
					'user_id'          => $user_id,
					'fetch_fields'     => true,
					'fetch_field_data' => true,
				)
			);

			foreach ( $groups as $group ) {
				$data['groups'][ $group->id ] = array(
					'name' => $group->name,
				);

				foreach ( $group->fields as $item ) {
					$data['groups'][ $group->id ]['fields'][ $item->id ] = array(
						'name'  => $item->name,
						'value' => array(
							'raw'          => $item->data->value,
							'unserialized' => $fields_endpoint->get_profile_field_unserialized_value( $item->data->value ),
							'rendered'     => $fields_endpoint->get_profile_field_rendered_value( $item->data->value, $item ),
						),
					);
				}
			}
		} else {
			$data = array( __( 'No extended profile data available as the component is inactive', 'buddypress' ) );
		}

		return $data;
	}

	/**
	 * Can user manage (delete/update) a member?
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_User $user User object.
	 * @param  string  $action The action to perform (update or delete).
	 * @return bool
	 */
	protected function can_manage_member( $user, $action = 'delete' ) {
		$capability = 'delete_user';

		if ( 'update' === $action ) {
			$capability = 'edit_user';
		}

		return current_user_can( $capability, $user->ID );
	}

	/**
	 * Updates the values of additional fields added to a data object.
	 *
	 * This function makes sure updating the field value thanks to the `id` property of
	 * the created/updated object type is consistent accross BuddyPress components.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User         $object  The WordPress user object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True on success, WP_Error object if a field cannot be updated.
	 */
	protected function update_additional_fields_for_object( $object, $request ) {
		if ( ! isset( $object->data ) ) {
			return new WP_Error(
				'invalid_user',
				__( 'The data for the user was not found.', 'buddypress' )
			);
		}

		$member     = $object->data;
		$member->id = $member->ID;

		return WP_REST_Controller::update_additional_fields_for_object( $member, $request );
	}

	/**
	 * Make sure to retrieve the needed arguments for the endpoint CREATABLE method.
	 *
	 * @since 0.1.0
	 *
	 * @param string $method Optional. HTTP method of the request.
	 * @return array Endpoint arguments.
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
		$args = WP_REST_Controller::get_endpoint_args_for_item_schema( $method );
		$key  = 'get_item';

		// Add member type args.
		$member_type_args = array(
			'description'       => __( 'Set type(s) for a member.', 'buddypress' ),
			'type'              => 'string',
			'enum'              => bp_get_member_types(),
			'context'           => array( 'edit' ),
			'sanitize_callback' => 'bp_rest_sanitize_member_types',
			'validate_callback' => 'bp_rest_sanitize_member_types',
		);

		if ( WP_REST_Server::CREATABLE === $method ) {
			$key = 'create_item';

			// We don't need the mention name to create a user.
			unset( $args['mention_name'] );

			// Add member type args.
			$args['types'] = $member_type_args;

			// But we absolutely need the email.
			$args['email'] = array(
				'description' => __( 'The email address for the member.', 'buddypress' ),
				'type'        => 'string',
				'format'      => 'email',
				'context'     => array( 'edit' ),
				'required'    => true,
			);
		} elseif ( WP_REST_Server::EDITABLE === $method ) {
			$key = 'update_item';

			/**
			 * 1. The mention name or user login are not updatable.
			 * 2. The password belongs to the Settings endpoint parameter.
			 */
			unset( $args['mention_name'], $args['user_login'], $args['password'] );

			// Add member type args.
			$args['types'] = $member_type_args;
		} elseif ( WP_REST_Server::DELETABLE === $method ) {
			$key = 'delete_item';
		}

		/**
		 * Filters the method query arguments.
		 *
		 * @since 0.1.0
		 *
		 * @param array  $args   Query arguments.
		 * @param string $method HTTP method of the request.
		 */
		return apply_filters( "bp_rest_members_{$key}_query_arguments", $args, $method );
	}

	/**
	 * Get the members schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_members',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'description' => __( 'A unique numeric ID for the Member.', 'buddypress' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'name'               => array(
					'description' => __( 'Display name for the member.', 'buddypress' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'mention_name'       => array(
					'description' => __( 'The name used for that user in @-mentions.', 'buddypress' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'link'               => array(
					'description' => __( 'Profile URL of the member.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'user_login'         => array(
					'description' => __( 'An alphanumeric identifier for the Member.', 'buddypress' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_username' ),
					),
				),
				'member_types'       => array(
					'description' => __( 'Member types associated with the member.', 'buddypress' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'registered_date'    => array(
					'description' => __( 'Registration date for the member.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
				'password'           => array(
					'description' => __( 'Password for the member (never included).', 'buddypress' ),
					'type'        => 'string',
					'context'     => array(), // Password is never displayed.
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_user_password' ),
					),
				),
				'roles'              => array(
					'description' => __( 'Roles assigned to the member.', 'buddypress' ),
					'type'        => 'array',
					'context'     => array( 'edit' ),
					'items'       => array(
						'type' => 'string',
					),
				),
				'capabilities'       => array(
					'description' => __( 'All capabilities assigned to the user.', 'buddypress' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
				'extra_capabilities' => array(
					'description' => __( 'Any extra capabilities assigned to the user.', 'buddypress' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
				'xprofile'           => array(
					'description' => __( 'Member XProfile groups and its fields.', 'buddypress' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'friendship_status'  => array(
					'description' => __( 'Friendship relation with, current, logged in user.', 'buddypress' ),
					'type'        => 'bool',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);

		// Avatars.
		if ( true === buddypress()->avatar->show_avatars ) {
			$avatar_properties = array();

			$avatar_properties['full'] = array(
				/* translators: 1: Full avatar width in pixels. 2: Full avatar height in pixels */
				'description' => sprintf( __( 'Avatar URL with full image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_full_width() ), number_format_i18n( bp_core_avatar_full_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$avatar_properties['thumb'] = array(
				/* translators: 1: Thumb avatar width in pixels. 2: Thumb avatar height in pixels */
				'description' => sprintf( __( 'Avatar URL with thumb image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_thumb_width() ), number_format_i18n( bp_core_avatar_thumb_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$schema['properties']['avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the member.', 'buddypress' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		/**
		 * Filters the members schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_members_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array_intersect_key(
			parent::get_collection_params(),
			array(
				'context'  => true,
				'page'     => true,
				'per_page' => true,
				'search'   => true,
			)
		);

		$params['type'] = array(
			'description'       => __( 'Shorthand for certain orderby/order combinations.', 'buddypress' ),
			'default'           => 'newest',
			'type'              => 'string',
			'enum'              => array( 'active', 'newest', 'alphabetical', 'random', 'online', 'popular' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Limit results to friends of a user.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_ids'] = array(
			'description'       => __( 'Pass IDs of users to limit result set.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes specific IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'integer' ),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['member_type'] = array(
			'description'       => __( 'Limit results set to certain type(s).', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'items'             => array( 'type' => 'string' ),
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['xprofile'] = array(
			'description'       => __( 'Limit results set to a certain XProfile field.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_members_collection_params', $params );
	}
}
