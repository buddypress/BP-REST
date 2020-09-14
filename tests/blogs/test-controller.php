<?php
/**
 * Blogs Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group blogs
 */
class BP_Test_REST_Blogs_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Blogs_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->blogs->id;
		$this->admin        = $this->factory->user->create( array(
			'role' => 'administrator',
		) );

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 1, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$u = $this->bp_factory->user->create();
		$this->bp->set_current_user( $u );

		$a = $this->bp_factory->blog->create();
		update_blog_option( $a, 'blog_public', '1' );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$blogs   = $response->get_data();
		$headers = $response->get_headers();

		$this->assertEquals( 2, $headers['X-WP-Total'] );
		$this->assertEquals( 1, $headers['X-WP-TotalPages'] );
		$this->assertTrue( count( $blogs ) === 2 );
		$this->assertTrue( ! empty( $blogs[0] ) );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$blog = $this->bp_factory->blog->create(
			array(
				'title'   => 'The Foo Bar Blog',
				'user_id' => $this->admin,
			)
		);

		bp_blogs_record_existing_blogs();
		update_blog_option( $blog, 'blog_public', '1' );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $blog ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$blogs = $response->get_data();

		$this->assertSame( $blogs[0]['id'], $blog );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_group_id() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_blog_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$old_settings = $settings = buddypress()->site_options;

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['registration'] = 'all';
		buddypress()->site_options = $settings;

		$this->bp->set_current_user( $this->admin );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_blog_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$blogs = $response->get_data();

		$this->assertSame( $blogs[0]['name'], 'Blog Name' );

		buddypress()->site_options = $old_settings;
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_blog_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_signup_disabled() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$this->bp->set_current_user( $this->admin );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_blog_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_blogs_signup_disabled', $response, 500 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_without_required_field() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$this->bp->set_current_user( $this->admin );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_blog_data();

		// Remove a required field.
		unset( $params['title'] );

		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$this->markTestSkipped();
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$this->markTestSkipped();
	}

	public function test_prepare_item() {
		$this->markTestSkipped();
	}

	protected function set_blog_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'title'   => 'Blog Name',
			'path'    => 'blog-path',
			'domain'  => 'blog-path.com',
			'user_id' => $this->admin,
			'data'    => [
				'public' => 1,
			],
		) );
	}

	public function get_additional_field( $data, $attribute )  {
		return bp_blogs_get_blogmeta( $data['id'], '_' . $attribute );
	}

	/**
	 * @group additional_fields
	 */
	public function test_get_additional_fields() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$registered_fields = $GLOBALS['wp_rest_additional_fields'];

		bp_rest_register_field( 'blogs', 'foo_field', array(
			'get_callback'    => array( $this, 'get_additional_field' ),
			'schema'          => array(
				'description' => 'Blogs single item Meta Field',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		) );

		$u = $this->bp_factory->user->create();
		$this->bp->set_current_user( $u );

		$blog_id = $this->bp_factory->blog->create(
			array(
				'title'   => 'The Foo Bar Blog',
				'user_id' => $u,
			)
		);

		bp_blogs_record_existing_blogs();
		update_blog_option( $blog_id, 'blog_public', '1' );

		$expected = 'bar_value';

		bp_blogs_update_blogmeta( $blog_id, '_foo_field', $expected );

		// GET
		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$request->set_param( 'include', array( $blog_id ) );
		$response = $this->server->dispatch( $request );

		$get_data = $response->get_data();

		$this->assertTrue( $expected === $get_data[0]['foo_field'] );

		$GLOBALS['wp_rest_additional_fields'] = $registered_fields;
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 9, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'user_id', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'path', $properties );
		$this->assertArrayHasKey( 'domain', $properties );
		$this->assertArrayHasKey( 'permalink', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'last_activity', $properties );
	}

	public function test_context_param() {
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
