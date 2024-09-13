<?php
namespace BigScoots\Cache;

defined('ABSPATH') || wp_die('Cheatin&#8217; uh?');

class Controller
{
  private \BigScoots_Cache $main_instance;
  private array $objects = [];
  private bool $skip_cache = false;
  private bool $purge_all_already_done = false;
  private static int $recently_purged_post_id = 0;

  public function __construct($main_instance)
  {
    $this->main_instance = $main_instance;
    $this->actions();
  }

  private function actions() : void
  {
    // Show the list of hooks fired on this page load - Only enable for debugging
    // add_action('all', [$this, 'show_hook_name']);

    // This sets response headers for backend
    add_action('init', [$this, 'setup_response_headers_backend'], 0);

    // These set response headers for frontend
    add_action('send_headers', [$this, 'bypass_cache_on_init'], PHP_INT_MAX);
    add_action('template_redirect', [$this, 'apply_cache'], PHP_INT_MAX);

    // Bypass Cache on Error Pages
    add_filter('wp_robots', [$this, 'bypass_cache_on_error_page'], (PHP_INT_MAX - 1000));

    if ( $this->main_instance->get_single_config('cf_bypass_redirects', 0) > 0 ) {
      add_action('wp_redirect', [$this, 'apply_cache_on_redirects'], PHP_INT_MAX, 2);
    }

    // Purge cache via cronjob
    add_action('init', [$this, 'cronjob_purge_cache']);

    // Purge OPCache when Redis Object Cache is disabled - https://wordpress.org/plugins/redis-cache/
    add_action('redis_object_cache_disable', [$this, 'object_cache_disable']);

    // Do not execute the following purge cache actions if page cache is disabled
    if ( !$this->is_page_cache_disabled() ) {
      // Purge cache when upgrader process is complete
      if ( 
        $this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0 ||
        $this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0
      ) {
        add_action('upgrader_process_complete', [$this, 'purge_on_update'], PHP_INT_MAX);
      }

      // Process the following requests if the environment is not `Staging` or plugin is not `Misconfigured` 
      if ( 
        !( 
          ($this->main_instance->get_environment_type() === 'Staging') || 
          ($this->main_instance->get_plan_name() === 'Misconfigured') 
        ) 
      ) {
        // WP Rocket actions
        if ($this->main_instance->get_single_config('cf_wp_rocket_purge_on_domain_flush', 0) > 0) {
          add_action('after_rocket_clean_domain', [$this, 'wp_rocket_hooks'], PHP_INT_MAX);
        }

        if ($this->main_instance->get_single_config('cf_wp_rocket_purge_on_rucss_job_complete', 0) > 0) {
          add_action('rocket_rucss_complete_job_status', [$this, 'wp_rocket_selective_url_purge_hooks'], PHP_INT_MAX, 1);
        }

        // Woocommerce actions
        if ($this->main_instance->get_single_config('cf_auto_purge_woo_product_page', 0) > 0) {
          add_action('woocommerce_order_status_changed', [$this, 'woocommerce_purge_product_page_on_sale'], PHP_INT_MAX, 4);
        }

        // Woocommerce scheduled sales
        if ($this->main_instance->get_single_config('cf_auto_purge_woo_scheduled_sales', 0) > 0) {
          add_action('wc_after_products_starting_sales', [$this, 'woocommerce_purge_scheduled_sales'], PHP_INT_MAX);
          add_action('wc_after_products_ending_sales', [$this, 'woocommerce_purge_scheduled_sales'], PHP_INT_MAX);
        }

        // Edd actions
        if ($this->main_instance->get_single_config('cf_auto_purge_edd_payment_add', 0) > 0) {
          add_action('edd_built_order', [$this, 'edd_purge_cache_on_payment_add'], PHP_INT_MAX);
        }

        // YASR actions
        if ($this->main_instance->get_single_config('cf_yasr_purge_on_rating', 0) > 0) {
          add_action('yasr_action_on_overall_rating', [$this, 'yasr_hooks'], PHP_INT_MAX, 1);
          add_action('yasr_action_on_visitor_vote', [$this, 'yasr_hooks'], PHP_INT_MAX, 1);
          add_action('yasr_action_on_visitor_multiset_vote', [$this, 'yasr_hooks'], PHP_INT_MAX, 1);
        }

        // WP Asset Clean Up actions
        if ($this->main_instance->get_single_config('cf_wpacu_purge_on_cache_flush', 0) > 0) {
          add_action('wpacu_clear_cache_after', [$this, 'wpacu_hooks'], PHP_INT_MAX);
        }

        // WP Recipe Maker actions
        if ($this->main_instance->get_single_config('cf_wprm_purge_on_cache_flush', 0) > 0) {
          add_action('wprm_clear_cache', [$this, 'wp_recipe_maker_cache_purge'], PHP_INT_MAX, 1);
        }

        // Autoptimize actions
        if ($this->main_instance->get_single_config('cf_autoptimize_purge_on_cache_flush', 0) > 0) {
          add_action('autoptimize_action_cachepurged', [$this, 'autoptimize_hooks'], PHP_INT_MAX);
        }

        // Purge cache on comments
        if ( $this->main_instance->get_single_config('cf_auto_purge_on_comments', 0) > 0 && $this->is_cache_enabled() ) {
          add_action('transition_comment_status', [$this, 'purge_cache_on_comment_status_change'], PHP_INT_MAX, 3);
          add_action('trash_comment', [$this, 'purge_cache_when_comment_is_trashed'], PHP_INT_MAX, 2);
          add_action('delete_comment', [$this, 'purge_cache_when_comment_is_deleted'], PHP_INT_MAX);
        }

        // Purge cache on Theme edit
        $purge_actions = [
          'wp_update_nav_menu',                                     // When a custom menu is updated
          'avada_clear_dynamic_css_cache',                          // When Avada theme purge its own cache
          'switch_theme',                                           // When user changes the theme
          'customize_save_after',                                   // Edit theme
          'permalink_structure_changed',                            // When permalink structure is update
        ];
    
        foreach ($purge_actions as $action) {
          add_action($action, [$this, 'purge_cache_on_theme_edit'], PHP_INT_MAX);
        }

        // Purge Cache on Post Edit
        $purge_actions = [
          'delete_post',                      // Delete a post
          'wp_trash_post',                    // Before a post is sent to the Trash
          'edit_post',                        // Edit a post
          'elementor/editor/after_save',      // Elementor edit
        ];
    
        foreach ($purge_actions as $action) {
          add_action($action, [$this, 'purge_cache_on_post_edit'], PHP_INT_MAX);
        }

        // Purge Cache when schedules posts are published
        add_action('transition_post_status', [$this, 'purge_cache_on_post_status_change'], PHP_INT_MAX, 3);
      }
    }

    // WooCommerce Cookie Optimization Handler
    if ($this->main_instance->get_single_config('cf_optimize_woo_cookie', 0) > 0) {
      add_action('template_redirect', [$this, 'woocommerce_cache_friendly_cookie_handler'], PHP_INT_MAX);
      add_action('woocommerce_cart_item_removed', [$this, 'woocommerce_cache_friendly_cookie_handler'], PHP_INT_MAX);
      add_action('woocommerce_thankyou', [$this, 'woocommerce_cache_friendly_cookie_handler'], PHP_INT_MAX);
    }
    
    // Bypass WP JSON REST
    if ($this->main_instance->get_single_config('cf_bypass_wp_json_rest', 0) > 0) {
      add_filter('rest_send_nocache_headers', '__return_true');
    }

    // Metabox
    if ($this->main_instance->get_single_config('cf_disable_single_metabox', 0) === 0) {
      add_action('add_meta_boxes', [$this, 'add_metaboxes'], PHP_INT_MAX);
      add_action('save_post', [$this, 'bs_cache_cache_mbox_save_values'], PHP_INT_MAX);
    }
  }

  public function add_metaboxes() : void
  {
    $allowed_screen_ids = apply_filters('bs_cache_bypass_cache_meta_box_allowed_screen_ids', ['post', 'page']);

    add_meta_box(
      'bs_cache_cache_mbox',
      __('BigScoots Cache Settings', 'bigscoots-cache'),
      [$this, 'bs_cache_cache_mbox_callback'],
      $allowed_screen_ids,
      'side'
    );
  }

  public function bs_cache_cache_mbox_callback(\WP_Post $post) : void
  {
    $bypass_cache = (int) get_post_meta($post->ID, 'bs_cache_bypass_cache', true);
    ?>
      <label for="bs_cache_bypass_cache">
        <?php esc_html_e('Bypass the cache for this page', 'bigscoots-cache'); ?>
      </label>
      <select name="bs_cache_bypass_cache">
        <option value="0" <?php if ($bypass_cache === 0) echo esc_attr('selected'); ?>><?php esc_html__('No', 'bigscoots-cache'); ?></option>
        <option value="1" <?php if ($bypass_cache === 1) echo esc_attr('selected'); ?>><?php esc_html__('Yes', 'bigscoots-cache'); ?></option>
      </select>
    <?php
  }

