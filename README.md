# BuddyPress RESTful API

[![Project Status: Active.](https://www.repostatus.org/badges/latest/active.svg)](https://www.repostatus.org/#active)

Access your BuddyPress site's data through an easy-to-use HTTP REST API.

## Documentation

We have extensive documentation of each endpoint/components and their CRUD actions: <https://developer.buddypress.org/bp-rest-api/>

## System Requirements (relevant for CI tests only)

* PHP >= 7.4
* WP >= 6.1
* BuddyPress >= Latest

## Installation

Drop this plugin in the wp-content/plugins directory and activate it. You need at least [WordPress 6.1](https://wordpress.org/download/) and [BuddyPress](https://buddypress.org/download/) to use the plugin.

## About

WordPress is moving towards becoming a fully-fledged application framework. BuddyPress can benefit from this new API by adding endpoints to access social data.

This plugin provides an easy to use REST API Endpoints for BuddyPress, available via HTTP. Grab your
site's data in simple JSON format, including users, groups, xprofile and more.
Retrieving or updating data is as simple as sending a HTTP request.
