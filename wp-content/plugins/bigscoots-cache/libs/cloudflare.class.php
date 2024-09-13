<?php
namespace BigScoots\Cache;

defined('ABSPATH') || wp_die('Cheatin&#8217; uh?');

class Cloudflare
{
  private \BigScoots_Cache $main_instance;
  private array $objects = [];

  public function __construct($main_instance)
  {
    $this->main_instance = $main_instance;
  }

  /**
   * Decrypts an encrypted salt using AES-256-CBC algorithm.
   *
   * @param string $encrypted_salt The encrypted salt string.
   * @return string|bool The decrypted salt on success, or false on failure.
  **/
  private function decrypt_salt(string $encrypted_salt)
  {
    // Check if the encrypted salt has any data before proceeding
    if ( !empty($encrypted_salt) ) {
      // Define the key and IV in hexadecimal format
      $keyHex = '546573746b6579';
      $ivHex = 'c25e89f34f9878266bede60ed2feddde';

      // Convert the key and IV from hexadecimal to binary
      $key = hex2bin($keyHex);
      $iv = hex2bin($ivHex);

      // Perform decryption using OpenSSL
      $decrypted = openssl_decrypt( base64_decode($encrypted_salt), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

      // Return the decrypted salt on success otherwise return DECRYPTION_FAILED
      return $decrypted ?: 'DECRYPTION_FAILED';
    }

    // Return false if decryption fails or the encrypted salt is empty
    return false;
  }

  /**
   * This function returns Cloudflare API headers based on provided authentication information.
   * 
   * @param using_curl A boolean parameter that indicates whether the headers are being used for a cURL
   * request or not. If it is set to true, the headers will be formatted differently to be used with
   * cURL.
   * 
   * @return an array of Cloudflare API headers, which includes either the X-Auth-Email and X-Auth-Key
   * headers or the Authorization header, depending on whether a global API key or API token has been
   * provided. The Content-Type header is also included in both cases. The headers are returned as an
   * array.
  **/
  private function get_cf_api_headers(bool $using_curl = false) : array
  {
    // Declare cf headers variable as a blank array
    $cf_headers = [];

    // Now check if email & global API key has been provided in the PHP constant or API token has been provided
    if ( defined( 'BS_SITE_CF_EMAIL_SALT' ) && defined( 'BS_SITE_CF_API_KEY_SALT' ) ) {

      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      $cf_email = $this->decrypt_salt( BS_SITE_CF_EMAIL_SALT );
      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      $cf_api_key = $this->decrypt_salt( BS_SITE_CF_API_KEY_SALT );

      // Check if the decryption worked properly if so proceed further
      if ( in_array( 'DECRYPTION_FAILED', [ $cf_email, $cf_api_key ] ) ) {
        $error = __('CF Email & Global API Key decryption failed. Check the encryption & decryption manually.', 'bigscoots-cache');
        $this->objects['logs']->add_log('cloudflare::decrypt_salt', $error);
        return $cf_headers;
      }

      // Check if we need the header for cURL request or wp_safe_remote_post()
      if ( $using_curl ) {
        $cf_headers = [
          "X-Auth-Email: {$cf_email}",
          "X-Auth-Key: {$cf_api_key}",
          'Cache-Control: no-cache',
          'Content-Type: application/json'
        ];
      } else {
        $cf_headers = [
          'X-Auth-Email'  => $cf_email,
          'X-Auth-Key'    => $cf_api_key,
          'Cache-Control' => 'no-cache',
          'Content-Type'  => 'application/json'
        ];
      }

    } elseif ( defined( 'BS_SITE_CF_API_TOKEN_SALT' ) ) { // CF API Token provided instead of global API key

      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      $cf_api_token = $this->decrypt_salt( BS_SITE_CF_API_TOKEN_SALT );

      // Check if the decryption worked properly if so proceed further
      if ( $cf_api_token === 'DECRYPTION_FAILED' ) {
        $error = __('CF API Token decryption failed. Check the encryption & decryption manually.', 'bigscoots-cache');
        $this->objects['logs']->add_log('cloudflare::decrypt_salt', $error);
        return $cf_headers;
      }

      // Check if we need the header for cURL request or wp_safe_remote_post()
      if ( $using_curl ) {
        $cf_headers = [
          "Authorization: Bearer {$cf_api_token}",
          'Cache-Control: no-cache',
          'Content-Type: application/json'
        ];
      } else {
        $cf_headers = [
          'Authorization' =>  "Bearer {$cf_api_token}",
          'Cache-Control' =>  'no-cache',
          'Content-Type'  =>  'application/json'
        ];
      }
    }

    return $cf_headers;
  }

  /**
   * Process URLs and categorize them into prefix purge URLs and file purge URLs.
   *
   * @param array $urls Array of URL strings
   * @return array Associative array with keys 'prefix_purge_urls', 'cache_tags_to_purge', and 'prefix_purge_friendly'
  **/
  private function process_urls(array $urls): array
  {
    // Initialize arrays
    $prefix_purge_urls = [];
    $cache_tags_to_purge = [];

    // Loop through each URL
    foreach ($urls as $url) {
      // Remove query parameters using strtok instead of regular expressions for better performance
      $url_query_removed = strtok($url, '?');

      // Check if the URL is just a hostname URL
      if ( preg_match('/^(https?:\/\/)?([^\/]+)\/?$/', $url_query_removed, $matches) ) {
        // Extract the host from the regex match
        $hostname = $matches[2];

        // The URL is a hostname URL, add '{$hostname}_front_page' to $cache_tags_to_purge
        $cache_tags_to_purge[] = "{$hostname}_front_page";

      } elseif ( preg_match('/%[0-9a-fA-F]{2}/', $url_query_removed) ) { // Check if URL contains HTML escaped characters
        $this->objects = $this->main_instance->get_objects();

        // Generate cache tag for this URL
        $cache_tag = $this->objects['cache_controller']->get_cache_tag($url);

        // The URL has special HTML encoded characters in it (e.g. %2a, %c2) URL, add the generated cache tag to $cache_tags_to_purge
        $cache_tags_to_purge[] = $cache_tag;

      } elseif ( substr($url_query_removed, -1) === '/' ) { // Check if the URL is ending with a trailing slash, if so, add the URL (after removing query parameters) to $prefix_purge_urls

        $prefix_purge_urls[] = $url_query_removed;

      } else { // the URL is not a hostname URL but it doesn't have trailing slash, so let's add it ourself to take advantage of prefix purge. Don't worry, it will also purge the URL without the trailing slash - already tested

        $prefix_purge_urls[] = $url_query_removed . '/'; // Make sure we are adding the trailing slash at the end of the URL

      }
    }

    // Remove protocol details from URLs in $prefix_purge_urls using str_replace for better performance
    $prefix_purge_urls = array_map(function ($url) : string {
      return str_replace(['https://', 'http://'], '', $url);
    }, $prefix_purge_urls);

    // Determine the value of $prefix_purge_friendly
    $prefix_purge_friendly = '';

    // Determine the value of $prefix_purge_friendly based on the emptiness of $prefix_purge_urls and $cache_tags_to_purge
    if (empty($prefix_purge_urls) && empty($cache_tags_to_purge)) {
      // Both arrays are empty, which should ideally not happen. Set $prefix_purge_friendly to indicate no URL data.
      $prefix_purge_friendly = 'no_url_data';
    } elseif (empty($prefix_purge_urls)) {
      // $prefix_purge_urls is empty but $cache_tags_to_purge has data.
      // This means the function received only hostname URLs.
      $prefix_purge_friendly = false;
    } elseif (empty($cache_tags_to_purge)) {
      // $cache_tags_to_purge is empty but $prefix_purge_urls has data.
      // This means the function received only non-hostname URLs.
      $prefix_purge_friendly = true;
    } else {
      // Both arrays have data, indicating a mix of hostname and non-hostname URLs.
      // Set $prefix_purge_friendly to 'partial' to indicate this.
      $prefix_purge_friendly = 'partial';
    }

    return [
      'prefix_purge_urls' => array_unique($prefix_purge_urls),
      'cache_tags_to_purge' => array_unique($cache_tags_to_purge),
      'prefix_purge_friendly' => $prefix_purge_friendly
    ];
  }

  /**
   * This function purges the entire cache for a website using Cloudflare, either through the Cloudflare API
   * or a BigScoots master server.
   * 
   * @param error A reference to a variable that will hold any error message generated during the cache
   * purge process.
   * 
   * @return a boolean value, either true or false.
  **/
  public function purge_entire_cache(string &$error) : bool
  {
    $this->objects = $this->main_instance->get_objects();

    do_action('bs_cache_cf_purge_whole_cache_before');

    // Do not process request if the environment is `Staging` - proceed otherwise
    if ($this->main_instance->get_environment_type() === 'Staging') {
      $this->objects['logs']->add_log('cloudflare::purge_entire_cache', 'This is a staging environment. Requests from staging sites are not cached, therefore cache clearing is not permitted.');
      return false;
    }

    // Do not clear cache if the plugin setup is misconfigured
    if ($this->main_instance->get_plan_name() === 'Misconfigured') {
      $this->objects['logs']->add_log('cloudflare::purge_entire_cache', 'Cache clearing operation failed due to misconfigurations in BigScoots Cache. Please contact support for further assistance.');
      return false;
    }

    // Declare the variable to hold the API response and set it to null
    $response = null;

    // Check if we are using this plugin with CF Enterprise client
    if ( $this->main_instance->get_plan_name() === 'Performance+' ) { // Request for CF ENT Client

      $site_url_parts = wp_parse_url(home_url('/'));
      $site_hostname = $site_url_parts['host'];
      $cloudflare_request = wp_json_encode( ['request_type' => 'purge_all', 'site_hostname' => $site_hostname] );
      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      $checksum = hash_hmac('sha1', $cloudflare_request, BS_MASTER_KEY);

      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      $response = wp_safe_remote_post(
        esc_url_raw(BS_MASTER_URL), 
        [
          'method'      =>  'POST',
          'timeout'     =>  defined('BS_CACHE_CURL_TIMEOUT') ? BS_CACHE_CURL_TIMEOUT : 10,
          'user-agent'  => 'BigScoots-Cache/' . $this->main_instance->get_current_plugin_version() . '; ' . get_bloginfo('url'),
          'headers'     =>  [
            'Cache-Control' =>  'no-cache',
            'Content-Type'  =>  'application/json'
          ],
          'body' => wp_json_encode([
            'message'    => $cloudflare_request,
            'checksum'   => $checksum,
            'website_id' => BS_SITE_ID
          ])
        ]
      );

    } elseif ( $this->main_instance->get_plan_name() === 'Standard' ) {
      // The plugin is being used for normal Cloudflare clients - make calls directly to Cloudflare API

      // Get the required details from the PHP constants
      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      $cf_zone_id = $this->decrypt_salt( BS_SITE_CF_ZONE_ID_SALT );

      // Check if the decryption worked properly if so proceed further
      if ( $cf_zone_id === 'DECRYPTION_FAILED' ) {
        $error = __('CF Zone ID decryption failed. Check the encryption & decryption manually.', 'bigscoots-cache');
        $this->objects['logs']->add_log('cloudflare::decrypt_salt', $error);
        return false;
      }

      // Get the CF header for the POST request
      $cf_headers = $this->get_cf_api_headers();

      // If we don't have the header, don't execute further
      if (empty($cf_headers)) return false;

      // If the client's Cloudflare account support purge by prefix, then it also support purge by hostname, so use that
      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      if ( defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE ) {

        $site_url_parts = wp_parse_url(home_url('/'));
        $site_hostname = $site_url_parts['host'];

        // Make the POST request to Cloudflare API for purging cache
        $response = wp_safe_remote_post(
          esc_url_raw( "https://api.cloudflare.com/client/v4/zones/{$cf_zone_id}/purge_cache" ),
          [
            'method'      =>  'POST',
            'timeout'     =>  defined('BS_CACHE_CURL_TIMEOUT') ? BS_CACHE_CURL_TIMEOUT : 10,
            'headers'     =>  $cf_headers,
            'user-agent'  => 'BigScoots-Cache/' . $this->main_instance->get_current_plugin_version() . '; ' . get_bloginfo('url'),
            'body'  =>  wp_json_encode( [
              'hosts' =>  [$site_hostname]
            ] )
          ]
        );

      } else {

        // Make the POST request to Cloudflare API for purging cache
        $response = wp_safe_remote_post(
          esc_url_raw( "https://api.cloudflare.com/client/v4/zones/{$cf_zone_id}/purge_cache" ), [
            'method'      =>  'POST',
            'timeout'     =>  defined('BS_CACHE_CURL_TIMEOUT') ? BS_CACHE_CURL_TIMEOUT : 10,
            'headers'     =>  $cf_headers,
            'user-agent'  => 'BigScoots-Cache/' . $this->main_instance->get_current_plugin_version() . '; ' . get_bloginfo('url'),
            'body'  =>  wp_json_encode( [
              'purge_everything'  =>  true
            ] )
          ]
        );

      }
    }

    if (is_wp_error($response)) {
      $error = __('Connection error: ', 'bigscoots-cache') . $response->get_error_message();
      $this->objects['logs']->add_log('cloudflare::purge_entire_cache', "Error wp_safe_remote_post: {$error}");
      return false;
    }

    $response_body = wp_remote_retrieve_body($response);

    if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY) {
      $this->objects['logs']->add_log('cloudflare::purge_entire_cache', "Response: {$response_body}");
    }

    $json = json_decode($response_body, true);

    if ( isset( $json['status'] ) && ( $json['status'] === 'error' ) ) { // This is for CF ENT when response coming from BigScoots master server

      $error = $json['message'];

      if (isset($json['error'])) {
        $error .= " : {$json['error']}";
      }

      return false;

    } elseif (isset($json['success']) && ($json['success'] === false) ) { //  This is for Free CF customer when response coming from CF API directly

      $error = [];

      foreach($json['errors'] as $single_error) {
        $error[] = "{$single_error['message']} (err code: {$single_error['code']})";
      }

      $error = implode(' - ', $error);

      return false;

    }

    do_action('bs_cache_cf_purge_whole_cache_after');

    return true;
  }

