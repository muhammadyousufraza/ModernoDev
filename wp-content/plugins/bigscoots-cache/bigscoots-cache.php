<?php
/**
 * BigScoots Cache
 *
 * @package      BigScootsCache
 * @author       Saumya Majumder
 * @copyright    BigScoots LLC.
 * @license      BigScoots Terms of Service
 *
 * @wordpress-plugin
 * Plugin Name:  BigScoots Cache
 * Plugin URI:   https://www.bigscoots.com/wordpress-speed-optimization/
 * Description:  Speed up your website like a 🚀 with BigScoots proprietary page caching system.
 * Version:      2.8.1
 * Requires at least: 5.7
 * Requires PHP: 7.4
 * Author:       BigScoots
 * Author URI:   https://www.bigscoots.com/
 * Text Domain:  bigscoots-cache
 * License:      BigScoots Terms of Service
 * License URI:  https://www.bigscoots.com/terms-of-service/
 * Update URI:   bigscoots-cache
**/

define( 'BS_CACHE_PLUGIN_PATH', plugin_dir_path(__FILE__) );
define( 'BS_CACHE_PLUGIN_URL', set_url_scheme( plugin_dir_url(__FILE__), 'https' ));
define( 'BS_CACHE_LOGS_STANDARD_VERBOSITY', 1 );
define( 'BS_CACHE_LOGS_HIGH_VERBOSITY', 2 );

// Define BS_MASTER_URL only when the plugin is being used for CF Enterprise account
if (defined('BS_MASTER_KEY') && defined('BS_SITE_ID') && !defined('BS_MASTER_URL')) {
  define('BS_MASTER_URL', 'https://main.bigscoots.com/cf-cache-purge/');
}

if (!defined('BS_CACHE_CURL_TIMEOUT')) {
  define('BS_CACHE_CURL_TIMEOUT', 10);
}

if (!defined('BS_CACHE_HOME_PAGE_SHOWS_POSTS')) {
  define('BS_CACHE_HOME_PAGE_SHOWS_POSTS', true);
}

class BigScoots_Cache
{
  private array $config = [];
  private array $objects = [];
  private string $version = '2.8.1';
  private static bool $is_persistent_object_cache_enabled = false;

  public function __construct()
  {
    register_deactivation_hook(__FILE__, [$this, 'deactivate_plugin']);
    
    if (!$this->init_config()) {
      $this->config = $this->get_default_config();
      $this->update_config();
    }

    if (!file_exists($this->get_plugin_wp_content_directory())) {
      $this->create_plugin_wp_content_directory();
    }

    // Check if persistent object cache is enabled on the website
    self::$is_persistent_object_cache_enabled = wp_using_ext_object_cache() ?? false;

    $this->update_plugin();
    $this->include_libs();
    $this->actions();
  }

  public function load_textdomain() : void
  {
    load_plugin_textdomain('bigscoots-cache', false, basename(dirname(__FILE__)) . '/languages/');
  }

  private function include_libs() : void
  {
    if (!empty($this->objects)) return;

    $this->objects = [];

    include_once(ABSPATH . 'wp-includes/pluggable.php');

    require_once BS_CACHE_PLUGIN_PATH . 'libs/cloudflare.class.php';
    require_once BS_CACHE_PLUGIN_PATH . 'libs/logs.class.php';
    require_once BS_CACHE_PLUGIN_PATH . 'libs/cache_controller.class.php';
    require_once BS_CACHE_PLUGIN_PATH . 'libs/backend.class.php';
    require_once BS_CACHE_PLUGIN_PATH . 'libs/api.class.php';

    $log_file_path = $this->get_plugin_wp_content_directory() . '/debug.log';

    $this->objects = apply_filters('bs_cache_include_libs_early', $this->objects);

    if ($this->get_single_config('log_enabled', 0) > 0) {
      $this->objects['logs'] = new \BigScoots\Cache\Logs($log_file_path, true, $this->get_single_config('log_max_file_size', 2), $this);
    } else {
      $this->objects['logs'] = new \BigScoots\Cache\Logs($log_file_path, false, $this->get_single_config('log_max_file_size', 2), $this);
    }

    $this->objects['logs']->set_verbosity((int) $this->get_single_config('log_verbosity', BS_CACHE_LOGS_STANDARD_VERBOSITY));

    $this->objects['cloudflare'] = new \BigScoots\Cache\Cloudflare($this);
    $this->objects['cache_controller'] = new \BigScoots\Cache\Controller($this);
    $this->objects['backend'] = new \BigScoots\Cache\Backend($this);
    $this->objects['api'] = new \BigScoots\Cache\API($this);

    $this->objects = apply_filters('bs_cache_include_libs_lately', $this->objects);

    $this->enable_wp_cli_support();
  }

  private function actions() : void
  {
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);

    // Multilanguage
    add_action('plugins_loaded', [$this, 'load_textdomain']);

