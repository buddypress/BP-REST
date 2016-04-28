<?php
/**
 * Plugin Name: BP REST
 * Plugin URI:  https://buddypress.org
 * Description: Access your BuddyPress site's data through an easy-to-use HTTP REST API.
 * Version:	    0.1.0
 * Author:	    BuddyPress
 * Author URI:  https://buddypress.org
 * Donate link: https://buddypress.org
 * License:	    GPLv2 or later
 * Text Domain: bp-rest
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2016 BuddyPress (email: contact@buddypress.org)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register BuddyPress endpoints.
 *
 * @since 0.1.0
 */
function bp_rest_api_endpoints() {
	if ( bp_is_active( 'activity' ) ) {
		require_once( dirname( __FILE__ ) . '/lib/endpoints/class-bp-activity-endpoints.php' );
		$controller = new BP_REST_Activity_Controller();
		$controller->register_routes();
	}
}
add_action( 'bp_rest_api_init', 'bp_rest_api_endpoints', 11 );
