<?php
/**
 * BP REST: BP_REST_Members_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Members endpoints.
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
		$this->namespace = 'buddypress/v1';
		$this->rest_base = 'members';
	}

	/**
	 * Prepares a single user output for response.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass        $user User data.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $user, $request ) {
		$data   = array();
		$schema = $this->get_item_schema();

		if ( ! empty( $schema['properties']['id'] ) ) {
			$data['id'] = $user->ID;
		}

		if ( ! empty( $schema['properties']['name'] ) ) {
			$data['name'] = $user->display_name;
		}

		if ( ! empty( $schema['properties']['email'] ) ) {
			$data['email'] = $user->user_email;
		}

		if ( ! empty( $schema['properties']['link'] ) ) {
			$data['link'] = bp_core_get_user_domain( $user->ID, $user->user_nicename, $user->user_login );
		}

		if ( ! empty( $schema['properties']['user_login'] ) ) {
			$data['user_login'] = bp_is_username_compatibility_mode() ? $user->user_login : $user->user_nicename;
		}

		if ( ! empty( $schema['properties']['registered_date'] ) ) {
			$data['registered_date'] = date( 'c', strtotime( $user->user_registered ) );
		}

		if ( ! empty( $schema['properties']['avatar_urls'] ) ) {
			$data['avatar_urls'] = array(
				'full'  => bp_core_fetch_avatar( array(
					'item_id' => $user->ID,
					'html'    => false,
					'type'    => 'full',
				) ),

				'thumb' => bp_core_fetch_avatar( array(
					'item_id' => $user->ID,
					'html'    => false,
				) ),
			);
		}

		// Member types.
		if ( ! empty( $schema['properties']['member_types'] ) ) {
			$data['member_types'] = bp_get_member_type( $user->ID, false );
			if ( false === $data['member_types'] ) {
				$data['member_types'] = array();
			}
		}

		// Defensively call array_values() to ensure an array is returned.
		if ( ! empty( $schema['properties']['roles'] ) ) {
			$data['roles'] = array_values( $user->roles );
		}

		if ( ! empty( $schema['properties']['capabilities'] ) ) {
			$data['capabilities'] = (object) $user->allcaps;
		}

		if ( ! empty( $schema['properties']['extra_capabilities'] ) ) {
			$data['extra_capabilities'] = (object) $user->caps;
		}

		if ( ! empty( $schema['properties']['xprofile'] ) ) {
			$data['xprofile'] = $this->xprofile_data( $user->ID );
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'embed';

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $user ) );

		/**
		 * Filters user data returned from the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param object           $user     User object used to create response.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return apply_filters( 'rest_member_prepare_user', $response, $user, $request );
	}

	/**
	 * Checks if a given request has access create members.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {

		if ( ! current_user_can( 'bp_moderate' ) ) {
			return new WP_Error( 'rest_member_cannot_create',
				__( 'Sorry, you are not allowed to create new members.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
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
		$user = $this->get_user( $request['id'] );

		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! current_user_can( 'bp_moderate' ) ) {
			return new WP_Error( 'rest_member_cannot_update',
				__( 'Sorry, you are not allowed to update this member.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
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
		$user = $this->get_user( $request['id'] );

		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! current_user_can( 'delete_user', $user->ID ) || ! current_user_can( 'bp_moderate' ) ) {
			return new WP_Error( 'rest_member_cannot_delete',
				__( 'Sorry, you are not allowed to delete this member.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Get XProfile info from the user.
	 *
	 * @since 0.1.0
	 *
	 * @param  int $user_id User ID.
	 * @return array XProfile info.
	 */
	protected function xprofile_data( $user_id ) {

		// Get XProfile group info.
		$groups = bp_xprofile_get_groups( array(
			'user_id'          => $user_id,
			'fetch_fields'     => true,
			'fetch_field_data' => true,
		) );

		$data = array();
		foreach ( $groups as $group ) {
			$data['groups'][ $group->id ] = array(
				'name' => $group->name,
			);

			foreach ( $group->fields as $item ) {
				$data['groups'][ $group->id ]['fields'][ $item->id ] = array(
					'name'  => $item->name,
					'value' => maybe_unserialize( $item->data->value ),
				);
			}
		}

		return $data;
	}

	/**
	 * Can we see a member?
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @param  boolean         $edit Edit fallback.
	 * @return boolean
	 */
	protected function can_see( $request, $edit = false ) {
		$user_id = bp_loggedin_user_id();
		$retval  = false;

		$user = $this->get_user( $request['id'] );
		if ( is_wp_error( $user ) ) {
			return false;
		}

		// Me, myself and I are always allowed access.
		if ( $user_id === $user->ID ) {
			return true;
		}

		// Moderators as well.
		if ( current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		}

		if ( current_user_can( 'list_users' ) ) {
			$retval = true;
		}

		// Fix for edit content.
		if ( $edit && 'edit' === $request['context'] && $retval ) {
			$retval = true;
		}

		/**
		 * Filter the retval.
		 *
		 * @since 0.1.0
		 *
		 * @param bool     $retval   Return value.
		 * @param int      $user_id  User ID.
		 * @param bool     $edit     Edit content.
		 */
		return (bool) apply_filters( 'rest_member_can_see', $retval, $user_id, $edit );
	}

	/**
	 * Prepares a single user for creation or update.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return object $prepared_user User object.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_user = parent::prepare_item_for_database( $request );

		// The parent class uses username instead of user_login.
		if ( ! isset( $prepared_user->user_login ) && isset( $request['user_login'] ) ) {
			$prepared_user->user_login = $request['user_login'];
		}

		/**
		 * Filters an user object before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_user An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request Request object.
		 */
		return apply_filters( 'rest_member_pre_insert_value', $prepared_user, $request );
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
			'title'      => 'member',
			'type'       => 'object',

			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the member.', 'buddypress' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'name'        => array(
					'description' => __( 'Display name for the member.', 'buddypress' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),

				'email'       => array(
					'description' => __( 'The email address for the member.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'edit' ),
					'required'    => true,
				),

				'link'        => array(
					'description' => __( 'Profile URL of the member.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view' ),
					'readonly'    => true,
				),

				'user_login'        => array(
					'description' => __( 'An alphanumeric identifier for the member.', 'buddypress' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_username' ),
					),
				),

				'registered_date' => array(
					'description' => __( 'Registration date for the member.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),

				'password'        => array(
					'description' => __( 'Password for the member (never included).', 'buddypress' ),
					'type'        => 'string',
					'context'     => array(), // Password is never displayed.
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_user_password' ),
					),
				),

				'roles'           => array(
					'description' => __( 'Roles assigned to the member.', 'buddypress' ),
					'type'        => 'array',
					'items'       => array(
						'type'    => 'string',
					),
					'context'     => array( 'edit' ),
				),

				'capabilities'    => array(
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

				'member_types' => array(
					'description' => __( 'Member types associated with the member.', 'buddypress' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'xprofile' => array(
					'description' => __( 'Member XProfile groups and its fields.', 'buddypress' ),
					'type'        => 'array',
					'context'     => array( 'embed', 'view', 'edit' ),
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

			$schema['properties']['avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the member.', 'buddypress' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		return $this->add_additional_fields_schema( $schema );
	}
}