  /**
   * This function asynchronously purges cache URLs/tags using the Cloudflare API.
   * 
   * @param items_to_purge An array of URLs to be purged from the Cloudflare cache.
   * @param purge_mode Denotes the purge mode of the request. It can be => url|tag|prefix
   * 
   * @return a boolean value of true|false.
  **/
  private function cf_api_purge_cache(array $items_to_purge = [], string $purge_mode = 'url') : bool
  {
    $this->objects = $this->main_instance->get_objects();

    // Get customer CF Zone ID
    // Get the required details from the PHP constants
    /** @disregard P1011 - BS_SITE_CF_ZONE_ID_SALT constant is set by bash script within wp-config.php **/
    $cf_zone_id = $this->decrypt_salt(BS_SITE_CF_ZONE_ID_SALT);

    // Check if the decryption worked properly if so proceed further
    if ($cf_zone_id === 'DECRYPTION_FAILED') {
      $error = __('CF Zone ID decryption failed. Check the encryption & decryption manually.', 'bigscoots-cache');
      $this->objects['logs']->add_log('cloudflare::decrypt_salt', $error);
      return false;
    }

    // Fetch the default request headers to make Cloudflare API calls
    $cf_headers = $this->get_cf_api_headers(true);

    // If we don't have the header, don't execute further
    if (empty($cf_headers)) return false;

    // Add User-Agent and Timeout to request header
    $cf_headers[] = 'User-Agent: BigScoots-Cache/' . $this->main_instance->get_current_plugin_version() . '; ' . get_bloginfo('url');
    $timeout = defined('BS_CACHE_CURL_TIMEOUT') ? BS_CACHE_CURL_TIMEOUT : 10;

    // Break $items_to_purge into chunks of 30 URLs in each array
    $items_to_purge_chunks = array_chunk($items_to_purge, 30);

    // Initiate cURL multi init (async)
    $multi_curl = curl_multi_init();
    $curl_array = [];
    $curl_index = 0;
    $is_high_verbosity_logging_enabled = $this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() === BS_CACHE_LOGS_HIGH_VERBOSITY;

    // Loop through items to purge chunks and initiate curl
    foreach ($items_to_purge_chunks as $items_to_purge_chunk) {
      $curl_handle = curl_init();

      // Set the POST body
      $request_body = '';

      switch ($purge_mode) {
        case 'url':
          $request_body = wp_json_encode(['files' => array_values($items_to_purge_chunk)]);
        break;

        case 'tag':
          $request_body = wp_json_encode(['tags' => array_values($items_to_purge_chunk)]);
        break;

        case 'prefix':
          $request_body = wp_json_encode(['prefixes' => array_values($items_to_purge_chunk)]);
        break;
      }

      if ($is_high_verbosity_logging_enabled) {
        $this->objects['logs']->add_log('cloudflare::cf_api_purge_cache', 'Request body for the ' . $this->main_instance->ordinal($curl_index + 1) . ' request: ' . print_r( json_decode($request_body, true), true ) );
      }

      // Set cURL options
      curl_setopt_array( $curl_handle, [
        CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/{$cf_zone_id}/purge_cache",
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POST => 1,
        CURLOPT_HTTPHEADER => $cf_headers,
        CURLOPT_POSTFIELDS => $request_body
      ] );

      // Add cURL multi handle
      curl_multi_add_handle($multi_curl, $curl_handle);

      // Store the cURL handle in the cURL array
      $curl_array[] = $curl_handle;

      $curl_index++;
    }

    // Execute the cURL multi handle
    $active = null;

    do {
      $status = curl_multi_exec($multi_curl, $active);

      // cURL status codes: https://curl.se/libcurl/c/libcurl-errors.html
      if ($status > 0) {
        // Log any cURL multi errors
        $error_message = curl_multi_strerror($status);
        $this->objects['logs']->add_log('cloudflare::cf_api_purge_cache', "cURL multi error: {$error_message}");
        return false;
      }

      if ($active) {
        // Wait a short time for more activity
        $select_status = curl_multi_select($multi_curl);

        if ($select_status === -1) {
          usleep(100); // Avoid busy-wait loop - sleep for 100 microseconds (0.1 milliseconds)
        }
      }
    } while ($active && $status === CURLM_OK);

    // Remove the cURL multi handles
    foreach ($curl_array as $index => $handle) {
      // Get the content of cURL request $curl_array[$i]
      if ($is_high_verbosity_logging_enabled) {
        $this->objects['logs']->add_log('cloudflare::cf_api_purge_cache', 'Response from the ' . $this->main_instance->ordinal($index + 1) . ' request: ' . curl_multi_getcontent($handle) );
      }

      curl_multi_remove_handle($multi_curl, $handle);

      // Free up additional memory resources by closing all the cURL requests
      curl_close($handle);
    }

    // Close cURL multi handle
    curl_multi_close($multi_curl);

    return true;
  }

