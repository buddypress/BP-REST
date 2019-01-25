<?php
/**
 * Group Invites Endpoint Tests.
 *
 * @package BP_REST
 * @group group-invites
 */
class BP_Test_REST_Group_Invites_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Group_Invites_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/buddypress/v1/group/invites';
		$this->user         = $this->factory->user->create( array(
			'role'       => 'administrator',
			'user_email' => 'admin@example.com',
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
		$routes = $this->server->get_routes();

		// Main.
		$this->assertArrayHasKey( $this->endpoint_url, $routes );
		$this->assertCount( 1, $routes[ $this->endpoint_url ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();
		$u4 = $this->factory->user->create();
		$u5 = $this->factory->user->create();

		$this->populate_group_with_invites( [ $u1, $u2, $u3, $u4 ], $this->group_id );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$u_ids = wp_list_pluck( $all_data, 'user_id' );

		// Check results.
		$this->assertEqualSets( [ $u1, $u2, $u3, $u4 ], $u_ids );
		$this->assertNotContains( $u5, $u_ids );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_user_not_logged_in() {
		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, 401 );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items_without_permission() {
		$u1 = $this->factory->user->create();
		$this->bp->set_current_user( $u1 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params( array(
			'group_id' => $this->group_id,
		) );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_user_cannot_view_group_invitations', $response, 403 );
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
		return true;
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item() {
		return true;
	}

	/**
	 * @group get_item
	 */
	public function test_prepare_item() {
		return true;
	}

	protected function check_invited_user_data( $user, $data ) {
		$this->assertEquals( $user->ID, $data['user_id'] );
		$this->assertEquals( $user->invite_sent, $data['invite_sent'] );
		$this->assertEquals( $user->inviter_id, $data['inviter_id'] );
		$this->assertEquals( $user->is_confirmed, $data['is_confirmed'] );
		$this->assertEquals( $user->membership_id, $data['membership_id'] );
	}

	protected function populate_group_with_invites( $users, $group_id ) {
		foreach ( $users as $user_id ) {
			groups_invite_user( array(
				'user_id'    => $user_id,
				'group_id'   => $group_id,
				'inviter_id' => $this->user,
			) );
		}
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 5, count( $properties ) );
		$this->assertArrayHasKey( 'user_id', $properties );
		$this->assertArrayHasKey( 'invite_sent', $properties );
		$this->assertArrayHasKey( 'inviter_id', $properties );
		$this->assertArrayHasKey( 'membership_id', $properties );
		$this->assertArrayHasKey( 'is_confirmed', $properties );
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
