<?php
/**
 * Member Avatar Endpoints Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group member-avatar
 */
class BP_Test_REST_Member_Avatar_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Member_Avatar_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/members/';

		$this->user_id = $this->bp_factory->user->create( array(
			'role' => 'administrator',
		) );

		add_filter( 'bp_attachment_upload_overrides', array( $this, 'filter_overrides' ), 10, 1 );
		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ), 20, 1 );

		$this->upload_results      = array();
		$this->image_file          = trailingslashit( buddypress()->plugin_dir ) . 'bp-core/images/mystery-man.jpg';
		$this->original_upload_dir = array();

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function test_register_routes() {
		$routes   = $this->server->get_routes();
		$endpoint = $this->endpoint_url . '(?P<user_id>[\d]+)/avatar';

		// Single.
		$this->assertArrayHasKey( $endpoint, $routes );
		$this->assertCount( 3, $routes[ $endpoint ] );
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
		$u1 = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u1 );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', $u1 ) );
		$request->set_param( 'context', 'view' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$this->assertNotEmpty( $all_data[0]['image'] );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_invalid_member() {
		$u1 = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u1 );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_unauthorized_member() {
		$u1 = $this->bp_factory->user->create();
		$u2 = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u2 );

		$request  = new WP_REST_Request( 'GET', sprintf( $this->endpoint_url . '%d/avatar', $u1 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 403 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		return true;
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_empty_image() {
		$this->bp->set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_member_avatar_no_image_file', $response, 500 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_invalid_member() {
		$u1 = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u1 );

		$request  = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/avatar', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = rest_get_server()->dispatch( $request );
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
	 * @group delete_item
	 */
	public function test_delete_item_failed() {
		$this->bp->set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_member_avatar_delete_failed', $response, 500 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_not_logged_in() {
		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_invalid_member() {
		$u1 = $this->bp_factory->user->create();

		$this->bp->set_current_user( $u1 );

		$request  = new WP_REST_Request( 'DELETE', sprintf( $this->endpoint_url . '%d/avatar', REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_member_invalid_id', $response, 404 );
	}

	/**
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		return true;
	}

	protected function check_avatar_data( $avatar, $data ) {
		if ( isset( $data['image'] ) ) {
			$this->assertEquals( $avatar, $data['image'] );
		} else {
			$this->assertEquals( $avatar->full, $data['full'] );
			$this->assertEquals( $avatar->thumb, $data['thumb'] );
		}
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 2, count( $properties ) );
		$this->assertArrayHasKey( 'full', $properties );
		$this->assertArrayHasKey( 'thumb', $properties );
	}

	public function test_context_param() {

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
	}

	public function tearDown() {
		parent::tearDown();
		remove_filter( 'bp_attachment_upload_overrides', array( $this, 'filter_overrides' ), 10 );
		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ), 20 );

		$this->upload_results      = array();
		$this->image_file          = '';
		$this->original_upload_dir = array();
	}

	public function filter_overrides( $overrides ) {
		$overrides['upload_error_handler'] = array( $this, 'upload_error_handler' );

		// Don't test upload for WordPress < 4.0.
		$overrides['test_upload'] = false;
		return $overrides;
	}

	public function filter_upload_dir( $upload_dir ) {
		$upload_dir['error'] = 'fake_upload_success';

		$this->upload_results = array(
			'new_file' => $upload_dir['path'] . '/mystery-man.jpg',
			'url'      => $upload_dir['url'] . '/mystery-man.jpg',
		);

		return $upload_dir;
	}

	/**
	 * To avoid copying files in tests, we're faking a succesfull uploads
	 * as soon as all the test_form have been executed in _wp_handle_upload
	 */
	public function upload_error_handler( $file, $message ) {
		if ( 'fake_upload_success' !== $message ) {
			return array( 'error' => $message );
		} else {
			return array(
				'file' => $this->upload_results['new_file'],
				'url'  => $this->upload_results['url'],
				'type' => 'image/jpeg',
			);
		}
	}
}