  /**
   * This is a PHP function that purges cache URLs from a Cloudflare server using a secure checksum and
   * a request to the BigScoots Master Server.
   * 
   * @param urls An array of URLs to be purged from the cache.
   * @param purge_mode Denotes the purge mode of the request. It can be => url|tag|prefix
   * 
   * @return the response from the request made to the BigScoots Master Server.
   */
  private function bs_master_server_purge_cache(array $items_to_purge = [], string $purge_mode = 'url')
  {
    // Get the request type based on the purge mode
    $request_type = '';

    switch ($purge_mode) {
      case 'url':
        $request_type = 'purge_urls';
      break;

      case 'tag':
        $request_type = 'purge_tags';
      break;

      case 'prefix':
        $request_type = 'purge_prefix';
      break;
    }

    // Create the request object
    $cloudflare_request = wp_json_encode([
      'request_type' => $request_type,
      'items_to_purge' => $items_to_purge
    ]);

    // Make the secure checksum
    /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
    $checksum = hash_hmac('sha1', $cloudflare_request, BS_MASTER_KEY);

    // Make the request to the BigScoots Master Server
    /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
    $response = wp_safe_remote_post(
      esc_url_raw(BS_MASTER_URL),
      [
        'method'      =>  'POST',
        'timeout'     =>  defined('BS_CACHE_CURL_TIMEOUT') ? BS_CACHE_CURL_TIMEOUT : 10,
        'user-agent'  => 'BigScoots-Cache/' . $this->main_instance->get_current_plugin_version() . '; ' . get_bloginfo('url'),
        'headers'     =>  [
          'Cache-Control' =>  'no-cache',
          'Content-Type'  =>  'application/json'
        ],
        'body' => wp_json_encode([
          'message'    => $cloudflare_request,
          'checksum'   => $checksum,
          'website_id' => BS_SITE_ID
        ])
      ]
    );

    return $response;
  }

