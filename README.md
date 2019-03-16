# BuddyPress RESTful API

Access your BuddyPress site's data through an easy-to-use HTTP REST API.

[![Build Status](https://travis-ci.org/buddypress/BP-REST.svg?branch=master)](https://travis-ci.org/buddypress/BP-REST)

The current endpoints are pretty much in flux, as we work toward adding and updating them.

Please use and provide feedback!

## System Requirements

* PHP >= 5.6
* WP >= 4.7
* BuddyPress = trunk

## Endpoints (Components) Supported

- [x] Activity `http://site.com/wp-json/buddypress/v1/activity`
- [x] Groups `http://site.com/wp-json/buddypress/v1/groups`
- [x] Group Membership `http://site.com/wp-json/buddypress/v1/groups/<group_id>/members`
- [x] Group Membership Request(s) `http://site.com/wp-json/buddypress/v1/groups/{group_id}/membership-request/`
- [x] Group Avatar `http://site.com/wp-json/buddypress/v1/groups/<group_id>/avatar`
- [x] Group Invites `http://site.com/wp-json/buddypress/v1/groups/<group_id>/invites`
- [x] XProfile Fields `http://site.com/wp-json/buddypress/v1/xprofile/fields`
- [x] XProfile Groups `http://site.com/wp-json/buddypress/v1/xprofile/groups`
- [x] XProfile Data `http://site.com/wp-json/buddypress/v1/xprofile/<field_id>/data/<user_id>`
- [x] Members `http://site.com/wp-json/buddypress/v1/members`
- [x] Members Profile Photo (aka Avatar) `http://site.com/wp-json/buddypress/v1/members/<user_id>/avatar`
- [x] Notifications `http://site.com/wp-json/buddypress/v1/notifications`
- [x] Components `http://site.com/wp-json/buddypress/v1/components`

## Endpoints (Components) Partly Supported

- [x] Messages `http://site.com/wp-json/buddypress/v1/messages`

## Endpoints (Components) Pending

- Friends
- Signups
- Settings
- Emails

## Installation

Drop this plugin in the wp-content/plugins directory and activate it. You need at least [WordPress 4.7](https://wordpress.org/download/) and [BuddyPress](https://buddypress.org/download/) to use the plugin.

## About

WordPress is moving towards becoming a fully-fledged application framework. BuddyPress can benefit from this new API by adding endpoints to access social data.

This plugin provides an easy to use REST API Endpoints for BuddyPress, available via HTTP. Grab your
site's data in simple JSON format, including users, groups, xprofile and more.
Retrieving or updating data is as simple as sending a HTTP request.

There's no fixed timeline for integration into BuddyPress core at this time, the BP REST API will be available as a feature plugin!
