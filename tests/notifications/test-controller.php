<?php
/**
 * Notifications Endpoint Tests.
 *
 * @package BP_REST
 * @group notification
 */
class BP_Test_REST_Notifications_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory      = new BP_UnitTest_Factory();
		$this->endpoint        = new BP_REST_Notifications_Endpoint();
		$this->endpoint_url    = '/buddypress/v1/' . buddypress()->notifications->id;
		$this->notification_id = $this->bp_factory->notification->create();

		$this->user = $this->factory->user->create( array(
			'role'       => 'administrator',
			'user_email' => 'admin@example.com',
		) );

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 1, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 1, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		wp_set_current_user( $this->user );

		$a1 = $this->bp_factory->notification->create();
		$a2 = $this->bp_factory->notification->create();
		$a3 = $this->bp_factory->notification->create();

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data     = $all_data;

		foreach ( $all_data as $data ) {
			$notification = $this->endpoint->get_notification_object( $data['id'] );
			$this->check_notification_data( $notification, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_not_logged_in() {
		$n = $this->bp_factory->notification->create();

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_authorization_required', $response, 401 );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_cannot_see_notifications() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $u );

		$a1 = $this->bp_factory->notification->create();

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_view_notifications', $response, 500 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		wp_set_current_user( $this->user );

		$notification = $this->endpoint->get_notification_object( $this->notification_id );
		$this->assertEquals( $this->notification_id, $notification->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $notification->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_notification_data( $notification, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_not_logged_in() {
		$n = $this->bp_factory->notification->create();
		$notification = $this->endpoint->get_notification_object( $n );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $notification->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_authorization_required', $response, 401 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_cannot_see_notification() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $u );

		$n = $this->bp_factory->notification->create( $this->set_notification_data() );

		$notification = $this->endpoint->get_notification_object( $n );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $n ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_view_notification', $response, 500 );
	}

	public function test_create_item() {
		$this->assertTrue( true );
	}

	public function test_update_item() {
		$this->assertTrue( true );
	}

	public function test_delete_item() {
		$this->assertTrue( true );
	}

	public function test_prepare_item() {

		$this->assertTrue( true );

		return;

		$n = $this->bp_factory->notification->create( array(
			'component_name' => 'messages',
			'user_id'        => $this->user,
			'is_new'         => true,
		) );

		$notification = BP_Notifications_Notification::get( array(
			'user_id' => $this->user,
		) );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $notification->id ) );
		$request->set_query_params( array( 'context' => 'edit' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_activity_data( $notification, $all_data[0], 'edit', $response->get_links() );
	}

	protected function check_notification_data( $notification, $data, $context, $links ) {
		$this->assertEquals( $notification->id, $data['id'] );
		$this->assertEquals( $notification->user_id, $data['user_id'] );
		$this->assertEquals( $notification->secondary_item_id, $data['secondary_association'] );
		$this->assertEquals( $notification->component_name, $data['component'] );
		$this->assertEquals( $notification->component_action, $data['action'] );
		$this->assertEquals( bp_rest_prepare_date_response( $notification->date_notified ), $data['date'] );
		$this->assertEquals( $notification->is_new, $data['unread'] );
	}

	protected function set_notification_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'component_name' => 'groups',
			'user_id'        => $this->user,
		) );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 8, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'prime_association', $properties );
		$this->assertArrayHasKey( 'secondary_association', $properties );
		$this->assertArrayHasKey( 'user_id', $properties );
		$this->assertArrayHasKey( 'component', $properties );
		$this->assertArrayHasKey( 'action', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'unread', $properties );
	}

	public function test_context_param() {

		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->notification_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