  /**
   * This function purges cache URLs using the Cloudflare API or the BigScoots master server for
   * Cloudflare Enterprise clients.
   * 
   * @param urls An array of URLs to be purged from the cache.
   * @param error  is a variable passed by reference that will hold any error message generated
   * during the execution of the function. If there are no errors,  will remain null.
   * 
   * @return a boolean value (true or false) depending on whether the cache purge request was
   * successful or not. If there was an error, the function sets the  variable with a message
   * describing the error.
   */
  public function purge_cache_urls(array $urls, string &$error) : bool
  {
    // Do not process request if the environment is `Staging` - proceed otherwise
    if ($this->main_instance->get_environment_type() === 'Staging') {
      $this->objects['logs']->add_log('cloudflare::purge_cache_urls', 'This is a staging environment. Requests from staging sites are not cached, therefore cache clearing is not permitted.');
      return false;
    }

    // Do not clear cache if the plugin setup is misconfigured
    if ($this->main_instance->get_plan_name() === 'Misconfigured') {
      $this->objects['logs']->add_log('cloudflare::purge_cache_urls', 'Cache clearing operation failed due to misconfigurations in BigScoots Cache. Please contact support for further assistance.');
      return false;
    }

    // Get the objects of different classes
    $this->objects = $this->main_instance->get_objects();

    // Remove duplicate URLs from the list of URLs
    $urls = array_unique($urls);

    do_action('bs_cache_cf_purge_cache_by_urls_before', $urls);

    if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY) {
      $this->objects['logs']->add_log('cloudflare::received_urls', 'Received URLs: ' . print_r($urls, true));
    }

