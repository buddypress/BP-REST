<?php
/**
 * Friends Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group friends
 */
class BP_Test_REST_Friends_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Friends_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->friends->id;
		$this->user         = $this->factory->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin@example.com',
				'user_login' => 'admin_user',
			)
		);

		$this->friendship_id = $this->create_friendship();

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
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\w-]+)', $routes );
		$this->assertCount( 3, $routes[ $this->endpoint_url . '/(?P<id>[\w-]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u = $this->factory->user->create();

		$s1 = $this->create_friendship( $u );
		$s2 = $this->create_friendship( $u );
		$s3 = $this->create_friendship( $u );
		$s4 = $this->create_friendship( $u );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params(
			array(
				'user_id'      => $u,
				'per_page'     => 2,
				'is_confirmed' => 0,
			)
		);
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();

		$this->assertEquals( 2, $headers['X-WP-Total'] );
		$this->assertEquals( 1, $headers['X-WP-TotalPages'] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_not_logged_in() {
		$s1 = $this->create_friendship();

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

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->friendship_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$friendship = $this->endpoint->get_friendship_object( $this->friendship_id );
		$all_data   = $response->get_data();

		$this->check_friendship_data( $friendship, $all_data[0], 'view' );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_friendship_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->friendship_id ) );
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

		$params = $this->set_friendship_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_friendship_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$u             = $this->factory->user->create();
		$friendship_id = $this->create_friendship( $u );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $friendship_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->assertTrue( 1 === $all_data[0]['is_confirmed'] );
	}

	/**
	 * @group update_item
	 */
	public function test_initiator_can_not_accept_its_own_friendship_request() {
		$u             = $this->factory->user->create();
		$friendship_id = $this->create_friendship( $u );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $friendship_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_friends_cannot_update_item', $response, 500 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_friendship_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $this->friendship_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->friendship_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$deleted = $response->get_data();

		$this->assertTrue( $deleted['deleted'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_reject_friendship_request() {
		$u             = $this->factory->user->create();
		$friendship_id = $this->create_friendship( $u );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $friendship_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$deleted = $response->get_data();

		$this->assertTrue( $deleted['deleted'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_friendship_id() {
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
		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->friendship_id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	public function test_prepare_item() {
		$this->bp->set_current_user( $this->user );

		$friendship = $this->endpoint->get_friendship_object( $this->friendship_id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->friendship_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_friendship_data( $friendship, $all_data[0], 'view' );
	}

	protected function set_friendship_data( $args = array() ) {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		return wp_parse_args(
			$args,
			array(
				'initiator_id' => $u1,
				'friend_id'    => $u2,
			)
		);
	}

	protected function create_friendship( $u = 0 ) {
		if ( empty( $u ) ) {
			$u = $this->factory->user->create();
		}

		$friendship                    = new BP_Friends_Friendship();
		$friendship->initiator_user_id = $this->user;
		$friendship->friend_user_id    = $u;
		$friendship->is_confirmed      = 0;
		$friendship->is_limited        = 0;
		$friendship->date_created      = bp_core_current_time();
		$friendship->save();

		return $friendship->id;
	}

	protected function check_friendship_data( $friend, $data ) {
		$this->assertEquals( $friend->id, $data['id'] );
		$this->assertEquals( $friend->initiator_user_id, $data['initiator_id'] );
		$this->assertEquals( $friend->friend_user_id, $data['friend_id'] );
		$this->assertEquals( $friend->is_confirmed, $data['is_confirmed'] );
		$this->assertEquals( bp_rest_prepare_date_response( $friend->date_created ), $data['date_created'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->friendship_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 5, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'initiator_id', $properties );
		$this->assertArrayHasKey( 'friend_id', $properties );
		$this->assertArrayHasKey( 'is_confirmed', $properties );
		$this->assertArrayHasKey( 'date_created', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->friendship_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
