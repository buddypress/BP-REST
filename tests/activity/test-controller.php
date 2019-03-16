<?php
/**
 * Activity Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group activity
 */
class BP_Test_REST_Activity_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Activity_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->activity->id;
		$this->activity_id  = $this->bp_factory->activity->create();

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
		$this->assertCount( 2, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group test
	 */
	public function test_get_items() {
		$this->bp->set_current_user( $this->user );

		$a1 = $this->bp_factory->activity->create();
		$a2 = $this->bp_factory->activity->create();
		$a3 = $this->bp_factory->activity->create();

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$activity = $this->endpoint->get_activity_object( $data['id'] );
			$this->check_activity_data( $activity, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_public_groups_items() {
		$component = buddypress()->groups->id;

		// Current user is $this->user.
		$g1 = $this->bp_factory->group->create( array(
			'status' => 'private',
		) );

		$g2 = $this->bp_factory->group->create( array(
			'status' => 'public',
		) );

		$a1 = $this->bp_factory->activity->create( array(
			'component'     => $component,
			'type'          => 'created_group',
			'user_id'       => $this->user,
			'item_id'       => $g1,
			'hide_sitewide' => true,
		) );

		$a2 = $this->bp_factory->activity->create( array(
			'component' => $component,
			'type'      => 'created_group',
			'user_id'   => $this->user,
			'item_id'   => $g2,
		) );

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'component' => $component,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$a_ids = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertNotContains( $a1, $a_ids );
		$this->assertContains( $a2, $a_ids );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_from_a_specific_group() {
		$component = buddypress()->groups->id;

		$g1 = $this->bp_factory->group->create( array( 'status' => 'public' ) );
		$g2 = $this->bp_factory->group->create( array( 'status' => 'public' ) );

		$a1 = $this->bp_factory->activity->create( array(
			'component' => $component,
			'type'      => 'created_group',
			'user_id'   => $this->user,
			'item_id'   => $g2,
		) );

		$a2 = $this->bp_factory->activity->create( array(
			'component' => $component,
			'type'      => 'created_group',
			'user_id'   => $this->user,
			'item_id'   => $g2,
		) );

		$a3 = $this->bp_factory->activity->create( array(
			'component'     => $component,
			'type'          => 'created_group',
			'user_id'       => $this->user,
			'item_id'       => $g2,
			'hide_sitewide' => true,
		) );

		$a4 = $this->bp_factory->activity->create( array(
			'component' => $component,
			'type'      => 'created_group',
			'user_id'   => $this->user,
			'item_id'   => $g1,
		) );

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array( 'group_id' => $g2 ) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$a_ids = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertEqualSets( [ $a1, $a2 ], $a_ids );
		$this->assertNotContains( $a3, $a_ids );
		$this->assertNotContains( $a4, $a_ids );
	}

	/**
	 * @group get_items
	 */
	public function test_get_private_group_items() {
		$component = buddypress()->groups->id;

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		// Current user is $u.
		$g1 = $this->bp_factory->group->create( array(
			'status'     => 'private',
			'creator_id' => $u,
		) );

		$g2 = $this->bp_factory->group->create( array(
			'status'     => 'public',
			'creator_id' => $this->user,
		) );

		$a1 = $this->bp_factory->activity->create( array(
			'component'     => $component,
			'type'          => 'created_group',
			'user_id'       => $u,
			'item_id'       => $g1,
			'hide_sitewide' => true,
		) );

		$a2 = $this->bp_factory->activity->create( array(
			'component' => $component,
			'type'      => 'created_group',
			'user_id'   => $this->user,
			'item_id'   => $g2,
		) );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'component'  => $component,
			'primary_id' => $g1,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$a_ids = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertNotContains( $a2, $a_ids );
		$this->assertContains( $a1, $a_ids );
	}

	/**
	 * @group get_items
	 */
	public function test_get_paginated_items() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$a = $this->bp_factory->activity->create( array( 'user_id' => $u ) );
		$this->bp_factory->activity->create_many( 5, array( 'user_id' => $u ) );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'user_id'  => $u,
			'page'     => 2,
			'per_page' => 5,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertEquals( 6, $headers['X-WP-Total'] );
		$this->assertEquals( 2, $headers['X-WP-TotalPages'] );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$activity = $this->endpoint->get_activity_object( $data['id'] );
			$this->check_activity_data( $activity, $data, 'view', $response->get_links() );
		}

		$a_ids = wp_list_pluck( $all_data, 'id' );
		$this->assertContains( $a, $a_ids );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_with_favorite() {
		$this->bp->set_current_user( $this->user );

		$a1 = $this->bp_factory->activity->create();
		$a2 = $this->bp_factory->activity->create();
		$a3 = $this->bp_factory->activity->create();

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		bp_activity_add_user_favorite( $a2, $u );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$f_ids = wp_filter_object_list( $response->get_data(), array( 'favorited' => true ), 'AND', 'id' );
		$f_id  = reset( $f_ids );
		$this->assertEquals( $a2, $f_id );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_with_no_favorite() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$this->bp_factory->activity->create_many( 3, array( 'user_id' => $u ) );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$request->set_query_params( array(
			'user_id' => $u,
		) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$f_ids = wp_filter_object_list( $response->get_data(), array( 'favorited' => false ), 'AND', 'id' );
		$this->assertTrue( 3 === count( $f_ids ) );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$this->bp->set_current_user( $this->user );

		$activity = $this->endpoint->get_activity_object( $this->activity_id );
		$this->assertEquals( $this->activity_id, $activity->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_activity_data( $activity, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_for_item_belonging_to_private_group() {
		$component = buddypress()->groups->id;

		// Current user is $this->user.
		$g1 = $this->bp_factory->group->create( array(
			'status' => 'private',
		) );

		$a1 = $this->bp_factory->activity->create( array(
			'component'     => $component,
			'type'          => 'created_group',
			'user_id'       => $this->user,
			'item_id'       => $g1,
			'hide_sitewide' => true,
		) );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . $a1 );

		// Non-authenticated.
		$this->bp->set_current_user( 0 );
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		// Not a member of the group.
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );

		// Member of the group.
		$new_member               = new BP_Groups_Member();
		$new_member->group_id     = $g1;
		$new_member->user_id      = $u;
		$new_member->is_confirmed = true;
		$new_member->save();

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * @group render_item
	 */
	public function test_render_item() {
		$this->bp->set_current_user( $this->user );

		$a = $this->bp_factory->activity->create( array(
			'user_id' => $this->user,
			'content' => 'links should be clickable: https://buddypress.org',
		) );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $a ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$a_data = reset( $all_data );

		$this->assertTrue( false !== strpos( $a_data['content']['rendered'], '</a>' ) );
	}

	/**
	 * @group render_item
	 */
	public function test_render_item_with_embed_post() {
		$this->bp->set_current_user( $this->user );
		$p = $this->factory->post->create();

		$a = $this->bp_factory->activity->create( array(
			'user_id' => $this->user,
			'content' => get_post_embed_url( $p ),
		) );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $a ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$a_data = reset( $all_data );

		$this->assertTrue( false !== strpos( $a_data['content']['rendered'], 'wp-embedded-content' ) );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_activity_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_activity_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_rest_create_item() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_activity_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_activity_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_no_content() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_activity_data( array( 'content' => '' ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_create_activity_empty_content', $response, 500 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_activity_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_in_a_group() {
		$this->bp->set_current_user( $this->user );
		$g = $this->bp_factory->group->create( array(
			'creator_id' => $this->user,
		) );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_activity_data( array(
			'component'       => buddypress()->groups->id,
			'primary_item_id' => $g,
		) );

		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_activity_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_no_content_in_a_group() {
		$this->bp->set_current_user( $this->user );
		$g = $this->bp_factory->group->create( array(
			'creator_id' => $this->user,
		) );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_activity_data( array(
			'component'       => buddypress()->groups->id,
			'primary_item_id' => $g,
			'content'         => '',
		) );

		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_create_activity_empty_content', $response, 500 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_blog_post_item() {
		$this->bp->set_current_user( $this->user );
		$p = $this->factory->post->create();

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_activity_data( array(
			'component'         => buddypress()->blogs->id,
			'primary_item_id'   => get_current_blog_id(),
			'secondary_item_id' => $p,
			'type'              => 'new_blog_post',
			'hidden'            => true,
		) );

		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_activity_response( $response );

		$activity = bp_activity_get( array(
			'show_hidden'  => true,
			'search_terms' => $params['content'],
			'filter'       => array(
				'object'       => buddypress()->blogs->id,
				'primary_id'   => get_current_blog_id(),
				'secondary_id' => $p,
			),
		) );

		$activity = reset( $activity['activities'] );
		$a_ids    = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertContains( $activity->id, $a_ids );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$this->bp->set_current_user( $this->user );

		$activity = $this->endpoint->get_activity_object( $this->activity_id );
		$this->assertEquals( $this->activity_id, $activity->id );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_activity_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_update_activity_response( $response );

		$new_data = $response->get_data();
		$this->assertNotEmpty( $new_data );

		$new_data = $new_data[0];

		$this->assertEquals( $this->activity_id, $new_data['id'] );
		$this->assertEquals( $params['content'], $new_data['content']['raw'] );

		$activity = $this->endpoint->get_activity_object( $this->activity_id );
		$this->assertEquals( $params['content'], $activity->content );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_activity_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_not_logged_in() {
		$activity = $this->endpoint->get_activity_object( $this->activity_id );

		$this->assertEquals( $this->activity_id, $activity->id );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_without_permission() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$a = $this->bp_factory->activity->create( array( 'user_id' => $u ) );

		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u2 );

		$activity = $this->endpoint->get_activity_object( $a );
		$this->assertEquals( $a, $activity->id );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_activity_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$this->bp->set_current_user( $this->user );

		$activity_id  = $this->bp_factory->activity->create( array(
			'content' => 'Deleted activity',
		) );

		$activity = $this->endpoint->get_activity_object( $activity_id );
		$this->assertEquals( $activity_id, $activity->id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$this->assertEquals( 'Deleted activity', $data['content']['raw'] );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_activity_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$activity = $this->endpoint->get_activity_object( $this->activity_id );
		$this->assertEquals( $this->activity_id, $activity->id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_without_permission() {
		$u           = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$activity_id = $this->bp_factory->activity->create( array( 'user_id' => $u ) );

		$u2 = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u2 );

		$activity = $this->endpoint->get_activity_object( $activity_id );
		$this->assertEquals( $activity_id, $activity->id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_favorite
	 */
	public function test_update_favorite() {
		$a = $this->bp_factory->activity->create( array(
			'user_id' => $this->user,
		) );

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d/favorite', $a ) );
		$request->add_header( 'content-type', 'application/json' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$f_ids = wp_filter_object_list( $response->get_data(), array( 'favorited' => true ), 'AND', 'id' );
		$f_id  = reset( $f_ids );
		$this->assertEquals( $a, $f_id );
	}

	/**
	 * @group update_favorite
	 */
	public function test_update_favorite_remove() {
		$a = $this->bp_factory->activity->create( array(
			'user_id' => $this->user,
		) );

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		bp_activity_add_user_favorite( $a, $u );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d/favorite', $a ) );
		$request->add_header( 'content-type', 'application/json' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$f_ids = wp_filter_object_list( $response->get_data(), array( 'favorited' => true ), 'AND', 'id' );
		$this->assertEmpty( $f_ids );
	}

	/**
	 * @group update_favorite
	 */
	public function test_update_favorite_when_disabled() {
		$a = $this->bp_factory->activity->create( array(
			'user_id' => $this->user,
		) );

		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		add_filter( 'bp_activity_can_favorite', '__return_false' );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d/favorite', $a ) );
		$request->add_header( 'content-type', 'application/json' );
		$response = $this->server->dispatch( $request );

		remove_filter( 'bp_activity_can_favorite', '__return_false' );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	public function test_prepare_item() {
		$this->bp->set_current_user( $this->user );

		$activity = $this->endpoint->get_activity_object( $this->activity_id );
		$this->assertEquals( $this->activity_id, $activity->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $activity->id ) );
		$request->set_query_params( array( 'context' => 'edit' ) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_activity_data( $activity, $all_data[0], 'edit', $response->get_links() );
	}

	protected function check_activity_data( $activity, $data, $context, $links ) {
		$this->assertEquals( $activity->user_id, $data['user_id'] );
		$this->assertEquals( $activity->component, $data['component'] );

		if ( 'view' === $context ) {
			$this->assertEquals( wpautop( $activity->content ), $data['content']['rendered'] );
		} else {
			$this->assertEquals( $activity->content, $data['content']['raw'] );
		}

		$this->assertEquals( $activity->type, $data['type'] );
		$this->assertEquals( bp_rest_prepare_date_response( $activity->date_recorded ), $data['date'] );
		$this->assertEquals( $activity->id, $data['id'] );
		$this->assertEquals( bp_activity_get_permalink( $activity->id ), $data['link'] );
		$this->assertEquals( $activity->item_id, $data['primary_item_id'] );
		$this->assertEquals( $activity->secondary_item_id, $data['secondary_item_id'] );
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

	protected function set_activity_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'content'   => 'Activity content',
			'type'      => 'activity_update',
			'component' => buddypress()->activity->id,
		) );
	}

	protected function check_update_activity_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayNotHasKey( 'Location', $headers );

		$data = $response->get_data();

		$activity = $this->endpoint->get_activity_object( $data[0]['id'] );
		$this->check_activity_data( $activity, $data[0], 'edit', $response->get_links() );
	}

	protected function check_create_activity_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$activity = $this->endpoint->get_activity_object( $data[0]['id'] );
		$this->check_activity_data( $activity, $data[0], 'edit', $response->get_links() );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 17, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'primary_item_id', $properties );
		$this->assertArrayHasKey( 'secondary_item_id', $properties );
		$this->assertArrayHasKey( 'user_id', $properties );
		$this->assertArrayHasKey( 'link', $properties );
		$this->assertArrayHasKey( 'component', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'content', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'comments', $properties );
		$this->assertArrayHasKey( 'comment_count', $properties );
		$this->assertArrayHasKey( 'user_avatar', $properties );
		$this->assertArrayHasKey( 'hidden', $properties );
		$this->assertArrayHasKey( 'favorited', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->activity_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
