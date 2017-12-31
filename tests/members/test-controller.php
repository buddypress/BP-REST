<?php
/**
 * Members Test.
 *
 * @package BP_REST
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
			self::$site = $factory->blog->create( array( 'domain' => 'rest.wordpress.org', 'path' => '/' ) );
			update_site_option( 'site_admins', array( 'superadmin' ) );
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user );

		if ( is_multisite() ) {
			wpmu_delete_blog( self::$site, true );
		}
	}

	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		buddypress()->members->types = array();

		$this->endpoint = new BP_REST_Members_Endpoint();
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/buddypress/v1/members', $routes );
		$this->assertCount( 2, $routes['/buddypress/v1/members'] );
		$this->assertArrayHasKey( '/buddypress/v1/members/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/buddypress/v1/members/(?P<id>[\d]+)'] );
		$this->assertArrayHasKey( '/buddypress/v1/members/me', $routes );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', '/buddypress/v1/members' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', '/buddypress/v1/members/' . self::$user );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request( 'GET', '/buddypress/v1/members' );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data     = $all_data[0];
		$userdata = get_userdata( $data['id'] );

		$this->check_user_data( $userdata, $data, 'view', $data['_links'] );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$user_id = $this->factory->user->create();

		// Register and set member types.
		bp_register_member_type( 'foo' );
		bp_register_member_type( 'bar' );
		bp_set_member_type( $user_id, 'foo' );
		bp_set_member_type( $user_id, 'bar', true );

		// Set up xprofile data.
		wp_set_current_user( self::$user );

		$request  = new WP_REST_Request( 'GET', sprintf( '/buddypress/v1/members/%d', $user_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$this->check_get_user_response( $response, 'view' );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		return;

		$this->allow_user_to_manage_multisite();
		wp_set_current_user( self::$user );

		$params = array(
			'user_login'  => 'testuser',
			'password'    => 'testpassword',
			'email'       => 'test@example.com',
			'name'        => 'Test User',
			'slug'        => 'test-user',
			'roles'       => array( 'editor' ),
		);

		$request = new WP_REST_Request( 'POST', '/buddypress/v1/members' );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $params );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( array( 'editor' ), $data['roles'] );
		$this->check_add_edit_user_response( $response );
	}

	/**
	 * @group upadte_item
	 */
	public function test_update_item() {
		$user_id = $this->factory->user->create( array(
			'user_email' => 'test@example.com',
			'user_pass'  => 'sjflsfls',
			'user_login' => 'test_update',
		) );

		$this->allow_user_to_manage_multisite();
		wp_set_current_user( self::$user );

		$userdata  = get_userdata( $user_id );
		$pw_before = $userdata->user_pass;

		$_POST['email'] = $userdata->user_email;

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body_params( $_POST );

		$response = $this->server->dispatch( $request );
		$this->check_add_edit_user_response( $response, true );

		// https://core.trac.wordpress.org/ticket/21429.
		$this->assertEquals( $pw_before, $userdata->user_pass );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$user_id = $this->factory->user->create( array( 'display_name' => 'Deleted User' ) );

		$this->allow_user_to_manage_multisite();
		wp_set_current_user( self::$user );

		$userdata = get_userdata( $user_id ); // cache for later.
		$request = new WP_REST_Request( 'DELETE', sprintf( '/wp/v2/users/%d', $user_id ) );
		$request->set_param( 'force', true );
		$request->set_param( 'reassign', false );
		$response = $this->server->dispatch( $request );

		// Not implemented in multisite.
		if ( is_multisite() ) {
			$this->assertErrorResponse( 'rest_cannot_delete', $response, 501 );
			return;
		}

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['deleted'] );
		$this->assertEquals( 'Deleted User', $data['previous']['name'] );
	}

	public function test_prepare_item() {
		wp_set_current_user( self::$user );

		$request = new WP_REST_Request();
		$request->set_param( 'context', 'view' );
		$user = get_user_by( 'id', get_current_user_id() );
		$data = $this->endpoint->prepare_item_for_response( $user, $request );

		$this->check_get_user_response( $data, 'view' );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/users' );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 19, count( $properties ) );
		$this->assertArrayHasKey( 'avatar_urls', $properties );
		$this->assertArrayHasKey( 'capabilities', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'email', $properties );
		$this->assertArrayHasKey( 'extra_capabilities', $properties );
		$this->assertArrayHasKey( 'first_name', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'last_name', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'locale', $properties );
		$this->assertArrayHasKey( 'meta', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'nickname', $properties );
		$this->assertArrayHasKey( 'registered_date', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'url', $properties );
		$this->assertArrayHasKey( 'username', $properties );
		$this->assertArrayHasKey( 'roles', $properties );
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

		$data     = $response->get_data();
		$userdata = get_userdata( $data['id'] );
		$this->check_user_data( $userdata, $data, 'edit', $response->get_links() );
	}

	protected function check_user_data( $user, $data, $context, $links ) {
		$this->assertEquals( $user->ID, $data['id'] );
		$this->assertEquals( $user->display_name, $data['name'] );
		$this->assertArrayHasKey( 'avatar_urls', $data );

		$url = bp_core_get_user_domain( $data['id'], $user->user_nicename, $user->user_login );
		//$this->assertEquals( $url, $data['link'] );

		if ( 'edit' === $context ) {
			$this->assertEquals( $user->user_email, $data['email'] );
			$this->assertEquals( (object) $user->allcaps, $data['capabilities'] );
			$this->assertEquals( (object) $user->caps, $data['extra_capabilities'] );
			$this->assertEquals( date( 'c', strtotime( $user->user_registered ) ), $data['registered_date'] );
			$this->assertEquals( $user->display_name, $data['name'] );
			$this->assertEquals( $user->roles, $data['roles'] );
		}

		if ( 'edit' !== $context ) {
			$this->assertArrayNotHasKey( 'roles', $data );
			$this->assertArrayNotHasKey( 'capabilities', $data );
			$this->assertArrayNotHasKey( 'registered', $data );
			$this->assertArrayNotHasKey( 'extra_capabilities', $data );
		}

		$this->assertEqualSets( array(
			'self',
			'collection',
		), array_keys( $links ) );

		$this->assertArrayNotHasKey( 'password', $data );
	}

	protected function allow_user_to_manage_multisite() {
		wp_set_current_user( self::$user );
		$user = wp_get_current_user();

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( $user->user_login ) );
		}
	}
}
