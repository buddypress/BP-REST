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

		$this->old_current_user = get_current_user_id();
	}

	public function tearDown() {
		parent::tearDown();
		$this->bp->set_current_user( $this->old_current_user );
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
		$this->assertCount( 3, $routes[ $single_endpoint ] );

		// Starred.
		$starred_endpoint = $endpoint . '/' . bp_get_messages_starred_slug() . '/(?P<id>[\d]+)';

		$this->assertArrayHasKey( $starred_endpoint, $routes );
		$this->assertCount( 1, $routes[ $starred_endpoint ] );
	}

	/**
	 * @group get_items
	 */
	public function test_get_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$this->bp_factory->message->create( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$this->bp_factory->message->create( array(
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'subject'    => 'Fooo',
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
		$this->assertNotEmpty( $all_data );

		$data = current( $all_data );
		$this->check_thread_data( $this->endpoint->get_thread_object( $data['id'] ), $data );
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

		$data = current( $all_data );
		$this->check_thread_data( $this->endpoint->get_thread_object( $data['id'] ), $data );
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
				'message'    => 'Content',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$all_data = $response->get_data();
		$this->assertNotEmpty( $all_data );

		$data = current( $all_data );
		$this->check_thread_data( $this->endpoint->get_thread_object( $data['id'] ), $data );
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
				'message'    => 'Content',
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
		$this->bp->set_current_user( $this->user );

		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params(
			array(
				'sender_id' => $this->user,
				'subject'   => 'Foo',
				'message'   => 'Content',
			)
		);

		$response = $this->server->dispatch( $request );

		$this->assertErrorResponse( 'rest_missing_callback_param', $response, 400 );
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

		$data = $response->get_data();

		$this->assertNotEmpty( $data );
		$this->assertTrue( $data['deleted'] );
		$this->assertTrue( $data['previous']['subject']['rendered'] === 'Foo' );
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
		$request->set_query_params(
			array(
				'user_id' => $u2,
			)
		);
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertNotEmpty( $data );
		$this->assertTrue( $data['deleted'] );
		$this->assertTrue( $data['previous']['subject']['rendered'] === 'Foo' );
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
	 * @group starred
	 */
	public function test_get_starred_items() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		// Init a thread.
		$m1 = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		// Init another thread.
		$m2_id = $this->bp_factory->message->create( array(
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'subject'    => 'Taz',
		) );

		// Create a reply.
		$r1 = $this->bp_factory->message->create_and_get( array(
			'thread_id'  => $m1->thread_id,
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'content'    => 'Bar',
		) );

		$this->bp->set_current_user( $u1 );

		bp_messages_star_set_action( array(
			'user_id'    => $u1,
			'message_id' => $r1->id,
		) );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url );
		$request->set_query_params(
			array(
				'user_id' => $u1,
				'box'     => 'starred',
			)
		);

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$threads  = wp_list_pluck( $data, 'id' );
		$this->assertNotContains( $m2_id, $threads );
		$this->assertContains( $m1->thread_id, $threads );

		$result = reset( $data );
		$this->assertNotEmpty( $result['starred_message_ids'] );
		$this->assertContains( $r1->id, $result['starred_message_ids'] );
	}

	/**
	 * @group starred
	 */
	public function test_update_starred_add_star() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		// Init a thread.
		$m1 = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		// Create a reply.
		$r1 = $this->bp_factory->message->create_and_get( array(
			'thread_id'  => $m1->thread_id,
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'content'    => 'Bar',
		) );

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . bp_get_messages_starred_slug() . '/' . $r1->id );
		$request->add_header( 'content-type', 'application/json' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$data     = reset( $data );

		$this->assertTrue( $data['is_starred'] );
	}

	/**
	 * @group starred
	 */
	public function test_update_starred_remove_star() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		// Init a thread.
		$m = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		bp_messages_star_set_action( array(
			'user_id'    => $u2,
			'message_id' => $m->id,
		) );

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . bp_get_messages_starred_slug() . '/' . $m->id );
		$request->add_header( 'content-type', 'application/json' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$data     = reset( $data );

		$this->assertFalse( $data['is_starred'] );
	}

	public function update_additional_field( $value, $data, $attribute ) {
		return bp_messages_update_meta( $data->id, '_' . $attribute, $value );
	}

	public function get_additional_field( $data, $attribute )  {
		return bp_messages_get_meta( $data['id'], '_' . $attribute );
	}

	/**
	 * @group additional_fields
	 */
	public function test_additional_fields_for_get_item() {
		$registered_fields = $GLOBALS['wp_rest_additional_fields'];

		bp_rest_register_field( 'messages', 'taz_field', array(
			'get_callback'    => array( $this, 'get_additional_field' ),
			'update_callback' => array( $this, 'update_additional_field' ),
			'schema'          => array(
				'description' => 'Message Meta Field',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		) );

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		// Init a thread.
		$m1 = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Foo',
		) );

		$expected = 'boz_value';
		bp_messages_update_meta( $m1->id, '_taz_field', $expected );
		$this->bp->set_current_user( $u2 );

		// GET
		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . $m1->thread_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$get_data = $response->get_data();

		$last_message = wp_list_filter( $get_data[0]['messages'], array( 'id' => $get_data[0]['message_id'] ) );
		$last_message = reset( $last_message );
		$this->assertTrue( $expected === $last_message['taz_field'] );

		$GLOBALS['wp_rest_additional_fields'] = $registered_fields;
	}

	/**
	 * @group additional_fields
	 */
	public function test_additional_fields_for_created_thread() {
		$registered_fields = $GLOBALS['wp_rest_additional_fields'];

		bp_rest_register_field( 'messages', 'foo_field', array(
			'get_callback'    => array( $this, 'get_additional_field' ),
			'update_callback' => array( $this, 'update_additional_field' ),
			'schema'          => array(
				'description' => 'Message Meta Field',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		) );

		$u = $this->factory->user->create();
		$this->bp->set_current_user( $this->user );
		$expected = 'bar_value';

		// POST
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params(
			array(
				'sender_id'  => $this->user,
				'recipients' => [ $u ],
				'subject'    => 'Foo',
				'message'    => 'Bar',
				'foo_field'  => $expected,
			)
		);
		$response = $this->server->dispatch( $request );

		$create_data = $response->get_data();
		$last_message = wp_list_filter( $create_data[0]['messages'], array( 'id' => $create_data[0]['message_id'] ) );
		$last_message = reset( $last_message );
		$this->assertTrue( $expected === $last_message['foo_field'] );

		$GLOBALS['wp_rest_additional_fields'] = $registered_fields;
	}

	/**
	 * @group additional_fields
	 */
	public function test_additional_fields_for_created_reply() {
		$registered_fields = $GLOBALS['wp_rest_additional_fields'];

		bp_rest_register_field( 'messages', 'bar_field', array(
			'get_callback'    => array( $this, 'get_additional_field' ),
			'update_callback' => array( $this, 'update_additional_field' ),
			'schema'          => array(
				'description' => 'Message Meta Field',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		) );

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		// Init a thread.
		$m1 = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'subject'    => 'Foo',
		) );

		$this->bp->set_current_user( $u1 );
		$expected = 'foo_value';

		// POST a reply.
		$request = new WP_REST_Request( 'POST', $this->endpoint_url );
		$request->set_query_params(
			array(
				'id'         => $m1->thread_id,
				'sender_id'  => $u1,
				'recipients' => array( $u2 ),
				'message'    => 'Taz',
				'bar_field'  => $expected,
			)
		);
		$response = $this->server->dispatch( $request );
		$create_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNotEmpty( $create_data );

		$last_message = wp_list_filter( $create_data[0]['messages'], array( 'id' => $create_data[0]['message_id'] ) );
		$last_message = reset( $last_message );
		$this->assertTrue( $expected === $last_message['bar_field'] );

		$GLOBALS['wp_rest_additional_fields'] = $registered_fields;
	}

	/**
	 * @group additional_fields
	 */
	public function test_additional_fields_for_last_message_updated() {
		$registered_fields = $GLOBALS['wp_rest_additional_fields'];

		bp_rest_register_field( 'messages', 'boz_field', array(
			'get_callback'    => array( $this, 'get_additional_field' ),
			'update_callback' => array( $this, 'update_additional_field' ),
			'schema'          => array(
				'description' => 'Message Meta Field',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		) );

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		// Init a thread.
		$m1 = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'subject'    => 'Foo',
		) );

		$this->bp_factory->message->create_and_get( array(
			'thread_id'  => $m1->thread_id,
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Bar',
		) );

		$this->bp_factory->message->create_and_get( array(
			'thread_id'  => $m1->thread_id,
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'subject'    => 'Taz',
		) );

		$this->bp->set_current_user( $u2 );
		$expected = 'taz_value';

		// Update the last message.
		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . $m1->thread_id );
		$request->set_query_params(
			array(
				'boz_field'  => $expected,
			)
		);
		$response    = $this->server->dispatch( $request );
		$update_data = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNotEmpty( $update_data );

		$last_message = wp_list_filter( $update_data[0]['messages'], array( 'id' => $update_data[0]['message_id'] ) );
		$last_message = reset( $last_message );
		$this->assertTrue( $expected === $last_message['boz_field'] );

		$GLOBALS['wp_rest_additional_fields'] = $registered_fields;
	}

	/**
	 * @group additional_fields
	 */
	public function test_additional_fields_for_specific_message_updated() {
		$registered_fields = $GLOBALS['wp_rest_additional_fields'];

		bp_rest_register_field( 'messages', 'top_field', array(
			'get_callback'    => array( $this, 'get_additional_field' ),
			'update_callback' => array( $this, 'update_additional_field' ),
			'schema'          => array(
				'description' => 'Message Meta Field',
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
			),
		) );

		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		// Init a thread.
		$m1 = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'subject'    => 'Top',
		) );

		$r1 = $this->bp_factory->message->create_and_get( array(
			'thread_id'  => $m1->thread_id,
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Up',
		) );

		$r1 = $this->bp_factory->message->create_and_get( array(
			'thread_id'  => $m1->thread_id,
			'sender_id'  => $u2,
			'recipients' => array( $u1 ),
			'subject'    => 'Upper',
		) );

		$this->bp->set_current_user( $u2 );
		$expected = 'up_value';

		// Update the last message.
		$request = new WP_REST_Request( 'PUT', $this->endpoint_url . '/' . $m1->thread_id );
		$request->set_query_params(
			array(
				'message_id' => $r1->id,
				'top_field'  => $expected,
			)
		);
		$response = $this->server->dispatch( $request );

		$update_data = $response->get_data();
		$specific_message = wp_list_filter( $update_data[0]['messages'], array( 'id' => $r1->id ) );
		$specific_message = reset( $specific_message );
		$this->assertTrue( $expected === $specific_message['top_field'] );

		$GLOBALS['wp_rest_additional_fields'] = $registered_fields;
	}

	/**
	 * @group prepare_recipient_for_response
	 */
	public function test_prepare_prepare_recipient_for_response() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();
		$u3 = $this->factory->user->create();

		$m = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2, $u3 ),
			'subject'    => 'Foo',
			'content'    => 'Content',
		) );

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . $m->thread_id );
		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$get_data   = $response->get_data();
		$recipients = $get_data[0]['recipients'];

		foreach( array( $u1, $u2, $u3 ) as $user_id ) {
			$this->assertEquals( esc_url( bp_core_get_user_domain( $user_id ) ), $recipients[ $user_id  ]['user_link'] );

			foreach ( array( 'full', 'thumb' ) as $type ) {
				$expected['user_avatars'][ $type ] = bp_core_fetch_avatar(
					array(
						'item_id' => $user_id ,
						'html'    => false,
						'type'    => $type,
					)
				);

				$this->assertEquals( $expected['user_avatars'][ $type ], $recipients[ $user_id ]['user_avatars'][ $type ] );
			}
		}
	}

	/**
	 * @group prepare_links
	 */
	public function test_prepare_add_links_to_response() {
		$u1 = $this->factory->user->create();
		$u2 = $this->factory->user->create();

		$m1 = $this->bp_factory->message->create_and_get( array(
			'sender_id'  => $u1,
			'recipients' => array( $u2 ),
			'subject'    => 'Bar',
			'content'    => 'Content',
		) );

		$r1 = $this->bp_factory->message->create_and_get( array(
			'thread_id'  => $m1->thread_id,
			'sender_id'  => $u2,
			'content'    => 'Reply',
		) );

		$this->bp->set_current_user( $u2 );

		$request = new WP_REST_Request( 'GET', $this->endpoint_url . '/' . $m1->thread_id );

		$request->set_param( 'context', 'view' );
		$response = $this->server->dispatch( $request );

		$get_links = $response->get_data();
		$links     = $get_links[0]['_links'];

		$this->assertEquals( rest_url( $this->endpoint_url . '/' ), $links['collection'][0]['href'] );
		$this->assertEquals( rest_url( $this->endpoint_url . '/' . $m1->thread_id ), $links['self'][0]['href'] );
		$this->assertEquals( rest_url( $this->endpoint_url . '/' . bp_get_messages_starred_slug() . '/' . $m1->id ), $links[ $m1->id ][0]['href'] );
		$this->assertEquals( rest_url( $this->endpoint_url . '/' . bp_get_messages_starred_slug() . '/' . $r1->id ), $links[ $r1->id ][0]['href'] );
	}

	/**
	 * @group get_item
	 */
	public function test_prepare_item() {
		$this->markTestSkipped();
	}

	protected function check_thread_data( $thread, $data ) {
		$this->assertEquals( $thread->thread_id, $data['id'] );
		$this->assertEquals( $thread->last_message_id, $data['message_id'] );
		$this->assertEquals( $thread->last_sender_id, $data['last_sender_id'] );
		$this->assertEquals( apply_filters( 'bp_get_message_thread_subject', wp_staticize_emoji( $thread->last_message_subject ) ), $data['subject']['rendered'] );
		$this->assertEquals( apply_filters( 'bp_get_message_thread_content', wp_staticize_emoji( $thread->last_message_content ) ), $data['message']['rendered'] );
		$this->assertEquals( bp_rest_prepare_date_response( $thread->last_message_date ), $data['date'] );
		$this->assertEquals( $thread->unread_count, $data['unread_count'] );
		$this->assertEquals( $thread->sender_ids, $data['sender_ids'] );
	}

	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', $this->endpoint_url );
		$response   = $this->server->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$this->assertEquals( 12, count( $properties ) );
		$this->assertArrayHasKey( 'id', $properties );
		$this->assertArrayHasKey( 'message_id', $properties );
		$this->assertArrayHasKey( 'last_sender_id', $properties );
		$this->assertArrayHasKey( 'subject', $properties );
		$this->assertArrayHasKey( 'date', $properties );
		$this->assertArrayHasKey( 'unread_count', $properties );
		$this->assertArrayHasKey( 'sender_ids', $properties );
		$this->assertArrayHasKey( 'messages', $properties );
		$this->assertArrayHasKey( 'starred_message_ids', $properties );
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
