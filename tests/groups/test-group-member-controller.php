<?php
/**
 * Group Members Endpoint Tests.
 *
 * @package BP_REST
 * @group group-members
 */
class BP_Test_REST_Group_Members_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Group_Members_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/buddypress/v1/group/members';
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

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();

		$this->populate_group_with_members( [ $u1, $u2, $u3 ], $this->group_id );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data     = $all_data;

		foreach ( $all_data as $data ) {
			$user = $this->endpoint->get_user( $data['id'] );
			$this->check_user_data( $user, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_paginated_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$u4 = $this->factory->user->create();
		$u5 = $this->factory->user->create();
		$u6 = $this->factory->user->create();

		$this->populate_group_with_members( [ $u1, $u2, $u3, $u4, $u5, $u6 ], $this->group_id );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
			'page'     => 2,
			'per_page' => 3,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();

		// $this->assertEquals( 6, $headers['X-WP-Total'] );
		// $this->assertEquals( 2, $headers['X-WP-TotalPages'] );

		$all_data = $response->get_data();
		$data     = $all_data;

		foreach ( $all_data as $data ) {
			$user = $this->endpoint->get_user( $data['id'] );
			$this->check_user_data( $user, $data, 'view', $response->get_links() );
		}
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
		return true;
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->populate_group_with_members( [ $u ], $this->group_id );

		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
			'id'       => $u,
			'action'   => 'ban',
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		foreach ( $all_data as $data ) {
			$user          = $this->endpoint->get_user( $data['id'] );
			$member_object = new BP_Groups_Member( $user->ID, $this->group_id );

			$this->assertTrue( $u === $member_object->user_id );
			$this->assertTrue( (bool) $member_object->is_banned );
			$this->check_user_data( $user, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group update_item
	 */
	public function test_promote_member() {

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->populate_group_with_members( [ $u ], $this->group_id );

		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
			'id'       => $u,
			'action'   => 'promote',
			'role'     => 'mod',
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		foreach ( $all_data as $data ) {
			$user          = $this->endpoint->get_user( $data['id'] );
			$member_object = new BP_Groups_Member( $user->ID, $this->group_id );

			$this->assertTrue( $u === $member_object->user_id );
			$this->assertTrue( (bool) $member_object->is_mod );
			$this->check_user_data( $user, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_group_id() {

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER,
			'id'       => $u,
			'action'   => 'promote',
			'role'     => 'mod',
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_group_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_id() {
		wp_set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_member_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_without_permission() {
		$u  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $u2 );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = array(
			'id'       => $u,
			'action'   => 'promote',
			'group_id' => $this->group_id,
			'role'     => 'admin',
		);

		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_member_cannot_update', $response, 500 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		return true;
	}

	/**
	 * @group get_item
	 */
	public function test_prepare_item() {
		return true;
	}

	protected function populate_group_with_members( $members, $group_id ) {
		// Add member to the group.
		foreach ( $members as $member_id ) {
			groups_join_group( $group_id, $member_id );
		}
	}

	protected function check_user_data( $user, $data, $context, $links ) {
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
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 13, count( $properties ) );
		$this->assertArrayHasKey( 'avatar_urls', $properties );
		$this->assertArrayHasKey( 'email', $properties );
		$this->assertArrayHasKey( 'capabilities', $properties );
		$this->assertArrayHasKey( 'extra_capabilities', $properties );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'registered_date', $properties );
		$this->assertArrayHasKey( 'password', $properties );
		$this->assertArrayHasKey( 'roles', $properties );
		$this->assertArrayHasKey( 'xprofile', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
