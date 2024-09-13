<?php
namespace BigScoots\Cache;

defined('ABSPATH') || wp_die('Cheatin&#8217; uh?');

/** @disregard P1009 - WP_CLI_Command PHP Class coming directly from WordPress core **/
class WP_CLI extends \WP_CLI_Command
{
  private \BigScoots_Cache $main_instance;
  private array $objects = [];

  public function __construct($main_instance)
  {
    $this->main_instance = $main_instance;
  }

  /**
   * Show current BigScoots Cache version
   *
   * @when after_wp_load
  **/
  public function version() : void
  {
    /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
    \WP_CLI::line('BigScoots Cache v' . get_option('bs_cache_version', false));
  }

  /**
   * Show the current status of BigScoots Cache plugin
   * 
   * @when after_wp_load
  **/
  public function status(array $args, array $assoc_args) : void
  {
    // Initialize the variables
    $this->objects = $this->main_instance->get_objects();
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

    // Make sure the request is successful and we got all the data that we need
    if ( isset( $dynamic_cache_url_resp_headers['success'] ) && !$dynamic_cache_url_resp_headers['success'] ) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error($dynamic_cache_url_resp_headers['message']);
    } else {
      $dynamic_cache_url_resp_headers = $dynamic_cache_url_resp_headers['headers'];
    }

    $home_url_resp_headers = $this->main_instance->get_response_header( home_url('/') );

