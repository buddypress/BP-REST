<?php
/**
 * Group Membership Request Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group group-membership-request
 */
class BP_Test_REST_Group_Membership_Request_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Group_Membership_Request_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->groups->id . '/membership-requests';
		$this->user         = $this->factory->user->create( array(
			'role'       => 'administrator',
			'user_email' => 'admin@example.com',
		) );

		$this->group_id = $this->bp_factory->group->create( array(
			'name'        => 'Group Test',
			'description' => 'Group Description',
			'creator_id'  => $this->user,
			'status'      => 'private',
		) );

		// Create a group with a group admin that is not a site admin.
		$this->g1admin = $this->factory->user->create( array(
			'role'       => 'subscriber',
			'user_email' => 'sub@example.com',
		) );
		$this->g1 = $this->bp_factory->group->create( array(
			'name'        => 'Group Test 1',
			'description' => 'Group Description 1',
			'status'      => 'private',
			'creator_id'  => $this->g1admin,
		) );

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}

		$this->old_current_user = get_current_user_id();
	}

	public function tearDown() {
		parent::tearDown();
		$this->bp->set_current_user( $this->old_current_user );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// GET and CREATE.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url ] );

		// PUT, etc.
		$put_endpoint = $this->endpoint_url . '/(?P<request_id>[\d]+)';

		$this->assertArrayHasKey( $put_endpoint, $routes );
		$this->assertCount( 3, $routes[ $put_endpoint ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u3  = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );
		groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u2 ) );
		groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u3 ) );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertTrue( 3 === count( $all_data ) );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_as_group_admin() {
		$u   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2  = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		groups_send_membership_request( array( 'group_id' => $this->g1, 'user_id' => $u ) );
		groups_send_membership_request( array( 'group_id' => $this->g1, 'user_id' => $u2 ) );

		$this->bp->set_current_user( $this->g1admin );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->g1,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertTrue( 2 === count( $all_data ) );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_as_requestor() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id' => $u,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertTrue( 1 === count( $all_data ) );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_is_not_logged_in() {
		$this->bp->set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_has_no_access_to_group() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( $u2 );
		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_requests_cannot_get_items', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_has_no_access_to_user() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( $u2 );
		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id' => $u,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_requests_cannot_get_items', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_invalid_group() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/'. $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$accepted = groups_is_user_member( $u, $this->group_id );
		$this->assertFalse( $accepted );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_is_not_logged_in() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( 0 );
		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/'. $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_membership_request() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_requests_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_no_access() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_requests_cannot_get_item', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id'  => $u,
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertTrue( $this->group_id === $all_data[0]['group_id'] && $u === $all_data[0]['user_id'] );
	}

	/**
	 * @group tst
	 */
	public function test_create_item_as_subscriber() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertTrue( $this->group_id === $all_data[0]['group_id'] && $u === $all_data[0]['user_id'] );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_is_not_logged_in() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->bp->set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id'  => $u,
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_member() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id'  => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_member_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_group() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id'  => $u,
			'group_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_an_already_group_member() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->bp->add_user_to_group( $u, $this->group_id );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id'  => $u,
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_requests_cannot_create_item', $response, 500 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_fails_with_pending_request() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id'  => $u,
			'group_id' => $this->group_id,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_requests_duplicate_request', $response, 500 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . $request_id );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$status = groups_is_user_member( $u, $this->group_id );
		$this->assertTrue( is_int( $status ) && $status > 0 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_as_group_admin() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );
		$request_id = groups_send_membership_request( array( 'group_id' => $this->g1, 'user_id' => $u ) );

		$this->bp->set_current_user( $this->g1admin );
		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$status = groups_is_user_member( $u, $this->g1 );
		$this->assertTrue( is_int( $status ) && $status > 0 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_is_not_logged_in() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( 0 );
		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_has_no_access() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_member_request_cannot_update_item', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_id() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_requests_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertTrue( $all_data['deleted'] );
		$this->assertEquals( $request_id, $all_data['previous']['id']);
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_as_requestor() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertTrue( $all_data['deleted'] );
		$this->assertEquals( $request_id, $all_data['previous']['id']);
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_as_group_admin() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );
		$request_id = groups_send_membership_request( array( 'group_id' => $this->g1, 'user_id' => $u ) );

		$this->bp->set_current_user( $this->g1admin );
		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertTrue( $all_data['deleted'] );
		$this->assertEquals( $request_id, $all_data['previous']['id']);
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_is_not_logged_in() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->g1, 'user_id' => $u ) );

		$this->bp->set_current_user( 0 );
		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_has_no_access() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request_id = groups_send_membership_request( array( 'group_id' => $this->group_id, 'user_id' => $u ) );

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $request_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_requests_cannot_delete_item', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_prepare_item() {
		$this->markTestSkipped();
	}

	protected function check_user_data( $user, $data, $member_object ) {
		$this->assertEquals( $user->ID, $data['id'] );
		$this->assertEquals( $user->display_name, $data['name'] );
		$this->assertEquals( $user->user_login, $data['user_login'] );
		$this->assertArrayHasKey( 'avatar_urls', $data );
		$this->assertArrayHasKey( 'thumb', $data['avatar_urls'] );
		$this->assertArrayHasKey( 'full', $data['avatar_urls'] );
		$this->assertArrayHasKey( 'member_types', $data );
		$this->assertEquals(
			bp_core_get_user_domain( $data['id'], $user->user_nicename, $user->user_login ),
			$data['link']
		);
		$this->assertArrayNotHasKey( 'roles', $data );
		$this->assertArrayNotHasKey( 'capabilities', $data );
		$this->assertArrayNotHasKey( 'extra_capabilities', $data );
		$this->assertArrayHasKey( 'xprofile', $data );
		$this->assertArrayNotHasKey( 'registered_date', $data );

		// Checking extra.
		$this->assertEquals( $member_object->is_mod, (bool) $data['is_mod'] );
		$this->assertEquals( $member_object->is_admin, (bool) $data['is_admin'] );
		$this->assertEquals( $member_object->is_banned, (bool) $data['is_banned'] );
		$this->assertEquals( $member_object->is_confirmed, (bool) $data['is_confirmed'] );
		$this->assertEquals(
			bp_rest_prepare_date_response( $member_object->date_modified, get_date_from_gmt( $member_object->date_modified ) ),
			$data['date_modified']
		);
		$this->assertEquals( bp_rest_prepare_date_response( $member_object->date_modified ), $data['date_modified_gmt'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 7, count( $properties ) );
		$this->assertArrayHasKey( 'user_id', $properties );
		$this->assertArrayHasKey( 'date_modified', $properties );
		$this->assertArrayHasKey( 'date_modified_gmt', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
