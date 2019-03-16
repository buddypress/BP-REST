<?php
/**
 * Group Invites Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group group-invites
 */
class BP_Test_REST_Group_Invites_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Group_Invites_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->groups->id . '/';
		$this->user         = $this->factory->user->create( array(
			'role'       => 'administrator',
			'user_email' => 'admin@example.com',
		) );

		$this->group_id = $this->bp_factory->group->create( array(
			'name'        => 'Group Test',
			'description' => 'Group Description',
			'creator_id'  => $this->user,
		) );

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$endpoint = $this->endpoint_url . '(?P<group_id>[\d]+)/invites';

		// Main.
		$this->assertArrayHasKey( $endpoint, $routes );
		$this->assertCount( 1, $routes[ $endpoint ] );

		// Single.
		$single_endpoint = $endpoint . '/(?P<user_id>[\d]+)';

		$this->assertArrayHasKey( $single_endpoint, $routes );
		$this->assertCount( 3, $routes[ $single_endpoint ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$u4 = $this->factory->user->create();
		$u5 = $this->factory->user->create();

		$this->populate_group_with_invites( [ $u1, $u2, $u3, $u4 ], $this->group_id );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/invites', $this->group_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$u_ids = wp_list_pluck( $all_data, 'user_id' );

		// Check results.
		$this->assertEqualSets( [ $u1, $u2, $u3, $u4 ], $u_ids );
		$this->assertNotContains( $u5, $u_ids );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_not_logged_in() {
		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/invites', $this->group_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_without_permission() {
		$u1 = $this->factory->user->create();
		$this->bp->set_current_user( $u1 );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/invites', $this->group_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		return true;
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$u1 = $this->factory->user->create();

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u1 ) );
		$request->set_query_params( array(
			'inviter_id' => $this->user,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->assertEquals( $u1, $all_data[0]['user_id'] );
		$this->assertEquals( $this->user, $all_data[0]['inviter_id'] );
		$this->assertFalse( (bool) $all_data[0]['is_confirmed'] );
		$this->assertTrue( (bool) $all_data[0]['invite_sent'] );
	}

	/**
	 * @group create_item
	 */
	public function test_mods_can_create_item() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$g1 = $this->bp_factory->group->create( array(
			'creator_id' => $u1,
		) );

		$this->bp->set_current_user( $u1 );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/invites/%d', $g1, $u2 ) );
		$request->set_query_params( array(
			'inviter_id' => $this->user,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->assertEquals( $u2, $all_data[0]['user_id'] );
		$this->assertEquals( $this->user, $all_data[0]['inviter_id'] );
		$this->assertFalse( (bool) $all_data[0]['is_confirmed'] );
		$this->assertTrue( (bool) $all_data[0]['invite_sent'] );
	}

	/**
	 * @group create_item
	 */
	public function test_inviter_can_not_invite_himself_to_group() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $this->user ) );
		$request->set_query_params( array(
			'inviter_id' => $this->user,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$u = $this->factory->user->create();

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u ) );
		$request->set_query_params( array(
			'inviter_id' => $this->user,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_member_id() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$request->set_query_params( array(
			'inviter_id' => $this->user,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_inviter_id() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u ) );
		$request->set_query_params( array(
			'inviter_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_group_id() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/invites/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $u ) );
		$request->set_query_params( array(
			'inviter_id' => $this->user,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_without_permission() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u1 ) );
		$request->set_query_params( array(
			'inviter_id' => $this->user,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$u1 = $this->factory->user->create();

		$this->populate_group_with_invites( [ $u1 ], $this->group_id );

		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u1 ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->assertEquals( $u1, $all_data[0]['user_id'] );
		$this->assertTrue( (bool) $all_data[0]['is_confirmed'] );
	}

	/**
	 * @group update_item
	 */
	public function test_moderators_can_update_item() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$g1 = $this->bp_factory->group->create( array(
			'creator_id' => $u1,
		) );

		$this->populate_group_with_invites( [ $u2 ], $g1 );

		$this->bp->set_current_user( $u1 );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '%d/invites/%d', $g1, $u2 ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->assertEquals( $u2, $all_data[0]['user_id'] );
		$this->assertTrue( (bool) $all_data[0]['is_confirmed'] );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_member_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_group__id() {
		$u1 = $this->factory->user->create();

		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '%d/invites/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $u1 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_not_logged_in() {
		$u1 = $this->factory->user->create();

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u1 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_without_permission() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$this->bp->set_current_user( $u2 );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u1 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$u1 = $this->factory->user->create();

		$this->populate_group_with_invites( [ $u1 ], $this->group_id );

		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u1 ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->assertEquals( $u1, $all_data[0]['user_id'] );
		$this->assertFalse( (bool) $all_data[0]['is_confirmed'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_moderators_can_delete_item() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$g1 = $this->bp_factory->group->create( array(
			'creator_id' => $u1,
		) );

		$this->populate_group_with_invites( [ $u2 ], $g1 );

		$this->bp->set_current_user( $u1 );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/invites/%d', $g1, $u2 ) );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->assertEquals( $u2, $all_data[0]['user_id'] );
		$this->assertFalse( (bool) $all_data[0]['is_confirmed'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_member_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_group_id() {
		$u1 = $this->factory->user->create();

		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/invites/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $u1 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$u1 = $this->factory->user->create();

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u1 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_without_permission() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$this->populate_group_with_invites( [ $u1 ], $this->group_id );

		$this->bp->set_current_user( $u2 );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/invites/%d', $this->group_id, $u1 ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_prepare_item() {
		return true;
	}

	protected function check_invited_user_data( $user, $data ) {
		$this->assertEquals( $user->ID, $data['user_id'] );
		$this->assertEquals( $user->invite_sent, $data['invite_sent'] );
		$this->assertEquals( $user->inviter_id, $data['inviter_id'] );
		$this->assertEquals( $user->is_confirmed, $data['is_confirmed'] );
	}

	protected function populate_group_with_invites( $users, $group_id ) {
		foreach ( $users as $user_id ) {
			groups_invite_user( array(
				'user_id'    => $user_id,
				'group_id'   => $group_id,
				'inviter_id' => $this->user,
			) );
		}
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/invites', $this->group_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 4, count( $properties ) );
		$this->assertArrayHasKey( 'user_id', $properties );
		$this->assertArrayHasKey( 'invite_sent', $properties );
		$this->assertArrayHasKey( 'inviter_id', $properties );
		$this->assertArrayHasKey( 'is_confirmed', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/invites', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
