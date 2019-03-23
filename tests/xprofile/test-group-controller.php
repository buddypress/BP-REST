<?php
/**
 * XProfile Field Groups Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group xprofile-group
 */
class BP_Test_REST_XProfile_Groups_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_XProfile_Field_Groups_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->profile->id . '/groups';
		$this->group_id     = $this->bp_factory->xprofile_group->create();

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
	 * @group get_items
	 */
	public function test_get_items() {
		$this->bp->set_current_user( $this->user );

		$this->bp_factory->xprofile_group->create_many( 5 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$field_group = $this->endpoint->get_xprofile_field_group_object( $data['id'] );
			$this->check_group_data( $field_group, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_publicly() {
		$this->bp_factory->xprofile_group->create_many( 5 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$field_group = $this->endpoint->get_xprofile_field_group_object( $data['id'] );
			$this->check_group_data( $field_group, $data, 'view', $response->get_links() );
		}
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$this->bp->set_current_user( $this->user );

		$field_group = $this->endpoint->get_xprofile_field_group_object( $this->group_id );
		$this->assertEquals( $this->group_id, $field_group->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field_group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_group_data( $field_group, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_publicly() {
		$field_group = $this->endpoint->get_xprofile_field_group_object( $this->group_id );
		$this->assertEquals( $this->group_id, $field_group->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field_group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_group_data( $field_group, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_id() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_field_group_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_field_group_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_field_group_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_rest_create_item() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_group_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->check_create_field_group_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_group_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_without_permission() {
		$u = $this->factory->user->create();
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_group_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$new_name = 'Updated name';
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $this->group_id ) );
		$request->add_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'name' => $new_name ] ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$object  = end( $all_data );
		$updated = $this->endpoint->get_xprofile_field_group_object( $object['id'] );

		$this->assertSame( $new_name, $updated->name );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_invalid_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_field_group_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $this->group_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item_without_permission() {
		$u = $this->factory->user->create();
		$this->bp->set_current_user( $u );

		$request  = new WP_REST_Request( 'PUT', sprintf( $this->endpoint_url . '/%d', $this->group_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$this->bp->set_current_user( $this->user );

		$field_group = $this->endpoint->get_xprofile_field_group_object( $this->group_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $field_group->id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_group_data( $field_group, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_field_group_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->group_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_without_permission() {
		$u = $this->factory->user->create();
		$this->bp->set_current_user( $u );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->group_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		$this->bp->set_current_user( $this->user );

		$group = $this->endpoint->get_xprofile_field_group_object( $this->group_id );
		$this->assertEquals( $this->group_id, $group->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $group->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->check_group_data( $group, $all_data[0], 'view', $response->get_links() );
	}

	protected function check_create_field_group_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$field_group = $this->endpoint->get_xprofile_field_group_object( $data[0]['id'] );
		$this->check_group_data( $field_group, $data[0], 'view', $response->get_links() );
	}

	protected function check_group_data( $group, $data, $context, $links ) {
		$this->assertEquals( $group->id, $data['id'] );
		$this->assertEquals( $group->name, $data['name'] );
		$this->assertEquals( $group->group_order, $data['group_order'] );
		$this->assertEquals( $group->can_delete, $data['can_delete'] );

		if ( 'view' === $context ) {
			$this->assertEquals( $group->description, $data['description']['rendered'] );
		} else {
			$this->assertEquals( $group->description, $data['description']['raw'] );
		}
	}

	protected function set_field_group_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'description' => 'Field Group Description',
			'name'        => 'Test Field Name',
			'can_delete'  => true,
		) );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->group_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 6, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'group_order', $properties );
		$this->assertArrayHasKey( 'can_delete', $properties );
		$this->assertArrayHasKey( 'fields', $properties );
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
