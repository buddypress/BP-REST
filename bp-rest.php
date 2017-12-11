<?php
/**
 * Plugin Name: BuddyPress REST API
 * Plugin URI: https://buddypress.org
 * Description: BuddyPress extension for WordPress' JSON-based REST API.
 * Author: The BuddyPress Community
 * Author URI: https://buddypress.org/
 * Version: 0.1.0
 * Text Domain: bp-rest
 * Domain Path: /languages/
 * Requires at least: 4.7.0
 * Tested up to: 4.9.1
 * Requires PHP: 5.5
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BuddyPress
 * @since 0.1.0
 */

/**
 * Copyright (c) 2018 BuddyPress (email: contact@buddypress.org)
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

defined( 'ABSPATH' ) || exit;

/**
 * Register BuddyPress endpoints.
 *
 * @since 0.1.0
 */
function bp_rest_api_endpoints() {
	// Bail early if no core rest support.
	if ( ! class_exists( 'WP_REST_Controller' ) ) {
		return;
	}

	if ( bp_is_active( 'activity' ) ) {
		require_once( dirname( __FILE__ ) . '/includes/bp-activity/classes/class-bp-rest-activity-endpoint.php' );
		$controller = new BP_REST_Activity_Endpoint();
		$controller->register_routes();
	}

	if ( bp_is_active( 'groups' ) ) {
		require_once( dirname( __FILE__ ) . '/includes/bp-groups/classes/class-bp-rest-groups-endpoint.php' );
		$controller = new BP_REST_Groups_Endpoint();
		$controller->register_routes();
	}
}
add_action( 'bp_rest_api_init', 'bp_rest_api_endpoints' );
