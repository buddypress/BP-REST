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
		$routes   = $this->server->get_routes();
		$base     = $this->endpoint_url . '(?P<group_id>[\d]+)/membership-request';
		$single   = $this->endpoint_url . 'membership-request/(?P<membership_id>[\d]+)';
		$endpoint = $base . '/(?P<user_id>[\d]+)';

		$this->assertArrayHasKey( $endpoint, $routes );
		$this->assertCount( 3, $routes[ $endpoint ] );
		$this->assertCount( 1, $routes[ $base ] );
		$this->assertCount( 1, $routes[ $single ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u3  = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->create_membership_request( $this->group_id, $u );
		$this->create_membership_request( $this->group_id, $u2 );
		$this->create_membership_request( $this->group_id, $u3 );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . $this->group_id . '/membership-request' );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->assertTrue( 3 === count( $all_data ) );

		foreach ( $all_data as $data ) {
			$user          = bp_rest_get_user( $data['id'] );
			$member_object = new BP_Groups_Member( $user->ID, $this->group_id );

			$accepted = groups_is_user_member( $user->ID, $this->group_id );
			$this->assertFalse( $accepted );

			$this->check_user_data( $user, $data, $member_object );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_is_not_logged_in() {
		$request = new WP_REST_Request( 'GET', $this->endpoint_url . $this->group_id . '/membership-request' );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_invalid_group() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/membership-request' );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$group_member = $this->create_membership_request( $this->group_id, $u );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . 'membership-request/' . $group_member->id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$user          = bp_rest_get_user( $data['id'] );
		$member_object = new BP_Groups_Member( $user->ID, $this->group_id );

		$accepted = groups_is_user_member( $user->ID, $this->group_id );
		$this->assertFalse( $accepted );

		$this->check_user_data( $user, $data, $member_object );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_is_not_logged_in() {
		$request = new WP_REST_Request( 'GET', $this->endpoint_url . 'membership-request/' . 0 );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_invalid_membership_request() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . 'membership-request/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_request_invalid_membership', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_no_access() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$group_member = $this->create_membership_request( $this->group_id, $u );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . 'membership-request/' . $group_member->id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url . $this->group_id . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$user          = bp_rest_get_user( $data['id'] );
			$member_object = new BP_Groups_Member( $user->ID, $this->group_id );

			$invited = groups_check_for_membership_request( $user->ID, $this->group_id );
			$this->assertTrue( false !== $invited );

			$this->check_user_data( $user, $data, $member_object );
		}
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_is_not_logged_in() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url . $this->group_id . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_member() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url . $this->group_id . '/membership-request/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_member_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_group() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_an_already_group_member() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$g = $this->bp_factory->group->create( array(
			'name' => 'Group Test',
		) );

		$this->bp->add_user_to_group( $u, $g );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url . $g . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$this->create_membership_request( $this->group_id, $u );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . $this->group_id . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$user          = bp_rest_get_user( $data['id'] );
			$member_object = new BP_Groups_Member( $user->ID, $this->group_id );

			$accepted = groups_is_user_member( $user->ID, $this->group_id );
			$this->assertTrue( false !== $accepted );

			$this->check_user_data( $user, $data, $member_object );
		}
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_is_not_logged_in() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . $this->group_id . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_member() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . $this->group_id . '/membership-request/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_member_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_group() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_already_group_member() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$g = $this->bp_factory->group->create( array(
			'name' => 'Group Test',
		) );

		$this->bp->add_user_to_group( $u, $g );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . $g . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_membership_update_no_request', $response, 500 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$this->create_membership_request( $this->group_id, $u );

		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . $this->group_id . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$user          = bp_rest_get_user( $data['id'] );
			$member_object = new BP_Groups_Member( $user->ID, $this->group_id );

			$accepted = groups_is_user_member( $user->ID, $this->group_id );
			$this->assertFalse( $accepted );

			$this->check_user_data( $user, $data, $member_object );
		}
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_is_not_logged_in() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . $this->group_id . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_member() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . $this->group_id . '/membership-request/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_member_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_group() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', $this->endpoint_url . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER . '/membership-request/' . $u );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_prepare_item() {
		return true;
	}

	protected function create_membership_request( $group_id, $user_id ) {
		$group_member                = new BP_Groups_Member();
		$group_member->group_id      = $group_id;
		$group_member->user_id       = $user_id;
		$group_member->inviter_id    = 0;
		$group_member->is_admin      = 0;
		$group_member->user_title    = '';
		$group_member->date_modified = bp_core_current_time();
		$group_member->is_confirmed  = 0;
		$group_member->save();

		return $group_member;
	}

	protected function check_user_data( $user, $data, $member_object) {
		$this->assertEquals( $user->ID, $data['id'] );
		$this->assertEquals( $user->display_name, $data['name'] );
		$this->assertEquals( $user->user_email, $data['email'] );
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
		$this->assertEquals( bp_rest_prepare_date_response( $member_object->date_modified ), $data['date_modified'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url . $this->group_id . '/members' );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 19, count( $properties ) );
		$this->assertArrayHasKey( 'avatar_urls', $properties );
		$this->assertArrayHasKey( 'email', $properties );
		$this->assertArrayHasKey( 'capabilities', $properties );
		$this->assertArrayHasKey( 'extra_capabilities', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'mention_name', $properties );
		$this->assertArrayHasKey( 'registered_date', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'roles', $properties );
		$this->assertArrayHasKey( 'xprofile', $properties );

		// Extra fields.
		$this->assertArrayHasKey( 'is_mod', $properties );
		$this->assertArrayHasKey( 'is_admin', $properties );
		$this->assertArrayHasKey( 'is_banned', $properties );
		$this->assertArrayHasKey( 'is_confirmed', $properties );
		$this->assertArrayHasKey( 'date_modified', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url . $this->group_id . '/membership-request' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