  public function bs_cache_cache_mbox_save_values(int $post_id) : void
  {
    if (array_key_exists('bs_cache_bypass_cache', $_POST)) {
      update_post_meta($post_id, 'bs_cache_bypass_cache', sanitize_text_field($_POST['bs_cache_bypass_cache']));
    }
  }

  // Function to show the list of the hooks fired on this page - Only enable for debugging
  /* public function show_hook_name(string $hook_name) : void
  {
    global $wp_filter;
    if (isset($wp_filter[$hook_name]) && !str_contains($hook_name, 'block_')) {
      var_dump($hook_name . "\n");
    }
  } */

  /**
   * Function to check if the manifest file exists and it's size is more than 0
   * This file is used by Cloudflare do to Prefetch URLs
   * @link: https://developers.cloudflare.com/speed/optimization/content/prefetch-urls/
  **/
  private function manifest_file_exists() : bool
  {
    if ( defined('ABSPATH') ) {
      $cache_key = 'bs_cache_prefetch_manifest_file_exists';
      $manifest_file_path = ABSPATH . 'manifest.txt';

      // Check if the result is stored in cache
      $result = $this->main_instance->get_system_cache($cache_key);

      if ($result !== false) {
        return ($result === 'yes');
      }

      // Perform file existence check
      $manifest_file_exists = file_exists($manifest_file_path);

      // Perform file size check, suppressing warnings
      if ($manifest_file_exists) {
        $manifest_file_exists = (filesize($manifest_file_path) > 0);
      }

      // Store the result in cache for a short duration
      $this->main_instance->set_system_cache($cache_key, $manifest_file_exists ? 'yes' : 'no', HOUR_IN_SECONDS); // Cache for 1 hour

      return $manifest_file_exists;
    } else {
      return false;
    }
  }

  /**
   * This function checks the requested URL and then based on the URL set the Cache-Tag
   * which is then further used by Cloudflare to purge the cache for that specific tag.
   * 
   * So, if the user is visiting the Home Page, we will set the cache tag as "{$hostname}_front_page",
   * for other pages, the cache tag will be the path of the webpage
   * 
   * @link: https://developers.cloudflare.com/cache/how-to/purge-cache/purge-by-tags/
  **/
  public function get_cache_tag(string $current_url = 'not-passed') : string
  {
    if ($current_url === 'not-passed') {
      $current_url = (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) ? "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}" : 'not-set';
    }

    if ($current_url === 'not-set') return $current_url;

    // Parse the current URL
    $current_url_parsed = wp_parse_url($current_url);

    if (empty($current_url_parsed['path']) || $current_url_parsed['path'] === '/') return "{$current_url_parsed['host']}_front_page";

    // If it's not the home page then let's generate a optimized version of the path to be used on cache tag
    $current_url_path = $current_url_parsed['path'];

    // Check if the path is ending with `/` then remove the tailing `/`
    if (substr($current_url_path, -1) === '/') {
      $current_url_path = rtrim($current_url_path, '/');
    }

    // Generate parsed path to be used in the cache tag
    $current_url_path = str_replace(['/', '%'], '_', $current_url_path);

    return "{$current_url_parsed['host']}{$current_url_path}";
  }

