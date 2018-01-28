<?php
/**
 * Activity Endpoint Tests.
 *
 * @package BP_REST
 */
class BP_Test_REST_Activity_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Activity_Endpoint();
		$this->endpoint_url = '/buddypress/v1/' . buddypress()->activity->id;

		$this->activity_id  = $this->bp_factory->activity->create();

		$this->user = $this->factory->user->create( array(
			'role'          => 'administrator',
			'user_email'    => 'admin@example.com',
		) );

		$this->random_user = $this->factory->user->create( array(
			'role'          => 'subscriber',
		) );
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
		wp_set_current_user( $this->user );

		$a1  = $this->bp_factory->activity->create();
		$a2  = $this->bp_factory->activity->create();
		$a3  = $this->bp_factory->activity->create();

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data     = $all_data;

		foreach ( $all_data as $data ) {
			$activity = $this->endpoint->get_activity_object( $data['id'] );
			$this->check_activity_data( $activity, $data, 'view', $response->get_links() );
		}
	}

	public function test_get_item() {
		wp_set_current_user( $this->user );

		$activity = $this->endpoint->get_activity_object( $this->activity_id );

		$this->assertEquals( $this->activity_id, $activity->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_activity_data( $activity, $all_data[0], 'view', $response->get_links() );
	}

	public function test_create_item() {
		return;
	}

	public function test_update_item() {
		return;
	}

	public function test_delete_item() {
		wp_set_current_user( $this->user );

		$activity_id  = $this->bp_factory->activity->create( array(
			'content' => 'Deleted activity',
		) );

		$activity = $this->endpoint->get_activity_object( $activity_id );

		$this->assertEquals( $activity_id, $activity->id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'Deleted activity', $data['content'] );
	}

	public function test_delete_item_invalid_id() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_activity_invalid_id', $response, 404 );
	}

	public function test_delete_item_not_logged_in() {

		$activity = $this->endpoint->get_activity_object( $this->activity_id );

		$this->assertEquals( $this->activity_id, $activity->id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_authorization_required', $response, 401 );
	}

	public function delete_item_without_permission() {
		$u = self::factory()->user->create();

		wp_set_current_user( $u );

		$activity = $this->endpoint->get_activity_object( $this->activity_id );

		$this->assertEquals( $this->activity_id, $activity->id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_delete_activity', $response, 403 );
	}

	public function test_prepare_item() {
		return;
	}

	protected function check_activity_data( $activity, $data, $context, $links ) {
		$this->assertEquals( $activity->user_id, $data['user'] );
		$this->assertEquals( $activity->component, $data['component'] );
		$this->assertEquals( $activity->content, $data['content'] );
		$this->assertEquals( $activity->type, $data['type'] );
		$this->assertEquals( $this->endpoint->prepare_date_response( $activity->date_recorded ), $data['date'] );
		$this->assertEquals( $activity->id, $data['id'] );
		$this->assertEquals( bp_activity_get_permalink( $activity->id ), $data['link'] );
		$this->assertEquals( $activity->item_id, $data['prime_association'] );
		$this->assertEquals( $activity->secondary_item_id, $data['secondary_association'] );
		$this->assertEquals( $activity->action, $data['title'] );
		$this->assertEquals( $activity->type, $data['type'] );
		$this->assertEquals( $activity->is_spam ? 'spam' : 'published', $data['status'] );

		$parent = 'activity_comment' === $activity->type ? $activity->item_id : 0;
		$this->assertEquals( $parent, $data['parent'] );
	}

	protected function check_add_edit_activity( $response, $update = false ) {
		if ( $update ) {
			$this->assertEquals( 200, $response->get_status() );
		} else {
			$this->assertEquals( 201, $response->get_status() );
		}

		$data     = $response->get_data();
		$activity = $this->endpoint->get_activity_object( $data['id'] );
		$this->check_activity_data( $activity, $data, 'edit', $response->get_links() );
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
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->activity_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
