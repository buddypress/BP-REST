<?php
/**
 * Signup Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group signup
 */
class BP_Test_REST_Signup_Endpoint extends WP_Test_REST_Controller_Testcase {

	/**
	 * Signup allowed.
	 *
	 * @var bool
	 */
	protected $signup_allowed;

	public function setUp() {

		if ( is_multisite() ) {
			$this->signup_allowed = get_site_option( 'registration' );
			update_site_option( 'registration', 'all' );
		} else {
			bp_get_option( 'users_can_register' );
			bp_update_option( 'users_can_register', 1 );
		}

		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Signup_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/signup';
		$this->user         = $this->factory->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin@example.com',
				'user_login' => 'admin_user',
			)
		);

		$this->signup_id = $this->create_signup();

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function tearDown() {
		if ( is_multisite() ) {
			update_site_option( 'registration', $this->signup_allowed );
		} else {
			bp_update_option( 'users_can_register', $this->signup_allowed );
		}

		parent::tearDown();
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\w-]+)', $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url . '/(?P<id>[\w-]+)' ] );
		$this->assertCount( 1, $routes[ $this->endpoint_url . '/activate/(?P<id>[\w-]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$this->bp->set_current_user( $this->user );

		$s1     = $this->create_signup();
		$signup = $this->endpoint->get_signup_object( $s1 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$request->set_query_params(
			array(
				'include' => $s1,
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->check_signup_data( $signup, $all_data[0] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_paginated_items() {
		$this->bp->set_current_user( $this->user );

		$s1 = $this->create_signup();
		$s2 = $this->create_signup();
		$s3 = $this->create_signup();
		$s4 = $this->create_signup();

		$signup = $this->endpoint->get_signup_object( $s1 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params(
			array(
				'per_page' => 2,
				'include'  => array( $s1, $s2, $s3, $s4 ),
			)
		);

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertEquals( 4, $headers['X-WP-Total'] );
		$this->assertEquals( 2, $headers['X-WP-TotalPages'] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_not_logged_in() {
		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_unauthorized_user() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$this->bp->set_current_user( $this->user );

		$signup = $this->endpoint->get_signup_object( $this->signup_id );
		$this->assertEquals( $this->signup_id, $signup->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%s', $this->signup_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_signup_data( $signup, $all_data[0] );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_signup_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%s', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%s', $this->signup_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_unauthorized_user() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%s', $this->signup_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_signup_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$signup = $response->get_data();

		$this->assertTrue( 'user1@example.com' === $signup[0]['user_email'] );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_signup_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_unauthorized_user() {
		$u = $this->factory->user->create();
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_signup_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 * @group activate_item
	 */
	public function test_update_item() {
		$this->bp->set_current_user( $this->user );

		$signup = $this->endpoint->get_signup_object( $this->signup_id );
		$this->assertEquals( $this->signup_id, $signup->id );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/activate/%d', $this->signup_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_signup_data( $signup, $all_data[0] );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_with_user_login() {
		$this->bp->set_current_user( $this->user );

		$signup = $this->endpoint->get_signup_object( $this->signup_id );
		$this->assertEquals( $this->signup_id, $signup->id );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/activate/%s', $signup->user_login ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_signup_data( $signup, $all_data[0], 'edit' );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_signup_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/activate/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$this->bp->set_current_user( $this->user );

		$signup = $this->endpoint->get_signup_object( $this->signup_id );
		$this->assertEquals( $this->signup_id, $signup->id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$deleted = $response->get_data();

		$this->assertTrue( $deleted['deleted'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_signup_id() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_unauthorized_user() {
		$u = $this->factory->user->create();
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	public function test_prepare_item() {
		$this->bp->set_current_user( $this->user );

		$signup = $this->endpoint->get_signup_object( $this->signup_id );
		$this->assertEquals( $this->signup_id, $signup->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_signup_data( $signup, $all_data[0] );
	}

	protected function set_signup_data( $args = array() ) {
		return wp_parse_args(
			$args,
			array(
				'user_login'     => 'admin_user',
				'user_email'     => 'user1@example.com',
				'title'          => 'Foo bar',
				'activation_key' => wp_generate_password( 12, false ),
			)
		);
	}

	protected function create_signup() {
		$signup = BP_Signup::add(
			array(
				'domain'         => 'foo',
				'path'           => 'bar',
				'title'          => 'Foo bar',
				'user_login'     => 'admin_user',
				'user_email'     => 'user1@example.com',
				'registered'     => bp_core_current_time(),
				'activation_key' => '12345',
				'meta'           => array(
					'field_1'  => 'Foo Bar',
					'meta1'    => 'meta2',
					'password' => wp_generate_password( 12, false ),
				),
			)
		);

		$s = new BP_Signup( $signup );

		return $s->id;
	}

	protected function check_signup_data( $signup, $data ) {
		$this->assertEquals( $signup->id, $data['id'] );
		$this->assertEquals( $signup->user_login, $data['user_login'] );
		$this->assertEquals( $signup->user_name, $data['user_name'] );
		$this->assertEquals( bp_rest_prepare_date_response( $signup->registered ), $data['registered'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 6, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'user_login', $properties );
		$this->assertArrayHasKey( 'user_name', $properties );
		$this->assertArrayHasKey( 'registered', $properties );
		$this->assertArrayHasKey( 'activation_key', $properties );
		$this->assertArrayHasKey( 'user_email', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
