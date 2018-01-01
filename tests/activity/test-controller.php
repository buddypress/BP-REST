<?php
/**
 * Activity Endpoint Tests.
 *
 * @package BP_REST
 */
class BP_Test_REST_Activity_Endpoint extends WP_Test_REST_Controller_Testcase {

	protected static $user;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$user = $factory->user->create( array(
			'role'          => 'administrator',
			'user_email'    => 'admin@example.com',
		) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user );
	}

	public function setUp() {
		parent::setUp();

		$this->endpoint     = new BP_REST_Activity_Endpoint();
		$this->endpoint_url = '/buddypress/v1/' . buddypress()->activity->id;
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)'] );
	}

	public function test_get_items() {
		return;
	}

	public function test_get_item() {
		wp_set_current_user( self::$user );

		// create an activity update
		$activity = self::factory()->activity->create( array(
			'type' => 'activity_update',
		) );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/%d', $activity->id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->check_activity_data( $activity, $data, 'view', $response->get_links() );
	}

	public function test_create_item() {
		return;
	}

	public function test_update_item() {
		return;
	}

	public function test_delete_item() {
		return;
	}

	public function test_prepare_item() {
		return;
	}

	protected function check_activity_data( $activity, $data, $context, $links ) {
		$this->assertEquals( $activity->user, $data['user'] );
		$this->assertEquals( $activity->component, $data['component'] );
		$this->assertEquals( $activity->type, $data['type'] );

		$this->assertEqualSets( array( 'self', 'collection', 'user' ), array_keys( $links ) );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 14, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'prime_association', $properties );
		$this->assertArrayHasKey( 'secondary_association', $properties );
		$this->assertArrayHasKey( 'user', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'component', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'comments', $properties );
		$this->assertArrayHasKey( 'user_avatar', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		// $request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url . self::$activity );
		// $response = $this->server->dispatch( $request );
		// $data     = $response->get_data();

		// $this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		// $this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
