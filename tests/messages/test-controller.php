<?php
/**
 * Messages Endpoint Tests.
 *
 * @package BuddyPress
 * @subpackage BP_REST
 * @group messages
 */
class BP_Test_REST_Messages_Endpoint extends WP_Test_REST_Controller_Testcase {

	public function setUp() {
		parent::setUp();

		$this->bp_factory   = new BP_UnitTest_Factory();
		$this->endpoint     = new BP_REST_Messages_Endpoint();
		$this->bp           = new BP_UnitTestCase();
		$this->endpoint_url = '/' . bp_rest_namespace() . '/' . bp_rest_version() . '/' . buddypress()->messages->id;
		$this->user         = $this->factory->user->create( array(
			'role'       => 'administrator',
			'user_email' => 'admin@example.com',
		) );

		if ( ! $this->server ) {
			$this->server = rest_get_server();
		}
	}

	public function test_register_routes() {
		$routes   = $this->server->get_routes();
		$endpoint = $this->endpoint_url;

		// Main.
		$this->assertArrayHasKey( $endpoint, $routes );
		$this->assertCount( 2, $routes[ $endpoint ] );

		// Single.
		$single_endpoint = $endpoint . '/(?P<id>[\d]+)';

		$this->assertArrayHasKey( $single_endpoint, $routes );
		$this->assertCount( 2, $routes[ $single_endpoint ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$m1 = $this->bp_factory->message->create( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$m2 = $this->bp_factory->message->create( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params(
			array(
				'user_id' => $u1,
			)
		);

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();

		foreach ( $all_data as $data ) {
			$this->check_thread_data(
				$this->endpoint->get_thread_object( $data['id'] ),
				$data
			);
		}
	}

	/**
	 * @group get_item
	 */
	public function test_get_item() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$m = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
			'content'    => 'Content',
		) );

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . $m->thread_id );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$this->check_thread_data(
				$this->endpoint->get_thread_object( $data['id'] ),
				$data
			);
		}
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_admin_access() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$m = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . $m->thread_id );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$this->check_thread_data(
				$this->endpoint->get_thread_object( $data['id'] ),
				$data
			);
		}
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_with_no_access() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();

		$m = $this->bp_factory->message->create( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$this->bp->set_current_user( $u3 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . $m );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_get_item_user_is_not_logged_in() {
		$request = new WP_REST_Request( 'GET', $this->endpoint_url );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params(
			array(
				'sender_id'  => $this->user,
				'recipients' => [ $u ],
				'subject'    => 'Foo',
				'content'    => 'Content',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$this->check_thread_data(
				$this->endpoint->get_thread_object( $data['id'] ),
				$data
			);
		}
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_user_is_not_logged_in() {
		$u = $this->factory->user->create();

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params(
			array(
				'sender_id'  => $this->user,
				'recipients' => [ $u ],
				'subject'    => 'Foo',
				'content'    => 'Content',
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_no_content() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params(
			array(
				'sender_id'  => $this->user,
				'recipients' => [ $u ],
				'subject'    => 'Foo',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
	}

	/**
	 * @group create_item
	 */
	public function test_create_item_with_no_receipts() {
		$u = $this->factory->user->create();

		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params(
			array(
				'sender_id' => $this->user,
				'subject'   => 'Foo',
				'content'   => 'Content',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
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
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$m = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
			'content'    => 'Content',
		) );

		$this->bp->set_current_user( $u2 );

		$request  = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $m->thread_id );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$this->check_thread_data(
				$this->endpoint->get_thread_object( $data['id'] ),
				$data
			);
		}
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_admin_access() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$m = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$this->bp->set_current_user( $this->user );

		$request  = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $m->thread_id );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		foreach ( $all_data as $data ) {
			$this->check_thread_data(
				$this->endpoint->get_thread_object( $data['id'] ),
				$data
			);
		}
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_with_no_access() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();

		$m = $this->bp_factory->message->create( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$this->bp->set_current_user( $u3 );

		$request  = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $m );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group delete_item
	 */
	public function test_delete_item_user_is_not_logged_in() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$m = $this->bp_factory->message->create( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$request  = new WP_REST_Request( 'DELETE', $this->endpoint_url . '/' . $m );
		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'bp_rest_authorization_required', $response, rest_authorization_required_code() );
	}

	/**
	 * @group get_item
	 */
	public function test_prepare_item() {
		return true;
	}

	protected function check_thread_data( $thread, $data ) {
		// $this->assertEquals( $thread->thread_id, $data['id'] );
		// $this->assertEquals( $thread->last_message_id, $data['message_id'] );
		// $this->assertEquals( $thread->last_sender_id, $data['last_sender_id'] );
		// $this->assertEquals( wp_staticize_emoji( $thread->last_message_subject ), $data['subject'] );
		// $this->assertEquals( wp_staticize_emoji( $thread->last_message_content ), $data['content'] );
		// $this->assertEquals( bp_rest_prepare_date_response( $thread->last_message_date ), $data['date'] );
		// $this->assertEquals( $thread->unread_count, $data['unread_count'] );
		// $this->assertEquals( $thread->sender_ids, $data['sender_ids'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 11, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'message_id', $properties );
		$this->assertArrayHasKey( 'last_sender_id', $properties );
		$this->assertArrayHasKey( 'subject', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'unread_count', $properties );
		$this->assertArrayHasKey( 'sender_ids', $properties );
		$this->assertArrayHasKey( 'messages', $properties );
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
