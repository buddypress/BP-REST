<?php
/**
 * XProfile Data Endpoint Tests.
 *
 * @package BP_REST
 * @group xprofile-data
 */
class BP_Test_REST_XProfile_Data_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_XProfile_Data_Endpoint();
		$this->field        = new BP_REST_XProfile_Fields_Endpoint();
		$this->endpoint_url = '/buddypress/v1/' . buddypress()->profile->id . '/data';
		$this->group_id     = $this->bp_factory->xprofile_group->create();
		$this->field_id     = $this->bp_factory->xprofile_field->create( [ 'field_group_id' => $this->group_id ] );
		$this->add_field_data( $this->field_id );

		$this->user = $this->factory->user->create( array(
			'role'       => 'administrator',
			'user_email' => 'admin@example.com',
		) );

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	protected function add_field_data( $field_id ) {
		$d1               = new stdClass();
		$d1->id           = 10;
		$d1->user_id      = bp_loggedin_user_id();
		$d1->field_id     = $field_id;
		$d1->value        = 'foo';
		$d1->last_updated = date( 'Y-m-d H:i:s', time() - 60 * 60 * 24 );
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 1, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 2, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$this->assertTrue( true );
	}

	/**
	 * @group testt
	 */
	public function test_get_item() {
		wp_set_current_user( $this->user );

		$field = $this->field->get_xprofile_field_object( $this->field_id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		var_dump( $all_data );

		//$this->check_field_data( $field, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_cannot_see_xprofile_field() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $u );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_view_xprofile_field', $response, 500 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_id() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_xprofile_field_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_field_data();
		$request->set_body_params( $params );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_field_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_rest_create_item() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->check_create_field_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_no_required_field() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data( array( 'type' => '' ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_invalid_type() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data( array( 'type' => 'group' ) );
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_without_permission() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $u );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_create_field', $response, 500 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$this->assertTrue( true );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		wp_set_current_user( $this->user );

		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$request->set_param( 'context', 'edit' );
		$response = $this->server->dispatch( $request );
		$this->assertNotInstanceOf( 'WP_Error', $response );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_field_data( $field, $all_data[0], 'view', $response->get_links() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_id() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_without_permission() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $u );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_delete_field', $response, 500 );
	}

	/**
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		wp_set_current_user( $this->user );

		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_field_data( $field, $all_data[0], 'edit', $response->get_links() );
	}

	protected function check_create_field_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$field = $this->endpoint->get_xprofile_field_object( $data[0]['id'] );
		$this->check_field_data( $field, $data[0], 'edit', $response->get_links() );
	}

	protected function set_field_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'field-id' => $this->field_id,
			'user-id'  => $this->user,
			'value'    => 'Field Value',
		) );
	}

	protected function check_field_data( $field, $data, $context, $links ) {
		$this->assertEquals( $field->id, $data['id'] );
		// $this->assertEquals( $field->group_id, $data['group_id'] );
		// $this->assertEquals( $field->parent_id, $data['parent_id'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 5, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'field-id', $properties );
		$this->assertArrayHasKey( 'user-id', $properties );
		$this->assertArrayHasKey( 'value', $properties );
		$this->assertArrayHasKey( 'last-updated', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
