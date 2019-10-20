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
		$this->assertCount( 1, $routes[ $this->endpoint_url ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		if ( function_exists( 'wp_initialize_site' ) ) {
			$this->setExpectedDeprecated( 'wpmu_new_blog' );
		}

		$u = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u );

		$this->bp_factory->blog->create();
		$this->bp_factory->blog->create();

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
		$this->assertSame( $blogs[0]['user_id'], $u );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$this->markTestSkipped();
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$this->markTestSkipped();
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

	protected function check_blog_data( $blog, $data ) {
		$this->assertEquals( $blog->blog_id, $data['id'] );
		$this->assertEquals( $blog->user_id, $data['user_id'] );
		$this->assertEquals( $blog->name, $data['name'] );
		$this->assertEquals( $blog->domain, $data['domain'] );
		$this->assertEquals( $blog->path, $data['path'] );
		$this->assertEquals( $blog->permalink, $data['permalink'] );
		$this->assertEquals( $blog->description, $data['description'] );
		$this->assertEquals( bp_rest_prepare_date_response( $blog->last_activity ), $data['last_activity'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 8, count( $properties ) );
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
