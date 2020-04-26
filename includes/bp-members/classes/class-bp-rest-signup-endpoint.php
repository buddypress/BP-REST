<?php
/**
 * BP REST: BP_REST_Signup_Endpoint class
 *
 * @package BuddyPress
 * @since 6.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Signup endpoints.
 *
 * Use /signup
 * Use /signup/{id}
 * Use /signup/activate/{id}
 *
 * @since 6.0.0
 */
class BP_REST_Signup_Endpoint extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = 'signup';
	}

	/**
	 * Register the component routes.
	 *
	 * @since 6.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Identifier for the signup. Can be a signup ID, an email address, or an activation key.', 'buddypress' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param(
							array(
								'default' => 'view',
							)
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'edit' ) ),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		// Register the activate route.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/activate/(?P<activation_key>[\w-]+)',
			array(
				'args'   => array(
					'activation_key' => array(
						'description' => __( 'Activation key of the signup.', 'buddypress' ),
						'type'        => 'string',
						'required'    => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'activate_item' ),
					'permission_callback' => array( $this, 'activate_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'edit' ) ),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieve signups.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'include'    => $request['include'],
			'order'      => $request['order'],
			'orderby'    => $request['orderby'],
			'user_login' => $request['user_login'],
			'number'     => $request['number'],
			'offset'     => $request['offset'],
		);

		if ( empty( $request['include'] ) ) {
			$args['include'] = false;
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 6.0.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_signup_get_items_query_args', $args, $request );

		// Actually, query it.
		$signups = BP_Signup::get( $args );

		$retval = array();
		foreach ( $signups['signups'] as $signup ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $signup, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $signups['total'], $args['number'] );

		/**
		 * Fires after a list of signups is fetched via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param array            $signups   Fetched signups.
		 * @param WP_REST_Response $response  The response data.
		 * @param WP_REST_Request  $request   The request sent to the API.
		 */
		do_action( 'bp_rest_signup_get_items', $signups, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to signup items.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		$retval = true;

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( true === $retval && ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not authorized to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the signup `get_items` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_signup_get_items_permissions_check', $retval, $request );
	}

	/**
	 * Retrieve single signup.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		// Get signup.
		$signup = $this->get_signup_object( $request['id'] );

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $signup, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires before a signup is retrieved via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Signup         $signup    The signup object.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'bp_rest_signup_get_item', $signup, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to get a signup.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;
		$signup = $this->get_signup_object( $request['id'] );

		if ( ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( true === $retval && empty( $signup ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_id',
				__( 'Invalid signup id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( true === $retval && ! bp_current_user_can( 'bp_moderate' ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not authorized to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the signup `get_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_signup_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Create signup.
	 *
	 * @since 6.0.0
	 *
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$request->set_param( 'context', 'edit' );

		// Validate user signup.
		$signup_validation = bp_core_validate_user_signup( $request['user_login'], $request['user_email'] );
		if ( is_wp_error( $signup_validation['errors'] ) && $signup_validation['errors']->get_error_messages() ) {
			// Return the first error.
			return new WP_Error(
				'bp_rest_signup_validation_failed',
				$signup_validation['errors']->get_error_message(),
				array(
					'status' => 500,
				)
			);
		}

		// Use the validated login and email.
		$user_login = $signup_validation['user_name'];
		$user_email = $signup_validation['user_email'];

		// Init the signup meta.
		$meta = array();

		// Init Some Multisite specific variables.
		$domain     = '';
		$path       = '';
		$site_title = '';
		$site_name  = '';

		if ( is_multisite() ) {
			$user_login = preg_replace( '/\s+/', '', sanitize_user( $user_login, true ) );
			$user_email = sanitize_email( $user_email );
			$wp_key_suffix = $user_email;

			if ( $this->is_blog_signup_allowed() ) {
				$site_title = $request->get_param( 'site_title' );
				$site_name = $request->get_param( 'site_name' );

				if ( $site_title && $site_name ) {
					// Validate the blog signup.
					$blog_signup_validation = bp_core_validate_blog_signup( $site_name, $site_title );
					if ( is_wp_error( $blog_signup_validation['errors'] ) && $blog_signup_validation['errors']->get_error_messages() ) {
						// Return the first error.
						return new WP_Error(
							'bp_rest_blog_signup_validation_failed',
							$blog_signup_validation['errors']->get_error_message(),
							array(
								'status' => 500,
							)
						);
					}

					$domain        = $blog_signup_validation['domain'];
					$wp_key_suffix = $domain;
					$path          = $blog_signup_validation['path'];
					$site_title    = $blog_signup_validation['blog_title'];
					$site_public   = (bool) $request->get_param( 'site_public' );

					$meta = array(
						'lang_id' => 1,
						'public'  => $site_public ? 1 : 0,
					);

					$site_language = $request->get_param( 'site_language' );
					$languages     = $this->get_available_languages();

					if ( in_array( $site_language, $languages, true ) ) {
						$language = wp_unslash( sanitize_text_field( $site_language ) );

						if ( $language ) {
							$meta['WPLANG'] = $language;
						}
					}
				}
			}
		}

		$password       = $request->get_param( 'password' );
		$check_password = $this->check_user_password( $password );

		if ( is_wp_error( $check_password ) ) {
			return $check_password;
		}

		// Hash and store the password.
		$meta['password'] = wp_hash_password( $password );

		$user_name = $request->get_param( 'user_name' );
		if ( $user_name ) {
			$meta['field_1']           = $user_name;
			$meta['profile_field_ids'] = 1;
		}

		if ( is_multisite() ) {
			// On Multisite, use the WordPress way to generate the activation key.
			$activation_key = substr( md5( time() . wp_rand() . $wp_key_suffix ), 0, 16 );

			if ( $site_title && $site_name ) {
				/** This filter is documented in wp-includes/ms-functions.php */
				$meta = apply_filters( 'signup_site_meta', $meta, $domain, $path, $site_title, $user_login, $user_email, $activation_key );
			} else {
				/** This filter is documented in wp-includes/ms-functions.php */
				$meta = apply_filters( 'signup_user_meta', $meta, $user_login, $user_email, $activation_key );
			}
		} else {
			$activation_key = wp_generate_password( 32, false );
		}

		/**
		 * Allow plugins to add their signup meta specific to the BP REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param array           $meta    The signup meta.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$meta = apply_filters( 'bp_rest_signup_create_item_meta', $meta, $request );

		$signup_args = array(
			'user_login'     => $user_login,
			'user_email'     => $user_email,
			'activation_key' => $activation_key,
			'domain'         => $domain,
			'path'           => $path,
			'title'          => $site_title,
			'meta'           => $meta,
		);

		// Add signup.
		$id = \BP_Signup::add( $signup_args );

		if ( ! is_numeric( $id ) ) {
			return new WP_Error(
				'bp_rest_signup_cannot_create',
				__( 'Cannot create new signup.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$signup        = $this->get_signup_object( $id );
		$signup_update = $this->update_additional_fields_for_object( $signup, $request );

		if ( is_wp_error( $signup_update ) ) {
			return $signup_update;
		}

		if ( is_multisite() ) {
			if ( $site_title && $site_name ) {
				/** This action is documented in wp-includes/ms-functions.php */
				do_action( 'after_signup_site', $signup->domain, $signup->path, $signup->title, $signup->user_login, $signup->user_email, $signup->activation_key, $signup->meta );
			} else {
				/** This action is documented in wp-includes/ms-functions.php */
				do_action( 'after_signup_user', $signup->user_login, $signup->user_email, $signup->activation_key, $signup->meta );
			}
		} else {
			/** This filter is documented in bp-members/bp-members-functions.php */
			if ( apply_filters( 'bp_core_signup_send_activation_key', true, false, $signup->user_email, $signup->activation_key, $signup->meta ) ) {
				$salutation = $signup->user_login;
				if ( isset( $signup->user_name ) && $signup->user_name ) {
					$salutation = $signup->user_name;
				}

				bp_core_signup_send_validation_email( false, $signup->user_email, $signup->activation_key, $salutation );
			}
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $signup, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a signup item is created via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Signup        $signup   The created signup.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_signup_create_item', $signup, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to create a signup.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		// The purpose of a signup is to allow a new user to register to the site.
		$retval = true;

		/**
		 * Filter the signup `create_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_signup_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete a signup.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$request->set_param( 'context', 'edit' );

		// Get the signup before it's deleted.
		$signup   = $this->get_signup_object( $request['id'] );
		$previous = $this->prepare_item_for_response( $signup, $request );
		$deleted  = BP_Signup::delete( array( $signup->id ) );

		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_signup_cannot_delete',
				__( 'Could not delete signup.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Build the response.
		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $previous->get_data(),
			)
		);

		/**
		 * Fires after a signup is deleted via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Signup        $signup   The deleted signup.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_signup_delete_item', $signup, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to delete a signup.
	 *
	 * @since 6.0.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the signup `delete_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_signup_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Activate a signup.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate_item( $request ) {
		$request->set_param( 'context', 'edit' );

		// Get the activation key.
		$activation_key = $request->get_param( 'activation_key' );

		// Get the signup to activate thanks to the activation key.
		$signup    = $this->get_signup_object( $activation_key );
		$activated = bp_core_activate_signup( $activation_key );

		if ( ! $activated ) {
			return new WP_Error(
				'bp_rest_signup_activate_fail',
				__( 'Fail to activate the signup.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $signup, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a signup is activated via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param BP_Signup        $signup   The activated signup.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_signup_activate_item', $signup, $response, $request );

		return $response;
	}

	/**
	 * Check if a given request has access to activate a signup.
	 *
	 * @since 6.0.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function activate_item_permissions_check( $request ) {
		$retval = true;
		// Get the activation key.
		$activation_key = $request->get_param( 'activation_key' );

		// Get the signup thanks to the activation key.
		$signup = $this->get_signup_object( $activation_key );

		if ( empty( $signup ) ) {
			$retval = new WP_Error(
				'bp_rest_invalid_activation_key',
				__( 'Invalid activation key.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Filter the signup `activate_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_signup_activate_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares signup to return as an object.
	 *
	 * @since 6.0.0
	 *
	 * @param  BP_Signup       $signup  Signup object.
	 * @param  WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $signup, $request ) {
		$data = array(
			'id'         => $signup->id,
			'user_login' => $signup->user_login,
			'registered' => bp_rest_prepare_date_response( $signup->registered ),
		);

		// The user name is only available when the xProfile component is active.
		if ( isset( $signup->user_name ) ) {
			$data['user_name'] = $signup->user_name;
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		if ( 'edit' === $context ) {
			$data['activation_key'] = $signup->activation_key;
			$data['user_email']     = $signup->user_email;
			$data['date_sent']      = bp_rest_prepare_date_response( $signup->date_sent );
			$data['count_sent']     = (int) $signup->count_sent;

			if ( is_multisite() && $signup->domain && $signup->path && $signup->title ) {
				if ( is_subdomain_install() ) {
					$domain_parts = explode( '.', $signup->domain );
					$site_name    = reset( $domain_parts );
				} else {
					$domain_parts = explode( '/', $signup->path );
					$site_name    = end( $domain_parts );
				}

				$data['site_name']     = $site_name;
				$data['site_title']    = $signup->title;
				$data['site_public']   = isset( $signup->meta['public'] ) ? (bool) $signup->meta['public'] : true;
				$data['site_language'] = isset( $signup->meta['WPLANG'] ) ? $signup->meta['WPLANG'] : get_locale();
			}

			// Remove the password from meta.
			if ( isset( $signup->meta['password'] ) ) {
				unset( $signup->meta['password'] );
			}

			$data['meta'] = $signup->meta;
		}

		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		/**
		 * Filter the signup response returned from the API.
		 *
		 * @since 6.0.0
		 *
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 * @param BP_Signup         $signup   Signup object.
		 */
		return apply_filters( 'bp_rest_signup_prepare_value', $response, $request, $signup );
	}

	/**
	 * Get signup object.
	 *
	 * @since 6.0.0
	 *
	 * @param int $identifier Signup identifier.
	 * @return BP_Signup|bool
	 */
	public function get_signup_object( $identifier ) {
		if ( is_numeric( $identifier ) ) {
			$signup_args['include'] = array( intval( $identifier ) );
		} elseif ( is_email( $identifier ) ) {
			$signup_args['usersearch'] = $identifier;
		} else {
			// The activation key is used when activating a signup.
			$signup_args['activation_key'] = $identifier;
		}

		// Get signups.
		$signups = \BP_Signup::get( $signup_args );

		if ( ! empty( $signups['signups'] ) ) {
			return reset( $signups['signups'] );
		}

		return false;
	}

	/**
	 * Check a user password for the REST API.
	 *
	 * @since 6.0.0
	 *
	 * @param string $value The password submitted in the request.
	 * @return string|WP_Error The sanitized password, if valid, otherwise an error.
	 */
	public function check_user_password( $value ) {
		$password = (string) $value;

		if ( empty( $password ) || false !== strpos( $password, '\\' ) ) {
			return new WP_Error(
				'rest_user_invalid_password',
				__( 'Passwords cannot be empty or contain the "\\" character.', 'buddypress' ),
				array( 'status' => 400 )
			);
		}

		return $password;
	}

	/**
	 * Is it possible to signup with a blog?
	 *
	 * @since 6.0.0
	 *
	 * @return bool True if blog signup is allowed. False otherwise.
	 */
	public function is_blog_signup_allowed() {
		$active_signup = get_network_option( get_main_network_id(), 'registration' );

		return 'blog' === $active_signup || 'all' === $active_signup;
	}

	/**
	 * Get site's available locales.
	 *
	 * @since 6.0.0
	 *
	 * @return array The list of available locales.
	 */
	public function get_available_languages() {
		/** This filter is documented in wp-signup.php */
		$languages = (array) apply_filters( 'signup_get_available_languages', get_available_languages() );
		return array_intersect_assoc( $languages, get_available_languages() );
	}

	/**
	 * Edit the type of the some properties for the CREATABLE & EDITABLE methods.
	 *
	 * @since 6.0.0
	 *
	 * @param string $method Optional. HTTP method of the request.
	 * @return array Endpoint arguments.
	 */
	public function get_endpoint_args_for_item_schema( $method = WP_REST_Server::CREATABLE ) {
		$args = WP_REST_Controller::get_endpoint_args_for_item_schema( $method );
		$key  = 'get_item';

		if ( WP_REST_Server::CREATABLE === $method ) {
			$key = 'create_item';

			// The password is required when creating a signup.
			$args['password'] = array(
				'description' => __( 'Password for the new user (never included).', 'buddypress' ),
				'type'        => 'string',
				'context'     => array(), // Password is never displayed.
				'required'    => true,
			);

			/**
			 * We do not need the meta for the create item method
			 * as we are building it inside this method.
			 */
			unset( $args['meta'] );
		} elseif ( WP_REST_Server::EDITABLE === $method ) {
			$key = 'update_item';
		} elseif ( WP_REST_Server::DELETABLE === $method ) {
			$key = 'delete_item';
		}

		/**
		 * Filters the method query arguments.
		 *
		 * @since 6.0.0
		 *
		 * @param array  $args   Query arguments.
		 * @param string $method HTTP method of the request.
		 */
		return apply_filters( "bp_rest_signup_{$key}_query_arguments", $args, $method );
	}

	/**
	 * Get the signup schema, conforming to JSON Schema.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_signup',
			'type'       => 'object',
			'properties' => array(
				'id'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique numeric ID for the signup.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),
				'user_login'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The username of the user the signup is for.', 'buddypress' ),
					'required'    => true,
					'type'        => 'string',
				),
				'user_email'     => array(
					'context'     => array( 'edit' ),
					'description' => __( 'The email for the user the signup is for.', 'buddypress' ),
					'type'        => 'string',
					'required'    => true,
				),
				'activation_key' => array(
					'context'     => array( 'edit' ),
					'description' => __( 'Activation key of the signup.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
				),
				'registered'     => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The registered date for the user, in the site\'s timezone.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
					'format'      => 'date-time',
				),
				'date_sent'      => array(
					'context'     => array( 'edit' ),
					'description' => __( 'The date the activation email was sent to the user, in the site\'s timezone.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
					'format'      => 'date-time',
				),
				'count_sent'     => array(
					'description' => __( 'The number of times the activation email was sent to the user.', 'buddypress' ),
					'type'        => 'integer',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),
				'meta'           => array(
					'context'     => array( 'edit' ),
					'description' => __( 'The signup meta information', 'buddypress' ),
					'type'        => 'object',
					'properties'  => array(
						'password' => array(
							'description' => __( 'Password for the new user (never included).', 'buddypress' ),
							'type'        => 'string',
							'context'     => array(), // Password is never displayed.
						),
					),
				),
			),
		);

		if ( bp_is_active( 'xprofile' ) ) {
			$schema['properties']['user_name'] = array(
				'context'     => array( 'view', 'edit' ),
				'description' => __( 'The new user\'s full name.', 'buddypress' ),
				'type'        => 'string',
				'required'    => true,
				'arg_options' => array(
					'sanitize_callback' => 'sanitize_text_field',
				),
			);
		}

		if ( is_multisite() && $this->is_blog_signup_allowed() ) {
			$schema['properties']['site_name'] = array(
				'context'     => array( 'edit' ),
				'description' => __( 'Unique site name (slug) of the new user\'s child site.', 'buddypress' ),
				'type'        => 'string',
				'default'     => '',
			);

			$schema['properties']['site_title'] = array(
				'context'     => array( 'edit' ),
				'description' => __( 'Title of the new user\'s child site.', 'buddypress' ),
				'type'        => 'string',
				'default'     => '',
			);

			$schema['properties']['site_public'] = array(
				'context'     => array( 'edit' ),
				'description' => __( 'Search engine visibility of the new user\'s  site.', 'buddypress' ),
				'type'        => 'boolean',
				'default'     => true,
			);

			$schema['properties']['site_language'] = array(
				'context'     => array( 'edit' ),
				'description' => __( 'Language to use for the new user\'s  site.', 'buddypress' ),
				'type'        => 'string',
				'default'     => get_locale(),
				'enum'        => array_merge( array( get_locale() ), $this->get_available_languages() ),
			);
		}

		/**
		 * Filters the signup schema.
		 *
		 * @param array $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_signup_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		unset( $params['page'], $params['per_page'], $params['search'] );

		$params['number'] = array(
			'description'       => __( 'Total number of signups to return.', 'buddypress' ),
			'default'           => 1,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['offset'] = array(
			'description'       => __( 'Offset the result set by a specific number of items.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
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

		$params['orderby'] = array(
			'description'       => __( 'Order by a specific parameter (default: signup_id).', 'buddypress' ),
			'default'           => 'signup_id',
			'type'              => 'string',
			'enum'              => array( 'signup_id', 'login', 'email', 'registered', 'activated' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddypress' ),
			'default'           => 'desc',
			'type'              => 'string',
			'enum'              => array( 'asc', 'desc' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_login'] = array(
			'description'       => __( 'Specific user login to return.', 'buddypress' ),
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
		return apply_filters( 'bp_rest_signup_collection_params', $params );
	}
}