    // Make sure the request is successful and we got all the data that we need
    if ( isset( $home_url_resp_headers['success'] ) && !$home_url_resp_headers['success'] ) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error($home_url_resp_headers['message']);
    } else {
      $home_url_resp_headers = $home_url_resp_headers['headers'];
    }

    $test_page_url_resp_headers = $this->main_instance->get_response_header( BS_CACHE_PLUGIN_URL . 'assets/testcache.html' );

    // Make sure the request is successful and we got all the data that we need
    if ( isset( $test_page_url_resp_headers['success'] ) && !$test_page_url_resp_headers['success'] ) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error($test_page_url_resp_headers['message']);
    } else {
      $test_page_url_resp_headers = $test_page_url_resp_headers['headers'];
    }

    for( $i = 0; $i < count($things_to_show); $i++ ) {
      $row_id = $i + 1;

      switch( $things_to_show[$i] ) {

        case 'Website Environment Type':
          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $this->main_instance->get_environment_type()
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

          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $plugin_cache_status
          ];
        break;

        case 'Plugin Version':
          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $this->main_instance->get_current_plugin_version()
          ];
        break;

        case 'Plugin Setup Mode':
          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $this->main_instance->get_plan_name()
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

          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $website_using_cloudflare
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

          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $cloudflare_setup_mode
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

          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $cache_rule_status
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

          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $home_url_cache_status
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

          $command_data[] = [
            'id'    =>  $row_id,
            'name'  =>  $things_to_show[$i],
            'value' =>  $test_page_url_cache_status
          ];
        break;
      }
    }

    /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
    $command_output_formatter = new \WP_CLI\Formatter($assoc_args, [
      'id',
      'name',
      'value'
    ]);

    $command_output_formatter->display_items($command_data);
  }

  /**
   * Enable cache
   *
   * @when after_wp_load
  **/
  public function enable_cache() : void
  {
    $this->objects = $this->main_instance->get_objects();

    if ($this->objects['cache_controller']->enable_cache()) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::success(__('Cache enabled successfully', 'bigscoots-cache'));
    } else {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(__('An error occurred while enabling the cache', 'bigscoots-cache'));
    }
  }

  /**
   * Disable cache
   *
   * @when after_wp_load
  **/
  public function disable_cache() : void
  {
    $this->objects = $this->main_instance->get_objects();

    if ($this->objects['cache_controller']->disable_cache()) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::success(__('Cache disabled successfully', 'bigscoots-cache'));
    } else {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(__('An error occurred while disabling the cache', 'bigscoots-cache'));
    }
  }

  /**
   * Purge cache for whole domain or specific post ids or URLs
   * 
   * Usage:
   * wp bs_cache purge_cache -> Purge everything for the domain
   * wp bs_cache purge_cache --post-ids=50 -> Purge cache for the URL which has post id 50 (related URLs not purged)
   * wp bs_cache purge_cache --post-ids=50,60,70 -> Purge cache for the URL which has post id 50, 60, 70 (related URLs not purged)
   * wp bs_cache purge_cache --post-ids=50 --related=yes -> Purge cache for the URL which has post id 50 (related URLs are also purged)
   * wp bs_cache purge_cache --post-ids=50,60,70 --related=yes -> Purge cache for the URL which has post id 50, 60, 70 (related URLs are also purged)
   * wp bs_cache purge_cache --urls="https://example.com/some-page/" -> Purge cache for the provided URL (related URLs doesn't work here)
   * wp bs_cache purge_cache --urls="https://example.com/some-page/, https://example.com/some-post/" -> Purge cache for the provided URLs (related URLs doesn't work here)
   * 
   * @when after_wp_load
  **/
  public function purge_cache(array $args, array $assoc_args) : void
  {
    // Do not clear the cache if the environment is `Staging` - proceed otherwise
    if ($this->main_instance->get_environment_type() === 'Staging') {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(__('This is a staging environment. Requests from staging sites are not cached, therefore cache clearing is not permitted.', 'bigscoots-cache'));
    }

    // Do not clear cache if the plugin setup is misconfigured
    if ($this->main_instance->get_plan_name() === 'Misconfigured') {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(__('Cache clearing operation failed due to misconfigurations in BigScoots Cache. Please contact support for further assistance.', 'bigscoots-cache'));
    }

    $this->objects = $this->main_instance->get_objects();

    // Purge by Post IDs
    if ( isset($assoc_args['post-ids']) ) {
      // Declare a variable to hold the URLs that needs to be purged
      $list_of_urls_to_purge = [];

      // Check if related URLs needs to be purged
      $purge_related_urls = ( isset($assoc_args['related']) && trim($assoc_args['related']) === 'yes' );

      // Get an array of post ids
      $post_ids = array_map( 'intval', explode(',', $assoc_args['post-ids']) );

      // Don't allow cache purge for the post ids that are part of ignored post type
      $ignored_post_types = apply_filters('bs_cache_ignored_post_types', $this->main_instance->get_single_config('cf_excluded_post_types', []));

      // Declare a variable to hold the list of post ids for which cache was actually purged
      $clear_cache_post_ids = [];

      // Loop through the post ids to add the URLs to the purge urls list
      foreach( $post_ids as $post_id ) {
        // Get the post object for the given post id
        $post = get_post($post_id);

        // Check we got a proper post id
        if (empty($post) || !$post instanceof \WP_Post) {
          // Show WP CLI warning message
          /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
          \WP_CLI::warning("Cannot purge cache for Post ID: {$post_id} — Either no post exists for this id or the given id is not for a post page.");

          // Skip the rest of the loop and continue with the next iteration
          continue;
        }

        // Check the post is not part of ignored post types
        if (is_array($ignored_post_types) && in_array($post->post_type, $ignored_post_types)) {
          // Show WP CLI warning message
          /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
          \WP_CLI::warning("Cannot purge cache for Post ID: {$post_id} (Post Type: {$post->post_type}) — This post id belongs to BigScoots Cache ignored post types.");

          // Skip the rest of the loop and continue with the next iteration
          continue;
        }

        // Check if the post status does not belong to `publish` or `private` - then don't clear the cache
        // As draft, scheduled or trash posts does not get cached
        if (!in_array($post->post_status, ['publish', 'private'])) {
          // Show WP CLI warning message
          /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
          \WP_CLI::warning("Cannot purge cache for Post ID: {$post_id} (Post Status: {$post->post_status}) — No published or private post exists for this post id.");

          // Skip the rest of the loop and continue with the next iteration
          continue;
        }

        // Get the related URLs if we need to clear cache for related URLs as well
        if ($purge_related_urls) {
          // Generate the purge URLs based on the post id
          $purge_urls = $this->objects['cache_controller']->get_post_related_links($post_id);

          // Add the users to list of urls to purge
          $list_of_urls_to_purge = [
            ...$list_of_urls_to_purge,
            ...$purge_urls
          ];

          // Add the post id to $clear_cache_post_ids for future log
          $clear_cache_post_ids[] = $post_id;
        } else {
          // Get the permalink for this post id
          $permalink = get_permalink($post_id);

          if ($permalink) {
            $list_of_urls_to_purge[] = $permalink;

            // Add the post id to $clear_cache_post_ids for future log
            $clear_cache_post_ids[] = $post_id;
          } else {
            // Show WP CLI warning message
            /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
            \WP_CLI::warning("Cannot purge cache for Post ID: {$post_id} — No permalink found for this post id.");
          }
        }
      }

      if (empty($list_of_urls_to_purge)) {
        // Show WP CLI error message
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::error('Cannot purge cache for Post IDs: ' . implode(', ', $post_ids) . ' — None of the provided post ids are eligible for cache purge.');
      }

      // Make sure we are removing the duplicate URLs from the $list_of_urls_to_purge
      $list_of_urls_to_purge = array_unique($list_of_urls_to_purge);

      if ( $this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == 2 && $this->main_instance->get_plan_name() === 'Standard' ) {
        $this->objects['logs']->add_log('wp_cli::purge_cache', 'List of URLs to be cleared from cache: ' . print_r($list_of_urls_to_purge, true));
      }

      // Clear cache for the generated URLs and log it
      if ($this->objects['cache_controller']->purge_urls($list_of_urls_to_purge)) {
        if ($purge_related_urls) {
          $this->objects['logs']->add_log('wp_cli::purge_cache', 'Successfully cleared cache for Post IDs: ' . implode(', ', $clear_cache_post_ids) . ' and it\'s related URLs.');

          /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
          \WP_CLI::success('Successfully cleared cache for Post IDs: ' . implode(', ', $clear_cache_post_ids) . ' and it\'s related URLs.');
        } else {
          $this->objects['logs']->add_log('wp_cli::purge_cache', 'Successfully cleared cache for Post IDs: ' . implode(', ', $clear_cache_post_ids) . '.');

          /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
          \WP_CLI::success('Successfully cleared cache for Post IDs: ' . implode(', ', $clear_cache_post_ids) . '.');
        }
      } else {
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::error(__('An error occurred while purging the cache. Please check the plugin log for further details.', 'bigscoots-cache'));
      }
    } elseif ( isset($assoc_args['urls']) ) { // Purge by URLs
      // Check if related URLs argument has been provided as it's not supported here
      $purge_related_urls_arg_provided = ( isset($assoc_args['related']) && trim($assoc_args['related']) === 'yes' );

      if ($purge_related_urls_arg_provided) {
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::error(__('Sorry! The `--related` argument is not supported when clearing cache by URLs. Please make another request without that argument.', 'bigscoots-cache'));
      }

      // Process the passed URLs to an array
      $list_of_urls_to_purge = array_map('trim', explode(',', $assoc_args['urls']));

      // Make sure we validate the URLs before we purge them
      $valid_list_of_urls_to_purge = [];

      foreach ($list_of_urls_to_purge as $url) {
        // URL encode non ASCII characters
        $url = (string) $this->main_instance->encode_non_ascii_chars_in_url($url);

        // Validate URL format
        if ($this->main_instance->is_valid_url($url)) {
          // Sanitize the URL for safe use
          $url = esc_url_raw($url);
          $valid_list_of_urls_to_purge[] = $url;
        } else {
          // Show WP CLI warning message
          /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
          \WP_CLI::warning("Cannot purge cache for URL: {$url} — Invalid URL provided.");
        }
      }

      if (empty($valid_list_of_urls_to_purge)) {
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::error(__('Sorry! Cannot clear cache for any of the given URLs as none of them are valid URLs.', 'bigscoots-cache'));
      }

      if ( $this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == 2 && $this->main_instance->get_plan_name() === 'Standard' ) {
        $this->objects['logs']->add_log('wp_cli::purge_cache', 'List of URLs to be cleared from cache: ' . print_r($list_of_urls_to_purge, true));
      }

      // Clear cache for the given URLs
      if ( $this->objects['cache_controller']->purge_urls($valid_list_of_urls_to_purge) ) {
        $this->objects['logs']->add_log('wp_cli::purge_cache', 'Successfully cleared cache for the given URLs: ' . print_r($valid_list_of_urls_to_purge, true));

        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::success(__('Successfully cleared cache for the given valid URLs.', 'bigscoots-cache'));
      } else {
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::error(__('An error occurred while purging the cache. Please check the plugin log for further details.', 'bigscoots-cache'));
      }
    } else {
      // Purge the cache for the entire domain if post-id or urls argument hasn't been provided
      if ($this->objects['cache_controller']->purge_all()) {
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::success(__('Successfully cleared cache for the entire domain.', 'bigscoots-cache'));

        if (!apply_filters('bs_cache_disable_clear_opcache', false)) {
          $this->purge_opcache();
        }

        if (!apply_filters('bs_cache_disable_clear_object_cache', false)) {
          $this->purge_object_cache();
        }
      } else {
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::error(__('An error occurred while purging the cache. Please check the plugin log for further details.', 'bigscoots-cache'));
      }
    }
  }

  /**
   * Purge Opcache
   *
   * @when after_wp_load
  **/
  public function purge_opcache() : void
  {
    $this->objects = $this->main_instance->get_objects();

    // Send a POST req to initiate the opcache purge action
    $response = wp_safe_remote_post(
      rest_url('bigscoots-cache/v2/clear-opcache'),
      [
        'method'    => 'DELETE',
        'sslverify' => false,
        'timeout'     =>  defined('BS_CACHE_CURL_TIMEOUT') ? BS_CACHE_CURL_TIMEOUT : 10,
        'user-agent'  => 'BigScoots-Cache/' . $this->main_instance->get_current_plugin_version() . '; ' . get_bloginfo('url'),
        'headers'   =>  [
          'Cache-Control'       =>  'no-cache',
          'Content-Type'        =>  'application/json',
          'x-bigscoots-request' =>  'yes'
        ]
      ]
    );

    if (is_wp_error($response)) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error('There was an error clearing the OPcache: ' . $response->get_error_message());
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_body['status'], $response_body['message'])) {
      if ($response_body['status'] === 'success') {
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::success( $response_body['message'] );

        $this->objects['logs']->add_log('wp_cli::purge_opcache', 'OPcache has been purged successfully!');
      } else {
        /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
        \WP_CLI::error( $response_body['message'] );
      }
    } else {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error('Something went wrong with processing the purge OPcache request. Response: ' . print_r($response_body, true));
    }
  }

  /**
   * Purge object cache
   *
   * @when after_wp_load
  **/
  public function purge_object_cache() : void
  {
    $this->objects = $this->main_instance->get_objects();

    // Attempt to run the wp cache flush command
    try {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::runcommand('cache flush');

      $message = 'Object cache has been purged successfully!';

      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::success($message);

      $this->objects['logs']->add_log('wp_cli::purge_object_cache', $message);
    } catch (\Exception $e) {
      $message = 'An error occurred while purging the object cache: ' . $e->getMessage();

      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error($message);

      $this->objects['logs']->add_log('wp_cli::purge_object_cache', $message);
    }
  }

  /**
   * Export config
   *
   * @when after_wp_load
  **/
  public function export_config(array $args) : void
  {
    if (!isset($args[0])) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(__('Plase enter the full path for the export file', 'bigscoots-cache'));
      return;
    }

    $export_path = $args[0];

    if (substr($export_path, -1) != '/') {
      $export_path .= '/';
    }

    $this->objects = $this->main_instance->get_objects();

    $config = $this->main_instance->export_config();
    $filename = $export_path . 'bs_cache_config.json';

    if (is_writable($filename)) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(sprintf('%s is not writable', $filename));
      return;
    }

    file_put_contents($filename, $config);

    /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
    \WP_CLI::success(sprintf('Config exported to %s', $filename));
  }

  /**
   * Import config
   *
   * @when after_wp_load
  **/
  public function import_config(array $args) : void
  {
    if (!isset($args[0])) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(__('Plase enter the full path to the export JSON file', 'bigscoots-cache'));
      return;
    }

    $filename = $args[0];

    if (!file_exists($filename)) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(sprintf('%s does not exists', $filename));
      return;
    }

    $this->objects = $this->main_instance->get_objects();
    $import_config = json_decode(trim(file_get_contents($filename)), true);

    if ($this->main_instance->import_config($import_config) === false) {
      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::error(__('Invalid data', 'bigscoots-cache'));
      return;
    }

    /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
    \WP_CLI::success(__('Data imported successfully', 'bigscoots-cache'));
  }
}