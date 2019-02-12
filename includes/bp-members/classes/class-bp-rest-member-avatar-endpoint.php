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
	 * Upload a member avatar.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Request|WP_Error
	 */
	public function create_item( $request ) {

		// Get the file via $_FILES or raw data.
		$files = $request->get_file_params();

		if ( empty( $files ) ) {
			return new WP_Error( 'bp_rest_member_avatar_no_image_file',
				__( 'Sorry, you need an image file to upload.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Get the file via raw data.
		$headers = $request->get_headers();

		// Set user ID for the upload path.
		$bp                     = buddypress();
		$bp->displayed_user     = new stdClass();
		$bp->displayed_user->id = (int) $request['user_id'];

		// Upload the avatar.
		$avatar = $this->upload_avatar_from_file( $files );

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
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to upload an avatar.', 'buddypress' ),
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
					__( 'Sorry, you cannot upload an avatar.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			}
		}

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
		$user_id = (int) $request['user_id'];
		$object  = 'user';

		$avatar = bp_core_fetch_avatar( array(
			'object'  => $object,
			'item_id' => $user_id,
			'html'    => false,
			'type'    => 'full',
		) );

		// Try to delete the avatar.
		$deleted = bp_core_delete_existing_avatar( array(
			'object'  => $object,
			'item_id' => $user_id,
		) );

		if ( ! $deleted ) {
			return new WP_Error( 'bp_rest_member_avatar_delete_failed',
				__( 'Sorry, there was a problem deleting the avatar.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

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
		 * @param string            $avatar   Deleted avatar url.
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
		$retval = $this->create_item_permissions_check( $request );

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

		// For the deleted endpoint.
		if ( is_string( $avatar ) ) {
			$data = array(
				'url' => $avatar,
			);
		} else {
			// For the create_item endpoint.
			$data = array(
				'name'   => $avatar->name,
				'file'   => $avatar->file,
				'url'    => $avatar->url,
				'dir'    => $avatar->dir,
				'width'  => $avatar->width,
				'height' => $avatar->height,
			);
		}

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
	protected function upload_avatar_from_file( $files ) {

		// Setup some variables.
		$bp                = buddypress();
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

		// The Avatar UI available width.
		$ui_available_width = 0;

		// Try to set the ui_available_width using the avatar_admin global.
		if ( isset( $bp->avatar_admin->ui_available_width ) ) {
			$ui_available_width = $bp->avatar_admin->ui_available_width;
		}

		// Maybe resize.
		$bp->avatar_admin->resized = $avatar_attachment->shrink( $avatar_original['file'], $ui_available_width );
		$avatar_object             = new stdClass();

		// We only want to handle one image after resize.
		if ( empty( $bp->avatar_admin->resized ) ) {
			$avatar_object->file = $avatar_original['file'];
			$avatar_object->dir  = str_replace( $upload_path, '', $avatar_original['file'] );
		} else {
			$avatar_object->file = $bp->avatar_admin->resized['path'];
			$avatar_object->dir  = str_replace( $upload_path, '', $bp->avatar_admin->resized['path'] );
			@unlink( $avatar_original['file'] );
		}

		// Check for WP_Error on what should be an image.
		if ( is_wp_error( $avatar_object->dir ) ) {
			return new WP_Error( 'bp_rest_member_avatar_error',
				sprintf( __( 'Upload failed! Error was: %s.', 'buddypress' ), $avatar_object->dir->get_error_message() ),
				array(
					'status' => 500,
				)
			);
		}

		// Set the url value for the image.
		$avatar_object->url = bp_core_avatar_url() . $avatar_object->dir;

		// Set the sizes of the image.
		$image_size            = @getimagesize( $avatar_object->file );
		$avatar_object->width  = $image_size[0];
		$avatar_object->height = $image_size[1];

		// Set the name of the image.
		$name                = $files['file']['name'];
		$name_parts          = pathinfo( $name );
		$avatar_object->name = trim( substr( $name, 0, - ( 1 + strlen( $name_parts['extension'] ) ) ) );

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
				'name'            => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The name of the image file.', 'buddypress' ),
					'type'        => 'string',
				),
				'file'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Full path of the image file.', 'buddypress' ),
					'type'        => 'string',
				),
				'url'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The url of the image file.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'dir'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The dir of the image file.', 'buddypress' ),
					'type'        => 'string',
				),
				'width'           => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The width of the image file.', 'buddypress' ),
					'type'        => 'integer',
				),
				'height'          => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The height of the image file.', 'buddypress' ),
					'type'        => 'integer',
				),
			),
		);

		return $schema;
	}
}
