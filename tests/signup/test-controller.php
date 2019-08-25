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
			)
		);

		$signup = BP_Signup::add(
			array(
				'domain'         => 'foo',
				'path'           => 'bar',
				'title'          => 'Foo bar',
				'user_login'     => 'user1',
				'user_email'     => 'user1@example.com',
				'registered'     => bp_core_current_time(),
				'activation_key' => '12345',
				'meta'           => array(
					'field_1' => 'Foo Bar',
					'meta1'   => 'meta2',
				),
			)
		);

		$s               = new BP_Signup( $signup );
		$this->signup_id = $s->id;

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

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		return true;
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$this->bp->set_current_user( $this->user );

		$signup = $this->endpoint->get_signup_object( $this->signup_id );
		$this->assertEquals( $this->signup_id, $signup->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_signup_data( $signup, $all_data[0], 'view' );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_group_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
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

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		return true;
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		return true;
	}

	/**
	 * @group test_delete_item
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
		$this->check_signup_data( $signup, $deleted['previous'], 'edit' );
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
		return true;
	}

	protected function check_signup_data( $signup, $data, $context = 'view' ) {
		$this->assertEquals( $signup->id, $data['id'] );
		$this->assertEquals( $signup->user_login, $data['user_login'] );
		$this->assertEquals( $signup->user_name, $data['user_name'] );
		$this->assertEquals( bp_rest_prepare_date_response( $signup->registered ), $data['registered'] );

		if ( 'edit' === $context ) {
			$this->assertEquals( $signup->activation_key, $data['activation_key'] );
			$this->assertEquals( $signup->user_email, $data['user_email'] );
		}
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
		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->signup_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
	}
}
