<?php
// BigScoots Cache WP REST API
namespace BigScoots\Cache;

defined('ABSPATH') || exit('Cheatin&#8217; uh?');

class API extends \WP_REST_Controller
{
  private \BigScoots_Cache $main_instance;
  private array $objects = [];

  public function __construct($main_instance)
  {
    $this->main_instance = $main_instance;

    $this->actions();
  }

  private function actions() : void
  {
    // Register the REST API Routes
    add_action('rest_api_init', [$this, 'register_routes']);
  }

  // Register the routes for the objects of the controller.
  public function register_routes() : void
  {
    $version = '2';
    $namespace = "bigscoots-cache/v{$version}";

    // 1. Check Plugin Status Route
    register_rest_route( $namespace, '/status', [
      [
        'methods'             =>  'HEAD, POST, PUT, PATCH, DELETE',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  \WP_REST_Server::READABLE,
        'callback'            =>  [$this, 'process_status_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 2. Test Cache Route
    register_rest_route( $namespace, '/test-cache', [
      [
        'methods'             =>  'HEAD, POST, PUT, PATCH, DELETE',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  \WP_REST_Server::READABLE,
        'callback'            =>  [$this, 'process_test_cache_request'],
        'permission_callback' =>  [$this, 'is_user_allowed_to_purge_cache'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 3. Clear Cloudflare Cache Route
    register_rest_route( $namespace, '/clear-cache', [
      [
        'methods'             =>  'GET, HEAD, POST, PUT, DELETE',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'PATCH',
        'callback'            =>  [$this, 'process_clear_cache_request'],
        'permission_callback' =>  [$this, 'is_user_allowed_to_purge_cache'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 4. Clear OPcache Route
    register_rest_route( $namespace, '/clear-opcache', [
      [
        'methods'             =>  'GET, HEAD, POST, PUT, PATCH',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  \WP_REST_Server::DELETABLE,
        'callback'            =>  [$this, 'process_clear_opcache_request'],
        'permission_callback' =>  [$this, 'has_bigscoots_special_header'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 5. Enable Cache Route
    register_rest_route( $namespace, '/enable-cache', [
      [
        'methods'             =>  'GET, HEAD, POST, PUT, DELETE',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'PATCH',
        'callback'            =>  [$this, 'process_enable_cache_request'],
        'permission_callback' =>  [$this, 'is_user_allowed_to_purge_cache'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 6. Disable Cache Route
    register_rest_route( $namespace, '/disable-cache', [
      [
        'methods'             =>  'GET, HEAD, POST, PUT, DELETE',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'PATCH',
        'callback'            =>  [$this, 'process_disable_cache_request'],
        'permission_callback' =>  [$this, 'is_user_allowed_to_purge_cache'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 7. Save Settings Visibility Toggle Route
    register_rest_route( $namespace, '/settings-visibility', [
      [
        'methods'             =>  'GET, HEAD, POST, PUT, DELETE',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'PATCH',
        'callback'            =>  [$this, 'process_save_settings_visibility_toggle_request'],
        'permission_callback' =>  [$this, 'is_user_allowed_to_purge_cache'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 8. Import Config Route
    register_rest_route( $namespace, '/import-config', [
      [
        'methods'             =>  'GET, HEAD, PUT, PATCH, DELETE',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  \WP_REST_Server::CREATABLE,
        'callback'            =>  [$this, 'process_import_config_request'],
        'permission_callback' =>  [$this, 'is_user_allowed_to_purge_cache'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 9. Clear Logs Route
    register_rest_route( $namespace, '/clear-logs', [
      [
        'methods'             =>  'GET, HEAD, POST, PUT, PATCH',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  \WP_REST_Server::DELETABLE,
        'callback'            =>  [$this, 'process_clear_logs_request'],
        'permission_callback' =>  [$this, 'is_user_allowed_to_purge_cache'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );

    // 10. Get Post Type Name Route
    register_rest_route( $namespace, '/post-type/(?P<id>[\d]+)', [
      [
        'methods'             =>  'HEAD, POST, PUT, PATCH, DELETE',
        'callback'            =>  [$this, 'request_not_allowed'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  'OPTIONS',
        'callback'            =>  [$this, 'allowed_for_preflight_request'],
        'permission_callback' =>  [$this, 'allow_all'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ],
      [
        'methods'             =>  \WP_REST_Server::READABLE,
        'callback'            =>  [$this, 'process_get_post_type_request'],
        'permission_callback' =>  [$this, 'has_bigscoots_special_header'],
        'args'                =>  $this->get_endpoint_args_for_item_schema( true )
      ]
    ] );
  }

  // Function to allow everyone to make the request
  public function allow_all() : bool
  {
    return true;
  }

  // Function to allow only the users who are allowed to purge cache to make the request
  public function is_user_allowed_to_purge_cache() : bool
  {
    return $this->main_instance->can_current_user_purge_cache();
  }

  /**
   * Function to allow BigScoots team based on header value
   * @param WP_REST_Request $request Full data about the request.
   */
  public function has_bigscoots_special_header(\WP_REST_Request $request) : bool
  {
    // Check if the request has BigScots special header
    // Retrieve the x-bigscoots-request header from the request
    $bigscoots_request = $request->get_header('x-bigscoots-request');

    // Check if the header is present and set to "yes" then return true else return false
    $request_allowed = ($bigscoots_request && strtolower($bigscoots_request) === 'yes');
    $is_user_allowed_to_purge_cache = $this->is_user_allowed_to_purge_cache();

    // Allow the request if the request has special bigscoots header or the user is allowed to purge cache
    return ( $request_allowed || $is_user_allowed_to_purge_cache );
  }

  // Function to return response for the request methods that are not allowed
  public function request_not_allowed(\WP_REST_Request $request) : \WP_REST_Response
  {
    // Get the request method
    $request_method = $request->get_method();

    return new \WP_REST_Response(
      [
        'success' =>  false,
        'status'  =>  'error',
        'message' =>  "Sorry! {$request_method} request is not allowed to this endpoint. Please make the request with appropriate method."
      ],
      405
    );
  }

  // Function to return response for OPTIONS requests to process browser preflight requests
  public function allowed_for_preflight_request(\WP_REST_Request $request) : \WP_REST_Response
  {
    // Get the request method
    // $request_method = $request->get_method();

    // Return 204 response with no response data as it's a preflight request
    return new \WP_REST_Response(
      null, // No response body content
      204
    );
  }

  /**
   * Process the Status Check request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_status_request(\WP_REST_Request $request) : \WP_REST_Response
  {
    $this->objects = $this->main_instance->get_objects();

    // Initialize the variables
    $command_data = [];
    $things_to_show = [
      'Website Environment Type', // e.g. production, staging, development
      'Plugin Cache Status',
      'Plugin Version',
      'Plugin Setup Mode',
      'Website Using Cloudflare',
      'Cloudflare Setup Mode',
      'Cache Rule Status',
      'Home Page Cache Status',
      'Test Page Cache Status'
    ];

    // Get all the response headers for the requires URLs
    $dynamic_cache_url_resp_headers = $this->main_instance->get_response_header( home_url('/?nocache=1') );

    if ( isset( $dynamic_cache_url_resp_headers['success'] ) && !$dynamic_cache_url_resp_headers['success'] ) {
      $dynamic_cache_url_resp_headers = [];
    } else {
      $dynamic_cache_url_resp_headers = $dynamic_cache_url_resp_headers['headers'];
    }

    $home_url_resp_headers = $this->main_instance->get_response_header( home_url('/') );

    // Make sure the request is successful and we got all the data that we need
    if ( isset( $home_url_resp_headers['success'] ) && !$home_url_resp_headers['success'] ) {
      $home_url_resp_headers = [];
    } else {
      $home_url_resp_headers = $home_url_resp_headers['headers'];
    }

    $test_page_url_resp_headers = $this->main_instance->get_response_header( BS_CACHE_PLUGIN_URL . 'assets/testcache.html' );

    // Make sure the request is successful and we got all the data that we need
    if ( isset( $test_page_url_resp_headers['success'] ) && !$test_page_url_resp_headers['success'] ) {
      $test_page_url_resp_headers = [];
    } else {
      $test_page_url_resp_headers = $test_page_url_resp_headers['headers'];
    }

    for( $i = 0; $i < count($things_to_show); $i++ ) {

      switch( $things_to_show[$i] ) {

        case 'Website Environment Type':
          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [ // Unique key for each field
            // The bel of the field
            'label'   =>  esc_html($things_to_show[$i]),
            // The value of the field (string, integer, float or array)
            'value'   =>  $this->main_instance->get_environment_type()
          ];
        break;

        case 'Plugin Cache Status':
          $plugin_cache_status = '';

          if ( !$this->objects['cache_controller']->is_cache_enabled() ) {
            $plugin_cache_status = 'Disabled';
          } elseif ( $this->objects['cache_controller']->is_page_cache_disabled() ) {
            $plugin_cache_status = 'Page Cache Disabled';
          } else {
            $plugin_cache_status = 'Enabled';
          }

          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [ // Unique key for each field
            // The bel of the field
            'label'   =>  esc_html($things_to_show[$i]),
            // The value of the field (string, integer, float or array)
            'value'   =>  $plugin_cache_status
          ];
        break;

        case 'Plugin Version':
          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $this->main_instance->get_current_plugin_version()
          ];
        break;

        case 'Plugin Setup Mode':
          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $this->main_instance->get_plan_name()
          ];
        break;

        case 'Website Using Cloudflare':
          $website_using_cloudflare = 'No';

          if ( 
            is_array( $home_url_resp_headers ) &&
            array_key_exists( 'server', $home_url_resp_headers ) &&
            $home_url_resp_headers['server'] === 'cloudflare' 
          ) {
            $website_using_cloudflare = 'Yes';
          }

          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $website_using_cloudflare
          ];
        break;

        case 'Cloudflare Setup Mode':
          $cloudflare_setup_mode = 'Standard';

          if ( 
            is_array( $dynamic_cache_url_resp_headers ) &&
            array_key_exists( 'x-bigscoots-cache-mode', $dynamic_cache_url_resp_headers ) &&
            $dynamic_cache_url_resp_headers['x-bigscoots-cache-mode'] === 'O2O' 
          ) {
            $cloudflare_setup_mode = 'O2O';
          }

          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $cloudflare_setup_mode
          ];
        break;

        case 'Cache Rule Status':
          $cache_rule_status = 'Not Working';

          if ( 
            is_array( $dynamic_cache_url_resp_headers ) &&
            $this->main_instance->get_plan_name() === 'Standard' &&
            array_key_exists( 'cf-cache-status', $dynamic_cache_url_resp_headers ) &&
            $dynamic_cache_url_resp_headers['cf-cache-status'] === 'BYPASS'
          ) {
            $cache_rule_status = 'Working';
          } elseif (
            is_array( $dynamic_cache_url_resp_headers ) &&
            $this->main_instance->get_plan_name() === 'Performance+' &&
            array_key_exists( 'x-bigscoots-cache-status', $dynamic_cache_url_resp_headers ) &&
            $dynamic_cache_url_resp_headers['x-bigscoots-cache-status'] === 'BYPASS'
          ) {
            $cache_rule_status = 'Working';
          } elseif (
            is_array( $dynamic_cache_url_resp_headers ) &&
            $this->main_instance->get_plan_name() === 'Performance+' &&
            array_key_exists( 'x-bigscoots-cache-status', $dynamic_cache_url_resp_headers ) &&
            $dynamic_cache_url_resp_headers['x-bigscoots-cache-status'] === 'DYNAMIC'
          ) {
            $cache_rule_status = 'Working - Page Cache Disabled';
          }

          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $cache_rule_status
          ];
        break;

        case 'Home Page Cache Status':
          $home_url_cache_status = 'Cloudflare Not Working';

          if (
            is_array( $home_url_resp_headers ) &&
            array_key_exists( 'cache-control', $home_url_resp_headers ) &&
            array_key_exists( 'x-bigscoots-cache-control', $home_url_resp_headers ) &&
            $home_url_resp_headers['cache-control'] === $home_url_resp_headers['x-bigscoots-cache-control'] &&
            $home_url_resp_headers['cache-control'] === 'no-store, no-cache, must-revalidate, max-age=0'
          ) {
            $home_url_cache_status = 'Bypassed from Cache';
          } elseif ( 
            is_array( $home_url_resp_headers ) &&
            $this->main_instance->get_plan_name() === 'Standard' &&
            array_key_exists( 'cf-cache-status', $home_url_resp_headers ) 
          ) {
            $home_url_cache_status = $home_url_resp_headers['cf-cache-status'];
          } elseif (
            is_array( $home_url_resp_headers ) &&
            $this->main_instance->get_plan_name() === 'Performance+' &&
            array_key_exists( 'x-bigscoots-cache-status', $home_url_resp_headers ) 
          ) {
            $home_url_cache_status = $home_url_resp_headers['x-bigscoots-cache-status'];
          }

          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $home_url_cache_status
          ];
        break;

        case 'Test Page Cache Status':
          $test_page_url_cache_status = 'Cloudflare Not Working';

          if ( 
            is_array( $test_page_url_resp_headers ) &&
            $this->main_instance->get_plan_name() === 'Standard' &&
            array_key_exists( 'cf-cache-status', $test_page_url_resp_headers ) 
          ) {
            $test_page_url_cache_status = $test_page_url_resp_headers['cf-cache-status'];
          } elseif (
            is_array( $test_page_url_resp_headers ) &&
            $this->main_instance->get_plan_name() === 'Performance+' &&
            array_key_exists( 'x-bigscoots-cache-status', $test_page_url_resp_headers )
          ) {
            $test_page_url_cache_status = $test_page_url_resp_headers['x-bigscoots-cache-status'];
          }

          $command_data[ strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $test_page_url_cache_status
          ];
        break;
      }
    }

    $this->objects['logs']->add_log('rest_api::check_status', 'Processed plugin status check request.');

    // Create the success response object
    $response = new \WP_REST_Response(
      [
        'success'   =>  true,
        'status'    =>  'success',
        'message'   =>  'Plugin status check request has been processed successfully.',
        'data'      =>  $command_data
      ],
      200
    );

    // Set no cache headers to the request as it's accessed via GET method
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);

    // Return success response
    return $response;
  }

  /**
   * Process the Test Cache request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_test_cache_request(\WP_REST_Request $request)
  {
    // Do not proceed if the environment is `Staging` - proceed otherwise
    if ($this->main_instance->get_environment_type() === 'Staging') {
      return new \WP_Error( 'test_cache_not_allowed_on_staging', __( 'This is a staging environment. Requests from staging sites are not cached, therefore test cache is unnecessary and is not permitted.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Do not clear cache if the plugin setup is misconfigured
    if ($this->main_instance->get_plan_name() === 'Misconfigured') {
      return new \WP_Error( 'plugin_misconfigured', __( 'Test Cache operation failed due to misconfigurations in BigScoots Cache. Please contact support for further assistance.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    $this->objects = $this->main_instance->get_objects();

    // Initialize the variables
    $error_home_page_request = '';
    $error_static_page_request = '';
    $static_resource_url = BS_CACHE_PLUGIN_URL . 'assets/testcache.html';
    $home_page_url = home_url('/');

    // Test Cache for the dynamic resource
    $home_page_response_headers = $this->objects['cloudflare']->page_cache_test($home_page_url, $error_home_page_request);

    if (!$home_page_response_headers) {
      // Test cache for the static resource
      $static_resource_response_headers = $this->objects['cloudflare']->page_cache_test($static_resource_url, $error_static_page_request, true);

      // Initialize error variable
      $error = '';

      // Error on both dynamic and static test
      if (!$static_resource_response_headers) {
        $error .= __('Page caching seems not working for both dynamic and static pages.', 'bigscoots-cache');
        $error .= '<br/><br/>';
        $error .= sprintf('Error on dynamic page (%s): %s', $home_page_url, $error_home_page_request);
        $error .= '<br/><br/>';
        $error .= sprintf('Error on static resource (%s): %s', $static_resource_url, $error_static_page_request);
        $error .= '<br/><br/>';

        // Add additional error data based on the Cloudflare plan customer is using
        if ( $this->main_instance->get_plan_name() === 'Standard' ) { // For Standard Users
          $error .= __('Please check if the page caching is working by yourself by surfing the website in incognito mode. Because sometimes Cloudflare bypass the cache for cURL requests. Reload a page two or three times. If you see the response header <strong>cf-cache-status: HIT</strong>, the page caching is working well.', 'bigscoots-cache');
        } elseif ( $this->main_instance->get_plan_name() === 'Performance+' ) { // For CF ENT Users
          $error .= __('Please check if the page caching is working by yourself by surfing the website in incognito mode. Because sometimes Cloudflare bypass the cache for cURL requests. Reload a page two or three times. If you see the response header <strong>x-bigscoots-cache-status: HIT</strong>, the page caching is working well.', 'bigscoots-cache');
        }
      } else { // Error on dynamic test only
        $error .= sprintf('Page caching is working for static page but seems not working for dynamic pages.', $static_resource_url);
        $error .= '<br/><br/>';
        $error .= sprintf('Error on dynamic page (%s): %s', $home_page_url, $error_home_page_request);
        $error .= '<br/><br/>';

        // Add additional error data based on the Cloudflare plan customer is using
        if ( $this->main_instance->get_plan_name() === 'Standard' ) { // For Standard Users
          $error .= __('Please check if the page caching is working by yourself by surfing the website in incognito mode. Because sometimes Cloudflare bypass the cache for cURL requests. Reload a page two or three times. If you see the response header <strong>cf-cache-status: HIT</strong>, the page caching is working well.', 'bigscoots-cache');
        } elseif ( $this->main_instance->get_plan_name() === 'Performance+' ) { // For CF ENT Users
          $error .= __('Please check if the page caching is working by yourself by surfing the website in incognito mode. Because sometimes Cloudflare bypass the cache for cURL requests. Reload a page two or three times. If you see the response header <strong>x-bigscoots-cache-status: HIT</strong>, the page caching is working well.', 'bigscoots-cache');
        }
      }

      $this->objects['logs']->add_log('rest_api::test_cache', 'Page cache seems not to be working properly.');

      return new \WP_Error( 'page_cache_not_working_properly', $error, [ 'status' => 200 ] );
    }

    $this->objects['logs']->add_log('rest_api::test_cache', 'Page cache is working properly.');

    // Create the success response object
    $response = new \WP_REST_Response(
      [
        'success'   =>  true,
        'status'    =>  'success',
        'message'   =>  'Page caching is working properly.',
        'static_resource_url'   =>  $static_resource_url,
        'home_page_url'         =>  $home_page_url
      ],
      200
    );

    // Set no cache headers to the request as it's accessed via GET method
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);

    // Return success response
    return $response;
  }

  /**
   * Process the Clear Cache request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_clear_cache_request(\WP_REST_Request $request)
  {
    // Do not clear the cache if the environment is `Staging` - proceed otherwise
    if ($this->main_instance->get_environment_type() === 'Staging') {
      return new \WP_Error( 'clear_cache_not_allowed_on_staging', __( 'This is a staging environment. Requests from staging sites are not cached, therefore cache clearing is not permitted.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Do not clear cache if the plugin setup is misconfigured
    if ($this->main_instance->get_plan_name() === 'Misconfigured') {
      return new \WP_Error( 'plugin_misconfigured', __( 'Cache clearing operation failed due to misconfigurations in BigScoots Cache. Please contact support for further assistance.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Get JSON data from the request body
    $data = $request->get_json_params();

    // Check if JSON data is present
    if ( empty( $data ) ) {
      return new \WP_Error( 'no_data_provided', __( 'Please provide the required JSON data to the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Making sure we have purge_type data
    if ( empty( $data['purge_type'] ) || !isset( $data['purge_type'] ) ) {
      return new \WP_Error( 'no_data_provided', __( 'Please provide the purge type name as part of the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Get the objects to perform necessary actions
    $this->objects = $this->main_instance->get_objects();

    // Do not proceed if cache is disabled
    if ( !$this->objects['cache_controller']->is_cache_enabled() ) {
      return new \WP_Error( 'cache_disabled', __( 'Cannot process the clear cache request as the cache is disabled.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Get the purge type data
    $purge_type = $data['purge_type'];

    switch ($purge_type) {
      case 'all': // Purge Everything Request
        // Clear the cache
        $this->objects['cache_controller']->purge_all();

        $this->objects['logs']->add_log('rest_api::clear_cache_all', 'Cleared Cloudflare cache for the entire site (Including static files).');

        // Return response
        return new \WP_REST_Response(
          [
            'success' =>  true,
            'status'  =>  'success',
            'message' =>  __('Your request to clear cache has been submitted! Please allow up to 30 seconds for this to take effect.', 'bigscoots-cache')
          ],
          200
        );
      break;

      case 'post_ids': // Purge by post ids
        // Do not proceed if page cache is disabled
        if ( $this->objects['cache_controller']->is_page_cache_disabled() ) {
          return new \WP_Error( 'page_cache_disabled', __( 'Cannot process the clear cache request as page cache has been disabled.', 'bigscoots-cache' ), [ 'status' => 403 ] );
        }

        // Making sure we have proper post_ids
        if ( empty( $data['post_ids'] ) || !isset( $data['post_ids'] ) ) {
          return new \WP_Error( 'no_data_provided', __( 'Please provide the post ids (in form of integer array) as part of the request to clear cache.', 'bigscoots-cache' ), [ 'status' => 403 ] );
        }

        // Retrieve and validate `purge_related_urls`
        $purge_related_urls = !empty($data['purge_related_urls']) && filter_var($data['purge_related_urls'], FILTER_VALIDATE_BOOLEAN);

        // Validate and sanitize post IDs before processing
        $post_ids = is_array($data['post_ids']) ? array_map(function ($item) : int {
          return (int) $item;
        }, $data['post_ids']) : [];

        // Declare the variables to hold post ids that was able to cache or not able to cache and why
        $post_ids_trying_to_clear_cache = [
          'cleared_cache' =>  [],
          'post_doesnt_exists'  =>  [],
          'post_part_of_ignored_post_type' => [],
          'post_status_is_not_publish_or_private' =>  [],
          'no_permalink_found'  =>  []
        ];

        // Don't allow cache purge for the post ids that are part of ignored post type
        $ignored_post_types = apply_filters('bs_cache_ignored_post_types', $this->main_instance->get_single_config('cf_excluded_post_types', []));

        // Loop through the $post_ids to generate the $list_of_urls_to_purge array
        $list_of_urls_to_purge = [];

        foreach( $post_ids as $post_id ) {
          // Get the post object for the given post id
          $post = get_post($post_id);

          // Check we got a proper post id
          if (empty($post) || !$post instanceof \WP_Post) {
            // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
            $post_ids_trying_to_clear_cache['post_doesnt_exists'][] = $post_id;

            // Skip the rest of the loop and continue with the next iteration
            continue;
          }

          // Check the post is not part of ignored post types
          if (is_array($ignored_post_types) && in_array($post->post_type, $ignored_post_types)) {
            // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
            $post_ids_trying_to_clear_cache['post_part_of_ignored_post_type'][] = "{$post_id} (Post Type: {$post->post_type})";

            // Skip the rest of the loop and continue with the next iteration
            continue;
          }

          // Check if the post status does not belong to `publish` or `private` - then don't clear the cache
          // As draft, scheduled or trash posts does not get cached
          if (!in_array($post->post_status, ['publish', 'private'])) {
            // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
            $post_ids_trying_to_clear_cache['post_status_is_not_publish_or_private'][] = "{$post_id} (Post Status: {$post->post_status})";

            // Skip the rest of the loop and continue with the next iteration
            continue;
          }

          if ($purge_related_urls) {
            // Generate the purge URLs based on the post id
            $urls_list = $this->objects['cache_controller']->get_post_related_links($post_id);
            
            // Add the users to list of urls to purge
            $list_of_urls_to_purge = [
              ...$list_of_urls_to_purge,
              ...$urls_list
            ];

            // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
            $post_ids_trying_to_clear_cache['cleared_cache'][] = $post_id;
          } else {
            // Get the permalink for this post id
            $permalink = get_permalink($post_id);

            if ($permalink) { // Ensure get_permalink returns a valid URL
              $list_of_urls_to_purge[] = $permalink;

              // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
              $post_ids_trying_to_clear_cache['cleared_cache'][] = $post_id;
            } else {
              // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
              $post_ids_trying_to_clear_cache['no_permalink_found'][] = $post_id;
            }
          }
        }

        if ( $this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY && $this->main_instance->get_plan_name() === 'Standard' ) {
          $this->objects['logs']->add_log('rest_api::clear_cache_based_post_ids', 'List of URLs to be cleared from cache: ' . print_r($list_of_urls_to_purge, true));
        }

        if (empty($list_of_urls_to_purge)) {
          return new \WP_Error( 'no_post_id_eligible_for_cache_purge', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids) . ' — None of the provided post ids are eligible for cache purge.', [ 'status' => 500 ] );
        }

        if ( !$this->objects['cache_controller']->purge_urls($list_of_urls_to_purge) ) {
          return new \WP_Error( 'something_went_wrong', __( 'An error occurred while cleaning the cache. Please contact support for further investigation.', 'bigscoots-cache' ), [ 'status' => 500 ] );
        }

        // Adjusted logging and response for clarity and to reflect action taken
        $action_taken = $purge_related_urls ? 'and related contents.' : 'permalink only.';
        $this->objects['logs']->add_log('rest_api::clear_cache_based_post_ids', "Cleared Cloudflare cache for post IDs: " . implode(',', $post_ids_trying_to_clear_cache['cleared_cache']) . " {$action_taken}");

        // Default response
        $response = [
          'success' => true,
          'status'  => 'success',
          'message' => 'Your request to clear cache has been submitted! Please allow up to 30 seconds for this to take effect.'
        ];

        // Adding to response header when we have post ids that doesn't exists
        if (!empty($post_ids_trying_to_clear_cache['post_doesnt_exists'])) {
          $response['post_ids_with_error']['post_doesnt_exists'] = $post_ids_trying_to_clear_cache['post_doesnt_exists'];

          $this->objects['logs']->add_log('rest_api::clear_cache_based_post_ids', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['post_doesnt_exists']) . ' — Either no post exists for these ids or the given ids are not for a post page.');
        }

        // Adding to response header when the post is part if ignored post type
        if (!empty($post_ids_trying_to_clear_cache['post_part_of_ignored_post_type'])) {
          $response['post_ids_with_error']['post_part_of_ignored_post_type'] = $post_ids_trying_to_clear_cache['post_part_of_ignored_post_type'];

          $this->objects['logs']->add_log('rest_api::clear_cache_based_post_ids', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['post_part_of_ignored_post_type']) . ' — These post ids belongs to BigScoots Cache ignored post types.');
        }

        // Adding to response header when the post status is not `publish` or `private`
        if (!empty($post_ids_trying_to_clear_cache['post_status_is_not_publish_or_private'])) {
          $response['post_ids_with_error']['post_status_is_not_publish_or_private'] = $post_ids_trying_to_clear_cache['post_status_is_not_publish_or_private'];

          $this->objects['logs']->add_log('rest_api::clear_cache_based_post_ids', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['post_status_is_not_publish_or_private']) . ' — No published or private post exists for these post ids.');
        }

        // Adding to response header when permalinks couldn't be found from the post ids
        if (!empty($post_ids_trying_to_clear_cache['no_permalink_found'])) {
          $response['post_ids_with_error']['no_permalink_found'] = $post_ids_trying_to_clear_cache['no_permalink_found'];

          $this->objects['logs']->add_log('rest_api::clear_cache_based_post_ids', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['no_permalink_found']) . ' — No permalink found for these post ids.');
        }

        // Return success response
        return new \WP_REST_Response($response, 200);
      break;

      case 'urls': // Purge by URLs
        // Check we have the URLs
        if ( empty( $data['urls'] ) || !isset( $data['urls'] ) ) {
          return new \WP_Error( 'no_data_provided', __( 'Please provide the URLs as a non-empty array.', 'bigscoots-cache' ), [ 'status' => 403 ] );
        }

        // Initialize an array to hold sanitized URLs
        $list_of_urls_to_purge = [];
        $list_of_urls_cant_be_purged = [];

        // Validate and sanitize urls to purge before processing
        foreach ($data['urls'] as $url) {
          // URL encode non ASCII characters
          $url = (string) $this->main_instance->encode_non_ascii_chars_in_url($url);

          // Validate URL format
          if (!$this->main_instance->is_valid_url($url)) {
            $list_of_urls_cant_be_purged[] = $url;
            continue; // Skip invalid URLs
          }
  
          // Sanitize the URL for safe use
          $url = esc_url_raw($url);
          $list_of_urls_to_purge[] = $url;
        }

        if ( !empty($list_of_urls_cant_be_purged) ) {
          $this->objects['logs']->add_log('rest_api::clear_cache_based_on_urls', 'These invalid URLs cannot be cleared from cache: ' . print_r($list_of_urls_cant_be_purged, true));
        }

        if ( empty($list_of_urls_to_purge) ) {
          return new \WP_Error('invalid_urls', __('None of the provided URLs are valid.', 'bigscoots-cache'), ['status' => 403]);
        }

        if ( $this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY && $this->main_instance->get_plan_name() === 'Standard' ) {
          $this->objects['logs']->add_log('rest_api::clear_cache_based_on_urls', 'List of URLs to be cleared from cache: ' . print_r($list_of_urls_to_purge, true));
        }

        if ( !$this->objects['cache_controller']->purge_urls($list_of_urls_to_purge) ) {
          return new \WP_Error( 'something_went_wrong', __( 'An error occurred while cleaning the cache. Please contact support for further investigation.', 'bigscoots-cache' ), [ 'status' => 500 ] );
        }

        $this->objects['logs']->add_log('rest_api::clear_cache_based_on_urls', 'Cleared Cloudflare cache for the given URLs.');

        // Return success response
        return new \WP_REST_Response([
          'success' => true,
          'status'  => 'success',
          'message' => 'Your request to clear cache has been submitted! Please allow up to 30 seconds for this to take effect.'
        ], 200);
      break;

      default:
        return new \WP_Error( 'improper_purge_type', __( 'The request cannot be processed as an improper purge type has been provided to the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
      break;
    }
  }

  /**
   * Process the Clear OPCache request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_clear_opcache_request(\WP_REST_Request $request)
  {
    // -------- Example of how to access request data inside the request (Not Used Here) - Start ---------- //
    // Get JSON data from the request body
    // $data = $request->get_json_params();

    // Check if JSON data is present
    // if ( empty( $data ) ) {
    //   return new \WP_Error( 'no_data_provided', __( 'Please provide the required JSON data to the request', 'bigscoots-cache' ), [ 'status' => 403 ] );
    // }

    // Example: Access a specific field in the JSON data
    // $some_data = isset( $data['some_key'] ) ? $data['some_key'] : '';
    // -------- Example of how to access request data inside the request (Not Used Here) - End ---------- //

    $this->objects = $this->main_instance->get_objects();

    // Return error if OPCache is not loaded for the site
    if ( !extension_loaded('Zend OPcache') ) {
      $this->objects['logs']->add_log('rest_api::clear_opcache', 'Can\'t clear Opcache. Zend OPcache extension is not loaded.');

      return new \WP_Error( 'opcache_not_loaded', __( 'Can\'t clear Opcache. Zend OPcache extension is not loaded.', 'bigscoots-cache' ), [ 'status' => 424 ] );
    }

    // Get Opcache status - cache it for 1 min
    $opcache_status = $this->main_instance->get_system_cache('opcache_status');
    if ($opcache_status === false) {
      $opcache_status = opcache_get_status();
      $this->main_instance->set_system_cache('opcache_status', $opcache_status, 60);
    }

    if ( !$opcache_status || !isset($opcache_status['opcache_enabled']) || $opcache_status['opcache_enabled'] === false ) {
      $this->objects['logs']->add_log('rest_api::clear_opcache', 'Can\'t clear Opcache. opcache_get_status() function did not return proper values.');

      return new \WP_Error( 'opcache_status_function_not_working', __( 'Can\'t clear Opcache. opcache_get_status() function did not return proper values.', 'bigscoots-cache' ), [ 'status' => 424 ] );
    }

    // Reset the Opcache - https://www.php.net/manual/en/function.opcache-reset.php#refsect1-function.opcache-reset-returnvalues
    if ( !opcache_reset() ) {
      $this->objects['logs']->add_log('rest_api::clear_opcache', 'Can\'t purge Opcache. opcache_reset() did not reset the cache and did not returned true.');

      return new \WP_Error( 'opcache_reset_function_not_working', __( 'Can\'t purge Opcache. opcache_reset() did not reset the cache and did not returned true.', 'bigscoots-cache' ), [ 'status' => 424 ] );
    }

    // OPCache Cleared Successfully — Log Response
    $this->objects['logs']->add_log('rest_api::clear_opcache', 'OPcache cleared successfully!');

    // OPCache Cleared Successfully — Return Response
    return new \WP_REST_Response(
      [
        'success' =>  true,
        'status'  =>  'success',
        'message' =>  'OPcache for your website has been cleared successfully!'
      ],
      200
    );
  }

  /**
   * Process Enable Cache request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_enable_cache_request(\WP_REST_Request $request)
  {
    $this->objects = $this->main_instance->get_objects();

    // Try to enable page cache and if encountered error throw the error
    if ( $this->objects['cache_controller']->enable_cache() === false ) {
      return new \WP_Error( 'enable_cache_error', __( 'An error occurred while enabling the cache on your website.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    $this->objects['logs']->add_log('rest_api::enable_cache', 'BigScoots Cache has been enabled.');

    // Return success response
    return new \WP_REST_Response(
      [
        'success'   =>  true,
        'status'    =>  'success',
        'message'   =>  __('BigScoots Cache has been enabled successfully on your website.', 'bigscoots-cache')
      ],
      200
    );
  }

  /**
   * Process Disable Cache request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_disable_cache_request(\WP_REST_Request $request)
  {
    $this->objects = $this->main_instance->get_objects();

    // Try to disable page cache and if encountered error throw the error
    if ( $this->objects['cache_controller']->disable_cache() === false ) {
      return new \WP_Error( 'disable_cache_error', __( 'An error occurred while disabling the cache on your website.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    $this->objects['logs']->add_log('rest_api::disable_cache', 'BigScoots Cache has been disabled.');

    // Return success response
    return new \WP_REST_Response(
      [
        'success'   =>  true,
        'status'    =>  'success',
        'message'   =>  __('BigScoots Cache has been disabled successfully on your website.', 'bigscoots-cache')
      ],
      200
    );
  }

  /**
   * Process the Save Settings Visibility Toggle request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_save_settings_visibility_toggle_request(\WP_REST_Request $request)
  {
    $this->objects = $this->main_instance->get_objects();

    // Get JSON data from the request body
    $data = $request->get_json_params();

    // Check if JSON data is present
    if ( empty( $data ) ) {
      return new \WP_Error( 'no_data_provided', __( 'Please provide the required JSON data to the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Making sure we have the proper user id for the request
    if ( empty( $data['user_id'] ) || !isset( $data['user_id'] ) ) {
      return new \WP_Error( 'user_id_not_provided', __( 'Please provide the user id as part of the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Making sure we have the show settings data
    if ( empty( $data['show_settings'] ) || !isset( $data['show_settings'] ) ) {
      return new \WP_Error( 'show_settings_not_provided', __( 'Please provide the show settings data as part of the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Making sure we have the settings page name that we want to perform the toggle action for
    if ( empty( $data['toggle_settings_page'] ) || !isset( $data['toggle_settings_page'] ) ) {
      return new \WP_Error( 'toggle_settings_page_not_provided', __( 'Please provide the settings page that you would like to toggle for as part of the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    } 

    // Get the user id and show settings data
    $user_id = (int) $data['user_id'];
    $show_settings = esc_attr( $data['show_settings'] ) === 'true' ? 'true' : 'false';
    $toggle_settings_page = esc_attr( $data['toggle_settings_page'] );

    if ( $user_id > 0 ) {
      // Create the meta key based on $toggle_settings_page
      switch($toggle_settings_page) {
        case 'bigscoots-cache-settings':
          $key = 'bs_cache_plugin_settings_visible';

          // Set the success message
          $message = ($show_settings === 'true') ? 'The advanced settings for BigScoots Cache plugin is unlocked successfully.' : 'The advanced settings for BigScoots Cache plugin is locked successfully.';
          $log_message = ($show_settings === 'true') ? "The advanced settings for BigScoots Cache plugin is unlocked successfully for user id: {$user_id}." : "The advanced settings for BigScoots Cache plugin is locked successfully for user id: {$user_id}.";
        break;

        case 'perfmatters-settings':
          $key = 'perfmatters_plugin_settings_visible';

          // Set the success message
          $message = ($show_settings === 'true') ? 'The advanced settings for Perfmatters plugin is unlocked successfully.' : 'The advanced settings for Perfmatters plugin is locked successfully.';
          $log_message = ($show_settings === 'true') ? "The advanced settings for Perfmatters plugin is unlocked successfully for user id: {$user_id}." : "The advanced settings for Perfmatters plugin is locked successfully for user id: {$user_id}.";
        break;

        default:
          return new \WP_Error( 'toggle_settings_key_cannot_be_set', __( 'Something went wrong! The user-meta key cannot be set. Please contact BigScoots support to check why this problem is happening.', 'bigscoots-cache' ), [ 'status' => 403 ] );
        break;
      }

      // Update the user meta based on the show settings data
      update_user_meta($user_id, $key, $show_settings);

      $this->objects['logs']->add_log('rest_api::settings_visibility', $log_message);

      // Return success response
      return new \WP_REST_Response(
        [
          'success'   =>  true,
          'status'    =>  'success',
          'message'   =>  $message
        ],
        200
      );
    } else {
      return new \WP_Error( 'invalid_user_id_provided', __( 'Invalid user id provided to the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }
  }

  /**
   * Process the Import Configuration request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_import_config_request(\WP_REST_Request $request)
  {
    $this->objects = $this->main_instance->get_objects();

    // Get JSON data from the request body
    $data = $request->get_json_params();

    // Check if JSON data is present
    if ( empty( $data ) ) {
      return new \WP_Error( 'no_data_provided', __( 'Please provide the required JSON data to the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Making sure we have the config for the request
    if ( empty( $data['config'] ) || !isset( $data['config'] ) ) {
      return new \WP_Error( 'config_not_provided', __( 'Please provide the configuration as part of the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    // Get the config
    $config = stripslashes($data['config']);
    $config = json_decode($config, true);

    // Try to import the config
    if ($this->main_instance->import_config($config) === false) {
      return new \WP_Error( 'invalid_config_provided', __( 'Invalid configuration provided to the request.', 'bigscoots-cache' ), [ 'status' => 403 ] );
    }

    $this->objects['logs']->add_log('rest_api::import_config', 'Configurations imported successfully.');

    // Return success response
    return new \WP_REST_Response(
      [
        'success'   =>  true,
        'status'    =>  'success',
        'message'   =>  'Configurations imported successfully. Now you must re-enable the page cache.'
      ],
      200
    );
  }

  /**
   * Process the Clear Logs request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_clear_logs_request(\WP_REST_Request $request) : \WP_REST_Response
  {
    $this->objects = $this->main_instance->get_objects();

    // Clear the logs
    $this->objects['logs']->reset_log();

    $this->objects['logs']->add_log('rest_api::clear_logs', 'Plugin log has been cleared.');

    // Return success response
    return new \WP_REST_Response(
      [
        'success'   =>  true,
        'status'    =>  'success',
        'message'   =>  'BigScoots Cache plugin log has been cleared successfully.'
      ],
      200
    );
  }

  /**
   * Process the get post type request
   *
   * @param WP_REST_Request $request Full data about the request.
   * @return WP_Error|WP_REST_Response
  **/
  public function process_get_post_type_request(\WP_REST_Request $request) : \WP_REST_Response
  {
    // Retrieve the 'id' parameter from the URL
    $post_id = (int) $request->get_param('id');

    // Retrieve post type from the post id
    $post_type = get_post_type( $post_id );

    $this->objects = $this->main_instance->get_objects();

    if ($post_type) {
      // Post Type fetched successfully — Log Response
      $this->objects['logs']->add_log('rest_api::get_post_type', "Post type fetched successfully for post id: {$post_id}.");

      // Post Type fetched successfully
      // Create the response object
      $response = new \WP_REST_Response(
        [
          'success'   =>  true,
          'status'    =>  'success',
          'message'   =>  'Post type fetched successfully!',
          'post_type' =>  $post_type
        ],
        200
      );

      // Set Cache-Control header to no cache as it's accessed via GET request
      $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
    } else {
      // Unable to fetch the post type for the given post id — Log Response
      $this->objects['logs']->add_log('rest_api::get_post_type', "Unable to fetch the post type for the given post id: {$post_id}!");

      // Post Type couldn't be fetched
      // Create the response object
      $response = new \WP_REST_Response(
        [
          'success'   =>  false,
          'status'    =>  'error',
          'message'   =>  "Unable to fetch the post type for the given post id: {$post_id}!",
        ],
        404
      );

      // Set Cache-Control header to no cache as it's accessed via GET request
      $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
    }

    // Return the response
    return $response;
  }
}