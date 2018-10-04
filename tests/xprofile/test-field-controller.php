<?php
/**
 * XProfile Field Endpoint Tests.
 *
 * @package BP_REST
 * @group xprofile-field
 */
class BP_Test_REST_XProfile_Fields_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_XProfile_Fields_Endpoint();
		$this->endpoint_url = '/buddypress/v1/' . buddypress()->profile->id . '/fields';
		$group              = $this->bp_factory->xprofile_group->create();
		$this->field_id     = $this->bp_factory->xprofile_field->create( [ 'field_group_id' => $group ] );

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
		// $this->assertArrayHasKey( $this->endpoint_url, $routes );
		// $this->assertCount( 1, $routes[ $this->endpoint_url ] );

		// Single.
		$this->assertArrayHasKey( $this->endpoint_url . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 1, $routes[ $this->endpoint_url . '/(?P<id>[\d]+)' ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$this->assertTrue( true );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		wp_set_current_user( $this->user );

		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );
		$this->assertEquals( $this->field_id, $field->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_field_data( $field, $all_data[0], 'view', $response->get_links() );
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
		$this->assertTrue( true );
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
		$this->assertTrue( true );
	}

	/**
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		wp_set_current_user( $this->user );

		$field = $this->endpoint->get_xprofile_field_object( $this->field_id );
		$this->assertEquals( $this->field_id, $field->id );

		$request = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '/%d', $field->id ) );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		$this->check_field_data( $field, $all_data[0], 'edit', $response->get_links() );
	}

	protected function check_field_data( $field, $data, $context, $links ) {
		$this->assertEquals( $field->id, $data['id'] );
		$this->assertEquals( $field->group_id, $data['group_id'] );
		$this->assertEquals( $field->parent_id, $data['parent_id'] );
		$this->assertEquals( $field->type, $data['type'] );
		$this->assertEquals( $field->name, $data['name'] );
		$this->assertEquals( $field->description, $data['description'] );
		$this->assertEquals( $field->is_required, $data['is_required'] );
		$this->assertEquals( $field->can_delete, $data['can_delete'] );
		$this->assertEquals( $field->field_order, $data['field_order'] );
		$this->assertEquals( $field->option_order, $data['option_order'] );
		$this->assertEquals( $field->order_by, $data['order_by'] );
		$this->assertEquals( $field->is_default_option, $data['is_default_option'] );

		if ( ! empty( $data['visibility_level'] ) ) {
			$this->assertEquals( $field->visibility_level, $data['visibility_level'] );
		}
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 14, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'group_id', $properties );
		$this->assertArrayHasKey( 'parent_id', $properties );
		$this->assertArrayHasKey( 'type', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'is_required', $properties );
		$this->assertArrayHasKey( 'can_delete', $properties );
		$this->assertArrayHasKey( 'field_order', $properties );
		$this->assertArrayHasKey( 'option_order', $properties );
		$this->assertArrayHasKey( 'order_by', $properties );
		$this->assertArrayHasKey( 'is_default_option', $properties );
		$this->assertArrayHasKey( 'visibility_level', $properties );
		$this->assertArrayHasKey( 'data', $properties );
	}

	public function test_context_param() {
		// Collection.
		$request  = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		// $this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		// $this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '/%d', $this->field_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}
}
