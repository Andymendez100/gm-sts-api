<?php 
/**
 * Gm STS Path API
 *
 * @package       Gm STS Path API
 * @author        Andy Mendez
 * @version       2.0.0
 *
 * @wordpress-plugin
 * Plugin Name:  Gm STS Path API

 * Description:   This plugin creates a custom REST API endpoint that retrieves data from the GM STS Path API.
 * Version:       2.0.1
 * Author:        Andy Mendez
 * License:       GPLv2
 */

 if (!defined('ABSPATH')) {
     die('You cannot be here');
 }

add_action('rest_api_init', 'create_rest_endpoint');
  
function create_rest_endpoint(){

      register_rest_route('gmsts/catalog', 'areaname', array(
        'methods' => 'POST',
        'callback' => 'handle_post_request',
      ));
}


function get_area_name($request) {
  $areaName = $request->get_param('areaName');

  if (empty($areaName)) {
      throw new Exception('No value for Area Name');
  }

  return $areaName;
}

function get_valid_token() {
  $token = get_transient('gm_sts_path_token');

  if (!isset($token) || !is_token_valid($token)) {
      $token = request_new_token();
  }

  return $token;
}

function make_post_request($url, $args) {
  $response = wp_remote_post($url, $args);

  if (is_wp_error($response)) {
      throw new Exception('Request error: ' . $response->get_error_message());
  }

  return $response;
}

function handle_post_request($request) {
  try {
      $areaName = get_area_name($request);
      $token = get_valid_token();

      $args = [
          'headers' => ['Content-Type' => 'application/json'],
          'body' => json_encode([
              'token' => trim($token['token'], '"'),
              'areaName' => $areaName,
          ]),
      ];

      $response = make_post_request('https://www.centerlearning.com/api/gmapi/path/getstspaths', $args);

      // If the request failed, try again with a new token
      if (is_wp_error($response) || wp_remote_retrieve_response_code($response) == 401) {
          $token = request_new_token();
          $args['body'] = json_encode([
              'token' => trim($token['token'], '"'),
              'areaName' => $areaName,
          ]);

          $response = make_post_request('https://www.centerlearning.com/api/gmapi/path/getstspaths', $args);
      }

      $body = wp_remote_retrieve_body($response);
      $gmAPIResponse = json_decode($body);
      $statusCode = wp_remote_retrieve_response_code($response);


      return new WP_REST_Response($gmAPIResponse, $statusCode);
  } catch (Exception $e) {
      return new WP_REST_Response(['message' => $e->getMessage()], 500);
  }
}


  
   function request_new_token() {
      // Set the request arguments
    $args = [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'username' => '3171298',
            'password' => 'Vertex#123',
        ]),
    ];

    // Make the POST request
    $response = wp_remote_post('https://www.centerlearning.com/api/gmapi/authentication/gettoken', $args);

    // Check for errors
    if (is_wp_error($response)) {
        throw new Exception('Request error: ' . $response->get_error_message());
    }

    // Get the response body
    $body = wp_remote_retrieve_body($response);

    // Create a new array to hold the token and the timestamp
    $token_data = [
        'token' => $body,
        'timestamp' => time(),
    ];

    // Save the token data in a transient that expires after 1 hour
    set_transient('gm_sts_path_token', $token_data, 3600);
    // Save the token data in memory
    // $token->token = $token_data;
  
    // Return the token data
    return $token_data;
  }


   function is_token_valid($token) {
    // Check if the token is still valid (less than 60 minutes old)
    if (isset($token['timestamp']) && (time() - $token['timestamp']) < 3600) {
      return true;
    } else {
      return false;
    }
  }