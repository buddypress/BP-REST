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

		$this->avatar = trailingslashit( buddypress()->plugin_dir ) . 'bp-core/images/mystery-man.jpg';

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function test_register_routes() {
		$routes   = $this->server->get_routes();
		$endpoint = $this->endpoint_url . '(?P<user_id>[\d]+)/avatar';

		// Single.
		$this->assertArrayHasKey( $endpoint, $routes );
		$this->assertCount( 1, $routes[ $endpoint ] );
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
	 * @group test
	 */
	public function test_create_item() {
		$u = $this->bp_factory->user->create();

		$this->bp->set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/avatar', $u ) );
		$request->set_file_params(
			array(
				'file' => array(
					'file'     => file_get_contents( $this->avatar ),
					'name'     => 'mystery-man.jpg',
					'size'     => filesize( $this->avatar ),
					'tmp_name' => $this->avatar,
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_empty_image() {
		$this->bp->set_current_user( $this->user_id );

		$request  = new WP_REST_Request( 'POST', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertErrorResponse( 'bp_rest_member_avatar_no_data', $response, 500 );
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
	 * @group prepare_item
	 */
	public function test_prepare_item() {
		return true;
	}

	protected function check_avatar( $avatar, $data ) {
		$this->assertEquals( $avatar->name, $data['name'] );
		$this->assertEquals( $avatar->url, $data['url'] );
		$this->assertEquals( $avatar->weight, $data['weight'] );
		$this->assertEquals( $avatar->height, $data['height'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 4, count( $properties ) );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'url', $properties );
		$this->assertArrayHasKey( 'width', $properties );
		$this->assertArrayHasKey( 'height', $properties );
	}

	public function test_context_param() {

		// Single.
		$request  = new WP_REST_Request( 'OPTIONS', sprintf( $this->endpoint_url . '%d/avatar', $this->user_id ) );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
	}
}
