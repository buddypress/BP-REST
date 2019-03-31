<?php
/**
 * BuddyPress Members Endpoints Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group members
 */
class BP_Test_REST_Members_Endpoint extends WP_Test_REST_Controller_Testcase {

	protected static $user;
	protected static $site;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$user = $factory->user->create( array(
			'role'          => 'administrator',
			'user_login'    => 'administrator',
			'user_nicename' => 'administrator',
			'user_email'    => 'admin@example.com',
		) );

		if ( is_multisite() ) {
			self::$site = $factory->blog->create( array(
				'domain' => 'rest.wordpress.org',
				'path'   => '/',
			) );

			update_site_option( 'site_admins', array( 'superadmin' ) );
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user );

		if ( is_multisite() ) {
			wpmu_delete_blog( self::$site, true );
		}
	}

	public function setUp() {
		parent::setUp();

		buddypress()->members->types = array();

		$this->endpoint     = new BP_REST_Members_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/members';

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
		$this->assertArrayHasKey( $this->endpoint_url . '/me', $routes );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'user_ids' => [ $u1, $u2, $u3 ],
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->assertTrue( 3 === count( $all_data ) );

		foreach ( $all_data as $data ) {
			$this->check_user_data( get_userdata( $data['id'] ), $data, 'view', $data['_links'] );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_paginated_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$u4 = $this->factory->user->create();

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'user_ids' => [ $u1, $u2, $u3, $u4 ],
			'page'     => 2,
			'per_page' => 2,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertEquals( 4, $headers['X-WP-Total'] );
		$this->assertEquals( 2, $headers['X-WP-TotalPages'] );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$this->check_user_data( get_userdata( $data['id'] ), $data, 'view', $data['_links'] );
		}
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$u = $this->factory->user->create();

		// Register and set member types.
		bp_register_member_type( 'foo' );
		bp_register_member_type( 'bar' );
		bp_set_member_type( $u, 'foo' );
		bp_set_member_type( $u, 'bar', true );

		$u_2 = $this->factory->user->create();
		$this->bp->set_current_user( $u_2 );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $u ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$this->check_get_user_response( $response );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_id() {
		$this->bp->set_current_user( self::$user );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$this->allow_user_to_manage_multisite();

		$params = array(
			'password'   => 'testpassword',
			'email'      => 'test@example.com',
			'user_login' => 'testuser',
			'name'       => 'Test User',
		);

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();

		$this->assertEquals( 'Test User', $data['name'] );
		$this->check_add_edit_user_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_without_permission() {
		$u = $this->factory->user->create();
		$this->bp->set_current_user( $u );

		$params = array(
			'password'   => 'testpassword',
			'email'      => 'test@example.com',
			'user_login' => 'testuser',
			'name'       => 'Test User',
		);

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$u = $this->factory->user->create( array(
			'email' => 'test@example.com',
			'name'  => 'User Name',
		) );

		$this->allow_user_to_manage_multisite();

		$userdata  = get_userdata( $u );
		$pw_before = $userdata->user_pass;

		$_POST['email'] = 'new@example.com';
		$_POST['name']  = 'New User Name';

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $u ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $_POST );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_user_response( $response, true );

		$new_data = $response->get_data();
		$this->assertNotEmpty( $new_data );

		$this->assertEquals( $pw_before, $userdata->user_pass );
		$this->assertEquals( 'new@example.com', $new_data['email'] );
		$this->assertEquals( 'New User Name', $new_data['name'] );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_not_logged_in() {
		$u = $this->factory->user->create();

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $u ) );
		$request->set_param( 'username', 'test_json_user' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_id() {
		$this->bp->set_current_user( self::$user );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( array(
			'id'       => '156',
			'username' => 'test_user',
			'password' => 'reallysimplepassword',
			'email'    => 'reallydumbguy@example.com',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_without_permission() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$this->bp->set_current_user( $u1 );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $u2 ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $_POST );

		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$u = $this->factory->user->create( array( 'display_name' => 'Deleted User' ) );

		$this->allow_user_to_manage_multisite();

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $u ) );
		$request->set_param( 'force', true );
		$request->set_param( 'reassign', false );
		$response = $this->server->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'bp_rest_authorization_required_code', $response, 501 );
			return;
		}

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$this->assertTrue( $data['deleted'] );
		$this->assertEquals( 'Deleted User', $data['previous']['name'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_id() {
		$this->bp->set_current_user( self::$user );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$request->set_param( 'force', true );
		$request->set_param( 'reassign', false );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$u = $this->factory->user->create();

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $u ) );
		$request->set_param( 'force', true );
		$request->set_param( 'reassign', false );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_without_permission() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$this->bp->set_current_user( $u1 );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $u2 ) );
		$request->set_param( 'force', true );
		$request->set_param( 'reassign', false );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	public function test_prepare_item() {
		$this->bp->set_current_user( self::$user );

		$request = new WP_REST_Request();
		$request->set_param( 'context', 'view' );
		$user = get_user_by( 'id', get_current_user_id() );
		$data = $this->endpoint->prepare_item_for_response( $user, $request );

		$this->check_get_user_response( $data, 'view' );
	}

	protected function check_get_user_response( $response, $context = 'view' ) {
		$data = $response->get_data();
		$user = get_userdata( $data['id'] );

		$this->check_user_data( $user, $data, $context, $response->get_links() );
	}

	protected function check_add_edit_user_response( $response, $update = false ) {
		if ( $update ) {
			$this->assertEquals( 200, $response->get_status() );
		} else {
			$this->assertEquals( 201, $response->get_status() );
		}

		$data = $response->get_data();
		$this->check_user_data( get_userdata( $data['id'] ), $data, 'edit', $response->get_links() );
	}

	protected function check_user_data( $user, $data, $context, $links ) {
		$this->assertEquals( $user->ID, $data['id'] );
		$this->assertEquals( $user->display_name, $data['name'] );
		$this->assertEquals( $user->user_email, $data['email'] );
		$this->assertEquals( $user->user_login, $data['user_login'] );
		$this->assertArrayHasKey( 'avatar_urls', $data );
		$this->assertArrayHasKey( 'thumb', $data['avatar_urls'] );
		$this->assertArrayHasKey( 'full', $data['avatar_urls'] );
		$this->assertArrayHasKey( 'member_types', $data );
		$this->assertArrayHasKey( 'xprofile', $data );
		$this->assertEquals(
			bp_core_get_user_domain( $data['id'], $user->user_nicename, $user->user_login ),
			$data['link']
		);

		if ( 'edit' === $context ) {
			$this->assertEquals( (object) $user->allcaps, $data['capabilities'] );
			$this->assertEquals( (object) $user->caps, $data['extra_capabilities'] );
			$this->assertEquals( $user->roles, $data['roles'] );
			$this->assertEquals( bp_rest_prepare_date_response( $user->user_registered ), $data['registered_date'] );
		} else {
			$this->assertArrayNotHasKey( 'roles', $data );
			$this->assertArrayNotHasKey( 'capabilities', $data );
			$this->assertArrayNotHasKey( 'extra_capabilities', $data );
			$this->assertArrayNotHasKey( 'registered_date', $data );
		}
	}

	protected function allow_user_to_manage_multisite() {
		$this->bp->set_current_user( self::$user );

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( wp_get_current_user()->user_login ) );
		}
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 14, count( $properties ) );
		$this->assertArrayHasKey( 'avatar_urls', $properties );
		$this->assertArrayHasKey( 'email', $properties );
		$this->assertArrayHasKey( 'capabilities', $properties );
		$this->assertArrayHasKey( 'extra_capabilities', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'mention_name', $properties );
		$this->assertArrayHasKey( 'registered_date', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'roles', $properties );
		$this->assertArrayHasKey( 'xprofile', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', self::$user ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
