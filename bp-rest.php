<?php
/**
 * Plugin Name: BuddyPress REST API
 * Plugin URI: https://buddypress.org
 * Description: BuddyPress extension for WordPress' JSON-based REST API.
 * Author: The BuddyPress Community
 * Author URI: https://buddypress.org/
 * Version: 0.1.0
 * Text Domain: bp-rest
 * Domain Path: languages/
 * Requires at least: 4.7
 * Tested up to: 5.1
 * Requires PHP: 5.6
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BuddyPress
 * @since 0.1.0
 */

/**
 * Copyright (c) 2019 BuddyPress (email: contact@buddypress.org)
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
add_action(
	'rest_api_init',
	function() {

		// Bail early if no core rest support.
		if ( ! class_exists( 'WP_REST_Controller' ) ) {
			return;
		}

		require_once dirname( __FILE__ ) . '/includes/bp-components/classes/class-bp-rest-components-endpoint.php';
		$controller = new BP_REST_Components_Endpoint();
		$controller->register_routes();

		if ( bp_is_active( 'members' ) ) {
			require_once dirname( __FILE__ ) . '/includes/bp-members/classes/class-bp-rest-members-endpoint.php';
			$controller = new BP_REST_Members_Endpoint();
			$controller->register_routes();

			require_once dirname( __FILE__ ) . '/includes/bp-attachments/classes/trait-attachments.php';
			require_once dirname( __FILE__ ) . '/includes/bp-attachments/classes/class-bp-rest-attachments-member-avatar-endpoint.php';
			$controller = new BP_REST_Attachments_Member_Avatar_Endpoint();
			$controller->register_routes();
		}

		if ( bp_is_active( 'activity' ) ) {
			require_once dirname( __FILE__ ) . '/includes/bp-activity/classes/class-bp-rest-activity-endpoint.php';
			$controller = new BP_REST_Activity_Endpoint();
			$controller->register_routes();
		}

		if ( bp_is_active( 'xprofile' ) ) {
			require_once dirname( __FILE__ ) . '/includes/bp-xprofile/classes/class-bp-rest-xprofile-fields-endpoint.php';
			$controller = new BP_REST_XProfile_Fields_Endpoint();
			$controller->register_routes();

			require_once dirname( __FILE__ ) . '/includes/bp-xprofile/classes/class-bp-rest-xprofile-field-groups-endpoint.php';
			$controller = new BP_REST_XProfile_Field_Groups_Endpoint();
			$controller->register_routes();

			require_once dirname( __FILE__ ) . '/includes/bp-xprofile/classes/class-bp-rest-xprofile-data-endpoint.php';
			$controller = new BP_REST_XProfile_Data_Endpoint();
			$controller->register_routes();
		}

		if ( bp_is_active( 'groups' ) ) {
			require_once dirname( __FILE__ ) . '/includes/bp-groups/classes/class-bp-rest-groups-endpoint.php';
			$controller = new BP_REST_Groups_Endpoint();
			$controller->register_routes();

			require_once dirname( __FILE__ ) . '/includes/bp-groups/classes/class-bp-rest-group-membership-endpoint.php';
			$controller = new BP_REST_Group_Membership_Endpoint();
			$controller->register_routes();

			require_once dirname( __FILE__ ) . '/includes/bp-groups/classes/class-bp-rest-group-membership-request-endpoint.php';
			$controller = new BP_REST_Group_Membership_Request_Endpoint();
			$controller->register_routes();

			require_once dirname( __FILE__ ) . '/includes/bp-groups/classes/class-bp-rest-group-invites-endpoint.php';
			$controller = new BP_REST_Group_Invites_Endpoint();
			$controller->register_routes();

			require_once dirname( __FILE__ ) . '/includes/bp-attachments/classes/trait-attachments.php';
			require_once dirname( __FILE__ ) . '/includes/bp-attachments/classes/class-bp-rest-attachments-group-avatar-endpoint.php';
			$controller = new BP_REST_Attachments_Group_Avatar_Endpoint();
			$controller->register_routes();
		}

		if ( bp_is_active( 'messages' ) ) {
			require_once dirname( __FILE__ ) . '/includes/bp-messages/classes/class-bp-rest-messages-endpoint.php';
			$controller = new BP_REST_Messages_Endpoint();
			$controller->register_routes();
		}

		if ( bp_is_active( 'notifications' ) ) {
			require_once dirname( __FILE__ ) . '/includes/bp-notifications/classes/class-bp-rest-notifications-endpoint.php';
			$controller = new BP_REST_Notifications_Endpoint();
			$controller->register_routes();
		}
	}
);

/**
 * Load functions so that they can also be used out of a REST request.
 *
 * @since 0.1.0
 */
add_action(
	'bp_include',
	function() {
		require_once dirname( __FILE__ ) . '/includes/functions.php';
	}
);
