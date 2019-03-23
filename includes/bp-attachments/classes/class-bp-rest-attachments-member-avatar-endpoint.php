<?php
/**
 * BP REST: BP_REST_Attachments_Member_Avatar_Endpoint class
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
class BP_REST_Attachments_Member_Avatar_Endpoint extends WP_REST_Controller {

	use BP_REST_Attachments;

	/**
	 * BP_Attachment_Avatar Instance.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_Attachment_Avatar
	 */
	protected $avatar_instance;

	/**
	 * Member object type.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $object = 'user';

	/**
	 * Member object.
	 *
	 * @since 0.1.0
	 *
	 * @var WP_User
	 */
	protected $user;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace       = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base       = 'members';
		$this->avatar_instance = new BP_Attachment_Avatar();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user_id>[\d]+)/avatar',
			array(
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
			)
		);
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
		$avatar = bp_core_fetch_avatar(
			array(
				'object'  => $this->object,
				'type'    => $request['type'],
				'item_id' => (int) $this->user->ID,
				'html'    => (bool) $request['html'],
				'alt'     => $request['alt'],
				'no_grav' => (bool) $request['no_gravatar'],
			)
		);

		if ( ! $avatar ) {
			return new WP_Error(
				'bp_rest_attachments_avatar_no_image',
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
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to access this member avatar.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		$this->user = bp_rest_get_user( $request['user_id'] );

		if ( true === $retval && ! $this->user instanceof WP_User ) {
			$retval = new WP_Error(
				'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			if ( true === $retval && bp_loggedin_user_id() !== $this->user->ID ) {
				$retval = new WP_Error(
					'bp_rest_authorization_required',
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
			return new WP_Error(
				'bp_rest_attachments_avatar_no_image_file',
				__( 'Sorry, you need an image file to upload.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

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

		if ( true === $retval && ( bp_disable_avatar_uploads() || ! buddypress()->avatar->show_avatars ) ) {
			$retval = new WP_Error(
				'bp_rest_attachments_member_avatar_disabled',
				__( 'Sorry, member avatar upload is disabled.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

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
		$deleted = bp_core_delete_existing_avatar(
			array(
				'object'  => $this->object,
				'item_id' => (int) $this->user->ID,
			)
		);

		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_attachments_avatar_delete_failed',
				__( 'Sorry, there was a problem deleting the avatar.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$avatar = bp_core_fetch_avatar(
			array(
				'object'  => $this->object,
				'item_id' => $this->user->ID,
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
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => esc_html__( 'Member Avatar', 'buddypress' ),
			'type'       => 'object',
			'properties' => array(
				'full'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Full size of the image file.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'thumb' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Thumb size of the image file.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
				),
			),
		);

		/**
		 * Filters the member avatar schema.
		 *
		 * @param string $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_member_avatar_schema', $schema );
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
