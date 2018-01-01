<?php
/**
 * Members Test.
 *
 * @package BP_REST
 */
class BP_Test_REST_Activity_Endpoint extends WP_Test_REST_Controller_Testcase {

	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->endpoint  = new BP_REST_Activity_Endpoint();

		$this->namespace = 'buddypress/v1';
		$this->rest_base = buddypress()->activity->id;
		$this->point     = $this->namespace . '/' . $this->rest_base;
	}

	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( $this->point, $routes );
		$this->assertCount( 2, $routes[ $this->point ] );
		$this->assertArrayHasKey( $this->point . '/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes[ $this->point . '/(?P<id>[\d]+)'] );
	}

	public function test_get_items() {
		return;
	}

	public function test_get_item() {
		return;
	}

	public function test_create_item() {
		return;
	}

	public function test_update_item() {
		return;
	}

	public function test_delete_item() {
		return;
	}

	public function test_prepare_item() {
		return;
	}

	public function test_get_item_schema() {
		return;
	}

	public function test_context_param() {
		return;
	}
}
