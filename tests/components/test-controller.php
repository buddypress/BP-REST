<?php
/**
 * Components Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group components
 */
class BP_Test_REST_Components_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Components_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/components';
		$this->user         = $this->factory->user->create( array(
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
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->assertTrue( 10 === count( $all_data ) );

		foreach ( $all_data as $component ) {
			$component = $this->endpoint->get_component_info( $component['name'] );
			$this->check_component_data( $component, $component );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_paginated() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'per_page' => 5,
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertEquals( 10, $headers['X-WP-Total'] );
		$this->assertEquals( 2, $headers['X-WP-TotalPages'] );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->assertTrue( 10 === count( $all_data ) );

		foreach ( $all_data as $component ) {
			$component = $this->endpoint->get_component_info( $component['name'] );
			$this->check_component_data( $component, $component );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_invalid_status() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'status' => 'another',
		) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_is_not_logged_in() {
		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_without_permission() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 403 );
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
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'name'   => 'blogs',
			'action' => 'deactivate',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->assertTrue( 'inactive' === $all_data[0]['status'] );

		$component = $this->endpoint->get_component_info( 'blogs' );
		$this->check_component_data( $component, $all_data[0] );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_nonexistent_component() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'name'   => 'blogssss',
			'action' => 'deactivate',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_component_nonexistent', $response, 500 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_empty_action() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'name'   => 'blogs',
			'action' => '',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_component_invalid_action', $response, 500 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_action() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'name'   => 'blogs',
			'action' => 'update',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_component_invalid_action', $response, 500 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_is_not_logged_in() {
		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'name'   => 'core',
			'action' => 'activate',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_without_permission() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url );
		$request->set_query_params( array(
			'name'   => 'core',
			'action' => 'activate',
		) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 403 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		return true;
	}

	public function test_prepare_item() {
		return true;
	}

	protected function check_component_data( $component, $data ) {
		$this->assertEquals( $component['name'], $data['name'] );
		$this->assertEquals( $component['status'], $data['status'] );
		$this->assertEquals( $component['title'], $data['title'] );
		$this->assertEquals( $component['description'], $data['description'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 4, count( $properties ) );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'status', $properties );
		$this->assertArrayHasKey( 'title', $properties );
		$this->assertArrayHasKey( 'description', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
