<?php
/**
 * Group Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group group
 */
class BP_Test_REST_Group_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Groups_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->groups->id;
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

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$this->bp->set_current_user( $this->user );

		$a1 = $this->bp_factory->group->create();
		$a2 = $this->bp_factory->group->create();
		$a3 = $this->bp_factory->group->create();

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$data     = $all_data;

		foreach ( $all_data as $data ) {
			$group = $this->endpoint->get_group_object( $data['id'] );
			$this->check_group_data( $group, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_paginated_items() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$a = $this->bp_factory->group->create( array( 'creator_id' => $u ) );
		$this->bp_factory->group->create_many( 5, array( 'creator_id' => $u ) );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'page'     => 2,
			'per_page' => 5,
			'user_id'  => $u,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertEquals( 6, $headers['X-WP-Total'] );
		$this->assertEquals( 2, $headers['X-WP-TotalPages'] );

		$all_data = $response->get_data();
		$data     = $all_data;
		foreach ( $all_data as $data ) {
			$group = $this->endpoint->get_group_object( $data['id'] );
			$this->check_group_data( $group, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$u = $this->factory->user->create();
		$this->bp->set_current_user( $u );

		$group = $this->endpoint->get_group_object( $this->group_id );
		$this->assertEquals( $this->group_id, $group->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_group_data( $group, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_group_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_hidden_group() {
		$u = $this->factory->user->create();
		$g = $this->bp_factory->group->create( array(
			'status' => 'hidden',
		) );

		$group = $this->endpoint->get_group_object( $g );

		$this->bp->add_user_to_group( $u, $group->id );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_group_data( $group, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_private_group() {
		$u = $this->factory->user->create();
		$g = $this->bp_factory->group->create( array(
			'status' => 'private',
		) );

		$group = $this->endpoint->get_group_object( $g );

		$this->bp->add_user_to_group( $u, $group->id );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_group_data( $group, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_hidden_group_without_being_from_group() {
		$u = $this->factory->user->create();
		$g = $this->bp_factory->group->create( array(
			'status' => 'hidden',
		) );

		$group = $this->endpoint->get_group_object( $g );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_private_group_without_being_from_group() {
		$u = $this->factory->user->create();
		$g = $this->bp_factory->group->create( array(
			'status' => 'private',
		) );

		$group = $this->endpoint->get_group_object( $g );
		$this->assertEquals( $g, $group->id );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_private_group_mods() {
		$g = $this->bp_factory->group->create( array(
			'status' => 'private',
		) );

		$group = $this->endpoint->get_group_object( $g );
		$this->assertEquals( $g, $group->id );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_group_data( $group, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group render_item
	 */
	public function test_render_item() {
		$this->bp->set_current_user( $this->user );

		$g = $this->bp_factory->group->create( array(
			'name'        => 'Group Test',
			'description' => 'links should be clickable: https://buddypress.org',
		) );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $g ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$all_data = $response->get_data();
		$a_data   = reset( $all_data );

		$this->assertTrue( false !== strpos( $a_data['description']['rendered'], '</a>' ) );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_group_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_group_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_rest_create_item() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_group_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_group_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_no_name() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_group_data( array( 'name' => '' ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_create_group_empty_name', $response, 500 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_group_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$group = $this->endpoint->get_group_object( $this->group_id );
		$this->assertEquals( $this->group_id, $group->id );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_group_data( array( 'description' => 'Updated Description' ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_update_group_response( $response );

		$new_data = $response->get_data();
		$new_data = $new_data[0];

		$this->assertEquals( $this->group_id, $new_data['id'] );
		$this->assertEquals( $params['description'], $new_data['description']['raw'] );

		$group = $this->endpoint->get_group_object( $new_data['id'] );
		$this->assertEquals( $params['description'], $group->description );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_not_logged_in() {
		$group = $this->endpoint->get_group_object( $this->group_id );

		$this->assertEquals( $this->group_id, $group->id );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_without_permission() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$a = $this->bp_factory->group->create( array( 'creator_id' => $u ) );

		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u2 );

		$group = $this->endpoint->get_group_object( $a );
		$this->assertEquals( $a, $group->id );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_group_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_moderators_can_update_item() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$g = $this->bp_factory->group->create( array(
			'creator_id'  => $u,
			'description' => 'New Description',
		) );

		$group = $this->endpoint->get_group_object( $g );
		$this->assertEquals( $g, $group->id );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_group_data( array( 'description' => 'Updated Description' ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_update_group_response( $response );

		$new_data = $response->get_data();
		$new_data = $new_data[0];

		$this->assertEquals( $g, $new_data['id'] );
		$this->assertEquals( $params['description'], $new_data['description']['raw'] );

		$group = $this->endpoint->get_group_object( $new_data['id'] );
		$this->assertEquals( $params['description'], $group->description );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$u        = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$group_id = $this->bp_factory->group->create( array(
			'name'        => 'Group name',
			'description' => 'Deleted group',
			'creator_id'  => $u,
		) );

		$group = $this->endpoint->get_group_object( $group_id );
		$this->assertEquals( $group_id, $group->id );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'Deleted group', $data['description']['raw'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$group = $this->endpoint->get_group_object( $this->group_id );
		$this->assertEquals( $this->group_id, $group->id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_without_permission() {
		$u        = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$group_id = $this->bp_factory->group->create( array( 'creator_id' => $u ) );

		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u2 );

		$group = $this->endpoint->get_group_object( $group_id );
		$this->assertEquals( $group_id, $group->id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_moderators_can_delete_item() {
		$u        = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$group_id = $this->bp_factory->group->create( array(
			'name'        => 'Group name',
			'description' => 'Deleted group',
			'creator_id'  => $u,
		) );

		$group = $this->endpoint->get_group_object( $group_id );
		$this->assertEquals( $group_id, $group->id );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 'Deleted group', $data['description']['raw'] );
	}

	public function test_prepare_item() {
		$this->bp->set_current_user( $this->user );

		$group = $this->endpoint->get_group_object( $this->group_id );
		$this->assertEquals( $this->group_id, $group->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_query_params( array( 'context' => 'edit' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_group_data( $group, $all_data[0], 'edit', $response->get_links() );
	}

	protected function check_group_data( $group, $data, $context, $links ) {
		$this->assertEquals( $group->id, $data['id'] );
		$this->assertEquals( $group->creator_id, $data['creator_id'] );
		$this->assertEquals( bp_rest_prepare_date_response( $group->date_created ), $data['date_created'] );
		$this->assertEquals( $group->enable_forum, $data['enable_forum'] );
		$this->assertEquals( bp_get_group_permalink( $group ), $data['link'] );
		$this->assertEquals( $group->name, $data['name'] );
		$this->assertEquals( $group->slug, $data['slug'] );
		$this->assertEquals( $group->status, $data['status'] );
		$this->assertEquals( $group->parent_id, $data['parent_id'] );

		if ( 'view' === $context ) {
			$this->assertEquals( wpautop( $group->description ), $data['description']['rendered'] );
		} else {
			$this->assertEquals( $group->description, $data['description']['raw'] );
			$this->assertEquals( $group->total_member_count, $data['total_member_count'] );
			$this->assertEquals( bp_rest_prepare_date_response( $group->last_activity ), $data['last_activity'] );
		}
	}

	protected function check_add_edit_group( $response, $update = false ) {
		if ( $update ) {
			$this->assertEquals( 200, $response->get_status() );
		} else {
			$this->assertEquals( 201, $response->get_status() );
		}

		$data  = $response->get_data();
		$group = $this->endpoint->get_group_object( $data['id'] );

		$this->check_group_data( $group, $data, 'edit', $response->get_links() );
	}

	protected function set_group_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'name'        => 'Group Name',
			'slug'        => 'group-name',
			'description' => 'Group Description',
			'creator_id'  => $this->user,
		) );
	}

	protected function check_update_group_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayNotHasKey( 'Location', $headers );

		$data = $response->get_data();

		$group = $this->endpoint->get_group_object( $data[0]['id'] );
		$this->check_group_data( $group, $data[0], 'edit', $response->get_links() );
	}

	protected function check_create_group_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$group = $this->endpoint->get_group_object( $data[0]['id'] );
		$this->check_group_data( $group, $data[0], 'edit', $response->get_links() );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 15, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'creator_id', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'enable_forum', $properties );
		$this->assertArrayHasKey( 'date_created', $properties );
		$this->assertArrayHasKey( 'admins', $properties );
		$this->assertArrayHasKey( 'mods', $properties );
		$this->assertArrayHasKey( 'parent_id', $properties );
		$this->assertArrayHasKey( 'total_member_count', $properties );
		$this->assertArrayHasKey( 'last_activity', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