    // Declare the variable to hold the API response and set it to false
    $response = false;
    $response2 = false;
    $purged_cache_in_async_mode = false;

    // Check if we are using this plugin with CF Enterprise client 
    // Or the site is using Standard plan but their account support purge by prefix
    /** @disregard P1011 - BS_SITE_CF_SUPPORT_PREFIX_PURGE constant is set by bash script in wp-config.php **/
    if ( 
      ( $this->main_instance->get_plan_name() === 'Performance+' ) || 
      ( $this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE ) 
    ) { // Request for CF ENT Client OR the client's CF account is an ENT acc and supports cache by prefix

      // Pass the received URLs to process_urls() function to check which URLs we can purge by prefix
      // and which needs to be purge by url
      $processed_urls = $this->process_urls($urls);

      if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY) {
        $this->objects['logs']->add_log('cloudflare::processed_urls', 'Processed URLs: ' . print_r($processed_urls, true));
      }

      if ($processed_urls['prefix_purge_friendly'] === true) {
        // Means we do not have any URLs for which we need to purge by Cache-Tag
        // We need to make 1 call just to do purge by prefix

        if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY) {
          $this->objects['logs']->add_log('cloudflare::starting_purge_req', 'Sending req to [purge by prefix]. URL list: ' . print_r($processed_urls['prefix_purge_urls'], true));
        }

