<?php
/**
 * BP REST: Attachments Trait
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attachments Trait
 *
 * @since 0.1.0
 */
trait BP_REST_Attachments {

	/**
	 * Avatar upload from File.
	 *
	 * @since 0.1.0
	 *
	 * @param array $files $_FILES superglobal.
	 * @return stdClass|WP_Error
	 */
	protected function upload_avatar_from_file( $files ) {
		$bp = buddypress();

		// Set global variables.
		if ( 'group' === $this->object ) {
			$bp->groups->current_group = $this->group;
			$upload_main_dir           = 'groups_avatar_upload_dir';
		} else {
			$upload_main_dir        = 'xprofile_avatar_upload_dir';
			$bp->displayed_user     = new stdClass();
			$bp->displayed_user->id = (int) $this->user->ID;
		}

		$avatar_attachment = $this->avatar_instance;

		// Needed to avoid 'Invalid form submission' error.
		$_POST['action'] = $avatar_attachment->action;
		$avatar_original = $avatar_attachment->upload( $files, $upload_main_dir );

		// Bail early in case of an error.
		if ( ! empty( $avatar_original['error'] ) ) {
			return new WP_Error(
				"bp_rest_attachments_{$this->object}_avatar_upload_error",
				sprintf(
					/* translators: %s is replaced with the error */
					__( 'Upload failed! Error was: %s.', 'buddypress' ),
					$avatar_original['error']
				),
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
			return new WP_Error(
				"bp_rest_attachments_{$this->object}_avatar_error",
				sprintf(
					/* translators: %$1s and %$2s is replaced with the correct sizes. */
					__( 'You have selected an image that is smaller than recommended. For best results, upload a picture larger than %$1s x %$2s pixels.', 'buddypress' ),
					bp_core_avatar_full_width(),
					bp_core_avatar_full_height()
				),
				array(
					'status' => 500,
				)
			);
		}

		// Delete existing image if one exists.
		$this->delete_existing_image();

		// Crop the profile photo accordingly and bail early in case of an error.
		$cropped = $this->crop_image( $image_file );
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

		unlink( $avatar_original['file'] );

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

		$resized = $this->avatar_instance->shrink( $file, $ui_available_width );

		// We only want to handle one image after resize.
		if ( empty( $resized ) ) {
			$image_file = $file;
			$img_dir    = str_replace( $upload_path, '', $file );
		} else {
			$image_file = $resized['path'];
			$img_dir    = str_replace( $upload_path, '', $resized['path'] );
			unlink( $file );
		}

		// Check for WP_Error on what should be an image.
		if ( is_wp_error( $img_dir ) ) {
			return new WP_Error(
				"bp_rest_attachments_{$this->object}_avatar_upload_error",
				sprintf(
					/* translators: %$1s is replaced with error message. */
					__( 'Upload failed! Error was: %$1s', 'buddypress' ),
					$img_dir->get_error_message()
				),
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
	 * @return array|WP_Error
	 */
	protected function crop_image( $image_file ) {
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
		$cropped = $this->avatar_instance->crop(
			array(
				'object'        => $this->object,
				'avatar_dir'    => ( 'group' === $this->object ) ? 'group-avatars' : 'avatars',
				'item_id'       => $this->get_item_id(),
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
			return new WP_Error(
				"bp_rest_attachments_{$this->object}_avatar_crop_error",
				sprintf(
					/* translators: %$1s is replaced with object type. */
					__( 'There was a problem cropping your %s photo.', 'buddypress' ),
					$this->object
				),
				array(
					'status' => 500,
				)
			);
		}

		return $cropped;
	}

	/**
	 * Delete group's existing avatar if one exists.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function delete_existing_image() {
		// Get existing avatar.
		$existing_avatar = bp_core_fetch_avatar(
			array(
				'object'  => $this->object,
				'item_id' => $this->get_item_id(),
				'html'    => false,
			)
		);

		// Check if the avatar exists before deleting.
		if ( ! empty( $existing_avatar ) ) {
			bp_core_delete_existing_avatar(
				array(
					'object'  => $this->object,
					'item_id' => $this->get_item_id(),
				)
			);
		}
	}

	/**
	 * Get item id.
	 *
	 * @return int
	 */
	protected function get_item_id() {
		return ( 'group' === $this->object ) ? $this->group->id : $this->user->ID;
	}
}
