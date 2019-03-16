<?php
/**
 * XProfile Data Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group xprofile-data
 */
class BP_Test_REST_XProfile_Data_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_XProfile_Data_Endpoint();
		$this->field        = new BP_REST_XProfile_Fields_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->profile->id . '/';
		$this->group_id     = $this->bp_factory->xprofile_group->create();
		$this->field_id     = $this->bp_factory->xprofile_field->create( [ 'field_group_id' => $this->group_id ] );

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

		$endpoint = $this->endpoint_url . '(?P<field_id>[\d]+)/data/(?P<user_id>[\d]+)';

		$this->assertArrayHasKey( $endpoint, $routes );
		$this->assertCount( 2, $routes[ $endpoint ] );
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
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/data/%d', $this->field_id, $this->user ) );
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
		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/data/%d', $this->field_id, $this->user ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_without_permission() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/data/%d', $this->field_id, $this->user ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_field_id() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/data/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $this->user ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
		$request->set_body( wp_json_encode( $params ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_field_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_member_id() {
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/data/%d', $this->field_id, REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$request->add_header( 'content-type', 'application/json' );

		$params = $this->set_field_data();
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
		$g = $this->bp_factory->xprofile_group->create();
		$f = $this->bp_factory->xprofile_field->create( [ 'field_group_id' => $g ] );

		xprofile_set_field_data( $f, $this->user, 'foo' );

		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/data/%d', $f, $this->user ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$this->assertEmpty( $data[0]['value'] );

		$field_data = $this->endpoint->get_xprofile_field_data_object( $data[0]['field_id'], $data[0]['user_id'] );

		$this->assertEmpty( $field_data->value );
		$this->assertEmpty( $field_data->last_updated );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_field_owner_can_delete() {
		$u = $this->bp_factory->user->create();
		$g = $this->bp_factory->xprofile_group->create();
		$f = $this->bp_factory->xprofile_field->create( array(
			'field_group_id' => $g,
		) );

		xprofile_set_field_data( $f, $u, 'foo' );

		$this->bp->set_current_user( $u );

		$request = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/data/%d', $f, $u ) );
		$response = $this->server->dispatch( $request );

		$this->assertNotInstanceOf( 'WP_Error', $response );
		$response = rest_ensure_response( $response );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		$this->assertEmpty( $data[0]['value'] );

		$field_data = $this->endpoint->get_xprofile_field_data_object( $data[0]['field_id'], $data[0]['user_id'] );

		$this->assertEmpty( $field_data->value );
		$this->assertEmpty( $field_data->last_updated );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_field_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/data/%d', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER, $this->user ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_invalid_field_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_user_id() {
		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/data/%d', $this->field_id, REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/data/%d', $this->field_id, $this->user ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_without_permission() {
		$u = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->bp->set_current_user( $u );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/data/%d', $this->field_id, $this->user ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
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
		$this->assertNotEmpty( $data );

		$field_data = $this->endpoint->get_xprofile_field_data_object( $data[0]['field_id'], $data[0]['user_id'] );
		$this->check_field_data( $field_data, $data[0] );
	}

	protected function set_field_data( $args = array() ) {
		return wp_parse_args( $args, array(
			'value' => 'Field Value',
		) );
	}

	protected function check_field_data( $field_data, $data ) {
		$this->assertEquals( $field_data->field_id, $data['field_id'] );
		$this->assertEquals( $field_data->user_id, $data['user_id'] );
		$this->assertEquals( $field_data->value, $data['value'] );
		$this->assertEquals( bp_rest_prepare_date_response( $field_data->last_updated ), $data['last_updated'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/data/%d', $this->field_id, $this->user ) );
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
		return true;
	}
}
