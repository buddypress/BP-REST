<?php
/**
 * BP REST: BP_REST_Attachments_Avatar_Endpoint class
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
class BP_REST_Attachments_Avatar_Endpoint extends WP_REST_Controller {

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
	 * Fetch an existing member avatar.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$avatar = bp_core_fetch_avatar( array(
			'object'  => 'user',
			'type'    => $request['type'],
			'item_id' => (int) $request['user_id'],
			'html'    => (bool) $request['html'],
			'alt'     => $request['alt'],
			'no_grav' => (bool) $request['no_gravatar'],
		) );

		if ( ! $avatar ) {
			return new WP_Error( 'bp_rest_attachments_avatar_no_image',
				__( 'Sorry, there was a problem fetching the avatar.', 'buddypress' ),
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
		 * Fires after a member avatar is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param string            $avatar   The avatar.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_avatar_get_item', $avatar, $response, $request );

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
				__( 'Sorry, you need to be logged in to access this member avatar.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$user = bp_rest_get_user( $request['user_id'] );
		if ( true === $retval && ! $user instanceof WP_User ) {
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

			if ( true === $retval ) {
				$retval = true;
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
		return apply_filters( 'bp_rest_attachments_avatar_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Upload a member avatar.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {

		// Get the image file via $_FILES.
		$files = $request->get_file_params();

		if ( empty( $files ) ) {
			return new WP_Error( 'bp_rest_attachments_avatar_no_image_file',
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
		 * Fires after a member avatar is uploaded via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass          $avatar   Avatar object.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_avatar_create_item', $avatar, $response, $request );

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
		return apply_filters( 'bp_rest_attachments_avatar_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete an existing avatar of a member.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$deleted = bp_core_delete_existing_avatar( array(
			'object'  => 'user',
			'item_id' => (int) $request['user_id'],
		) );

		if ( ! $deleted ) {
			return new WP_Error( 'bp_rest_attachments_avatar_delete_failed',
				__( 'Sorry, there was a problem deleting the avatar.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$avatar = bp_core_fetch_avatar(
			array(
				'object'  => 'user',
				'item_id' => $request['user_id'],
				'html'    => false,
				'type'    => 'full',
			)
		);

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
		 * @param string            $avatar   Gravatar url.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_avatar_delete_item', $avatar, $response, $request );

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
		return apply_filters( 'bp_rest_attachments_avatar_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares avatar data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass|string $avatar  Avatar object or string with url or image with html.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $avatar, $request ) {
		if ( is_string( $avatar ) ) {
			$data = array(
				'image' => $avatar,
			);
		} else {
			$data = array(
				'full'  => $avatar->full,
				'thumb' => $avatar->thumb,
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
		return apply_filters( 'bp_rest_attachments_avatar_prepare_value', $response, $request );
	}

	/**
	 * Avatar Upload from File.
	 *
	 * @since 0.1.0
	 *
	 * @param array           $files     $_FILES superglobal.
	 * @param WP_REST_Request $request  Full details about the request.
	 * @return stdClass|WP_Error
	 */
	protected function upload_avatar_from_file( $files, $request ) {

		// Setup some variables.
		$bp                     = buddypress();
		$bp->displayed_user     = new stdClass();
		$bp->displayed_user->id = (int) $request['user_id'];
		$user_id                = $bp->displayed_user->id;
		$avatar_attachment      = $this->avatar_attachment_instance();

		// Needed to avoid 'Invalid form submission' error.
		$_POST['action'] = $avatar_attachment->action;
		$avatar_original = $avatar_attachment->upload( $files, 'xprofile_avatar_upload_dir' );

		// Bail early in case of an error.
		if ( ! empty( $avatar_original['error'] ) ) {
			return new WP_Error( 'bp_rest_attachments_avatar_upload_error',
				sprintf( __( 'Upload failed! Error was: %s.', 'buddypress' ), $avatar_original['error'] ),
				array(
					'status' => 500,
				)
			);
		}

		// Get image and bail early if there is an error.
		$image_file = $this->resize( $avatar_original['file'] );
		if ( is_wp_error( $image_file ) ) {
			return $image_file;
		}

		// If the uploaded image is smaller than the "full" dimensions, throw a warning.
		if ( $avatar_attachment->is_too_small( $image_file ) ) {
			return new WP_Error( 'bp_rest_attachments_avatar_error',
				sprintf(
					__( 'You have selected an image that is smaller than recommended. For best results, upload a picture larger than %d x %d pixels.', 'buddypress' ),
					bp_core_avatar_full_width(),
					bp_core_avatar_full_height()
				),
				array(
					'status' => 500,
				)
			);
		}

		// Delete existing image if one exists.
		$this->delete_existing_image( $user_id );

		// Crop the profile photo accordingly and bail early in case of an error.
		$cropped = $this->crop_image( $image_file, $user_id );
		if ( is_wp_error( $cropped ) ) {
			return $cropped;
		}

		// Build response object.
		$avatar_object = new stdClass();
		foreach ( [ 'full', 'thumb' ] as $key_type ) {

			// Update path with an url.
			$url = str_replace( bp_core_avatar_upload_path(), '', $cropped[ $key_type ] );

			// Set image url to its size/type.
			$avatar_object->{$key_type} = bp_core_avatar_url() . $url;
		}

		@unlink( $avatar_original['file'] );

		return $avatar_object;
	}

	/**
	 * Resize image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $file Image to resize.
	 * @return string|WP_Error
	 */
	protected function resize( $file ) {
		$bp          = buddypress();
		$upload_path = bp_core_avatar_upload_path();

		if ( ! isset( $bp->avatar_admin ) ) {
			$bp->avatar_admin = new stdClass();
		}

		// The Avatar UI available width.
		$ui_available_width = 0;

		// Try to set the ui_available_width using the avatar_admin global.
		if ( isset( $bp->avatar_admin->ui_available_width ) ) {
			$ui_available_width = $bp->avatar_admin->ui_available_width;
		}

		$resized = $this->avatar_attachment_instance()->shrink( $file, $ui_available_width );

		// We only want to handle one image after resize.
		if ( empty( $resized ) ) {
			$image_file = $file;
			$img_dir    = str_replace( $upload_path, '', $file );
		} else {
			$image_file = $resized['path'];
			$img_dir    = str_replace( $upload_path, '', $resized['path'] );
			@unlink( $file );
		}

		// Check for WP_Error on what should be an image.
		if ( is_wp_error( $img_dir ) ) {
			return new WP_Error( 'bp_rest_attachments_avatar_upload_error',
				sprintf( __( 'Upload failed! Error was: %s', 'buddypress' ), $img_dir->get_error_message() ),
				array(
					'status' => 500,
				)
			);
		}

		return $image_file;
	}

	/**
	 * Crop image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $image_file Image to crop.
	 * @param int    $user_id   User ID.
	 * @return array|WP_Error
	 */
	protected function crop_image( $image_file, $user_id ) {
		$image          = getimagesize( $image_file );
		$avatar_to_crop = str_replace( bp_core_avatar_upload_path(), '', $image_file );

		// Get avatar full width and height.
		$full_height = bp_core_avatar_full_height();
		$full_width  = bp_core_avatar_full_width();

		// Use as much as possible of the image.
		$avatar_ratio = $full_width / $full_height;
		$image_ratio  = $image[0] / $image[1];

		if ( $image_ratio >= $avatar_ratio ) {
			// Uploaded image is wider than BP ratio, so we crop horizontally.
			$crop_y = 0;
			$crop_h = $image[1];

			// Get the target width by multiplying unmodified image height by target ratio.
			$crop_w    = $avatar_ratio * $image[1];
			$padding_w = round( ( $image[0] - $crop_w ) / 2 );
			$crop_x    = $padding_w;
		} else {
			// Uploaded image is narrower than BP ratio, so we crop vertically.
			$crop_x = 0;
			$crop_w = $image[0];

			// Get the target height by multiplying unmodified image width by target ratio.
			$crop_h    = $avatar_ratio * $image[0];
			$padding_h = round( ( $image[1] - $crop_h ) / 2 );
			$crop_y    = $padding_h;
		}

		add_filter( 'bp_attachments_current_user_can', '__return_true' );

		// Crop the image.
		$cropped = $this->avatar_attachment_instance()->crop(
			array(
				'object'        => 'user',
				'avatar_dir'    => 'avatars',
				'item_id'       => $user_id,
				'original_file' => $avatar_to_crop,
				'crop_w'        => $crop_w,
				'crop_h'        => $crop_h,
				'crop_x'        => $crop_x,
				'crop_y'        => $crop_y,
			)
		);

		remove_filter( 'bp_attachments_current_user_can', '__return_false' );

		// Check for errors.
		if ( empty( $cropped['full'] ) || empty( $cropped['thumb'] ) || is_wp_error( $cropped['full'] ) || is_wp_error( $cropped['thumb'] ) ) {
			return new WP_Error( 'bp_rest_attachments_avatar_crop_error',
				sprintf( __( 'There was a problem cropping your profile photo.', 'buddypress' ) ),
				array(
					'status' => 500,
				)
			);
		}

		return $cropped;
	}

	/**
	 * Delete user's existing avatar if one exists.
	 *
	 * @since 0.1.0
	 *
	 * @param int $user_id  User object.
	 * @return void
	 */
	protected function delete_existing_image( $user_id ) {
		$object = 'user';

		// Get existing avatar.
		$existing_avatar = bp_core_fetch_avatar(
			array(
				'object'  => $object,
				'item_id' => $user_id,
				'html'    => false,
			)
		);

		// Check if the avatar exists before deleting.
		if ( ! empty( $existing_avatar ) ) {
			bp_core_delete_existing_avatar(
				array(
					'object'  => $object,
					'item_id' => $user_id,
				)
			);
		}
	}

	/**
	 * Return an instance of the BP_Attachment_Avatar class.
	 *
	 * @since 0.1.0
	 *
	 * @return BP_Attachment_Avatar
	 */
	protected function avatar_attachment_instance() {
		return new BP_Attachment_Avatar();
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
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['alt'] = array(
			'description'       => __( 'The alt attribute for the <img> element.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['no_grav'] = array(
			'description'       => __( 'Whether to disable the default Gravatar fallback.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
