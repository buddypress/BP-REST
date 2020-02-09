<?php
/**
 * Group Cover Endpoints Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group group-cover
 */
class BP_Test_REST_Attachments_Group_Cover_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Attachments_Group_Cover_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->groups->id . '/';

		$this->user_id = $this->bp_factory->user->create( array(
			'role' => 'administrator',
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
		$endpoint = $this->endpoint_url . '(?P<group_id>[\d]+)/cover';

		// Single.
		$this->assertArrayHasKey( $endpoint, $routes );
		$this->assertCount( 3, $routes[ $endpoint ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$this->markTestSkipped();
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$this->markTestSkipped();
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_with_no_image() {
		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_attachments_group_cover_no_image', $response, 500 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_group_id() {
		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/cover', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$this->markTestSkipped();
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_no_valid_image_directory() {
		$this->bp->set_current_user( $this->user_id );

		$image_file = __DIR__ . '/assets/test-image.jpg';

		$_FILES['file'] = array(
			'tmp_name' => $image_file,
			'name'     => 'mystery-man.jpg',
			'type'     => 'image/jpeg',
			'error'    => 0,
			'size'     => filesize( $image_file ),
		);

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$request->set_file_params( $_FILES );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_attachments_group_cover_upload_error', $response, 500 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_empty_image() {
		$this->bp->set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_attachments_group_cover_no_image_file', $response, 500 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_unauthorized_user() {
		$u1 = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u1 );

		$request  = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_group_id() {
		$this->bp->set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/cover', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group update_item
	 */
	public function test_update_item() {
		$this->markTestSkipped();
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		$this->markTestSkipped();
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_unauthorized_user() {
		$u1 = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u1 );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_group_id() {
		$this->bp->set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/cover', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = $this->server->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_group_invalid_id', $response, 404 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_failed() {
		$this->bp->set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_attachments_group_cover_delete_failed', $response, 500 );
	}

	/**
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		$this->markTestSkipped();
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 1, count( $properties ) );
		$this->assertArrayHasKey( 'image', $properties );
	}

	public function test_context_param() {

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/cover', $this->group_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertNotEmpty( $data );
	}
}