    // Hooks for cache purge programmatically
    add_action('bigscoots/cache/purge/programmatically', [$this, 'third_party_cache_purge'], PHP_INT_MAX, 2);
    add_action('bigscoots/opcache/purge/programmatically', [$this, 'third_party_opcache_purge'], PHP_INT_MAX);
    add_action('bigscoots/object-cache/purge/programmatically', [$this, 'third_party_object_cache_purge'], PHP_INT_MAX);
  }

  public function get_default_config() : array
  {
    $config = [];

    // Cloudflare config
    $config['cf_auto_purge']                                      = 1;
    $config['cf_auto_purge_all']                                  = 0;
    $config['cf_auto_purge_on_comments']                          = 1;
    $config['cf_auto_purge_related_pages_on_comments']            = 0;
    $config['cf_cache_enabled']                                   = 0;
    $config['cf_maxage']                                          = 31536000; // 1 year
    $config['cf_browser_maxage']                                  = 60; // 1 minute
    $config['cf_post_per_page']                                   = get_option('posts_per_page', 0);
    $config['cf_purge_url_secret_key']                            = wp_generate_password(20, false, false);
    $config['cf_prefetch_urls']                                   = 0;
    $config['cf_strip_cookies']                                   = 0;
    $config['cf_auto_purge_opcache_on_upgrader_process_complete'] = 1;
    $config['cf_auto_purge_on_upgrader_process_complete']         = 0;

    // Pages
    $config['cf_excluded_urls'] = [
      // Paths
      '/wp-admin*', 
      '/wp-login*', 
      '/wc-api/*', 
      '/edd-api/*', 
      '/login*',
      '/mepr/*', 
      '/register/*', 
      '/dashboard/*', 
      '/members-area/*', 
      '/checkout/*', 
      '/my-account*', 
      '/account/*',
      '/wishlist-member/*',

      // Query Params
      '/*?s=*', 
      '/*?p=*', 
      '/*?eddfile=*', 
      '/*nobscache*', 
      '/*nocache*', 
      '/*nowprocket*', 
      '/*bscachebust*', 
      '/*phs_downloads-mbr*', 
      '/*removed_item*', 
      '/*ao_speedup_cachebuster*', 
      '/*jetpack=comms*', 
      '/*ao_noptirocket*',
      '/*perfmattersoff*',
      '/*perfmattersjsoff*',
      '/*perfmatterscssoff*'
    ];

    $config['cf_excluded_post_types']  = [
      'aawp_table',
      'acf-field',
      'acf-field-group',
      'acf-post-type',
      'acf-taxonomy',
      'act_template',
      'advanced_ads',
      'ae_global_templates',
      'archive-template',
      'attachment',
      'audiolist',
      'audioplayer',
      'bkap_booking',
      'bkap_gcal_event',
      'bkap_reminder',
      'bkap_resource',
      'bp-email',
      'bp-invite',
      'br_filters_group',
      'br_product_filter',
      'brb_collection',
      'breakdance_acf_block',
      'breakdance_block',
      'breakdance_footer',
      'breakdance_form_res',
      'breakdance_header',
      'breakdance_popup',
      'breakdance_template',
      'buddyboss_fonts',
      'cookielawinfo',
      'cp_popups',
      'cultivate_landing',
      'custom-css-js',
      'custom-mega-menu',
      'custom-post-template',
      'custom_css',
      'customize_changeset',
      'divi_overlay',
      'dlm_download',
      'easy_affiliate_link',
      'edd_discount',
      'edd_license_log',
      'edd_log',
      'edd_payment',
      'edd_subscription_log',
      'elementor_font',
      'elementor_icons',
      'elementor_library',
      'elementor_snippet',
      'elementskit_template',
      'et_pb_layout',
      'feast_layouts',
      'feast_modern_cats',
      'frm_display',
      'frm_form_actions',
      'frm_styles',
      'gblocks_global_style',
      'gblocks_templates',
      'gp_elements',
      'ig_campaign',
      'ig_message',
      'insertpostads',
      'it_boom_bar',
      'jet-popup',
      'jet-woo-builder',
      'kadence_conversions',
      'kadence_element',
      'kadence_form',
      'kadence_query',
      'kadence_query_card',
      'kb_icon',
      'kt_gallery',
      'lasso-urls',
      'ld-coupon',
      'ld-thrivecart',
      'memberpresscoupon',
      'memberpressrule',
      'monsterinsights_note',
      'mp-reminder',
      'ms_communication',
      'ms_relationship',
      'mv_create',
      'newsletterglue',
      'oembed_cache',
      'omapi',
      'owl-carousel',
      'pgc_simply_gallery',
      'pretty-link',
      'product-feed',
      'product_variation',
      'revision',
      'reusable_blocks',
      'rttpg',
      'sc_collection',
      'sc_order',
      'sc_product',
      'sc_subscription',
      'sc_us_path',
      'search-filter-widget',
      'shop_coupon',
      'shop_order',
      'shop_subscription',
      'shop-page-wp',
      'shortcoder',
      'sl-insta-media',
      'spectra-popup',
      'tdb_templates',
      'tdc-review',
      'thirstylink',
      'udb_widgets',
      'udb_admin_page',
      'um_form',
      'um_directory',
      'uncode_gallery',
      'uncodeblock',
      'uo-loop',
      'uo-loop-filter',
      'uo-recipe',
      'user_request',
      'wbcr-snippets',
      'whatsapp-accounts',
      'wp_block',
      'wp_font_face',
      'wp_font_family',
      'wp_global_styles',
      'wp_navigation',
      'wp_quiz',
      'wp_show_posts',
      'wp_template',
      'wp_template_part',
      'wpc-module',
      'wpcode',
      'wpdiscuz_form',
      'wprm_recipe',
      'wpupg_grid',
      'wpzoom_rcb'
    ];

    $config['cf_bypass_front_page']             = 0;
    $config['cf_bypass_pages']                  = 0;
    $config['cf_bypass_home']                   = 0;
    $config['cf_bypass_archives']               = 0;
    $config['cf_bypass_tags']                   = 0;
    $config['cf_bypass_category']               = 0;
    $config['cf_bypass_author_pages']           = 0;
    $config['cf_bypass_single_post']            = 0;
    $config['cf_bypass_feeds']                  = 1;
    $config['cf_bypass_search_pages']           = 1;
    $config['cf_bypass_logged_in']              = 1;
    $config['cf_bypass_amp']                    = 0;
    $config['cf_bypass_file_robots']            = 1;
    $config['cf_bypass_sitemap']                = 1;
    $config['cf_bypass_ajax']                   = 1;
    $config['cf_bypass_query_var']              = 0;
    $config['cf_bypass_wp_json_rest']           = 1;
    $config['cf_bypass_redirects']              = 0;

    // WooCommerce
    $config['cf_bypass_woo_shop_page']           = 0;
    $config['cf_bypass_woo_pages']               = 0;
    $config['cf_bypass_woo_product_tax_page']    = 0;
    $config['cf_bypass_woo_product_tag_page']    = 0;
    $config['cf_bypass_woo_product_cat_page']    = 0;
    $config['cf_bypass_woo_product_page']        = 0;
    $config['cf_bypass_woo_cart_page']           = 1;
    $config['cf_bypass_woo_checkout_page']       = 1;
    $config['cf_bypass_woo_checkout_pay_page']   = 1;
    $config['cf_bypass_woo_account_page']        = 1;
    $config['cf_auto_purge_woo_product_page']    = 0;
    $config['cf_auto_purge_woo_scheduled_sales'] = 1;
    $config['cf_optimize_woo_cookie']            = 1;

    // WP Rocket
    $config['cf_wp_rocket_purge_on_domain_flush']             = 0;
    $config['cf_wp_rocket_purge_on_rucss_job_complete']       = 1;

    // Yasr
    $config['cf_yasr_purge_on_rating'] = 0;

    // WP Asset Clean Up
    $config['cf_wpacu_purge_on_cache_flush'] = 1;

    // WP Recipe Maker
    $config['cf_wprm_purge_on_cache_flush'] = 1;

    // Autoptimize
    $config['cf_autoptimize_purge_on_cache_flush'] = 1;

    // EDD
    $config['cf_bypass_edd_checkout_page']         = 1;
    $config['cf_bypass_edd_success_page']          = 0;
    $config['cf_bypass_edd_failure_page']          = 0;
    $config['cf_bypass_edd_purchase_history_page'] = 1;
    $config['cf_bypass_edd_login_redirect_page']   = 1;
    $config['cf_auto_purge_edd_payment_add']       = 0;

    // Logs
    $config['log_enabled'] = 1;
    $config['log_max_file_size'] = 2; // Megabytes
    $config['log_verbosity'] = BS_CACHE_LOGS_STANDARD_VERBOSITY;

    // Other
    $config['cf_remove_purge_option_toolbar'] = 0;
    $config['cf_disable_single_metabox'] = 1;
    $config['cf_purge_roles'] = [];
    $config['cf_prefetch_urls_on_hover'] = 1;
    $config['keep_settings_on_deactivation'] = 1;

    return $config;
  }

  public function get_current_plugin_version() : string
  {
    return $this->version;
  }

  public function get_single_config(string $name, $default = false)
  {
    if (empty($this->config)) return $default;

    if (!isset($this->config[$name])) return $default;

    if (is_array($this->config[$name])) return $this->config[$name];

    if (is_numeric(trim($this->config[$name]))) return (int) trim($this->config[$name]);

    return trim($this->config[$name]);
  }

  public function set_single_config(string $name, $value) : void
  {
    if (is_array($value)) {
      $this->config[trim($name)] = $value;
    } else {
      $this->config[trim($name)] = trim($value);
    }
  }

  public function update_config() : void
  {
    update_option('bs_cache_config', $this->config);
  }

  private function init_config() : bool
  {
    $this->config = get_option('bs_cache_config', []);

    if (empty($this->config)) return false;

    // If the option exists, return true
    return true;
  }

  public function set_config(array $config) : void
  {
    $this->config = $config;
  }

  public function get_config() : array
  {
    return $this->config;
  }

  // Check if the site uses persistent object cache
  public function is_persistent_object_cache_enabled() : bool
  {
    return self::$is_persistent_object_cache_enabled;
  }

  // Normalize cache key as transient cache key cannot be more than 172 characters
  private function normalize_cache_key(string $key) : string
  {
    return (strlen($key) > 172) ? substr($key, 0, 172) : $key;
  }

  // Method to set system cache - use object cache if site has persistent object cache else use transient
  public function set_system_cache(string $key, $data, int $expiration) : void
  {
    // Normalize Cache Key
    $key = $this->normalize_cache_key(sanitize_text_field($key));

    if ($this->is_persistent_object_cache_enabled()) {
      wp_cache_set($key, $data, 'bigscoots-cache', $expiration);
    } else {
      set_transient($key, $data, $expiration);
    }
  }

  // Method to get system cache - use object cache if site has persistent object cache else use transient
  public function get_system_cache(string $key)
  {
    // Normalize Cache Key
    $key = $this->normalize_cache_key(sanitize_text_field($key));

    if ($this->is_persistent_object_cache_enabled()) {
      $found = false;
      $cached_data = wp_cache_get($key, 'bigscoots-cache', false, $found);
      return $found ? $cached_data : false;
    } else {
      return get_transient($key);
    }
  }

  // Plugin Upgrade Routine
  private function update_plugin() : void
  {
    // Codes to run for general plugin update process
    $current_version = get_option('bs_cache_version', '0.0.0');

    if (version_compare($current_version, $this->version, '<')) {
      if (empty($this->objects)) {
        $this->include_libs();
      }

      $this->objects['logs']->add_log('bs_cache::update_plugin', "Updating to v{$this->version}");

      // Process Update config
      // Update Normal Plugin Settings
      $this->set_single_config('cf_auto_purge_edd_payment_add', 0);
      $this->set_single_config('cf_prefetch_urls_on_hover', 1);
      $this->set_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 1);

      // Get the default config values - in case we need to update the configs
      $default_config = $this->get_default_config();

      // ----------- Update Excluded URls list -----------
      $cf_excluded_urls = $this->get_single_config('cf_excluded_urls', []);

      if ( is_array($cf_excluded_urls) ) {
        // Remove the ones that are no longer need/getting updated
        $remove_cf_excluded_urls_list = [
          '/*s=*',
          '/*p=*',
          '/*eddfile=*',
        ];

        foreach ($cf_excluded_urls as $url) {
          if (in_array($url, $remove_cf_excluded_urls_list)) {
            $find_key = array_search($url, $cf_excluded_urls);
            unset($cf_excluded_urls[$find_key]);
          }
        }

        // Loop through $default_config['cf_excluded_urls'] and if they do to already exists then add them to $cf_excluded_urls
        foreach ($default_config['cf_excluded_urls'] as $url) {
          if ( !in_array($url, $cf_excluded_urls) ) $cf_excluded_urls[] = $url;
        }

        $cf_excluded_urls = array_values($cf_excluded_urls);

        $this->set_single_config('cf_excluded_urls', $cf_excluded_urls);
      }

      // ----------- Update excluded post types list -----------
      $cf_excluded_post_types = $this->get_single_config('cf_excluded_post_types', []);

      if ( is_array($cf_excluded_post_types) ) {
        // Loop through $default_config['cf_excluded_post_types'] and if they do to already exists then add them to $cf_excluded_post_types
        foreach ($default_config['cf_excluded_post_types'] as $post_type) {
          if ( !in_array($post_type, $cf_excluded_post_types) ) $cf_excluded_post_types[] = $post_type;
        }

        // Sort the excluded post types list before storing it.
        sort($cf_excluded_post_types);

        $cf_excluded_post_types = array_values($cf_excluded_post_types);

        $this->set_single_config('cf_excluded_post_types', $cf_excluded_post_types);
      }

      // ----------- Finally, Update the plugin config -----------
      $this->update_config();

      // Disable/Enable Cache to process the plugin update changes
      add_action('shutdown', function () : void {
        $objects = $this->get_objects();

        // ********************** VERY IMPORTANT ***************************** 
        // If this plugin update require cache to be purged on the client site,
        // set the following variable to `true` otherwise keep it `false`
        // -------------------------------------------------------------------
        // If this update does not require a full cache purge but require partial
        // cache purge, then set $this_update_requires_partial_cache_purge to `true`
        // else keep it to `false`.
        // --------------------------------------------------------------------
        $this_update_requires_full_cache_purge = false;
        $this_update_requires_partial_cache_purge = true;
        // --------------------------------------------------------------------

        // if $this_update_requires_partial_cache_purge is set to `true` then purge the required files
        if ( !$this_update_requires_full_cache_purge && $this_update_requires_partial_cache_purge ) {
          // Get the site Hostname URL
          $site_url = home_url();

          if ( $this->get_plan_name() === 'Performance+' ) {
            // Clear CDN Cache
            self::clear_cache([ "{$site_url}/wp-content/plugins/bigscoots-cache/" ]);

            // Clear OPCache
            self::clear_opcache();
          } elseif ( $this->get_plan_name() === 'Standard' ) {
            // Clear CDN Cache
            self::clear_cache([
              "{$site_url}/wp-content/plugins/bigscoots-cache/assets/js/bs-cache-instant-prefetch-page.min.js" 
            ]);

            // Clear OPCache
            self::clear_opcache();
          }
        } elseif ( !$this_update_requires_full_cache_purge && !$this_update_requires_partial_cache_purge ) { // No cache purge needed - just purge the opcache
          // Clear OPCache
          self::clear_opcache();
        }

        // Enable Disable the Page Cache to take effect of the changes
        $objects['cache_controller']->disable_cache($this_update_requires_full_cache_purge);
        $objects['cache_controller']->enable_cache();

        $objects['logs']->add_log('bs_cache::update_plugin', "Update Done to v{$this->version}");
      }, PHP_INT_MAX);
    }

    update_option('bs_cache_version', $this->version);
  }

  public function deactivate_plugin() : void
  {
    if ($this->get_single_config('keep_settings_on_deactivation', 1) > 0) {
      $this->objects['cache_controller']->reset_all(true);
    } else {
      $this->objects['cache_controller']->reset_all();
    }

    $this->delete_plugin_wp_content_directory();
  }

  /**
   * The PHP function "ordinal" returns the ordinal suffix (st, nd, rd, or th) for a given number.
   * 
   * @param int number The `ordinal` function you provided is a PHP function that takes an integer
   * as input and returns the ordinal representation of that number (e.g., 1st, 2nd, 3rd, 4th, etc.).
   * 
   * @return string the ordinal representation of the input number.
  **/
  public function ordinal(int $number = 1) : string
  {
    $mod_100 = $number % 100;

    // Check for special cases: 11th, 12th, 13th
    if ($mod_100 >= 11 && $mod_100 <= 13) {
      return "{$number}th";
    }

    // Determine the suffix for other cases
    $mod_10 = $number % 10;

    switch($mod_10) {
      case 1:
        return "{$number}st";
      case 2:
        return "{$number}nd";
      case 3:
        return "{$number}rd";
      default:
        return "{$number}th";
    }
  }

  public function get_objects() : array
  {
    return $this->objects;
  }

  public function add_plugin_action_links(array $actions) : array
  {
    return array_merge($actions, [
      '<a href="' . admin_url('options-general.php?page=bigscoots-cache') . '">' . __('Settings', 'bigscoots-cache') . '</a>',
      '<a href="https://wpo.bigscoots.com/user/tickets/open" target="_blank" rel="nofollow">' . __('Support', 'bigscoots-cache') . '</a>',
    ]);
  }

  public function get_plugin_wp_content_directory() : string
  {
    $parts = wp_parse_url(home_url());

    return WP_CONTENT_DIR . "/bigscoots-cache/{$parts['host']}";
  }

  public function get_plugin_wp_content_directory_url() : string
  {
    $parts = wp_parse_url(home_url());

    return content_url("bigscoots-cache/{$parts['host']}");
  }

  private function create_plugin_wp_content_directory() : void
  {
    $parts = wp_parse_url(home_url());
    $path = WP_CONTENT_DIR . '/bigscoots-cache/';

    if (!file_exists($path)) {
      wp_mkdir_p($path);
      chmod($path, 0755);
    }

    $path .= $parts['host'];

    if (!file_exists($path) && wp_mkdir_p($path)) {
      chmod($path, 0755);
      file_put_contents("{$path}/index.php", '<?php // Silence is golden');
    }
  }

  public function delete_plugin_wp_content_directory() : void
  {
    $parts = wp_parse_url(home_url());
    $path = WP_CONTENT_DIR . '/bigscoots-cache/';
    $path .= $parts['host'];

    if (file_exists($path)) {
      $this->delete_directory_recursive($path);
    }
  }

  public function delete_directory_recursive(string $dir) : bool
  {
    if (!class_exists('RecursiveDirectoryIterator') || !class_exists('RecursiveIteratorIterator')) return false;

    $it    = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
      if ($file->isDir()) {
        rmdir($file->getRealPath());
      } else {
        wp_delete_file($file->getRealPath());
      }
    }

    rmdir($dir);

    return true;
  }

  public function enable_wp_cli_support() : void
  {
    /** @disregard P1011 - WP_CLI constant coming directly from WordPress core **/
    if (defined('WP_CLI') && WP_CLI && !class_exists('\BigScoots\Cache\WP_CLI') && class_exists('WP_CLI_Command')) {

      require_once BS_CACHE_PLUGIN_PATH . 'libs/wpcli.class.php';

      $wpcli = new \BigScoots\Cache\WP_CLI($this);

      /** @disregard P1009 - WP_CLI PHP Class coming directly from WordPress core **/
      \WP_CLI::add_command('bs_cache', $wpcli);
    }
  }

  public function can_current_user_purge_cache() : bool
  {
    // First, check if the user is logged in at all
    if (!is_user_logged_in()) return false;

    // Now that we know a user is logged in, get their ID for caching purposes
    $user_id = get_current_user_id();

    // Construct a unique cache key for this user
    $cache_key = "current_user_can_purge_cache_{$user_id}";
    $cached_result = $this->get_system_cache($cache_key);

    // If we have a cached result, return it immediately
    if ($cached_result !== false) return $cached_result === 'yes';

    // Admin check since they're likely allowed to do anything
    if (current_user_can('manage_options')) {
      $this->set_system_cache($cache_key, 'yes', YEAR_IN_SECONDS); // Cache for 1 year as it's admin user
      return true;
    }

    // Get the allowed roles and check if the user's role allows cache purge
    $allowed_roles = $this->get_single_config('cf_purge_roles', []);

    // If we don't have any custom role allowed for cache purge then return false
    if (empty($allowed_roles)) return false;

    // Get the current user details
    $current_user = wp_get_current_user();

    if (!$current_user instanceof \WP_User) return false;

    $is_user_allowed_to_purge_cache = !empty( array_intersect( $allowed_roles, (array) $current_user->roles ) );

    // Cache the result of this check for future requests for 6 hours
    $this->set_system_cache($cache_key, $is_user_allowed_to_purge_cache ? 'yes' : 'no', 6 * HOUR_IN_SECONDS);

    return $is_user_allowed_to_purge_cache;
  }

  public function get_wordpress_roles() : array
  {
    global $wp_roles;
    $wordpress_roles = [];

    foreach ($wp_roles->roles as $role => $role_data) {
      $wordpress_roles[] = $role;
    }

    return $wordpress_roles;
  }

  public function get_plan_name() : string
  {
    if (defined('BS_MASTER_KEY') && defined('BS_SITE_ID') && defined('BS_MASTER_URL')) {
      return 'Performance+';
    } elseif (
      defined('BS_SITE_CF_ZONE_ID_SALT') &&
      (
        (defined('BS_SITE_CF_EMAIL_SALT') && defined('BS_SITE_CF_API_KEY_SALT')) ||
        (defined('BS_SITE_CF_API_TOKEN_SALT'))
      )
    ) {
      return 'Standard';
    } else {
      return 'Misconfigured';
    }
  }

  public function get_environment_type() : string
  {
    if (!defined('WP_ENVIRONMENT_TYPE')) {
      return 'Production';  // Default to production if not defined
    }

    /** @disregard P1011 - WP_ENVIRONMENT_TYPE constant is being set by Bash script in wp-config.php at the time of staging setup **/
    switch (WP_ENVIRONMENT_TYPE) {
      case 'staging':
        $environment = 'Staging';
      break;

      case 'development':
        $environment = 'Development';
      break;

      default:
        $environment = 'Production';  // Default to production if none match
      break;
    }

    return $environment;
  }

  public function is_valid_url(string $url) : bool
  {
    // Check if the URL is valid using filter_var
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
      return false;
    }

    // Ensure the URL starts with http or https
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
      return false;
    }

    // Parse the URL to get its components
    $parsed_url = wp_parse_url($url);

    // Ensure the host is not an IP address and not 'localhost'
    if (
      isset($parsed_url['host']) && 
      (
        filter_var($parsed_url['host'], FILTER_VALIDATE_IP) !== false ||
        $parsed_url['host'] === 'localhost'
      )
    ) {
      return false;
    }

    // Ensure the URL does not contain a port
    if (isset($parsed_url['port'])) {
      return false;
    }

    return true;
  }

  public function is_api_request() : bool
  {
    // Wordpress standard API
    if ((defined('REST_REQUEST') && REST_REQUEST) || strcasecmp(substr($_SERVER['REQUEST_URI'], 0, 8), '/wp-json') == 0) return true;

    // WooCommerce standard API
    if (strcasecmp(substr($_SERVER['REQUEST_URI'], 0, 8), '/wc-api/') == 0) return true;

    // WooCommerce standard API
    if (strcasecmp(substr($_SERVER['REQUEST_URI'], 0, 9), '/edd-api/') == 0) return true;

    return false;
  }

  public function wildcard_match(string $pattern, string $subject) : bool
  {
    $pattern = '#^' . preg_quote($pattern) . '$#i'; // Case insensitive
    $pattern = str_replace('\*', '.*', $pattern);
    //$pattern=str_replace('\.', '.', $pattern);

    if (!preg_match($pattern, $subject, $regs)) return false;

    return true;
  }

  /**
   * The function `encode_non_ascii_chars_in_url` uses a regular expression to encode non-ASCII
   * characters in a given URL string.
   * 
   * @param url The `encode_non_ascii_chars_in_url` function takes a URL as input and encodes any
   * non-ASCII characters in the URL using `rawurlencode`. The regular expression `/[^\x20-\x7f]/`
   * matches any characters that are not in the ASCII range (characters with byte values
   * 
   * @return The function `encode_non_ascii_chars_in_url` takes a URL as input and returns the URL with
   * non-ASCII characters encoded using `rawurlencode`.
  **/
  public function encode_non_ascii_chars_in_url(string $url) : string
  {
    return preg_replace_callback('/[^\x20-\x7f]/', function ($match) : string {
      return rawurlencode($match[0]);
    }, $url);
  }

  /**
   * The function `get_response_header` sends a GET request to a provided URL and returns the response
   * headers.
   * 
   * @param url The URL of the website or resource you want to retrieve the response header from.
   * 
   * @return an array of response headers.
  **/
  public function get_response_header(string $url = 'https://example.com/') : array
  {
    // Validate the URL
    if (empty($url)) {
      return [
        'success' =>  false,
        'message' =>  'Please pass a URL to the function call to process the request'
      ];
    }

    // Construct the User-Agent string
    $user_agent = 'BigScoots-Cache/' . $this->get_current_plugin_version() . '; ' . get_bloginfo('url');

    // Initialize cURL session
    $ch = curl_init();

    // Initialize an empty array to hold the HTTP headers
    $headers = [];

    // Apply the cURL options using curl_setopt_array
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true,
      CURLOPT_NOBODY => true,
      CURLOPT_SSL_VERIFYPEER => false, // SSL verification disabled for performance gain
      CURLOPT_TIMEOUT => defined('BS_CACHE_CURL_TIMEOUT') ? BS_CACHE_CURL_TIMEOUT : 10,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_HTTPHEADER => [
        "User-Agent: {$user_agent}"
      ],
      CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$headers) : int {
        // Check if the header starts with 'HTTP/' which indicates the start of a new set of headers
        if (strpos($header, 'HTTP/') === 0) {
          $headers = [];  // Reset the headers array
        }

        // Calculate the length of the header
        $len = strlen($header);

        // Split the header into name and value
        $header = explode(':', $header, 2);

        // If the header does not contain a colon, it's not a valid header, skip it
        if (count($header) < 2) return $len;

        // Normalize the header name to lowercase. HTTP headers are case-insensitive.
        $name = strtolower(trim($header[0]));

        // Remove leading and trailing whitespaces from the header value
        $value = trim($header[1]);

        // If this header name was received before, aggregate the values into an array.
        // This is important for headers that can appear multiple times (e.g., Set-Cookie).
        if (isset($headers[$name])) {
          // If the header already has multiple values, append to the array
          if (is_array($headers[$name])) {
            $headers[$name][] = $value;
          } else {
            // If the header appears for the second time, create an array with both values
            $headers[$name] = [$headers[$name], $value];
          }
        } else {
          // This is the first occurrence of this header, simply store it
          $headers[$name] = $value;
        }

        // Return the length of the header to inform cURL that we've consumed it
        return $len;
      }
    ]);

    // Execute the cURL session
    curl_exec($ch);

    // Check for errors and log them if any
    if (curl_errno($ch)) {
      $error_message = curl_error($ch);
      curl_close($ch);  // Close the cURL session

      return [
        'success' =>  false,
        'message' =>  "There was an error fetching the URL ({$url}): {$error_message}"
      ];
    }

    // Close the cURL session
    curl_close($ch);

    return [
      'success' =>  true,
      'message' =>  'Successfully fetched the HTTP header for the url:',
      'headers' =>  $headers
    ];
  }

  public function export_config() : string
  {
    return wp_json_encode($this->get_config());
  }

  public function import_config(array $import_config)
  {
    if (!is_array($import_config)) return false;

    if (!isset($import_config['cf_cache_enabled'])) return false;

    $this->objects['cache_controller']->reset_all();

    unset($import_config['cf_cache_enabled']);

    $default_config = $this->get_config();
    $default_config = array_merge($default_config, $import_config);
    $this->set_config($default_config);
    $this->update_config();
  }

  /**
   * Function which third party plugins can use to purge OPCache using BigScoots Cache
  **/
  public static function clear_opcache() : void
  {
    // Create our own action to receive all these passed data and take action on them
    do_action('bigscoots/opcache/purge/programmatically');
  }

  public function third_party_opcache_purge() : void
  {
    $this->objects['cache_controller']->purge_opcache();
    $this->objects['logs']->add_log('bs_cache::opcache_purge_programmatically', 'OPCache has been purged for this website.');
  }

  /**
   * Function which third party plugins can use to purge Object Cache using BigScoots Cache
  **/
  public static function clear_object_cache() : void
  {
    // Create our own action to receive all these passed data and take action on them
    do_action('bigscoots/object-cache/purge/programmatically');
  }

  public function third_party_object_cache_purge() : void
  {
    $this->objects['cache_controller']->purge_object_cache();
    $this->objects['logs']->add_log('bs_cache::object_cache_purge_programmatically', 'Object cache has been purged for this website.');
  }

  /**
   * Function which third party plugins can use to purge BigScoots Cache
   * 
   * @param $items_to_purge: Could be -> an Post ID (int) OR an array of URLs OR false for purge everything
   * @param $purge_related_pages: Whether you would like to purge the related pages associated with a post id (like taxonomy, category pages, home pages, etc.)
  **/
  public static function clear_cache($items_to_purge, bool $purge_related_pages = false) : void
  {
    // Create our own action to receive all these passed data and take action on them
    do_action( 'bigscoots/cache/purge/programmatically', $items_to_purge, $purge_related_pages );
  }

  public function third_party_cache_purge($items_to_purge, bool $purge_related_pages) : bool
  {
    // Ensure that we got $items_to_purge is set
    if ( !isset($items_to_purge) ) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'BigScoots Cache cannot clear the cache as no data has been provided for the items to purge. The expected value of items_to_purge are Post ID (in integer format) or an array of URLs for which cache needs to be purged or `purge_everything` (string) if the user wish to purge everything for the domain.');
      return false;
    }

    // If $items_to_purge is an array make sure we have items in it
    if ( is_array($items_to_purge) && empty($items_to_purge) ) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'BigScoots Cache cannot clear the cache as an blank array has been provided for items to purge parameter.');
      return false;
    }

    // If $items_to_purge is string but not `purge_everything` - log error and retrun false
    if ( is_string($items_to_purge) && $items_to_purge !== 'purge_everything' ) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'BigScoots Cache cannot clear the cache as an unrecognized items to purge has been provided.');
      return false;
    }
    
    // If $items_to_purge -> `purge_everything` (string) - then purge cache for the whole domain
    if ( is_string($items_to_purge) && $items_to_purge === 'purge_everything' ) {
      $this->objects['cache_controller']->purge_all();
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'Purged BigScoots Cache for the entire domain.');

      if (!apply_filters('bs_cache_disable_clear_opcache', false)) {
        $this->objects['cache_controller']->purge_opcache();
      }

      return true;
    }

    // If $items_to_purge is an integer value - that means post ID has been passed to the call
    if (is_int($items_to_purge) && $items_to_purge > 0) {
      return $this->purge_single_post_id($items_to_purge, $purge_related_pages);
    }

    // If we have received an array for $items_to_purge
    if (is_array($items_to_purge) && !empty($items_to_purge)) {
      return $this->purge_array_items($items_to_purge, $purge_related_pages);
    }

    // If for some reason none of the above code blocks are executed, return false
    return false;
  }

  /**
   * The function `purge_single_post_id` clears the cache for a specific post ID and its related
   * pages if specified, handling various checks and logging messages along the way.
   * 
   * @param post_id The `purge_single_post_id` function is designed to clear the cache for a specific
   * post based on the provided `post_id`. The function first checks if the post exists and meets
   * certain criteria before proceeding with cache purging.
   * @param purge_related_pages The `purge_related_pages` parameter in the `purge_single_post_id`
   * function determines whether to purge only the main post URL or also the related pages URLs
   * associated with the post.
   * 
   * @return a boolean value - `true` if the cache purge was successful, and `false` if there was an
   * error or if certain conditions were not met during the cache purge process.
  **/
  private function purge_single_post_id(int $post_id, bool $purge_related_pages) : bool
  {
    // Get the post object for the given post id
    $post = get_post($post_id);

    // Don't allow cache purge for the post ids that are part of ignored post type
    $ignored_post_types = apply_filters('bs_cache_ignored_post_types', $this->get_single_config('cf_excluded_post_types', []));

    // Check we got a proper post id
    if (empty($post) || !$post instanceof \WP_Post) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', "Cannot purge cache for Post ID: {$post_id} — Either no post exists for this id or the given id is not for a post page.");
      return false;
    }

    // Check the post is not part of ignored post types
    if (is_array($ignored_post_types) && in_array($post->post_type, $ignored_post_types)) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', "Cannot purge cache for Post ID: {$post_id} — The post id belongs to BigScoots Cache ignored Post Type: {$post->post_type}.");
      return false;
    }

    // Check if the post status does not belong to `publish` or `private` - then don't clear the cache
    // As draft, scheduled or trash posts does not get cached
    if (!in_array($post->post_status, ['publish', 'private'])) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', "Cannot purge cache for Post ID: {$post_id} — No published or private post exists for this post id. Post status for the given post id: {$post->post_status}");
      return false;
    }

    // Declare a variable to hold the URLs that needs to be purged
    $list_of_urls_to_purge = [];

    // Now start gathering the list of URLs to purge
    if ($purge_related_pages) {
      $list_of_urls_to_purge = $this->objects['cache_controller']->get_post_related_links($post_id);
    } else {
      $permalink = get_permalink($post_id);

      if ($permalink) {
        $list_of_urls_to_purge[] = $permalink;
      } else {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', "Unable to purge BigScoots Cache for post ID -> {$post_id}. - No permalink found for this Post ID.");
        return false;
      }
    }

    // Make sure we are removing the duplicate URLs from the $list_of_urls_to_purge
    $list_of_urls_to_purge = array_unique($list_of_urls_to_purge);

    if ( $this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY && $this->get_plan_name() === 'Standard' ) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'List of URLs to be cleared from cache: ' . print_r($list_of_urls_to_purge, true));
    }

    // Clear the cache for the list of urls to purge and log it
    if ($this->objects['cache_controller']->purge_urls($list_of_urls_to_purge)) {
      $log_message = $purge_related_pages ? "Purged BigScoots Cache for Post ID: {$post_id} and its related pages." : "Purged BigScoots Cache for Post ID: {$post_id}.";
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', $log_message);
      return true;
    } else {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'An error occurred while purging the cache. Please check the plugin log for further details.');
      return false;
    }

    // Return false as a final catch-all
    return false;
  }

  /**
   * The function `purge_array_items` clears the cache based on either an array of integer post IDs
   * or an array of string URLs, handling various validation checks and logging actions along the
   * way.
   * 
   * @param items_to_purge The `items_to_purge` parameter in the `purge_array_items` function is an
   * array that contains either integer post IDs or string URLs that need to be purged from the
   * cache. The function checks the type of data in the array (integer or string) and performs cache
   * purging actions
   * @param purge_related_pages The `purge_related_pages` parameter in the `purge_array_items`
   * function determines whether related pages should also be purged along with the main item being
   * purged. If `purge_related_pages` is set to `true`, the function will generate URLs for related
   * pages based on the post
   * 
   * @return a boolean value - `true` if the cache purge operation was successful, and `false` if
   * there was an error or if the provided data was improper.
  **/
  private function purge_array_items(array $items_to_purge, bool $purge_related_pages) : bool
  {
    // Check if the $items_to_purge is an integer array or string array to determine what type of purge acrtion should we make
    $is_int_array = false;
    $is_string_array = false;

    foreach ($items_to_purge as $item) {
      if (is_int($item)) {
        $is_int_array = true;
      } elseif (is_string($item)) {
        $is_string_array = true;
      } else {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'The items to purge array has improper data. Either pass an array of integer Post IDs or an array of string URLs in order to clear the cache.');
        return false;
      }
    }

    // When the array includes both string and integer - don't proceed. Log error and return false
    if ($is_int_array && $is_string_array) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'The items to purge array has improper data. Either pass an array of integer Post IDs or an array of string URLs in order to clear the cache.');
      return false;
    }

    // Declare a variable to hold the URLs that needs to be purged
    $list_of_urls_to_purge = [];

    // When `$items_to_purge` is a list of Integer post IDs array
    if ($is_int_array) {
      // Don't allow cache purge for the post ids that are part of ignored post type
      $ignored_post_types = apply_filters('bs_cache_ignored_post_types', $this->get_single_config('cf_excluded_post_types', []));

      // Declare the variables to hold post ids that was able to cache or not able to cache and why
      $post_ids_trying_to_clear_cache = [
        'cleared_cache' =>  [],
        'post_doesnt_exists'  =>  [],
        'post_part_of_ignored_post_type' => [],
        'post_status_is_not_publish_or_private' =>  [],
        'no_permalink_found'  =>  []
      ];

      foreach ($items_to_purge as $item) {
        // Get the post object for the given post id
        $post = get_post($item);

        // Check we got a proper post id
        if (empty($post) || !$post instanceof \WP_Post) {
          // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
          $post_ids_trying_to_clear_cache['post_doesnt_exists'][] = $item;

          // Skip the rest of the loop and continue with the next iteration
          continue;
        }

        // Check the post is not part of ignored post types
        if (is_array($ignored_post_types) && in_array($post->post_type, $ignored_post_types)) {
          // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
          $post_ids_trying_to_clear_cache['post_part_of_ignored_post_type'][] = "{$item} (Post Type: {$post->post_type})";

          // Skip the rest of the loop and continue with the next iteration
          continue;
        }

        // Check if the post status does not belong to `publish` or `private` - then don't clear the cache
        // As draft, scheduled or trash posts does not get cached
        if (!in_array($post->post_status, ['publish', 'private'])) {
          // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
          $post_ids_trying_to_clear_cache['post_status_is_not_publish_or_private'][] = "{$item} (Post Status: {$post->post_status})";

          // Skip the rest of the loop and continue with the next iteration
          continue;
        }

        // Now start gathering the list of URLs to purge
        if ($purge_related_pages) {
          // Generate the purge URLs based on the post id
          $urls_to_purge = $this->objects['cache_controller']->get_post_related_links($item);

          // Add the users to list of urls to purge
          $list_of_urls_to_purge = [
            ...$list_of_urls_to_purge,
            ...$urls_to_purge
          ];

          // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
          $post_ids_trying_to_clear_cache['cleared_cache'][] = $item;
        } else {
          // Get the permalink for this post id
          $permalink = get_permalink($item);

          if ($permalink) {
            $list_of_urls_to_purge[] = $permalink;

            // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
            $post_ids_trying_to_clear_cache['cleared_cache'][] = $item;
          } else {
            // Add the post id to $post_ids_trying_to_clear_cache list to ube used for log later
            $post_ids_trying_to_clear_cache['no_permalink_found'][] = $item;
          }
        }
      }
    } elseif ($is_string_array) { // When `$items_to_purge` is a list of URLs
      // Declare variable to store invalid URLs
      $invalid_urls = [];

      // Make sure we validate the URLs before we purge them
      foreach ($items_to_purge as $url) {
        // URL encode non ASCII characters
        $url = (string) $this->encode_non_ascii_chars_in_url($url);

        // Validate URL format
        if ($this->is_valid_url($url)) {
          // Sanitize the URL for safe use
          $url = esc_url_raw($url);
          $list_of_urls_to_purge[] = $url;
        } else {
          $invalid_urls[] = $url;
        }
      }
    } else {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'The items to purge array has improper data. Either pass an array of integer Post IDs or an array of string URLs in order to clear the cache.');
      return false;
    }

    // If we are clearing cache by post ids then log for the post ids for which we we re unabel to purge cache and why
    if ($is_int_array) {
      // Logging for the post ids for which post/page doesn't exists
      if (!empty($post_ids_trying_to_clear_cache['post_doesnt_exists'])) {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['post_doesnt_exists']) . ' — Either no post exists for these ids or the given ids are not for a post page.');
      }

      // Logging for the cases where post is part of ignored post type
      if (!empty($post_ids_trying_to_clear_cache['post_part_of_ignored_post_type'])) {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['post_part_of_ignored_post_type']) . ' — These post ids belongs to BigScoots Cache ignored post types.');
      }

      // Logging for the cases where post status is not `publish` or `private`
      if (!empty($post_ids_trying_to_clear_cache['post_status_is_not_publish_or_private'])) {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['post_status_is_not_publish_or_private']) . ' — No published or private post exists for these post ids.');
      }

      // Logging for the cases where permalink couldn't be generated for the post ids
      if (!empty($post_ids_trying_to_clear_cache['no_permalink_found'])) {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'Cannot purge cache for Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['no_permalink_found']) . ' — No permalink found for these post ids.');
      }

      // Logging for cases where we got nothing to purge
      if (empty($list_of_urls_to_purge)) {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'Cannot purge cache for Post IDs: ' . implode(', ', $items_to_purge) . ' — None of the provided post ids are eligible for cache purge.');

        // No point proceeding further - return false
        return false;
      }
    } elseif ($is_string_array) {
      if (!empty($invalid_urls)) {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'These invalid URLs cannot be cleared from cache: ' . print_r($invalid_urls, true));
      }

      // Logging for cases where we got nothing to purge
      if (empty($list_of_urls_to_purge)) {
        $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'Cannot purge cache for URLs: ' . implode(', ', $items_to_purge) . ' — None of the provided URLs are eligible for cache purge.');

        // No point proceeding further - return false
        return false;
      }
    }

    // Make sure we are removing the duplicate URLs from the $list_of_urls_to_purge
    $list_of_urls_to_purge = array_unique($list_of_urls_to_purge);

    if ( $this->objects['logs'] instanceof \BigScoots\Cache\Logs && $this->objects['logs']->get_verbosity() == BS_CACHE_LOGS_HIGH_VERBOSITY && $this->get_plan_name() === 'Standard' ) {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'List of URLs to be cleared from cache: ' . print_r($list_of_urls_to_purge, true));
    }

    // Clear the cache for the list of urls to purge and log it
    if ($this->objects['cache_controller']->purge_urls($list_of_urls_to_purge)) {
      if ($is_int_array && $purge_related_pages) {
        $log_message = 'Purged BigScoots Cache for the Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['cleared_cache']) . ' and their related pages.';
      } elseif ($is_int_array && !$purge_related_pages) {
        $log_message = 'Purged BigScoots Cache for the Post IDs: ' . implode(', ', $post_ids_trying_to_clear_cache['cleared_cache']) . '.';
      } else {
        $log_message = 'Purged BigScoots Cache for the provided URLs list: ' . print_r($items_to_purge, true);
      }

      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', $log_message);

      return true;
    } else {
      $this->objects['logs']->add_log('bs_cache::cache_purge_programmatically', 'An error occurred while purging the cache. Please check the plugin log for further details.');
      return false;
    }
  }
}

// Activate this plugin as last plugin
add_action('plugins_loaded', function () : void {
  if (!isset($GLOBALS['bigscoots_cache']) || empty($GLOBALS['bigscoots_cache'])) {
    $GLOBALS['bigscoots_cache'] = new BigScoots_Cache();
  }
}, PHP_INT_MAX);