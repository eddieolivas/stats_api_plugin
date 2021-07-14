<?php
/*
    Plugin Name: Stats API
    Plugin URI: https://healthcaresuccess.com
    Description: Display resident and staff statistics
    Author: Eddie Olivas
    Version: 1.0
    Author URI: https://healthcaresuccess.com
*/
 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// A function to return all location IDs 
function get_location_ids() {
    $locationIds = array(
        'anchor',
        'capstone',
        'cobblestone',
        'fleetwood',
        'greer',
        'iva',
        'linley',
        'manna',
        'mccormick',
        'patewood',
        'poinsett',
        'theridge',
        'riverfalls',
        'simpsonville',
        'southernoaks'
    );

    return $locationIds;
}

// Upon plugin activation create a row in the wp_options table for each location
register_activation_hook( __FILE__, 'sahc_stats_activation' );
function sahc_stats_activation() {
    $locationIds = get_location_ids();
    foreach ( $locationIds as $locationId ) {
        $optionName = 'covid_stats_HTML_' . $locationId;
        if ( !get_option($optionName) ) {
            add_option($optionName, '');
        }
    }

    check_stats_for_updates();
}

// Upon plugin deactivation delete wp_options table for each location
register_deactivation_hook( __FILE__, 'sahc_stats_deactivation' );
function sahc_stats_deactivation() {
    $locationIds = get_location_ids();
    foreach ( $locationIds as $locationId ) {
        $optionName = 'covid_stats_HTML_' . $locationId;
        delete_option($optionName, '');
    }
}

// Shortcode [show_stats id=LOCATION_ID] pulls the HTML from the options table for the location specified in the id
function stats_api_func(  $atts ) {
    $locationId = $atts['id'];
    $response = get_option('covid_stats_HTML_' . $locationId);

    return $response;
}
add_shortcode( 'show_stats', 'stats_api_func' );

// Connects to the stats API Node app to check for updated stats
add_action('sahc_check_stats', 'check_stats_for_updates');
function check_stats_for_updates() {
    $locationIds = get_location_ids();
    $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJfaWQiOiI1ZWI5NzJjNjljNDc4MjM0MTRlOWQ3OTEiLCJpYXQiOjE1ODkyMTY5NTF9.wQ6PVioK7nGxGaZWiMzVvhm1QKHA86IDOqqQfMjvPOU';
    $options = array('http' => array(
        'method'  => 'GET',
        'header' => 'Authorization: Bearer '.$token
    ));
    $context  = stream_context_create($options);

    // Check the response for each location to see if it's been updated. If so, update the location data in the WordPress database
    foreach( $locationIds as $locationId ) {
        $api_url = 'https://covid-stats-distribution-api.herokuapp.com/locations/' . $locationId;
        $response = file_get_contents($api_url, false, $context);
        $optionName = 'covid_stats_HTML_' . $locationId;

        if ( $response !== get_option($optionName) ) {
            update_option($optionName, $response);
        }
    }
}

// Add an every 30 minute schedule to the cron schedules
add_filter( 'cron_schedules', 'add_thirty_minute_cron_interval' );
function add_thirty_minute_cron_interval( $schedules ) { 
    $schedules['thirty_minutes'] = array(
        'interval' => 1800,
        'display'  => esc_html__( 'Every Thirty minutes' ), );
    return $schedules;
}

// If there's no scheduled cron event to check the stats, schedule one in 30 minutes
if ( ! wp_next_scheduled( 'sahc_check_stats' ) ) {
    wp_schedule_event( time(), 'thirty_minutes', 'sahc_check_stats' );
}