  public function setup_response_headers_backend() : void
  {
    $this->objects = $this->main_instance->get_objects();

    if (is_admin()) {

      if (!$this->is_cache_enabled()) {

        add_filter('nocache_headers', function () : array {
          return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-BigScoots-Cache' => 'disabled',
            'X-BigScoots-Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-BigScoots-Cache-Plan' => $this->main_instance->get_plan_name()
          ];
        }, PHP_INT_MAX);

      } else {

        add_filter('nocache_headers', function () : array {
          return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-BigScoots-Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-BigScoots-Cache' => 'no-cache',
            'X-BigScoots-Cache-Plan' => $this->main_instance->get_plan_name(),
            'Pragma' => 'no-cache',
            'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time())
          ];
        }, PHP_INT_MAX);

      }
    }

    if (!$this->is_cache_enabled()) {

      add_filter('nocache_headers', function () : array {
        return [
          'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
          'X-BigScoots-Cache' => 'disabled',
          'X-BigScoots-Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
          'X-BigScoots-Cache-Plan' => $this->main_instance->get_plan_name()
        ];
      }, PHP_INT_MAX);

    } elseif ($this->is_url_to_bypass() || $this->can_i_bypass_cache()) {

      add_filter('nocache_headers', function () : array {
        return [
          'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
          'X-BigScoots-Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
          'X-BigScoots-Cache' => 'no-cache',
          'X-BigScoots-Cache-Plan' => $this->main_instance->get_plan_name(),
          'Pragma' => 'no-cache',
          'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time())
        ];
      }, PHP_INT_MAX);

    } else {

      add_filter('nocache_headers', function () : array {
        $headers = [
          'Cache-Control' => $this->get_cache_control_value(), // Used by Cloudflare
          'X-BigScoots-Cache-Control' => $this->get_cache_control_value(), // Used by all
          'X-BigScoots-Cache' => 'cache',
          'X-BigScoots-Cache-Plan' => $this->main_instance->get_plan_name()
        ];

        // For Cloudflare Enterprise Plan users & sites that support "Purge By Prefix" add the Cache-Tag & Prefetch URLs manifest file
        /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
        if ($this->main_instance->get_plan_name() === 'Performance+' || ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE)) {
          $headers['Cache-Tag'] = $this->get_cache_tag();

          if ( $this->main_instance->get_single_config('cf_prefetch_urls', 0) > 0 && $this->manifest_file_exists() ) {
            $site_hostname_url = home_url('', 'https');
            $headers['Link'] = "<{$site_hostname_url}/manifest.txt>; rel=\"prefetch\"";
          }
        }

        return $headers;
      }, PHP_INT_MAX);

    }
  }

  public function bypass_cache_on_init() : void
  { // This fires on the redirects when bypass for redirects is not on
    if (is_admin()) return;

    $this->objects = $this->main_instance->get_objects();

    if (!$this->is_cache_enabled()) {
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache: disabled');
      header('X-BigScoots-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());
      return;
    }

    if ($this->skip_cache) return;

    header_remove('Pragma');
    header_remove('Expires');
    header_remove('Cache-Control');

    if ($this->is_url_to_bypass()) {
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('Pragma: no-cache');
      header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));
      header('X-BigScoots-Cache: no-cache');
      header('X-BigScoots-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());

      $this->skip_cache = true;
      return;
    }

    if ($this->is_cache_enabled()) {
      header('Cache-Control: ' . $this->get_cache_control_value());
      header('X-BigScoots-Cache: cache');
      header('X-BigScoots-Cache-Control: ' . $this->get_cache_control_value());
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());

      // For Cloudflare Enterprise Plan users & sites that support "Purge By Prefix" add the Cache-Tag & Prefetch URLs manifest file
      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      if ( $this->main_instance->get_plan_name() === 'Performance+' || ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE) ) {
        header('Cache-Tag: ' . $this->get_cache_tag());

        if ( $this->main_instance->get_single_config('cf_prefetch_urls', 0) > 0 && $this->manifest_file_exists() ) {
          $site_hostname_url = home_url('', 'https');
          header("Link: <{$site_hostname_url}/manifest.txt>; rel=\"prefetch\"");
        }
      }
    }
  }

  public function apply_cache() : void
  { // This function fires on the normal WP Posts/Pages/CPT
    if (is_admin()) return;

    $this->objects = $this->main_instance->get_objects();

    if (!$this->is_cache_enabled()) {
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache: disabled');
      header('X-BigScoots-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());
      return;
    }

    if ($this->skip_cache) {
      return;
    }

    if ($this->can_i_bypass_cache()) {
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('Pragma: no-cache');
      header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));
      header('X-BigScoots-Cache: no-cache');
      header('X-BigScoots-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());
      return;
    }

    if ($this->main_instance->get_single_config('cf_strip_cookies', 0) > 0) {
      header_remove('Set-Cookie');
    }

    header_remove('Pragma');
    header_remove('Expires');
    header_remove('Cache-Control');

    // These headers are getting added to the normal pages when cache is enabled
    header('Cache-Control: ' . $this->get_cache_control_value());
    header('X-BigScoots-Cache: cache');
    header('X-BigScoots-Cache-Control: ' . $this->get_cache_control_value());
    header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());

    // For Cloudflare Enterprise Plan users & sites that support "Purge By Prefix" add the Cache-Tag & Prefetch URLs manifest file
    /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
    if ( $this->main_instance->get_plan_name() === 'Performance+' || ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE) ) {
      header('Cache-Tag: ' . $this->get_cache_tag());

      if ( $this->main_instance->get_single_config('cf_prefetch_urls', 0) > 0 && $this->manifest_file_exists() ) {
        $site_hostname_url = home_url('', 'https');
        header("Link: <{$site_hostname_url}/manifest.txt>; rel=\"prefetch\"");
      }
    }
  }

  // This function checks if the the page has 5XX error then send no-cache header
  public function bypass_cache_on_error_page(array $robots) : array
  {
    // Get the current HTTP response code
    $http_status_code = http_response_code();

    // Check if the HTTP response code starts with '5'
    if (intval( floor($http_status_code / 100) ) === 5) {
      $this->objects = $this->main_instance->get_objects();

      // Set the "Cache-Control: no-store, no-cache, must-revalidate, max-age=0" header
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('Pragma: no-cache');
      header('X-BigScoots-Cache: no-cache');
      header('X-BigScoots-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());

      // For Cloudflare Enterprise Plan users & sites that support "Purge By Prefix" add the Cache-Tag & Prefetch URLs manifest file
      /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
      if ( $this->main_instance->get_plan_name() === 'Performance+' || ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_ZONE_ID_SALT') && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE) ) {
        header('Cache-Tag: ' . $this->get_cache_tag());

        if ( $this->main_instance->get_single_config('cf_prefetch_urls', 0) > 0 && $this->manifest_file_exists() ) {
          $site_hostname_url = home_url('', 'https');
          header("Link: <{$site_hostname_url}/manifest.txt>; rel=\"prefetch\"");
        }
      }
    }

    return $robots;
  }

  // Modify the headers for redirects
  public function apply_cache_on_redirects(string $location, int $status) : string
  {
    if (is_admin()) return $location;

    $this->objects = $this->main_instance->get_objects();

    if (!$this->is_cache_enabled()) {

      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache: disabled');
      header('X-BigScoots-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());

      return $location;

    }
    
    if ( in_array( $status, [301, 302, 304, 307, 308] ) ) { // Send no cache header as the user has selected the option not to cache redirects

      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache: no-cache');
      header('X-BigScoots-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());

      return $location;
    }

    if ($this->skip_cache) {
      return $location;
    }

    if ($this->can_i_bypass_cache()) {
      header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('Pragma: no-cache');
      header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time()));
      header('X-BigScoots-Cache: no-cache');
      header('X-BigScoots-Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
      header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());
      return $location;
    }

    header_remove('Pragma');
    header_remove('Expires');
    header_remove('Cache-Control');

    // These headers are getting added to the redirects when cache is enabled
    header('Cache-Control: ' . $this->get_cache_control_value());
    header('X-BigScoots-Cache: cache');
    header('X-BigScoots-Cache-Control: ' . $this->get_cache_control_value());
    header('X-BigScoots-Cache-Plan: ' . $this->main_instance->get_plan_name());
    header('X-BigScoots-Cache-Status-Code: ' . $status);

    // For Cloudflare Enterprise Plan users & sites that support "Purge By Prefix" add the Cache-Tag & Prefetch URLs manifest file
    /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
    if ( $this->main_instance->get_plan_name() === 'Performance+' || ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_ZONE_ID_SALT') && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE) ) {
      header('Cache-Tag: ' . $this->get_cache_tag());

      if ( $this->main_instance->get_single_config('cf_prefetch_urls', 0) > 0 && $this->manifest_file_exists() ) {
        $site_hostname_url = home_url('', 'https');
        header("Link: <{$site_hostname_url}/manifest.txt>; rel=\"prefetch\"");
      }
    }

    return $location;
  }

  public function purge_all() : bool
  {
    $this->objects = $this->main_instance->get_objects();
    $error = '';

    // Avoid to send multiple purge requests for the same session
    if ($this->purge_all_already_done) {
      return true;
    }

    // Purge everything in Cloudflare
    if (!$this->objects['cloudflare']->purge_entire_cache($error)) {
      $this->objects['logs']->add_log('cache_controller::purge_all', "Unable to purge the whole Cloudflare cache due to error: {$error}");
      return false;
    }

    // Must purge OPcache as it's a purge all request
    if (!apply_filters('bs_cache_disable_clear_opcache', false) && !defined('WP_CLI')) {
      $this->purge_opcache();
    }

    // Must purge object cache as it's a purge all request
    if (!apply_filters('bs_cache_disable_clear_object_cache', false) && !defined('WP_CLI')) {
      $this->purge_object_cache();
    }

    // Add Log that we've purged everything from Cloudflare cache for this website
    $this->objects['logs']->add_log('cache_controller::purge_all', 'Purged everything from Cloudflare cache!');

    do_action('bs_cache_purge_all');

    $this->purge_all_already_done = true;

    return true;
  }

  public function purge_urls(array $urls) : bool
  {
    if (empty($urls)) return false;

    $this->objects = $this->main_instance->get_objects();
    $error = '';

    // Strip out external links or invalid URLs
    foreach ($urls as $array_index => $single_url) {
      if ($this->is_external_link($single_url) || substr(strtolower($single_url), 0, 4) != 'http') {
        unset($urls[$array_index]);
      }
    }

    if (!$this->objects['cloudflare']->purge_cache_urls($urls, $error)) {
      $this->objects['logs']->add_log('cache_controller::purge_urls', "Unable to purge some URLs from Cloudflare due to error: {$error}");
      return false;
    }

    $this->objects['logs']->add_log('cache_controller::purge_urls', 'Purged specific URLs from Cloudflare cache');

    do_action('bs_cache_purge_urls', $urls);

    return true;
  }

  public function cronjob_purge_cache() : void
  {
    // Do not process request if the environment is `Staging` and plugin setup is `Misconfigured` - proceed otherwise
    if ( ($this->main_instance->get_environment_type() === 'Staging') || ($this->main_instance->get_plan_name() === 'Misconfigured') ) return;

    if ($this->is_cache_enabled() && isset($_GET['bscache-purge-all']) && $_GET['bscache-sec-key'] == $this->main_instance->get_single_config('cf_purge_url_secret_key', wp_generate_password(20, false, false))) {

      $this->objects = $this->main_instance->get_objects();

      $this->purge_all();
      $this->objects['logs']->add_log('cache_controller::cronjob_purge_cache', 'Cache purging complete');

      exit('Cache purged');
    }
  }

  /**
   * @param string $new_status The new comment status.
   * @param string $old_status The old comment status.
   * @param WP_Comment $comment    Comment object.
  **/
  public function purge_cache_on_comment_status_change(string $new_status, string $old_status, \WP_Comment $comment) : void
  {
    // Store the trashed comment id so that we can ensure purge cache on post edit doesn't happen
    self::$recently_purged_post_id = $comment->comment_post_ID;

    if ( ($old_status !== $new_status && $new_status === 'approved') || ($old_status === 'approved' && $new_status === 'unapproved') || ($old_status === 'approved' && $new_status === 'spam') ) {
      $this->main_instance->set_system_cache('purge_cache_on_comment_status_change', 'purging', 5); // Expire in 5 seconds

      $done = $this->main_instance->get_system_cache("purged_cache_comment_status_change_done_{$comment->comment_post_ID}");

      // $this->objects['logs']->add_log('cache_controller::should_process_purge',  $done );

      if ($done === 'already_purged_wait_30s') return;

      $current_action = function_exists('current_action') ? current_action() : '';

      $this->objects = $this->main_instance->get_objects();

      $clear_cache_for_related_pages = ( $this->main_instance->get_single_config('cf_auto_purge_related_pages_on_comments', 0) > 0 );
      $urls = [];

      if ( $clear_cache_for_related_pages ) {
        $urls = $this->get_post_related_links($comment->comment_post_ID);
      } else {
        $permalink = get_permalink($comment->comment_post_ID);

        if ($permalink) {
          $urls[] = $permalink;
        } else {
          $this->objects['logs']->add_log('cache_controller::purge_cache_on_comment_status_change', "Cache cannot be cleared as no permalink found for Post ID: {$comment->comment_post_ID}");

          // Return - Permalink couldn't be fetched
          return;
        }
      }

      $this->purge_urls($urls);

      if ( $clear_cache_for_related_pages ) {
        $this->objects['logs']->add_log('cache_controller::purge_cache_on_comment_status_change', "Purge Cloudflare cache for single post page and related pages (Post ID: {$comment->comment_post_ID}) [Comment Status: {$old_status} -> {$new_status}] - Fired action: {$current_action} — System Cache: purged_cache_comment_status_change_done_{$comment->comment_post_ID} created — Purge action rate limited for next 30 sec");
      } else {
        $this->objects['logs']->add_log('cache_controller::purge_cache_on_comment_status_change', "Purge Cloudflare cache for only the single post page (Post ID: {$comment->comment_post_ID}) [Comment Status: {$old_status} -> {$new_status}] - Fired action: {$current_action} — System Cache: purged_cache_comment_status_change_done_{$comment->comment_post_ID} created — Purge action rate limited for next 30 sec");
      }

      $this->main_instance->set_system_cache("purged_cache_comment_status_change_done_{$comment->comment_post_ID}", 'already_purged_wait_30s', 30); // Expire in 30 sec
    }
  }

  /**
   * @param string     $comment_id The comment ID as a numeric string.
   * @param WP_Comment $comment    The comment to be trashed.
  **/
  public function purge_cache_when_comment_is_trashed(string $comment_ID, \WP_Comment $comment) : void
  {
    // Store the trashed comment id so that we can ensure purge cache on post edit doesn't happen
    self::$recently_purged_post_id = $comment->comment_post_ID;

    // Don't purge the cache is the comment is not currently in approved state
    if ($comment->comment_approved != '1') return;

    $this->main_instance->set_system_cache('purge_cache_when_comment_is_trashed', 'purging', 5); // Expire in 5 seconds

    $done = $this->main_instance->get_system_cache("purged_cache_comment_trashed_done_{$comment->comment_post_ID}");

    // $this->objects['logs']->add_log('cache_controller::should_process_purge',  $done );

    if ($done === 'already_purged_wait_30s') return;

    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    $clear_cache_for_related_pages = ( $this->main_instance->get_single_config('cf_auto_purge_related_pages_on_comments', 0) > 0 );
    $urls = [];

    if ( $clear_cache_for_related_pages ) {
      $urls = $this->get_post_related_links($comment->comment_post_ID);
    } else {
      $permalink = get_permalink($comment->comment_post_ID);

      if ($permalink) {
        $urls[] = $permalink;
      } else {
        $this->objects['logs']->add_log('cache_controller::purge_cache_when_comment_is_trashed', "Cache cannot be cleared as no permalink found for Post ID: {$comment->comment_post_ID}");

        // Return - Permalink couldn't be fetched
        return;
      }
    }

    $this->purge_urls($urls);

    if ( $clear_cache_for_related_pages ) {
      $this->objects['logs']->add_log('cache_controller::purge_cache_when_comment_is_trashed', "Purge Cloudflare cache for single post page and related pages (Post ID: {$comment->comment_post_ID}) - Fired action: {$current_action} — System Cache: purged_cache_comment_trashed_done_{$comment->comment_post_ID} created — Purge action rate limited for next 30 sec");
    } else {
      $this->objects['logs']->add_log('cache_controller::purge_cache_when_comment_is_trashed', "Purge Cloudflare cache for only the single post page (Post ID: {$comment->comment_post_ID}) - Fired action: {$current_action} — System Cache: purged_cache_comment_trashed_done_{$comment->comment_post_ID} created — Purge action rate limited for next 30 sec");
    }

    $this->main_instance->set_system_cache("purged_cache_comment_trashed_done_{$comment->comment_post_ID}", 'already_purged_wait_30s', 30); // Expire in 30 sec
  }

  /**
   * @param string     $comment_id The comment ID as a numeric string.
  **/
  public function purge_cache_when_comment_is_deleted($comment_ID) : void
  {
    // Get comment object
    $comment_ID = (int) $comment_ID;
    $comment = get_comment($comment_ID);

    // If we don't have an object then don't do anything.
    if (!$comment instanceof \WP_Comment) return;

    // Don't purge the cache is the comment is not currently in approved state
    if ($comment->comment_approved != '1') return;

    $this->main_instance->set_system_cache('purge_cache_when_comment_is_deleted', 'purging', 5); // Expire in 5 seconds

    $done = $this->main_instance->get_system_cache("purged_cache_comment_deleted_done_{$comment->comment_post_ID}");

    // $this->objects['logs']->add_log('cache_controller::should_process_purge',  $done );

    if ($done === 'already_purged_wait_30s') return;

    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    $clear_cache_for_related_pages = ( $this->main_instance->get_single_config('cf_auto_purge_related_pages_on_comments', 0) > 0 );
    $urls = [];

    if ( $clear_cache_for_related_pages ) {
      $urls = $this->get_post_related_links($comment->comment_post_ID);
    } else {
      $permalink = get_permalink($comment->comment_post_ID);

      if ($permalink) {
        $urls[] = $permalink;
      } else {
        $this->objects['logs']->add_log('cache_controller::purge_cache_when_comment_is_deleted', "Cache cannot be cleared as no permalink found for Post ID: {$comment->comment_post_ID}");

        // Return - Permalink cannot be fetched
        return;
      }
    }

    $this->purge_urls($urls);

    if ( $clear_cache_for_related_pages ) {
      $this->objects['logs']->add_log('cache_controller::purge_cache_when_comment_is_deleted', "Purge Cloudflare cache for single post page and related pages (Post ID: {$comment->comment_post_ID}) - Fired action: {$current_action} — System Cache: purged_cache_comment_deleted_done_{$comment->comment_post_ID} created — Purge action rate limited for next 30 sec");
    } else {
      $this->objects['logs']->add_log('cache_controller::purge_cache_when_comment_is_deleted', "Purge Cloudflare cache for only the single post page (Post ID: {$comment->comment_post_ID}) - Fired action: {$current_action} — System Cache: purged_cache_comment_deleted_done_{$comment->comment_post_ID} created — Purge action rate limited for next 30 sec");
    }

    $this->main_instance->set_system_cache("purged_cache_comment_deleted_done_{$comment->comment_post_ID}", 'already_purged_wait_30s', 30); // Expire in 30 sec
  }

  public function purge_cache_on_theme_edit() : void
  {
    if (($this->main_instance->get_single_config('cf_auto_purge', 0) > 0 || $this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) && $this->is_cache_enabled()) {

      $this->main_instance->set_system_cache('purge_cache_on_theme_edit', 'purging', 5); // Expire in 5 seconds

      $done = $this->main_instance->get_system_cache('purged_cache_on_theme_edit_done');

      // $this->objects['logs']->add_log('cache_controller::should_process_purge',  $done );

      if ($done === 'already_purged_wait_60s') return;

      $current_action = function_exists('current_action') ? current_action() : '';

      $this->objects = $this->main_instance->get_objects();

      $this->purge_all();

      $this->objects['logs']->add_log('cache_controller::purge_cache_on_theme_edit', "Purge whole Cloudflare cache - Fired action: {$current_action} — System Cache: purged_cache_on_theme_edit_done created — Purge action rate limited for next 60 sec");

      $this->main_instance->set_system_cache('purged_cache_on_theme_edit_done', 'already_purged_wait_60s', 60); // Expire in 60 sec
    }
  }

  public function purge_cache_on_post_edit(int $post_id) : void
  {
    // Don't invoke this for trashing comments
    if ( self::$recently_purged_post_id > 0 ) {
      self::$recently_purged_post_id = 0;
      return;
    }

    // Don't clear cache unnecessarily
    if ( 
      $this->main_instance->get_system_cache('purge_cache_on_theme_edit') === 'purging' || 
      $this->main_instance->get_system_cache('purge_cache_on_post_status_change') === 'purging' ||
      $this->main_instance->get_system_cache('purge_cache_on_comment_status_change') === 'purging' ||
      $this->main_instance->get_system_cache('purge_cache_when_comment_is_trashed') === 'purging' ||
      $this->main_instance->get_system_cache('purge_cache_when_comment_is_deleted') === 'purging'
    ) return;

    $done = $this->main_instance->get_system_cache("purged_cache_post_edit_done_{$post_id}");

    // $this->objects['logs']->add_log('cache_controller::should_process_purge',  $done );

    if ($done === 'already_purged_wait_60s') return;

    // Get the post object of the current post id
    $post = get_post($post_id);

    // Do not clear cache if the post is not a WP_Post
    if (!$post instanceof \WP_Post) return;

    // No need to clear post if the post status is not in the allowed list
    if (!in_array( $post->post_status, ['publish', 'private'] ) ) return;

    // Ignore cache purge if the post is from the ignored post types
    $ignored_post_types = apply_filters('bs_cache_ignored_post_types', $this->main_instance->get_single_config('cf_excluded_post_types', []));

    // Do not clear cache if the current post type is within the ignored post types
    if (!empty($ignored_post_types) && in_array($post->post_type, $ignored_post_types)) return;

    // Do not run this on the WordPress Nav Menu Pages
    global $pagenow;
    if ($pagenow === 'nav-menus.php') return;

    // Time to clear the cache
    if (($this->main_instance->get_single_config('cf_auto_purge', 0) > 0 || $this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) && $this->is_cache_enabled()) {

      $this->main_instance->set_system_cache('purge_cache_on_post_edit', 'purging', 5); // Expire in 5 seconds

      $current_action = function_exists('current_action') ? current_action() : '';

      $this->objects = $this->main_instance->get_objects();

      if ($this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) {
        $this->purge_all();
        $this->objects['logs']->add_log('cache_controller::purge_cache_on_post_edit', "Purge whole Cloudflare cache (fired action: {$current_action}) [Post ID: {$post_id}] [Post Type: {$post->post_type}]");
      } else {
        $this->purge_urls( $this->get_post_related_links($post_id) );
        $this->objects['logs']->add_log('cache_controller::purge_cache_on_post_edit', "Purge Cloudflare cache for only post id {$post_id} (Post Type: {$post->post_type}) and related contents - Fired action: {$current_action} — System Cache: purged_cache_post_edit_done_{$post_id} created — Purge action rate limited for next 60 sec");
      }

      $this->main_instance->set_system_cache("purged_cache_post_edit_done_{$post_id}", 'already_purged_wait_60s', 60); // Expire in 60 sec
    }
  }

  public function purge_cache_on_post_status_change(string $new_status, string $old_status, \WP_Post $post) : void
  {
    // Don't clear cache unnecessarily
    if ( $this->main_instance->get_system_cache('purge_cache_on_theme_edit') === 'purging' || $this->main_instance->get_system_cache('purge_cache_on_post_edit') === 'purging' ) return;

    $done = $this->main_instance->get_system_cache("purge_cache_on_post_status_change_done_{$post->ID}");

    // $this->objects['logs']->add_log('cache_controller::should_process_purge',  $done );

    if ($done === 'already_purged_wait_60s') return;

    // Ignore cache purge if the post is from the ignored post types
    $ignored_post_types = apply_filters('bs_cache_ignored_post_types', $this->main_instance->get_single_config('cf_excluded_post_types', []));

    // Do not clear cache if the current post type is within the ignored post types
    if (!empty($ignored_post_types) && in_array($post->post_type, $ignored_post_types)) return;

    if (($this->main_instance->get_single_config('cf_auto_purge', 0) > 0 || $this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) && $this->is_cache_enabled()) {

      if ( 
        ( in_array($old_status, ['future', 'draft', 'pending']) && in_array($new_status, ['publish', 'private']) ) ||
        ( in_array($old_status, ['publish', 'private']) && $new_status === 'draft' )
      ) {
        self::$recently_purged_post_id = $post->ID;

        $this->main_instance->set_system_cache('purge_cache_on_post_status_change', 'purging', 5); // Expire in 5 sec

        $current_action = function_exists('current_action') ? current_action() : '';

        $this->objects = $this->main_instance->get_objects();

        if ($this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0) {
          $this->purge_all();
          $this->objects['logs']->add_log('cache_controller::purge_cache_on_post_status_change', "Purge whole Cloudflare cache (fired action: {$current_action}) [Post ID: {$post->ID}] [Post Type: {$post->post_type}]");
        } else {
          $this->purge_urls( $this->get_post_related_links($post->ID) );
          $this->objects['logs']->add_log('cache_controller::purge_cache_on_post_status_change', "Purge Cloudflare cache for only post id {$post->ID} (Post Type: {$post->post_type}) and related contents - Fired action: {$current_action} — System Cache: purge_cache_on_post_status_change_done_{$post->ID} created — Purge action rate limited for next 60 sec");
        }
      }
    }

    $this->main_instance->set_system_cache("purge_cache_on_post_status_change_done_{$post->ID}", 'already_purged_wait_60s', 60); // Expire in 60 sec
  }

  public function get_post_related_links(int $post_id) : array
  {
    global $wp_rewrite;
    
    $this->objects = $this->main_instance->get_objects();

    // Initialize the variable that will hold all the list of URL for which cache needs to be purged
    $list_of_urls = apply_filters('bs_cache_post_related_url_init', __return_empty_array(), $post_id);

    $post_type = get_post_type($post_id);
    $using_permalink = $wp_rewrite->using_permalinks();

    // If the post ID doesn't exists then return blank array else proceed
    if (!$post_type) return $list_of_urls;

    /**
     * Before we proceed further, lets store the permalink fro post id to the $list_of_urls
     * it's the least that we need to purge for any given post id
    **/
    $permalink_from_post_id = get_permalink($post_id);

    $list_of_urls[] = $permalink_from_post_id;

    /**
     * Add a filter to check if the site don't want to purge related URLs (like taxonomy, author, category etc.)
     * It is highly discouraged to use this filter. But it's here in case any site ever need it.
     * Use it with Caution as when this filter is active no related URLs will be purged
     * By default this feature is disabled and $disable_related_urls_purge is set to `false`
    **/
    $disable_related_urls_purge = apply_filters('bs_cache_disable_related_urls_purge', __return_false(), $post_id);

    // If disable_related_urls_purge is enabled return list_of_urls from here & don't proceed further
    if ($disable_related_urls_purge) return $list_of_urls;

    // Get the home page URL
    $home_url = home_url('/');

    //Purge taxonomies terms URLs
    $post_type_taxonomies = get_object_taxonomies($post_type);
    $added_parents = []; // To keep track of added parent terms

    foreach ($post_type_taxonomies as $taxonomy) {

      if ($taxonomy instanceof \WP_Taxonomy && ($taxonomy->public == false || $taxonomy->rewrite == false)) {
        continue;
      }

      $terms = get_the_terms($post_id, $taxonomy);

      if (empty($terms) || is_wp_error($terms)) {
        continue;
      }

      foreach ($terms as $term) {
        // Add current term link
        $term_link = get_term_link($term);

        if (!is_wp_error($term_link)) {

          $list_of_urls[] = $term_link;

          if ( $this->main_instance->get_plan_name() === 'Standard' && ($this->main_instance->get_single_config('cf_post_per_page', 0) > 0) && $using_permalink ) {

            // Thanks to Davide Prevosto for the suggest
            $term_count   = $term->count;
            $pages_number = ceil($term_count / $this->main_instance->get_single_config('cf_post_per_page', 0));
            $max_pages    = $pages_number > 10 ? 10 : $pages_number; // Purge max 10 pages

            for ($i = 2; $i <= $max_pages; $i++) {
              $paginated_url = "{$term_link}page/" . user_trailingslashit($i);
              $list_of_urls[] = $paginated_url;
            }
          }

          // Check for parent term
          if ($term->parent != 0 && !in_array($term->parent, $added_parents)) {
            $parent_term = get_term_by('id', $term->parent, $taxonomy);

            if (!is_wp_error($parent_term)) {

              $parent_link = get_term_link($parent_term);

              if (!is_wp_error($parent_link) && !in_array($parent_link, $list_of_urls)) {
                $list_of_urls[] = $parent_link;
                $added_parents[] = $term->parent; // Remember added parent

                if ( $this->main_instance->get_plan_name() === 'Standard' && ($this->main_instance->get_single_config('cf_post_per_page', 0) > 0) && $using_permalink ) {
                  // Add paginated URLs for the parent term
                  $parent_term_count = $parent_term->count;
                  $parent_pages_number = ceil($parent_term_count / $this->main_instance->get_single_config('cf_post_per_page', 0));
                  $parent_max_pages = $parent_pages_number > 10 ? 10 : $parent_pages_number; // Purge max 10 pages for parent

                  for ($i = 2; $i <= $parent_max_pages; $i++) {
                    $parent_paginated_url = "{$parent_link}page/" . user_trailingslashit($i);
                    $list_of_urls[] = $parent_paginated_url;
                  }
                }
              }
            }
          }
        }
      }
    }

    // Author URL
    $author_id = get_post_field('post_author', $post_id);
    $author_url = get_author_posts_url($author_id);
    $list_of_urls[] = $author_url;
    $list_of_urls[] = get_author_feed_link($author_id);

    // Add paginated author URLs if applicable
    if ($this->main_instance->get_plan_name() === 'Standard' && ($this->main_instance->get_single_config('cf_post_per_page', 0) > 0) && $using_permalink) {
      // Cache key for storing author total post count
      $author_total_posts_cache_key = "author_total_post_count_{$author_id}";
      $total_posts = $this->main_instance->get_system_cache($author_total_posts_cache_key);

      if ( $total_posts === false ) {
        $total_posts = count_user_posts($author_id);

        // Cache the result for next 3 hours
        $this->main_instance->set_system_cache($author_total_posts_cache_key, $total_posts, 3 * HOUR_IN_SECONDS);
      }

      $total_posts = (int) $total_posts;
      
      $pages_number = ceil($total_posts / $this->main_instance->get_single_config('cf_post_per_page', 0));
      $max_pages = $pages_number > 10 ? 10 : $pages_number;

      for ($i = 2; $i <= $max_pages; $i++) {
        $list_of_urls[] = "{$author_url}page/" . user_trailingslashit($i);
      }
    }

    // Archives and their feeds
    if (get_post_type_archive_link($post_type) == true) {
      $post_type_archive_link = trailingslashit( get_post_type_archive_link($post_type) );

      if ( $post_type_archive_link !== $home_url ) {
        $list_of_urls[] = $post_type_archive_link;
        $list_of_urls[] = get_post_type_archive_feed_link($post_type);

        // Add paginated post type archive URLs if applicable
        if ($this->main_instance->get_plan_name() === 'Standard' && ($this->main_instance->get_single_config('cf_post_per_page', 0) > 0) && $using_permalink) {
          $total_posts = $this->get_total_post_count($post_type);
          $pages_number = ceil($total_posts / $this->main_instance->get_single_config('cf_post_per_page', 0));
          $max_pages = $pages_number > 10 ? 10 : $pages_number;

          for ($i = 2; $i <= $max_pages; $i++) {
            $list_of_urls[] = "{$post_type_archive_link}page/" . user_trailingslashit($i);
          }
        }
      }
    }

    // Also clean URL for trashed post.
    if (get_post_status($post_id) == 'trash') {
      $trash_post = get_permalink($post_id);
      $trash_post = str_replace('__trashed', '', $trash_post);
      $list_of_urls[] = $trash_post;
      $list_of_urls[] = "{$trash_post}feed/";
    }

    // Purge the home page as well if BS_CACHE_HOME_PAGE_SHOWS_POSTS set to true
    if (defined('BS_CACHE_HOME_PAGE_SHOWS_POSTS') && BS_CACHE_HOME_PAGE_SHOWS_POSTS === true) {
      if ($permalink_from_post_id !== $home_url) {
        $list_of_urls[] = $home_url;
      }
    }

    // Add extra URL paths if the site is using Performance Package or support purge by prefix
    /** @disregard P1011 - The constant is set using bash script in the wp-config.php **/
    if ($this->main_instance->get_plan_name() === 'Performance+' || ($this->main_instance->get_plan_name() === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE)) {
      // Add home page paginated URL path to related URLs list
      $home_page_paginated_url_structure = home_url('/page/');
      $list_of_urls[] = $home_page_paginated_url_structure;

      // If WP JSON bypass is not selected, add WP JSON URL path to related URL list
      if ($this->main_instance->get_single_config('cf_bypass_wp_json_rest', 0) === 0) {
        $wp_json_url_path = home_url('/wp-json/');
        $list_of_urls[] = $wp_json_url_path;
      }
    }

    $page_link = get_permalink( get_option('page_for_posts') );
    
    if ( 
      is_string($page_link) &&
      !empty($page_link) &&
      (get_option('show_on_front') == 'page') &&
      ($home_url !== $page_link) &&
      !(isset($post_type_archive_link) && $post_type_archive_link === $page_link)
    ) {
      $list_of_urls[] = $page_link;

      // Add paginated blog page URLs if applicable
      if ($this->main_instance->get_plan_name() === 'Standard' && ($this->main_instance->get_single_config('cf_post_per_page', 0) > 0) && $using_permalink) {
        $total_posts = $this->get_total_post_count($post_type); // Count for all post types
        $pages_number = ceil($total_posts / $this->main_instance->get_single_config('cf_post_per_page', 0));
        $max_pages = $pages_number > 10 ? 10 : $pages_number;

        for ($i = 2; $i <= $max_pages; $i++) {
          $list_of_urls[] = user_trailingslashit("{$page_link}page/{$i}");
        }
      }
    }

    return $list_of_urls;
  }

  private function get_total_post_count(string $post_type = 'post', string $status = 'publish') : int
  {
    global $wpdb;
    $cache_key = "total_posts_count_{$post_type}_{$status}";
    $total_posts = $this->main_instance->get_system_cache($cache_key);

    if ($total_posts === false) {
      // Direct SQL query to count posts with caching
      $total_posts = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s", $post_type, $status) );

      // Cache the result for 3 hours. Adjust the cache duration based on the expected frequency of changes.
      $this->main_instance->set_system_cache($cache_key, $total_posts, 3 * HOUR_IN_SECONDS);
    }

    return (int) $total_posts;
  }

  public function reset_all(bool $keep_settings = false) : void
  {
    $this->objects = $this->main_instance->get_objects();
    $error = '';

    // Purge cache for the entire site (including static files)
    $this->purge_all();

    // Restore default plugin config
    if ($keep_settings == false) {
      $this->main_instance->set_config($this->main_instance->get_default_config());
      $this->main_instance->update_config();
    } else {
      $this->main_instance->set_single_config('cf_cache_enabled', 0);
      $this->main_instance->update_config();
    }

    // Reset log
    $this->objects['logs']->reset_log();
    $this->objects['logs']->add_log('cache_controller::reset_all', 'Reset complete');
  }

  public function is_url_to_bypass() : bool
  {
    $this->objects = $this->main_instance->get_objects();

    // Bypass API requests
    if ($this->main_instance->is_api_request()) {
      return true;
    }

    // Bypass AMP
    if ($this->main_instance->get_single_config('cf_bypass_amp', 0) > 0 && preg_match('/(\/)((\?amp)|(amp\/))/', $_SERVER['REQUEST_URI'])) {
      return true;
    }

    // Bypass sitemap
    if ($this->main_instance->get_single_config('cf_bypass_sitemap', 0) > 0 && strcasecmp($_SERVER['REQUEST_URI'], '/sitemap_index.xml') == 0 || preg_match('/[a-zA-Z0-9]-sitemap.xml$/', $_SERVER['REQUEST_URI'])) {
      return true;
    }

    // Bypass robots.txt
    if ($this->main_instance->get_single_config('cf_bypass_file_robots', 0) > 0 && preg_match('/^\/robots.txt/', $_SERVER['REQUEST_URI'])) {
      return true;
    }

    // Bypass the cache on excluded URLs
    $excluded_urls = $this->main_instance->get_single_config('cf_excluded_urls', []);

    if (is_array($excluded_urls) && !empty($excluded_urls)) {

      $current_url = $_SERVER['REQUEST_URI'];

      if (isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) > 0) {
        $current_url .= "?{$_SERVER['QUERY_STRING']}";
      }

      foreach ($excluded_urls as $url_to_exclude) {
        if ($this->main_instance->wildcard_match($url_to_exclude, $current_url)) {
          return true;
        }
      }
    }

    if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || (defined('DOING_AJAX') && DOING_AJAX) || (defined('DOING_CRON') && DOING_CRON)) {
      return true;
    }

    if (in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'])) {
      return true;
    }

    return false;
  }

  public function can_i_bypass_cache() : bool
  {
    global $post;

    $this->objects = $this->main_instance->get_objects();

    // Immediately bypass for admin or logged-in users
    if (is_admin() || is_user_logged_in()) {
      return true;
    }

    // Use early returns for conditions that are cheap to check or likely common
    if (apply_filters('bs_cache_cache_bypass', false) === true) {
      return true;
    }

    // Bypass post protected by password
    if ($post instanceof \WP_Post && post_password_required($post->ID)) {
      return true;
    }

    /**
     * Bypass cache for posts within `sc_product` post type
     * @link https://www.studiocart.co/docs/general/troubleshoot-error-messages-at-checkout/#payment-intent-error
    **/
    if ($post instanceof \WP_Post && in_array($post->post_type, ['sc_product'])) {
      return true;
    }

    // Bypass requests with query var
    if ($this->main_instance->get_single_config('cf_bypass_query_var', 0) > 0 && isset($_SERVER['QUERY_STRING']) && strlen($_SERVER['QUERY_STRING']) > 0) {
      return true;
    }

    // Bypass AJAX requests
    if ($this->main_instance->get_single_config('cf_bypass_ajax', 0) > 0) {

      if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return true;
      }

      /** @disregard P1010 - This is a WordPress core function **/
      if (function_exists('is_ajax') && is_ajax()) {
        return true;
      }

      if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') || (defined('DOING_AJAX') && DOING_AJAX)) {
        return true;
      }

      // Check if the constant for doing AJAX is defined
      if (defined('DOING_AJAX') && DOING_AJAX) {
        return true;
      }
    }

    // Bypass Wordpress pages
    if ($this->main_instance->get_single_config('cf_bypass_front_page', 0) > 0 && is_front_page()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_pages', 0) > 0 && is_page()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_home', 0) > 0 && is_home()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_archives', 0) > 0 && is_archive()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_tags', 0) > 0 && is_tag()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_category', 0) > 0 && is_category()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_feeds', 0) > 0 && is_feed()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_search_pages', 0) > 0 && is_search()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_author_pages', 0) > 0 && is_author()) {
      return true;
    }

    if ($this->main_instance->get_single_config('cf_bypass_single_post', 0) > 0 && is_single()) {
      return true;
    }

    // Bypass WooCommerce pages
    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_cart_page', 0) > 0 && function_exists('is_cart') && is_cart()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_account_page', 0) > 0 && function_exists('is_account') && is_account()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_checkout_page', 0) > 0 && function_exists('is_checkout') && is_checkout()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_checkout_pay_page', 0) > 0 && function_exists('is_checkout_pay_page') && is_checkout_pay_page()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_shop_page', 0) > 0 && function_exists('is_shop') && is_shop()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_product_page', 0) > 0 && function_exists('is_product') && is_product()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_product_cat_page', 0) > 0 && function_exists('is_product_category') && is_product_category()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_product_tag_page', 0) > 0 && function_exists('is_product_tag') && is_product_tag()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_product_tax_page', 0) > 0 && function_exists('is_product_taxonomy') && is_product_taxonomy()) {
      return true;
    }

    /** @disregard P1010 - This is a WooCommerce core function **/
    if ($this->main_instance->get_single_config('cf_bypass_woo_pages', 0) > 0 && function_exists('is_woocommerce') && is_woocommerce()) {
      return true;
    }

    // Bypass EDD pages
    /** @disregard P1010 - This is a Easy Digital Downloads (EDD) core function **/
    if ($post instanceof \WP_Post && $this->main_instance->get_single_config('cf_bypass_edd_checkout_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('purchase_page', 0) == $post->ID) {
      return true;
    }

    /** @disregard P1010 - This is a Easy Digital Downloads (EDD) core function **/
    if ($post instanceof \WP_Post && $this->main_instance->get_single_config('cf_bypass_edd_success_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('success_page', 0) == $post->ID) {
      return true;
    }

    /** @disregard P1010 - This is a Easy Digital Downloads (EDD) core function **/
    if ($post instanceof \WP_Post && $this->main_instance->get_single_config('cf_bypass_edd_failure_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('failure_page', 0) == $post->ID) {
      return true;
    }

    /** @disregard P1010 - This is a Easy Digital Downloads (EDD) core function **/
    if ($post instanceof \WP_Post && $this->main_instance->get_single_config('cf_bypass_edd_purchase_history_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('purchase_history_page', 0) == $post->ID) {
      return true;
    }

    /** @disregard P1010 - This is a Easy Digital Downloads (EDD) core function **/
    if ($post instanceof \WP_Post && $this->main_instance->get_single_config('cf_bypass_edd_login_redirect_page', 0) > 0 && function_exists('edd_get_option') && edd_get_option('login_redirect_page', 0) == $post->ID) {
      return true;
    }

    // Bypass single post by metabox
    if ($this->main_instance->get_single_config('cf_disable_single_metabox', 0) === 0 && $post instanceof \WP_Post && (int) get_post_meta($post->ID, 'bs_cache_bypass_cache', true) > 0) {
      return true;
    }

    return false;
  }

  public function get_cache_control_value() : string
  {
    // Default CDN Cache TTL
    $cdn_cache_ttl = apply_filters('bs_cache_cdn_cache_ttl', (int) $this->main_instance->get_single_config('cf_maxage', 31536000));

    // Default Browser Cache TTL
    $browser_cache_ttl = apply_filters('bs_cache_browser_cache_ttl', (int) $this->main_instance->get_single_config('cf_browser_maxage', 60));

    $value = "s-maxage={$cdn_cache_ttl}, max-age={$browser_cache_ttl}";

    return apply_filters('bs_cache_control', $value);
  }

  public function is_cache_enabled() : bool
  {
    if ($this->main_instance->get_single_config('cf_cache_enabled', 0) > 0) return true;

    return false;
  }

  public function object_cache_disable($result) : void
  {
    $this->objects = $this->main_instance->get_objects();

    // Purge OPcache if Object Cache has been disabled successfully.
    if ($result && $this->purge_opcache()) {
      $this->objects['logs']->add_log('cache_controller::object_cache_disable', 'OPcache has been flushed as Object Cache has been disabled on the website.');
    } else {
      $this->objects['logs']->add_log('cache_controller::object_cache_disable', 'Unable to flush OPcache — either the object cache plugin has some issue disabling object cache on the website or this website has some issue with OPcache setup. Try clearing the OPcache via BigScoots Cache plugin settings page.');
    }
  }

  public function wp_rocket_hooks() : void
  {
    // Do not run this on the WordPress Nav Menu page
    global $pagenow;

    if ($pagenow === 'nav-menus.php') return;

    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    // Purge the cache for the entire website (Including static files)
    $this->purge_all();

    $this->objects['logs']->add_log('cache_controller::wp_rocket_hooks', "Purge whole Cloudflare cache (fired action: {$current_action})");
  }

  public function wp_rocket_selective_url_purge_hooks($url_to_purge) : void
  {
    $current_action = function_exists('current_action') ? current_action() : '';
    $this->objects = $this->main_instance->get_objects();

    // If we are receiving only 1 URL then wrap it inside an array else if we are receiving an array of URLs then pass that
    $url_to_purge = is_array($url_to_purge) ? $url_to_purge : [$url_to_purge];

    // As WP Rocket generates RUCSS for URLs with query param in them
    // we need to get the URLs without query params and then pass then to purge URL
    $sanitized_urls_to_purge = [];
    $cache_key_list = [];

    foreach ($url_to_purge as $url) {
      // Parse the URL
      $parsed_url = wp_parse_url($url);

      // Get sanitized version of the URL
      if (isset($parsed_url['path'])) {
        $path = $parsed_url['path'];

        // Replace spammy 1 being added to the path URLs
        $path = str_replace(['/1%'], '/%', $path);

        // URL decode the path
        $path = urldecode($path);

        // Replace all other unnecessary garbage from the URL
        $path = str_replace(['"', '\'', '\\', '%', '|', '(', ')', '!', '#', '*', ',', ''], '', $path);

        // Remove unnecessary consecutive / (e.g. //, ///, ////) paths from the path
        $path = preg_replace('~/+~', '/', $path);
      } else {
        $path = '/';
      }

      $sanitized_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $path;

      // Generate the cache key based on the path used for rate limiting
      $cache_key = 'purge_cache_rocket_rucss_' . ( $path === '/' ? 'front_page' : str_replace(['-', '.', '/', '%'], '_', ltrim($path, '/') ) );

      if ( $this->main_instance->get_system_cache($cache_key) !== 'already_purged_wait_30_min' ) {
        // Add the sanitized URL to the array
        $sanitized_urls_to_purge[] = $sanitized_url;

        // Add this transition key to the list for the logs
        $cache_key_list[] = $cache_key;

        // Create the cache
        $this->main_instance->set_system_cache($cache_key, 'already_purged_wait_30_min', 30 * MINUTE_IN_SECONDS);
      }
    }

    // Purge if we have sanitized URLs to purge
    if (!empty($sanitized_urls_to_purge)) {
      $this->purge_urls($sanitized_urls_to_purge);

      $urls_purged = wp_json_encode($sanitized_urls_to_purge);
      $cache_keys = wp_json_encode($cache_key_list);

      $this->objects['logs']->add_log('cache_controller::wp_rocket_selective_url_purge_hooks', "Purge Cloudflare cache for only URL {$urls_purged} — (Fired action: {$current_action}) — System Cache: {$cache_keys} created — Purge action for these URLs via this action rate limited for next 30 min");
    }
  }

  public function yasr_hooks($post_id) : void
  {
    static $done = [];

    if (isset($done[$post_id])) {
      return;
    }

    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    $urls = [];

    $post_id = is_array($post_id) ? $post_id['post_id'] : $post_id;

    $urls[] = get_permalink($post_id);

    $this->purge_urls($urls);

    $this->objects['logs']->add_log('cache_controller::yasr_hooks', "Purge Cloudflare cache for only post {$post_id} - Fired action: {$current_action}");

    $done[$post_id] = true;
  }

  public function wpacu_hooks() : void
  {
    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    $this->purge_all();
    $this->objects['logs']->add_log('cache_controller::wpacu_hooks', "Purge whole Cloudflare cache (fired action: {$current_action})");
  }

  public function wp_recipe_maker_cache_purge($post_id) : void
  {
    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    $urls = [];

    if ($post_id) { // Post ID is an integer
      // Get the permalink for this post id
      $urls[] = get_permalink( (int) $post_id );

      // Purge the cache for this url
      $this->purge_urls($urls);
      $this->objects['logs']->add_log('cache_controller::wp_recipe_maker_cache_purge', "Cleared cache for the WP Recipe Maker Post (Post ID: {$post_id}) [URL: {$urls[0]}] - Fired action: {$current_action}");
    }
  }

  public function autoptimize_hooks() : void
  {
    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    $this->purge_all();
    $this->objects['logs']->add_log('cache_controller::autoptimize_hooks', "Purge whole Cloudflare cache (fired action: {$current_action})");
  }

  public function purge_on_update() : void
  {
    $done = $this->main_instance->get_system_cache('purged_cache_on_update_done');

    // $this->objects['logs']->add_log('cache_controller::should_process_purge',  $done );

    if ($done === 'already_purged_wait_120s') return;

    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    if ( 
      (
        !$this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0 &&
        $this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0
      ) ||
      (
        $this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0 &&
        $this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0 &&
        $this->main_instance->get_environment_type() === 'Staging'
      ) || // If it's a staging environment, don't clear CF Cache even if it's specified
      (
        $this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0 &&
        !$this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0 &&
        $this->main_instance->get_environment_type() === 'Staging'
      ) || // If it's a staging environment, don't clear CF Cache even if it's specified
      (
        $this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0 &&
        $this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0 &&
        $this->main_instance->get_plan_name() === 'Misconfigured'
      ) || // If the plugin setup is Misconfigured, don't clear CF Cache even if it's specified
      (
        $this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0 &&
        !$this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0 &&
        $this->main_instance->get_plan_name() === 'Misconfigured'
      ) // If the plugin setup is Misconfigured, don't clear CF Cache even if it's specified
    ) {

      if (!apply_filters('bs_cache_disable_clear_opcache', false)) {
        // Purge only OPcache
        $this->objects['logs']->add_log('cache_controller::purge_on_update', "Clearing OPcache (fired action: {$current_action})");
        $this->purge_opcache();
      }

      if (!apply_filters('bs_cache_disable_clear_object_cache', false)) {
        // Purge Object Cache
        $this->objects['logs']->add_log('cache_controller::purge_on_update', "Clearing Object Cache (fired action: {$current_action})");
        $this->purge_object_cache();
      }

    } elseif (
      (
        $this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0 &&
        $this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0
      ) ||
      (
        $this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0 &&
        !$this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0
      )
    ) {
      // Purge everything + OPcache
      $this->purge_all();
      $this->objects['logs']->add_log('cache_controller::purge_on_update', "Cleared whole BigScoots Cache (fired action: {$current_action}) — System Cache: purged_cache_on_update_done created — Purge action rate limited for next 120 sec");

      $this->main_instance->set_system_cache('purged_cache_on_update_done', 'already_purged_wait_120s', 120); // 120 sec
    }
  }

  public function edd_purge_cache_on_payment_add() : void
  {
    $current_action = function_exists('current_action') ? current_action() : '';

    $this->objects = $this->main_instance->get_objects();

    $this->purge_all();
    $this->objects['logs']->add_log('cache_controller::edd_purge_cache_on_payment_add', "Purge whole Cloudflare cache (fired action: {$current_action})");
  }

  /**
   * @param int $order_id order id of the sale.
   * @param string $old_status old status of the order.
   * @param string $new_status new status of the order.
   * @param WC_Order $order order object.
   * @disregard P1009 - Coming from WooCommerce
  **/
  public function woocommerce_purge_product_page_on_sale(int $order_id, string $old_status, string $new_status, \WC_Order $order) : void
  {
    // Do not proceed if the payment has been completed for the order
    if ( !( ($old_status !== $new_status) && in_array( $new_status, ['processing', 'completed'] ) ) ) return;

    // Do not execute if an order is being converted from processing to completed
    if ( $old_status === 'processing' && $new_status === 'completed' ) return;

    if ( !function_exists('wc_get_page_id') ) return;

    $this->objects = $this->main_instance->get_objects();
    $product_ids = [];
    $urls = [];

    // Get shop page URL
    /** @disregard P1010 - WooCommerce core function **/
    $urls[] = get_permalink( wc_get_page_id('shop') );

    // Iterate through the items in the order
    foreach ( $order->get_items() as $item_id => $item ) {
      // Get the product from the item
      $product = $item->get_product();

      if ( $product ) {
        // Get the product ID
        $product_id = $product->get_id();
        $product_ids[] = $product_id;

        // Get the product permalink and other related URLs like product category URLs and parent category URLs
        $urls_to_purge = $this->get_post_related_links($product_id);
        $urls = [
          ...$urls,
          ...$urls_to_purge
        ];
      }
    }

    $urls = array_unique($urls);

    $this->purge_urls($urls);

    $this->objects['logs']->add_log('cache_controller::woocommerce_purge_product_page_on_sale', 'Purge product pages (Product IDs: ' . trim( join( ', ', $product_ids ) ) . ") and related pages for WooCommerce order ID: {$order_id}");
  }

  public function woocommerce_purge_scheduled_sales($product_id_list) : void
  {
    if ( !( function_exists('wc_get_page_id') ) ) return;

    $this->objects = $this->main_instance->get_objects();

    $urls = [];

    if (is_array($product_id_list) && !empty($product_id_list)) {

      // Get shop page URL
      /** @disregard P1010 - WooCommerce core function **/
      $urls[] = get_permalink( wc_get_page_id('shop') );

      foreach ($product_id_list as $product_id) {
        // Get the product permalink and other related URLs like product category URLs and parent category URLs
        $urls_to_purge = $this->get_post_related_links($product_id);
        $urls = [
          ...$urls,
          ...$urls_to_purge
        ];
      }

      if (!empty($urls)) {
        $this->purge_urls( array_unique($urls) );
      }

      $this->objects['logs']->add_log('cache_controller::woocommerce_purge_scheduled_sales', 'Purge product pages (IDs: ' . trim( join( ', ', $product_id_list ) ) . ") and related pages");
    }
  }

  public function woocommerce_cache_friendly_cookie_handler() : void
  {
    // Store frequently used instances in variables for better performance
    /** @disregard P1010 - WooCommerce core function **/
    $woo = function_exists('WC') ? WC() : false;

    // Don't do anything if WooCommerce instance is not available or required methods do not exist
    /** @disregard P1009 - WooCommerce class is coming from WooCommerce plugin **/
    if (
      !$woo instanceof \WooCommerce
      || !isset($woo->cart)
      || !isset($woo->session)
      || !method_exists($woo->cart, 'get_cart_contents_count')
      || !method_exists($woo->session, 'has_session')
      || !method_exists($woo->session, 'destroy_session')
    ) {
      return;
    }

    // Don't do anything for WooCommerce Ajax requests
    if ( (defined('DOING_AJAX') && DOING_AJAX) && isset($_REQUEST['wc-ajax']) ) return;

    // Don't do anything if it's cart, checkout or my account page page
    /** @disregard P1010 - WooCommerce core function **/
    if ( is_cart() || is_checkout() || is_account_page() ) return;

    // Only handle the Woo cookies in a cache-friendly way for non-logged-in users with an empty cart
    if (!is_user_logged_in() && $woo->cart->get_cart_contents_count() === 0 && $woo->session->has_session()) {
      $woo->session->destroy_session();
    }
  }

  public function is_external_link(string $url) : bool
  {
    $source = wp_parse_url(home_url());
    $target = wp_parse_url($url);

    if (empty($source['host']) || empty($target['host'])) return false;

    if (strcasecmp($target['host'], $source['host']) === 0) return false;

    return true;
  }

  public function purge_object_cache() : bool
  {
    if (!function_exists('wp_cache_flush')) return false;

    wp_cache_flush();

    $this->objects = $this->main_instance->get_objects();

    $this->objects['logs']->add_log('cache_controller::purge_object_cache', 'Flushed object cache.');

    return true;
  }

  // Allows users to purge opcache when cache is purged via plugin settings page
  public function purge_opcache() : bool
  {
    if (!extension_loaded('Zend OPcache')) return false;

    $opcache_status = opcache_get_status();

    if (!$opcache_status || !isset($opcache_status['opcache_enabled']) || $opcache_status['opcache_enabled'] === false) return false;

    if (!opcache_reset()) return false;

    $this->objects = $this->main_instance->get_objects();

    $this->objects['logs']->add_log('cache_controller::purge_opcache', 'OPcache purged successfully!');

    return true;
  }

  public function enable_cache() : bool
  {
    $this->main_instance->set_single_config('cf_cache_enabled', 1);
    $this->main_instance->update_config();

    $cache_disabled_path = WP_CONTENT_DIR . '/bigscoots-cache/cache_disabled';

    if (file_exists($cache_disabled_path)) {
      wp_delete_file($cache_disabled_path);
    }

    return true;
  }

  public function disable_cache(bool $purge_cache_on_disable = true) : bool
  {
    $this->objects = $this->main_instance->get_objects();

    if ( $purge_cache_on_disable ) {
      $this->objects['logs']->add_log('cache_controller::disable_cache', 'Disabling cache and clearing cache for the whole domain.');
      $this->purge_all();
    }

    $this->main_instance->set_single_config('cf_cache_enabled', 0);
    $this->main_instance->update_config();

    $cache_disabled_path = WP_CONTENT_DIR . '/bigscoots-cache/cache_disabled';
    file_put_contents($cache_disabled_path, '1');

    return true;
  }

  public function is_page_cache_disabled() : bool
  {
    // Get the path for the page cache disabled indicator file
    $page_cache_disabled_path = WP_CONTENT_DIR . '/bigscoots-cache/page_cache_disabled';

    return file_exists($page_cache_disabled_path); // TRUE -> if file exists | FALSE -> otherwise
  }
}