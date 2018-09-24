# BP REST API v1.0 (BP-API)

Access your BuddyPress site's data through an easy-to-use HTTP REST API.

[![Build Status](https://travis-ci.org/buddypress/BP-REST.svg?branch=master)](https://travis-ci.org/buddypress/BP-REST)

The current endpoints are pretty much in flux, as we work toward adding and updating them.

Please use and provide feedback!

## System Requirements

* PHP >= 5.6
* WP >= 4.9
* BuddyPress trunk (development version).

## Endpoints (Components) Supported

- [x] Activity
- [x] Members
- [x] Groups
- [x] Notifications

## Endpoints (Components) Partially Supported

- Messages
- XProfile Fields
- XProfile Groups

## Endpoints (Components) Pending

- [ ] Emails
- [ ] Friends
- [ ] Signups
- [ ] Components
- [ ] Group Invites
- [ ] Group Members
- [ ] Settings

## Installation

Drop this plugin in the wp-content/plugins directory and activate it. You need at least [WordPress 4.7](https://wordpress.org/download/) to use the plugin.

## About

WordPress is moving towards becoming a fully-fledged application framework. BuddyPress can benefit from this new API by adding endpoints to access social data.

This plugin provides an easy to use REST API Endpoints for BuddyPress, available via HTTP. Grab your
site's data in simple JSON format, including users, groups, xprofile and more.
Retrieving or updating data is as simple as sending a HTTP request.

There's no fixed timeline for integration into BuddyPress core at this time, the BP REST API will be available as a feature plugin!

## Get Involved

Weekly BP REST API dev chat are held on Wednesdays at 22:00 UTC in WordPress Slack channel [#buddypress](https://wordpress.slack.com/archives/buddypress)
