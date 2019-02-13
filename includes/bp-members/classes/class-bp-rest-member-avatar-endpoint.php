<?php
/**
 * BP REST: BP_REST_Member_Avatar_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Member Avatar endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Member_Avatar_Endpoint extends WP_REST_Controller {

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
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<user_id>[\d]+)/avatar', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => $this->get_item_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
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
	 * Fetch an existing avatar of a member.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request
	 */
	public function get_item( $request ) {
		$avatar = bp_core_fetch_avatar( array(
			'object'  => 'user',
			'item_id' => (int) $request['user_id'],
			'html'    => (bool) $request['html'],
			'type'    => $request['type'],
		) );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $avatar, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a member avatar is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param string            $avatar   Deleted avatar url.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_member_avatar_get_item', $avatar, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to get a member avatar.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to access the member avatar.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );
		if ( true === $retval && ! $user ) {
			$retval = new WP_Error( 'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			if ( true === $retval && bp_loggedin_user_id() !== $user->ID ) {
				$retval = new WP_Error( 'bp_rest_authorization_required',
					__( 'Sorry, you cannot get this member avatar.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

		/**
		 * Filter the member avatar `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_member_avatar_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Upload a member avatar.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error
	 */
	public function create_item( $request ) {

		// Get the file via $_FILES.
		$files = $request->get_file_params();

		if ( empty( $files ) ) {
			return new WP_Error( 'bp_rest_member_avatar_no_image_file',
				__( 'Sorry, you need an image file to upload.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Upload the avatar.
		$avatar = $this->upload_avatar_from_file( $files, $request );

		if ( is_wp_error( $avatar ) ) {
			return $avatar;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $avatar, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a member avatar is added via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass          $avatar   Avatar object.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_member_avatar_create_item', $avatar, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to upload a member avatar.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the member avatar `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_member_avatar_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete an existing avatar of a member.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error
	 */
	public function delete_item( $request ) {
		$deleted = bp_core_delete_existing_avatar( array(
			'object'  => 'user',
			'item_id' => (int) $request['user_id'],
		) );

		if ( ! $deleted ) {
			return new WP_Error( 'bp_rest_member_avatar_delete_failed',
				__( 'Sorry, there was a problem deleting the avatar.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$avatar        = new stdClass();
		$avatar->full  = '';
		$avatar->thumb = '';

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $avatar, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a member avatar is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass          $avatar   Avatar object.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_member_avatar_delete_item', $avatar, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to delete member avatar.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the member avatar `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_member_avatar_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares avatar data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass|string $avatar   Avatar object | Avatar url.
	 * @param WP_REST_Request $request  Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $avatar, $request ) {
		$data = array(
			'full'  => $avatar->full,
			'thumb' => $avatar->thumb,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		/**
		 * Filter a member avatar value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response  $response Response.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_member_avatar_prepare_value', $response, $request );
	}

	/**
	 * Avatar Upload from File.
	 *
	 * @param array $files Image file information.
	 * @return stdClass
	 */
	protected function upload_avatar_from_file( $files, $request ) {

		// Setup some variables.
		$bp                     = buddypress();
		$bp->displayed_user     = new stdClass();
		$bp->displayed_user->id = (int) $request['user_id'];
		$user_id                = $bp->displayed_user->id;
		$object                 = 'user';

		$upload_path       = bp_core_avatar_upload_path();
		$upload_dir_filter = 'xprofile_avatar_upload_dir';

		if ( ! isset( $bp->avatar_admin ) ) {
			$bp->avatar_admin = new stdClass();
		}

		// Upload the file.
		$avatar_attachment = new BP_Attachment_Avatar();
		$_POST['action']   = $avatar_attachment->action;
		$avatar_original   = $avatar_attachment->upload( $files, $upload_dir_filter );

		// In case of an error.
		if ( ! empty( $avatar_original['error'] ) ) {
			return new WP_Error( 'bp_rest_member_avatar_error',
				sprintf( __( 'Upload failed! Error was: %s.', 'buddypress' ), $avatar_original['error'] ),
				array(
					'status' => 500,
				)
			);
		}

		// Delete the existing avatar files for the object.
		$existing_avatar = bp_core_fetch_avatar(
			array(
				'object'  => $object,
				'item_id' => $user_id,
				'html'    => false,
			)
		);

		/**
		 * Check that the new avatar doesn't have the same name as the
		 * old one before deleting
		 */
		if ( ! empty( $existing_avatar ) ) {
			bp_core_delete_existing_avatar(
				array(
					'object'      => $object,
					'item_id'     => $user_id,
				)
			);
		}

		// The Avatar UI available width.
		$ui_available_width = 0;

		// Try to set the ui_available_width using the avatar_admin global.
		if ( isset( $bp->avatar_admin->ui_available_width ) ) {
			$ui_available_width = $bp->avatar_admin->ui_available_width;
		}

		// Set avatar types.
		$avatar_object = $this->upload_avatar_types( $upload_path, $avatar_original['file'], $user_id );

		@unlink( $avatar_original['file'] );

		return $avatar_object;
	}

	/**
	 * Upload avatar types.
	 *
	 * @since 0.1.0
	 *
	 * @param string $upload_path Upload path.
	 * @param string $image       Image file.
	 * @param int    $user_id     User ID.
	 * @return stdClass
	 */
	protected function upload_avatar_types( $upload_path, $image, $user_id ) {
		$types = array( 'full', 'thumb' );
		$data  = @getimagesize( $image );
		$ext   = 'jpg';

		if ( 'image/png' === $data['mime'] ) {
			$ext = 'png';
		}

		$avatar_object = new stdClass();

		foreach ( $types as $key_type ) {
			$filename  = wp_unique_filename( $upload_path, uniqid() . "-bp{$key_type}.{$ext}" );
			$dest_path = $upload_path . '/avatars/' . $user_id . '/' . $filename;

			$url                        = str_replace( $upload_path, '', $dest_path );
			$avatar_object->{$key_type} = bp_core_avatar_url() . $url;

			copy( $image, $dest_path );
		}

		return $avatar_object;
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
			'title'      => 'avatar',
			'type'       => 'object',
			'properties' => array(
				'full'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Full size of the image file.', 'buddypress' ),
					'type'        => 'string',
				),
				'thumb'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Thumb size of the image file.', 'buddypress' ),
					'type'        => 'string',
				),
			),
		);

		return $schema;
	}

	/**
	 * Get the query params for the `get_item`.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['type'] = array(
			'description'       => __( 'Whether you would like the `full` or the smaller `thumb`.', 'buddypress' ),
			'default'           => 'thumb',
			'type'              => 'string',
			'enum'              => array( 'thumb', 'full' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['html'] = array(
			'description'       => __( 'Whether to return an <img> HTML element, vs a raw URL to an avatar.', 'buddypress' ),
			'default'           => true,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
