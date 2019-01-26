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

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 1, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 1, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		return true;
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
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/x-www-form-urlencoded' );

		$params = $this->set_field_data();
		$request->set_body_params( $params );
		$response = $this->server->dispatch( $request );

		$this->check_create_field_response( $response );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_authorization_required', $response, 401 );
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
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_user_cannot_view_field_data', $response, 403 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_field_id() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data( [ 'field_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ] );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_member_id() {
		wp_set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data( [ 'user_id' => REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ] );
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		return true;
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		return true;
	}

	/**
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		return true;
	}

	protected function check_create_field_response( $response ) {
		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$field = $this->endpoint->get_xprofile_field_object( $data[0]['field_id'] );
		$this->check_field_data( $field, $data[0], 'edit', $response->get_links() );
	}

	protected function add_field_data( $field_id ) {
		$d1               = new stdClass();
		$d1->id           = 10;
		$d1->user_id      = bp_loggedin_user_id();
		$d1->field_id     = $field_id;
		$d1->value        = 'foo';
		$d1->last_updated = date( 'Y-m-d H:i:s', time() - 60 * 60 * 24 );
	}

	protected function set_field_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'field_id' => $this->field_id,
			'user_id'  => $this->user,
			'value'    => 'Field Value',
		) );
	}

	protected function check_field_data( $field, $data, $context, $links ) {
		$this->assertEquals( $field->id, $data['field_id'] );
		$this->assertEquals( $field->data->user_id, $data['user_id'] );
		$this->assertEquals( $field->data->value, $data['value'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 4, count( $properties ) );
		$this->assertArrayHasKey( 'field_id', $properties );
		$this->assertArrayHasKey( 'user_id', $properties );
		$this->assertArrayHasKey( 'value', $properties );
		$this->assertArrayHasKey( 'last_updated', $properties );
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

		// $this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		// $this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
