<?php
/**
 * Blog Avatar Endpoints Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group blog-avatar
 */
class BP_Test_REST_Attachments_Blog_Avatar_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Attachments_Blog_Avatar_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->blogs->id . '/';

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_register_routes() {
		$routes   = $this->server->get_routes();
		$endpoint = $this->endpoint_url . '(?P<id>[\d]+)/avatar';

		// Single.
		$this->assertArrayHasKey( $endpoint, $routes );
		$this->assertCount( 1, $routes[ $endpoint ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$this->markTestSkipped();
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$u = $this->bp_factory->user->create();
		$expected = array(
			'full'  => get_avatar_url( $u, array( 'size' => 150 ) ),
			'thumb' => get_avatar_url( $u, array( 'size' => 50 ) ),
		);

		$this->bp->set_current_user( $u );

		$blog_id = $this->bp_factory->blog->create();

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', $blog_id ) );
		$request->set_param( 'context', 'view' );

		$response = rest_get_server()->dispatch( $request );
		$all_data = $response->get_data();

		$this->assertSame( $all_data[0], $expected );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_site_icon() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected = array(
			'full'  => 'https://buddypress.org/media/disc.png',
			'thumb' => 'https://buddypress.org/media/disc.png',
		);

		$u = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u );

		$blog_id = $this->bp_factory->blog->create();

		add_filter( 'get_site_icon_url', array( $this, 'filter_site_icon_url' ), 10, 1 );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', $blog_id ) );
		$request->set_param( 'context', 'view' );
		$request->set_param( 'no_user_gravatar', true );

		$response = rest_get_server()->dispatch( $request );
		$all_data = $response->get_data();

		remove_filter( 'get_site_icon_url', array( $this, 'filter_site_icon_url' ), 10, 1 );

		$this->assertSame( $all_data[0], $expected );
	}

	public function filter_site_icon_url( $url ) {
		return 'https://buddypress.org/media/disc.png';
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_no_grav() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$u = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u );

		$blog_id = $this->bp_factory->blog->create();
		$expected = array(
			'full'  => bp_get_blog_avatar( array( 'blog_id' => $blog_id, 'html' => false, 'type' => 'full' ) ),
			'thumb' => bp_get_blog_avatar( array( 'blog_id' => $blog_id, 'html' => false, 'type' => 'thumb' ) ),
		);

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', $blog_id ) );
		$request->set_param( 'context', 'view' );
		$request->set_param( 'no_user_gravatar', true );

		$response = rest_get_server()->dispatch( $request );
		$all_data = $response->get_data();

		$this->assertSame( $all_data[0], $expected );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_user_id() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$current_user = get_current_user_id();

		$u = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u );

		$blog_id = $this->bp_factory->blog->create( array( 'meta' => array( 'public' => 1 ) ) );

		$this->bp->set_current_user( $current_user );

		// Remove admins.
		add_filter( 'bp_blogs_get_blogs', array( $this, 'filter_admin_user_id' ), 10, 1 );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', $blog_id ) );
		$response = rest_get_server()->dispatch( $request );

		remove_filter( 'bp_blogs_get_blogs', array( $this, 'filter_admin_user_id' ), 10, 1 );

		$this->assertErrorResponse( 'bp_rest_blog_avatar_get_item_user_failed', $response, 500 );
	}

	public function filter_admin_user_id( $blog_results ) {
		unset( $blog_results['blogs'][0]->admin_user_id );

		return $blog_results;
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_blog_id() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_blog_invalid_id', $response, 404 );
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

	/**
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		$this->markTestSkipped();
	}

	public function test_get_item_schema() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$blog_id = $this->bp_factory->blog->create();

		// Single.
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/avatar', $blog_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 2, count( $properties ) );
		$this->assertArrayHasKey( 'full', $properties );
		$this->assertArrayHasKey( 'thumb', $properties );
	}

	public function test_context_param() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$blog_id = $this->bp_factory->blog->create();

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/avatar', $blog_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data );
	}
}