        // ------------- PURGE BY PREFIX req - Start ------------ //
        // Check if this request is for is for client's own CF account
        /** @disregard P1011 - BS_SITE_CF_SUPPORT_PREFIX_PURGE constant is set by bash script in wp-config.php **/
        if ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE) {

          // We are using client's CF account - Sending purge API calls asynchronously
          $this->cf_api_purge_cache( $processed_urls['prefix_purge_urls'], 'prefix' );
          $purged_cache_in_async_mode = true;

        } else {

          // We are using BigScoots Cloudflare Enterprise account
          $response = $this->bs_master_server_purge_cache( $processed_urls['prefix_purge_urls'], 'prefix' );

        }
        // ------------- PURGE BY PREFIX req - End ------------ //

      } elseif ($processed_urls['prefix_purge_friendly'] === 'partial') { // We have data in both `prefix_purge_urls` and `cache_tags_to_purge`. So, we need to send 2 req to the master server. One for purge by prefix and the other is for purge by url

        // Log the data
        if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY) {
          $this->objects['logs']->add_log('cloudflare::starting_purge_req', 'Sending req to [purge by prefix]. URL list: ' . print_r($processed_urls['prefix_purge_urls'], true));
          $this->objects['logs']->add_log('cloudflare::starting_purge_req', 'Sending req to [purge by cache-tag]. Cache-Tag list: ' . print_r($processed_urls['cache_tags_to_purge'], true));
        }

        
        // Check if this request is for is for client's own CF account
        /** @disregard P1011 - BS_SITE_CF_SUPPORT_PREFIX_PURGE constant is set by bash script in wp-config.php **/
        if ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE) {

          // We are using client's CF account - Sending purge API calls asynchronously

          // ------------- PURGE BY PREFIX req - Start ------------ //
          $this->cf_api_purge_cache( $processed_urls['prefix_purge_urls'], 'prefix' );
          $purged_cache_in_async_mode = true;
          // ------------- PURGE BY PREFIX req - End ------------ //

          // ------------- PURGE BY CACHE-TAG req - Start ------------ //
          $this->cf_api_purge_cache( $processed_urls['cache_tags_to_purge'], 'tag' );
          $purged_cache_in_async_mode = true;
          // ------------- PURGE BY CACHE-TAG req - End ------------ //

        } else {

          // We are using BigScoots Cloudflare Enterprise account

          // ------------- PURGE BY PREFIX req - Start ------------ //
          $response = $this->bs_master_server_purge_cache( $processed_urls['prefix_purge_urls'], 'prefix' );
          // ------------- PURGE BY PREFIX req - End ------------ //

          // ------------- PURGE BY CACHE-TAG req - Start ------------ //
          $response2 = $this->bs_master_server_purge_cache( $processed_urls['cache_tags_to_purge'], 'tag' );
          // ------------- PURGE BY CACHE-TAG req - End ------------ //

        }

      } else { // We just need to purge by Cache-Tag

        if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY) {
          $this->objects['logs']->add_log('cloudflare::starting_purge_req', 'Sending req to [purge by cache-tag]. Cache-Tag list: ' . print_r($processed_urls['cache_tags_to_purge'], true));
        }

        // ------------- PURGE BY CACHE-TAG req - Start ------------ //
        // Check if this request is for is for client's own CF account
        /** @disregard P1011 - BS_SITE_CF_SUPPORT_PREFIX_PURGE constant is set by bash script in wp-config.php **/
        if ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE) {

          // We are using client's CF account - Sending purge API calls asynchronously
          $this->cf_api_purge_cache( $processed_urls['cache_tags_to_purge'], 'tag' );
          $purged_cache_in_async_mode = true;

        } else {

          // We are using BigScoots Cloudflare Enterprise account
          $response = $this->bs_master_server_purge_cache( $processed_urls['cache_tags_to_purge'], 'tag' );

        }
        // ------------- PURGE BY CACHE-TAG req - End ------------ //

      }
    } elseif ($this->main_instance->get_plan_name() === 'Standard' && !defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE')) {
      // The plugin is being used for normal Cloudflare clients (ac doesn't support purge by prefix) - make calls directly to Cloudflare API

      // We are using client's CF account - Sending purge API calls asynchronously
      $this->cf_api_purge_cache( $urls, 'url' );
      $purged_cache_in_async_mode = true;

    }

    // Return true when cache purged in async mode
    if ($purged_cache_in_async_mode) return true;

    if ($response) {

      if (is_wp_error($response)) {
        $error = __('Connection error: ', 'bigscoots-cache') . $response->get_error_message();
        $this->objects['logs']->add_log('cloudflare::purge_cache', "Error wp_safe_remote_post: {$error}");
        return false;
      }

      $response_body = wp_remote_retrieve_body($response);

      if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY) {
        $this->objects['logs']->add_log('cloudflare::purge_cache', "Response: {$response_body}");
      }

      $json = json_decode($response_body, true);

      if (isset($json['status']) && ($json['status'] === 'error')) { // This is for CF ENT when response coming from BigScoots master server

        $error = $json['message'];

        if (isset($json['error'])) {
          $error .= " : {$json['error']}";
        }

        return false;

      }
    }

    if ($response2) {

      if (is_wp_error($response2)) {
        $error = __('Connection error: ', 'bigscoots-cache') . $response2->get_error_message();
        $this->objects['logs']->add_log('cloudflare::purge_cache', "Error wp_safe_remote_post: {$error}");
        return false;
      }

      $response_body = wp_remote_retrieve_body($response2);

      if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY) {
        $this->objects['logs']->add_log('cloudflare::purge_cache', "Response: {$response_body}");
      }

      $json = json_decode($response_body, true);

      if (isset($json['status']) && ($json['status'] === 'error')) { // This is for CF ENT when response coming from BigScoots master server

        $error = $json['message'];

        if (isset($json['error'])) {
          $error .= " : {$json['error']}";
        }

        return false;

      }
    }

    do_action('bs_cache_cf_purge_cache_by_urls_after', $urls);

    return true;
  }

  /**
   * This function tests if page caching is working properly on a website behind Cloudflare.
   * 
   * @param url The URL of the website to be tested for page caching.
   * @param error  is a variable passed by reference that will contain an error message if the
   * function encounters an issue.
   * @param test_static A boolean parameter that determines whether to test for static content or not.
   * If set to true, the function will only test for static content caching. If set to false, it will
   * test for both static and dynamic content caching.
   * 
   * @return a boolean value (true or false) depending on whether the page cache test was successful or
   * not. If the test is successful, it returns true. If there is an error, it returns false and sets
   * the  variable with a message describing the error.
   */
  public function page_cache_test(string $url, string &$error, bool $test_static = false) : bool
  {
    $this->objects = $this->main_instance->get_objects();

    $this->objects['logs']->add_log('cloudflare::page_cache_test', "Starting page cache test for: {$url}");

    // Get the response header for the received URL using cURL (fastest execution)
    $response = $this->main_instance->get_response_header( $url );

    if ( isset($response['success']) && !$response['success'] ) {
      $error = __('Connection error: ', 'bigscoots-cache') . $response['message'];
      $this->objects['logs']->add_log('cloudflare::page_cache_test', "Error get_response_header: {$error}");
      return false;
    }

    $headers = $response['headers'];

    if ($this->objects['logs'] instanceof \BigScoots\Cache\Logs) {
      $this->objects['logs']->add_log('cloudflare::page_cache_test', 'Response Headers: ' . var_export($headers, true));
    }

    if (!$test_static && !isset($headers['x-bigscoots-cache'])) {
      $error = __('BigScoots Cache plugin is not detected on your home page. If you have activated other caching systems, please disable them and retry the test.', 'bigscoots-cache');
      return false;
    }

    if (!$test_static && $headers['x-bigscoots-cache'] == 'no-cache') {
      $error = __('BigScoots Cache is not enabled on your home page. It\'s not possible to verify if the page caching is working properly.', 'bigscoots-cache');
      return false;
    }

    if (
      ( $this->main_instance->get_plan_name() === 'Standard' && !isset($headers['cf-cache-status']) ) ||
      ( $this->main_instance->get_plan_name() === 'Performance+' && !isset($headers['x-bigscoots-cache-status']) )
    ) {
      $error = __('We do not see any any cache status header. Seem that your website is not using BigScoots Cache system properly. Please contact BigScoots Support to check why you are having this problem.', 'bigscoots-cache');
      return false;
    }

    if (!isset($headers['cache-control'])) {
      $error = __('Unable to find the Cache-Control response header.', 'bigscoots-cache');
      return false;
    }

    if (!$test_static && !isset($headers['x-bigscoots-cache-control'])) {
      $error = __('Unable to find the X-BigScoots-Cache-Control response header.', 'bigscoots-cache');
      return false;
    }

    // Cache Status: HIT / MISS / EXPIRED
    // Both Standard & CF ENT Users
    if (
      ( $this->main_instance->get_plan_name() === 'Standard' && (strcasecmp($headers['cf-cache-status'], 'HIT') == 0 || strcasecmp($headers['cf-cache-status'], 'MISS') == 0 || strcasecmp($headers['cf-cache-status'], 'EXPIRED') == 0) ) ||
      ( $this->main_instance->get_plan_name() === 'Performance+' && (strcasecmp($headers['x-bigscoots-cache-status'], 'HIT') == 0 || strcasecmp($headers['x-bigscoots-cache-status'], 'MISS') == 0 || strcasecmp($headers['x-bigscoots-cache-status'], 'EXPIRED') == 0) )
    ) {
      return true;
    }

    // Cache Status: REVALIDATED
    // Standard Users
    if ( $this->main_instance->get_plan_name() === 'Standard' && strcasecmp($headers['cf-cache-status'], 'REVALIDATED') == 0 ) {
      $error = sprintf('Cache status: %s - The resource is served from cache but is stale. The resource was revalidated by either an If-Modified-Since header or an If-None-Match header.', $headers['cf-cache-status']);
      return false;
    }

    // CF ENT Users
    if ( $this->main_instance->get_plan_name() === 'Performance+' && strcasecmp($headers['x-bigscoots-cache-status'], 'REVALIDATED') == 0 ) {
      $error = sprintf('Cache status: %s - The resource is served from cache but is stale. The resource was revalidated by either an If-Modified-Since header or an If-None-Match header.', $headers['x-bigscoots-cache-status']);
      return false;
    }

    // Cache Status: UPDATING
    // Standard Users
    if ( $this->main_instance->get_plan_name() === 'Standard' && strcasecmp($headers['cf-cache-status'], 'UPDATING') == 0 ) {
      $error = sprintf('Cache status: %s - The resource was served from cache but is expired. The resource is currently being updated by the origin web server. UPDATING is typically seen only for very popular cached resources.', $headers['cf-cache-status']);
      return false;
    }

    // CF ENT Users
    if ( $this->main_instance->get_plan_name() === 'Performance+' && strcasecmp($headers['x-bigscoots-cache-status'], 'UPDATING') == 0 ) {
      $error = sprintf('Cache status: %s - The resource was served from cache but is expired. The resource is currently being updated by the origin web server. UPDATING is typically seen only for very popular cached resources.', $headers['x-bigscoots-cache-status']);
      return false;
    }

    // Cache Status BYPASS
    // Standard Users
    if ( $this->main_instance->get_plan_name() === 'Standard' && strcasecmp($headers['cf-cache-status'], 'BYPASS') == 0 ) {
      $error = sprintf('Cache status: %s - BigScoots Cache has been instructed to not cache this asset. It has been served directly from the origin server.', $headers['cf-cache-status']);
      return false;
    }

    // CF ENT Users
    if ( $this->main_instance->get_plan_name() === 'Performance+' && strcasecmp($headers['x-bigscoots-cache-status'], 'BYPASS') == 0 ) {
      $error = sprintf('Cache status: %s - BigScoots Cache has been instructed to not cache this asset. It has been served directly from the origin server.', $headers['x-bigscoots-cache-status']);
      return false;
    }

    // Cache Status: DYNAMIC
    // Standard Users
    if ( $this->main_instance->get_plan_name() === 'Standard' && strcasecmp($headers['cf-cache-status'], 'DYNAMIC') == 0 ) {

      $cookies = wp_remote_retrieve_cookies($response);

      if (!empty($cookies)) {
        $error = sprintf('Cache status: %s - The resource was not cached by default and your current BigScoots Cache configuration doesn\'t instruct us to cache the resource. Try to enable the <strong>Strip response cookies on pages that should be cached</strong> option and retry.', $headers['cf-cache-status']);
      } else {
        $error = sprintf('Cache status: %s - The resource was not cached by default and your current BigScoots Cache configuration doesn\'t instruct us to cache the resource.  Instead, the resource was requested from the origin web server.', $headers['cf-cache-status']);
      }

      return false;
    }

    // CF ENT Users
    if ( $this->main_instance->get_plan_name() === 'Performance+' && strcasecmp($headers['x-bigscoots-cache-status'], 'DYNAMIC') == 0 ) {

      $cookies = wp_remote_retrieve_cookies($response);

      if (!empty($cookies)) {
        $error = sprintf('Cache status: %s - The resource was not cached by default and your current BigScoots Cache configuration doesn\'t instruct us to cache the resource. Try to enable the <strong>Strip response cookies on pages that should be cached</strong> option and retry.', $headers['x-bigscoots-cache-status']);
      } else {
        $error = sprintf('Cache status: %s - The resource was not cached by default and your current BigScoots Cache configuration doesn\'t instruct us to cache the resource.  Instead, the resource was requested from the origin web server.', $headers['x-bigscoots-cache-status']);
      }

      return false;
    }

    $error = __('Undefined error', 'bigscoots-cache');

    return false;
  }
}