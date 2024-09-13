<?php
namespace BigScoots\Cache;

defined( 'ABSPATH' ) || wp_die( 'Cheatin&#8217; uh?' );

class Backend
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
    add_action('init', [$this, 'export_config']);
    add_action('admin_enqueue_scripts', [$this, 'load_custom_wp_admin_styles_and_script']);
    add_filter('admin_body_class', [$this, 'add_cache_status_class_admin']);
    add_filter('body_class', [$this, 'add_cache_status_class_frontend']);

    // Modify Script Attributes based of the script handle
    add_filter('script_loader_tag', [$this, 'modify_script_attributes'], 12, 2);

    add_action('admin_menu', [$this, 'add_admin_menu_pages']);

    if (is_admin() && is_user_logged_in() && current_user_can('manage_options')) {
      // Action rows
      add_filter('post_row_actions', [$this, 'add_post_row_actions'], PHP_INT_MAX, 2);
      add_filter('page_row_actions', [$this, 'add_post_row_actions'], PHP_INT_MAX, 2);
    }

    if ($this->main_instance->get_single_config('cf_prefetch_urls_on_hover', 0) > 0) {
      add_action('wp_head', [$this, 'add_speculation_rules'], PHP_INT_MAX);
      add_action('wp_enqueue_scripts', [$this, 'remove_perfmatters_instant_page_script'], PHP_INT_MAX);
    }

    if ($this->main_instance->get_single_config('cf_remove_purge_option_toolbar', 0) === 0 && $this->main_instance->can_current_user_purge_cache()) {
      // Load assets on frontend too
      add_action('wp_enqueue_scripts', [$this, 'load_custom_wp_admin_styles_and_script']);

      // Admin toolbar options
      add_action('admin_bar_menu', [$this, 'add_toolbar_items'], PHP_INT_MAX);
    }

    // Perfmatters Settings Page Message
    if (is_admin() && ($this->main_instance->get_plan_name() === 'Performance+')) {
      $perfmatters_active = false;

      if (function_exists('is_plugin_active') && is_plugin_active('perfmatters/perfmatters.php')) {
        $perfmatters_active = true;
      } elseif ($this->is_plugin_active_alternative('perfmatters/perfmatters.php')) {
        $perfmatters_active = true;
      }

      if ($perfmatters_active) {
        // Show the notice in the Perfmatters Settings page, asking users not to tinker with it
        add_action('admin_notices', [$this, 'show_perfmatters_settings_notice']);

        // Add class to admin body denoting whether or not the user is allowed to see the Perfmmatters settings
        add_filter('admin_body_class', [$this, 'add_perfmatters_settings_visibility_class_admin']);

        // Add Inline CSS to hide the Perfmatters Settings page
        add_action('admin_head', [$this, 'add_perfmatters_hide_settings_style']);
      }
    }

    // Show debug information within Site Health section — same data as `wp bs_cache status`
    add_filter('debug_information', [$this, 'add_bs_cache_debug_info'], 10, 1);
  }

  // Check if a plugin is active without using is_plugin_active()
  public function is_plugin_active_alternative(string $plugin) : bool
  {
    $active_plugins = get_option('active_plugins', []);

    if (is_multisite()) {
      $active_site_wide_plugins = get_site_option('active_sitewide_plugins', []);
      $active_site_wide_plugins = array_values($active_site_wide_plugins);
      $active_plugins = [
        ...$active_plugins,
        ...$active_site_wide_plugins
      ];
    }

    return in_array($plugin, $active_plugins);
  }

  public function add_cache_status_class_admin(string $classes) : string
  {
    if ( $this->main_instance->get_plan_name() === 'Misconfigured' ) {
      $classes .= ' bs-cache-misconfigured ';
    } elseif ($this->main_instance->get_single_config('cf_cache_enabled', 0) > 0) {
      $classes .= ' bs-cache-enabled ';
    } else {
      $classes .= ' bs-cache-disabled ';
    }

    return $classes;
  }

  public function add_perfmatters_settings_visibility_class_admin(string $classes) : string
  {
    $current_user = wp_get_current_user();

    // If it's `bigscoots` user then don't do anything
    if ($current_user instanceof \WP_User && (($current_user->user_login === 'bigscoots') || (get_user_meta($current_user->ID, 'perfmatters_plugin_settings_visible', true) === 'true')) ) {
      $classes .= ' bs-cache-show-perfmatters-settings ';
    } else {
      $classes .= ' bs-cache-hide-perfmatters-settings ';
    }

    return $classes;
  }

  public function add_cache_status_class_frontend(array $classes) : array
  {
    if ( is_user_logged_in() && is_admin_bar_showing() ) {
      if ( $this->main_instance->get_plan_name() === 'Misconfigured' ) {
        $classes = [
          ...$classes,
          'bs-cache-misconfigured'
        ];
      } elseif ( $this->main_instance->get_single_config('cf_cache_enabled', 0) > 0 ) {
        $classes = [
          ...$classes,
          'bs-cache-enabled'
        ];
      } else {
        $classes = [
          ...$classes,
          'bs-cache-disabled'
        ];
      }
    }

    return $classes;
  }

  public function show_perfmatters_settings_notice() : void
  {
    $screen = get_current_screen();
    $current_user = wp_get_current_user();

    // Show the following message only for Perfmatters Settings Page
    // If it's `bigscoots` user then don't do anything
    if ($screen instanceof \WP_Screen && $current_user instanceof \WP_User && ($screen->id === 'settings_page_perfmatters') && ($current_user->user_login !== 'bigscoots') ) {
      // Get the details about the user to see if they can view the plugin settings
      $show_settings = get_user_meta($current_user->ID, 'perfmatters_plugin_settings_visible', true);

      echo '<div id="bs_cache_main_content" class="bs-cache-perfmatters-settings-hide-wrapper">
        <div class="plugin_support_note perfmatters_plugin_support_note description_section highlighted">
          <div class="plugin-support-heading-holder">
            <h3>Important Note</h3>
            <svg id="a" xmlns="http://www.w3.org/2000/svg" width="26" height="25.28" viewBox="0 0 129.32 125.76"><circle cx="62.88" cy="62.88" r="62.88" fill="#fff"/><path d="M67.17,5.1c6.68,0,12.1,5.4,12.1,12.08h-.01v10.41c-.32.04-.67.07-1,.07-4.48,0-8.11-3.64-8.11-8.13v-1.07c-1.91,1.11-4.15,1.75-6.53,1.75-7.2,0-13.03-5.84-13.03-13.04,0-.8.07-1.58.2-2.33.12-.67.99-.8,1.33-.21,1.06,1.9,3.09,3.2,5.43,3.2,2.15,0,3.48-.68,4.82-1.36,1.34-.68,2.67-1.36,4.82-1.36ZM90.54,47.11c2.1,1.15,3.89,2.6,5.27,4.26v.03c1.89,2.3,2.98,5,2.98,7.89,0,6.39-4.11,12.07-10.46,15.68-4.5,2.56-10.13,4.07-16.25,4.07-5.06,0-9.78-1.03-13.81-2.84h-.01c4.95,4.25,12.32,6.94,20.57,6.94,2.11,0,4.16-.17,6.13-.51-2.03,3.36-4.66,6.32-7.73,8.75.07.19.12.37.17.56h0c1.82,6.76,1.43,30.75-1.61,31.57-2.85.76-13.97-17.7-16.71-25.18-.68.04-1.37.07-2.05.07-3.25,0-6.4-.48-9.35-1.37h-.02c-3.29,6.27-7.2,12-8.95,11.69-1.86-.32-3.71-7.96-4.6-15.48,3.8,1.93,8.02,3.13,12.48,3.43-12.91-4.36-22.21-16.57-22.21-30.97,0-2.3.28-4.7.82-7.15.02-.08.04-.16.05-.24.02-.08.03-.16.05-.24.01-.04.03-.08.03-.12.02-.09.04-.17.06-.25h0c.02-.08.04-.17.06-.25.04-.19.08-.36.13-.55.02-.07.04-.13.05-.19.02-.06.03-.13.05-.19.02-.07.04-.15.06-.23.02-.08.04-.15.06-.23l.12-.44c.02-.07.03-.13.05-.19.01-.05.03-.09.04-.14l.36-1.16s-.01.04-.02.06c0-.03.02-.05.02-.08h0c2.8-8.56,8.23-17.38,14.77-24.66-.31.84-.52,1.59-.6,2.2-.83,6.4,7.92,12.83,19.55,14.36,1.7.21,3.35.32,4.95.32,8.23,0,14.99-2.8,17.05-7.15.12-.25.25-.52.38-.81h0c2.53-5.37,7.96-16.9,18.98-16.9,6.85,0,11.47,2.4,15.26,4.36,2.63,1.37,4.86,2.52,7.16,2.52,3,0,4.6-1.15,5.15-4.67,1.7,9.2-4.47,9.91-11.5,10.72-4.98.57-10.39,1.2-13.75,4.92-1.56,1.73-2.52,3.67-3.46,5.58-.9,1.82-1.78,3.61-3.15,5.14-1.97-1.21-4.2-2.2-6.64-2.91ZM26.36,54.16s0,0,0,0c.62-6.47,2.73-13.06,5.82-19.22-7.05.04-12.75,5.79-12.75,12.84,0,4.47,2.29,8.41,5.75,10.71.32-1.44.71-2.88,1.18-4.34ZM38.25,71.5c5.11,1.22,10.45-2.87,11.94-9.12,1.49-6.24-1.45-12.29-6.56-13.51-5.11-1.22-10.45,2.87-11.94,9.12-1.49,6.24,1.45,12.29,6.56,13.51Z" fill="#415aff" fill-rule="evenodd"/></svg>
          </div>
          <p>We strongly advise opening a support ticket with our team if you encounter any problems with broken webpages due to optimizations or require specific exclusions. Modifying these settings on your own is not recommended.</p>
          <div class="plugin-support-note-btn-holder">
            <a href="https://wpo.bigscoots.com/user/tickets/open" target="_blank" class="button small-btn button-primary-solid" rel="nofollow">Open Support Ticket</a>
            <button type="button" id="toggle-perfmatters-settings" class="button small-btn button-danger" data-user_id="' . esc_attr($current_user->ID) . '" data-show_settings="' . esc_attr($show_settings ?: 'false') . '">' . esc_html($show_settings === 'true' ? 'Hide Plugin Settings' : 'Show Plugin Settings') . '</button>
          </div>
        </div>
      </div>';
    }
  }

  public function add_perfmatters_hide_settings_style() : void
  {
    $screen = get_current_screen();

    // Add the following style only on Perfmatters settings page
    if ($screen instanceof \WP_Screen && $screen->id === 'settings_page_perfmatters') {
      echo '<style>body.bs-cache-hide-perfmatters-settings div#perfmatters-admin-container div:not(#perfmatters-admin-header)>div.perfmatters-admin-block{position:relative;}body.bs-cache-hide-perfmatters-settings div#perfmatters-admin-container div:not(#perfmatters-admin-header)>div.perfmatters-admin-block::before{content:"";position:absolute;top:70px;left:0;width:100%;height:calc(100% - 70px);background-color:rgba(255,255,255,.25);backdrop-filter:blur(4px);z-index:999;}</style>';
    }
  }

  public function load_custom_wp_admin_styles_and_script() : void
  {
    $this->objects = $this->main_instance->get_objects();

    $css_version = $this->main_instance->get_current_plugin_version();
    $js_version = $css_version;
    $screen = (is_admin() && function_exists('get_current_screen')) ? get_current_screen() : false;

    // Don't load the scripts for Divi visual editor pages
    $on_divi_builder_page = empty( $_GET['et_fb'] ) ? false : true;

    // Don't load the scripts for Oxygen Builder visual editor pages
    $page_action = $_GET['action'] ?? false;
    $on_oxygen_ct_builder_page = $_GET['ct_builder'] ?? false; // If true, it will return "true" as String
    $on_oxygen_builder_page = (substr($page_action, 0, strlen('oxy_render')) === 'oxy_render') ? true : false;

    wp_register_style('bs_cache_sweetalert_css', BS_CACHE_PLUGIN_URL . 'assets/css/sweetalert2.min.css', [], '11.7.20.1');
    wp_register_style('bs_cache_admin_css', BS_CACHE_PLUGIN_URL . 'assets/css/style.min.css', ['bs_cache_sweetalert_css'], $css_version);

    wp_register_script('bs_cache_sweetalert_js', BS_CACHE_PLUGIN_URL . 'assets/js/sweetalert2.min.js', [], '11.7.20', true);
    wp_register_script('bs_cache_admin_js', BS_CACHE_PLUGIN_URL . 'assets/js/backend.min.js', ['bs_cache_sweetalert_js'], $js_version, true);
    wp_localize_script('bs_cache_admin_js', 'bs_cache_data', [
      'api_base'      => esc_url_raw( rest_url('bigscoots-cache/v2') ),
      'api_nonce'     => wp_create_nonce( 'wp_rest' ),
      'cache_enabled' => $this->main_instance->get_single_config('cf_cache_enabled', 0),
      'cache_status'  => $this->main_instance->get_plan_name()
    ]);

    // Making sure we are not adding the following scripts for pages which will cause issues
    /** @disregard P1010 - These functions are coming from WordPress AMP plugins **/
    if (
      !(
        (function_exists('amp_is_request') && (!is_admin() && amp_is_request())) ||
        (function_exists('ampforwp_is_amp_endpoint') && (!is_admin() && ampforwp_is_amp_endpoint())) ||
        ($screen instanceof \WP_Screen && in_array($screen->base, ['woofunnels_page_wfob', 'toplevel_page_xlwcty_builder', 'settings_page_imagify', 'media_page_imagify-bulk-optimization'])) ||
        is_customize_preview() ||
        filter_var($on_oxygen_ct_builder_page, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ||
        $on_oxygen_builder_page || $on_divi_builder_page
      )
    ) {
      wp_enqueue_style('bs_cache_admin_css');
      wp_enqueue_script('bs_cache_admin_js');
    }
  }

  public function add_speculation_rules() : void
  {
    // Don't add the speculation rules for the AMP and customizer preview pages
    /** @disregard P1010 - These functions are coming from WordPress AMP plugins **/
    if ( (function_exists('amp_is_request') && amp_is_request()) || (function_exists('ampforwp_is_amp_endpoint') && ampforwp_is_amp_endpoint()) || is_customize_preview() ) return;

    // Allowing filter based turning off speculation rule if it's ever needed on any specific page
    if (!apply_filters('bs_cache_speculation_rules_enabled', true)) return;

    // This workaround is needed for WP 6.4. See <https://core.trac.wordpress.org/ticket/60320>.
    $needs_html5_workaround = (
      ! current_theme_supports( 'html5', 'script' ) &&
      version_compare( strtok( get_bloginfo( 'version' ), '-' ), '6.4', '>=' ) &&
      version_compare( strtok( get_bloginfo( 'version' ), '-' ), '6.5', '<' )
    );

    if ( $needs_html5_workaround ) {
      $backup_wp_theme_features = $GLOBALS['_wp_theme_features'];
      add_theme_support( 'html5', array( 'script' ) );
    }

    // Create the list of URL match regex that should be ignored from prerendering
    $base_href_exclude_paths = [
      '/wp-login.php',
      '/wp-admin/*',
      '/wp-content/*',
      '/wp-content/plugins/*',
      '/wp-content/uploads/*',
      '/wp-content/themes/stylesheet/*',
      '/wp-content/themes/template/*',
      '/checkout/*',
      '/checkouts/*',
      '/logout/*',
      '/*/print/*', // WP Tasty
      '/wprm_print/*', // WPRM
      '/*\\?*(^|&)(_wpnonce|add-to-cart|add_to_cart|add-to-checkout|cart|edd_action|download_id|edd_options|wlmapi)(=|&|$)*'
    ];

    // Add filter to add more URL patterns to be added to exclude list
    $href_exclude_paths = (array) apply_filters('bs_cache_speculation_href_exclude_paths', []);

    // Ensure that:
    // 1. There are no duplicates.
    // 2. The base paths cannot be removed.
    // 3. The array has sequential keys (i.e. array_is_list()).
    // List of paths to exclude from the user-provided list
    $exclude_paths = array_values(
      array_diff_key( $base_href_exclude_paths, [ array_key_last($base_href_exclude_paths) => '' ] )
    );
    
    $exclude_paths[] = '/*';

    // Filter out the paths that should be excluded
    $filtered_href_exclude_paths = array_filter(
      array_map('trim', $href_exclude_paths),
      static function ($href_exclude_path) use ($exclude_paths) : bool {
        return !in_array($href_exclude_path, $exclude_paths);
      }
    );

    // Merge base paths with the filtered paths and ensure there are no duplicates
    $href_exclude_paths = array_values(
      array_unique( [
        ...$base_href_exclude_paths,
        ...$filtered_href_exclude_paths
      ] )
    );

    // Add a filter to change the Speculation Rule mode from prerender to prefetch if needed
    $speculation_rule_mode = (string) trim( apply_filters('bs_cache_speculation_mode', 'prerender') );
    // Do not allow any other mode except prerender or prefetch
    $speculation_rule_mode = in_array($speculation_rule_mode, ['prerender', 'prefetch']) ? $speculation_rule_mode : 'prerender';

    // Create the Speculation Rule
    $rules = [
      $speculation_rule_mode => [
        [
          'source' => 'document',
          'where'  => [
            'and' =>  [
              // Include any URLs within the same site.
              [
                'href_matches' => '/*'
              ],
              // Ignore the URLs that do not need to be pre-rendered based on path or query params
              [
                'not' =>  [
                  'href_matches' => $href_exclude_paths
                ]
              ],
              // Also exclude rel=nofollow links, as plugins like WooCommerce use that on their add-to-cart links.
              [
                'not' =>  [
                  'selector_matches' => 'a[rel~="nofollow"]'
                ]
              ],
              // Add `.no-prerender` class to the exclude list as well in case any site owner or plugins like to use that
              [
                'not' =>  [
                  'selector_matches' => '.no-prerender'
                ]
              ]
            ]
          ],
          'eagerness' => 'moderate'
        ]
      ]
    ];

    // Adding a filter incase anyone would ever likes to modify the speculation rule before it gets added to the webpage
    $speculation_rules = (array) apply_filters('bs_cache_speculation_rules', $rules);

    // JavaScript to remove Speculation Rules and instead use instant page to prefetch the URLs using link tags
    $instant_page_invocation_script = 'document.addEventListener("DOMContentLoaded",function(){if(!(HTMLScriptElement.supports && HTMLScriptElement.supports("speculationrules"))){var bs_cache_speculation_script_element=document.getElementById("bs-cache-speculation-rules");bs_cache_speculation_script_element&&bs_cache_speculation_script_element.parentNode.removeChild(bs_cache_speculation_script_element);var bs_cache_instant_prefetch_script_element=document.createElement("script");bs_cache_instant_prefetch_script_element.id="bs-cache-instant-prefetch-js",bs_cache_instant_prefetch_script_element.type="module",bs_cache_instant_prefetch_script_element.src="' . BS_CACHE_PLUGIN_URL . 'assets/js/bs-cache-instant-prefetch-page.min.js' . '",document.body.appendChild(bs_cache_instant_prefetch_script_element)}})';

    // Check if the current website is using an older version of WP (<5.7.0) - in that case print the scripts in head
    // Otherwise use `wp_print_inline_script_tag()` function to properly load the inline scripts
    if (function_exists('wp_print_inline_script_tag')) { // Site running WP >=5.7.0
      // Add Speculation Rules on the website
      /** @disregard P1010 - This is WordPress core function **/
      wp_print_inline_script_tag(
        wp_json_encode($speculation_rules),
        [
          'id'   => 'bs-cache-speculation-rules',
          'type' => 'speculationrules',
        ]
      );

      // Add instant page invocation script to the website
      /** @disregard P1010 - This is WordPress core function **/
      wp_print_inline_script_tag(
        $instant_page_invocation_script,
        [
          'id'   => 'bs-cache-instant-prefetch-invoker-js',
          'type' => 'text/javascript',
        ]
      );
    } else { // Site running WP < 5.7.0
      // Print the speculation rules
      echo '<script id="bs-cache-speculation-rules" type="speculationrules">' . wp_json_encode($speculation_rules) . '</script>';

      // Print the instant page invocation script
      echo '<script id="bs-cache-instant-prefetch-invoker-js" type="text/javascript">' . $instant_page_invocation_script . '</script>';
    }
  
    if ( $needs_html5_workaround ) {
      $GLOBALS['_wp_theme_features'] = $backup_wp_theme_features; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    }
  }

  public function remove_perfmatters_instant_page_script() : void
  {
    /** @disregard P1010 - These functions are coming from WordPress AMP plugins **/
    if (!( (function_exists('amp_is_request') && amp_is_request()) || (function_exists('ampforwp_is_amp_endpoint') && ampforwp_is_amp_endpoint()) || is_customize_preview())) {
      // If Perfmatters Instant Page option is enabled then dequeue that script and use our instant page implementation
      if ( wp_script_is('perfmatters-instant-page') ) {
        wp_dequeue_script('perfmatters-instant-page');
        wp_deregister_script('perfmatters-instant-page');
      }
    }
  }

  public function modify_script_attributes(string $tag, string $handle) : string
  {
    // List of scripts added by this plugin
    $plugin_scripts = [
      'bs_cache_sweetalert_js',
      'bs_cache_admin_js'
    ];

    // Check if handle is any of the above scripts made sure we load them as defer
    if (in_array($handle, $plugin_scripts)) {
      return str_replace(' id', ' defer id', $tag);
    }

    return $tag;
  }

  public function add_toolbar_items(\WP_Admin_Bar $admin_bar) : void
  {
    $screen = is_admin() ? get_current_screen() : false;

    // Don't load the scripts for Divi visual editor pages
    $on_divi_builder_page = empty( $_GET['et_fb'] ) ? false : true;

    // Don't load the scripts for Oxygen Builder visual editor pages
    $page_action = $_GET['action'] ?? false;
    $on_oxygen_ct_builder_page = $_GET['ct_builder'] ?? false; // If true, it will return "true" as String
    $on_oxygen_builder_page = (substr($page_action, 0, strlen('oxy_render')) === 'oxy_render') ? true : false;

    // Make sure we don't add the following admin bar menu for pages which will cause issues as it is not gonna work on those pages
    /** @disregard P1010 - These functions are coming from WordPress AMP plugins **/
    if (
      (function_exists('amp_is_request') && (!is_admin() && amp_is_request())) ||
      (function_exists('ampforwp_is_amp_endpoint') && (!is_admin() && ampforwp_is_amp_endpoint())) ||
      ($screen instanceof \WP_Screen && in_array($screen->base, ['woofunnels_page_wfob', 'toplevel_page_xlwcty_builder', 'settings_page_imagify', 'media_page_imagify-bulk-optimization'])) ||
      is_customize_preview() ||
      filter_var($on_oxygen_ct_builder_page, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ||
      $on_oxygen_builder_page || $on_divi_builder_page
    ) return;

    $this->objects = $this->main_instance->get_objects();

    if ($this->main_instance->get_single_config('cf_remove_purge_option_toolbar', 0) === 0) {

      $admin_bar->add_menu( [
        'id'    => 'bs-cache-toolbar-container',
        'title' => '<span class="ab-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cloud-fill" viewBox="0 0 16 16"><path d="M4.406 3.342A5.53 5.53 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773 16 11.569 14.502 13 12.687 13H3.781C1.708 13 0 11.366 0 9.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383"/></svg></span><span class="ab-label">' . __('BigScoots Cache', 'bigscoots-cache') . '</span>',
        'href'  => current_user_can('manage_options') ? add_query_arg( ['page' => 'bigscoots-cache'], admin_url('options-general.php') ) : '#',
        'meta'  =>  [
          'rel'     =>  'nofollow',
          'class'   =>  'bs-cache-toolbar-container no-prerender'
        ]
      ] );

      if ($this->main_instance->get_single_config('cf_cache_enabled', 0) > 0) {

        global $post, $pagenow;

        // Purge Everything
        $admin_bar->add_menu( [
          'id'      => 'bs-cache-toolbar-force-purge-everything',
          'parent'  => 'bs-cache-toolbar-container',
          'title'   => 'Clear Cache for Entire Site (<strong>Including</strong> Images and CSS/JS)',
          'href'    => '#',
          'meta'  =>  [
            'rel'     =>  'nofollow',
            'class'   =>  'bs-cache-toolbar-force-purge-everything no-prerender'
          ]
        ] );

        if ( 
          !$this->objects['cache_controller']->is_page_cache_disabled() &&
          $post instanceof \WP_Post &&
          (
            (is_admin() && !empty($pagenow) && in_array($pagenow, ['post.php'])) ||
            (is_user_logged_in() && !is_admin())
          )
        ) {

          // Also don't show this option for the post types for which are ignored from cache
          $ignored_post_types = apply_filters('bs_cache_ignored_post_types', $this->main_instance->get_single_config('cf_excluded_post_types', []));

          if ( is_array($ignored_post_types) && !in_array($post->post_type, $ignored_post_types) && in_array($post->post_status, ['publish', 'private']) ) {

            // Purge Cache for This Page Only
            $admin_bar->add_menu( [
              'id'      => 'bs-cache-toolbar-purge-single',
              'parent'  => 'bs-cache-toolbar-container',
              'title'   => 'Clear Cache for This Page Only',
              'href'    => "#{$post->ID}",
              'meta'  =>  [
                'rel'     =>  'nofollow',
                'class'   =>  'bs-cache-toolbar-purge-single no-prerender'
              ]
            ] );
          }
        }

        // Purge Cache for Any URL Specified by the user
        $admin_bar->add_menu( [
          'id'      => 'bs-cache-toolbar-purge-user-given-urls',
          'parent'  => 'bs-cache-toolbar-container',
          'title'   => 'Clear Cache for Specific URLs',
          'href'    => '#',
          'meta'  =>  [
            'rel'     =>  'nofollow',
            'class'   =>  'bs-cache-toolbar-purge-user-given-urls no-prerender'
          ]
        ] );

        // Help Article Link
        $admin_bar->add_menu( [
          'id'      => 'bs-cache-toolbar-help-article',
          'parent'  => 'bs-cache-toolbar-container',
          'title'   => __('Unsure Which Option to Choose?', 'bigscoots-cache'),
          'href'    => 'http://help.bigscoots.com/en/articles/7942918-bigscoots-cache-clearing-options',
          'meta'  =>  [
            'target'  => '_blank',
            'rel'     => 'nofollow',
            'class'   => 'bs-cache-toolbar-help-article no-prerender',
            'title'   => 'Unsure of which option to choose? Check the help article.',
            'html'    => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-patch-question-fill" viewBox="0 0 16 16"><path d="M5.933.87a2.89 2.89 0 0 1 4.134 0l.622.638.89-.011a2.89 2.89 0 0 1 2.924 2.924l-.01.89.636.622a2.89 2.89 0 0 1 0 4.134l-.637.622.011.89a2.89 2.89 0 0 1-2.924 2.924l-.89-.01-.622.636a2.89 2.89 0 0 1-4.134 0l-.622-.637-.89.011a2.89 2.89 0 0 1-2.924-2.924l.01-.89-.636-.622a2.89 2.89 0 0 1 0-4.134l.637-.622-.011-.89a2.89 2.89 0 0 1 2.924-2.924l.89.01zM7.002 11a1 1 0 1 0 2 0 1 1 0 0 0-2 0m1.602-2.027c.04-.534.198-.815.846-1.26.674-.475 1.05-1.09 1.05-1.986 0-1.325-.92-2.227-2.262-2.227-1.02 0-1.792.492-2.1 1.29A1.7 1.7 0 0 0 6 5.48c0 .393.203.64.545.64.272 0 .455-.147.564-.51.158-.592.525-.915 1.074-.915.61 0 1.03.446 1.03 1.084 0 .563-.208.885-.822 1.325-.619.433-.926.914-.926 1.64v.111c0 .428.208.745.585.745.336 0 .504-.24.554-.627"/></svg>'
          ]
        ] );
      }
    }
  }

  public function add_post_row_actions(array $actions, \WP_Post $post) : array
  {
    $this->objects = $this->main_instance->get_objects();

    // Get the ignored post types set in the settings page:
    $ignored_post_types = apply_filters('bs_cache_ignored_post_types', $this->main_instance->get_single_config('cf_excluded_post_types', []));

    // Don't add the option for posts within ignored post type and for post that doesn't have status `publish` or `private`
    if ( is_array($ignored_post_types) && !in_array($post->post_type, $ignored_post_types) && in_array($post->post_status, ['publish', 'private']) ) {
      $actions['bs_cache_single_purge'] = '<a class="bs_cache_action_row_single_post_cache_purge" data-post_id="' . $post->ID . '" href="#" target="_blank">' . __('Clear Cache for This Page Only', 'bigscoots-cache') . '</a>';
    }

    return $actions;
  }

  public function add_admin_menu_pages() : void
  {
    add_options_page(
      __('BigScoots Cache', 'bigscoots-cache'),
      __('BigScoots Cache', 'bigscoots-cache'),
      'manage_options',
      'bigscoots-cache',
      [$this, 'admin_menu_page_index']
    );
  }

  public function admin_menu_page_index() : void
  {
    // User permission check
    if (!current_user_can('manage_options')) {
      wp_die( esc_html__('Permission denied', 'bigscoots-cache') );
    }

    $this->objects = $this->main_instance->get_objects();

    $success_msg = '';

    // Save settings
    if ( isset( $_POST['bs_cache_submit_general'] ) ) {
      // Nonce Check
      if ( ! isset( $_POST['bs_cache_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bs_cache_settings_nonce'] ) ), 'bs_cache_settings_nonce' ) ) {
        wp_die( esc_html__('Permission denied — Nonce mismatch', 'bigscoots-cache') );
      }

      // Logs
      $this->main_instance->set_single_config('log_enabled', (int) $_POST['bs_cache_log_enabled']);

      // Log max file size
      $this->main_instance->set_single_config('log_max_file_size', (int) sanitize_text_field( $_POST['bs_cache_log_max_file_size'] ));

      // Log verbosity
      $this->main_instance->set_single_config('log_verbosity', sanitize_text_field($_POST['bs_cache_log_verbosity']) );

      if ($this->main_instance->get_single_config('log_enabled', 0) > 0) {
        $this->objects['logs']->enable_logging();
      } else {
        $this->objects['logs']->disable_logging();
      }

      // Immediate saving to allow you to immediately apply the connection settings
      $this->main_instance->update_config();

      if (isset($_POST['bs_cache_post_per_page']) && (int) $_POST['bs_cache_post_per_page'] >= 0) {
        $this->main_instance->set_single_config('cf_post_per_page', (int) sanitize_text_field( $_POST['bs_cache_post_per_page'] ));
      }

      if (isset($_POST['bs_cache_maxage']) && (int) $_POST['bs_cache_maxage'] >= 0) {
        $this->main_instance->set_single_config('cf_maxage', (int) sanitize_text_field( $_POST['bs_cache_maxage'] ));
      }

      if (isset($_POST['bs_cache_browser_maxage']) && (int) $_POST['bs_cache_browser_maxage'] >= 0) {
        $this->main_instance->set_single_config('cf_browser_maxage', (int) sanitize_text_field( $_POST['bs_cache_browser_maxage'] ));
      }

      if (isset($_POST['bs_cache_cf_auto_purge'])) {
        $this->main_instance->set_single_config('cf_auto_purge', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge']));
      } else {
        $this->main_instance->set_single_config('cf_auto_purge', 0);
      }

      if (isset($_POST['bs_cache_cf_auto_purge_all'])) {
        $this->main_instance->set_single_config('cf_auto_purge_all', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge_all']));
      } else {
        $this->main_instance->set_single_config('cf_auto_purge_all', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_single_post'])) {
        $this->main_instance->set_single_config('cf_bypass_single_post', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_single_post']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_single_post', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_author_pages'])) {
        $this->main_instance->set_single_config('cf_bypass_author_pages', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_author_pages']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_author_pages', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_search_pages'])) {
        $this->main_instance->set_single_config('cf_bypass_search_pages', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_search_pages']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_search_pages', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_feeds'])) {
        $this->main_instance->set_single_config('cf_bypass_feeds', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_feeds']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_feeds', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_category'])) {
        $this->main_instance->set_single_config('cf_bypass_category', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_category']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_category', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_tags'])) {
        $this->main_instance->set_single_config('cf_bypass_tags', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_tags']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_tags', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_archives'])) {
        $this->main_instance->set_single_config('cf_bypass_archives', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_archives']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_archives', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_home'])) {
        $this->main_instance->set_single_config('cf_bypass_home', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_home']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_home', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_front_page'])) {
        $this->main_instance->set_single_config('cf_bypass_front_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_front_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_front_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_pages'])) {
        $this->main_instance->set_single_config('cf_bypass_pages', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_pages']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_pages', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_amp'])) {
        $this->main_instance->set_single_config('cf_bypass_amp', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_amp']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_amp', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_ajax'])) {
        $this->main_instance->set_single_config('cf_bypass_ajax', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_ajax']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_ajax', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_query_var'])) {
        $this->main_instance->set_single_config('cf_bypass_query_var', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_query_var']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_query_var', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_wp_json_rest'])) {
        $this->main_instance->set_single_config('cf_bypass_wp_json_rest', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_wp_json_rest']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_wp_json_rest', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_redirects'])) {
        $this->main_instance->set_single_config('cf_bypass_redirects', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_redirects']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_redirects', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_sitemap'])) {
        $this->main_instance->set_single_config('cf_bypass_sitemap', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_sitemap']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_sitemap', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_file_robots'])) {
        $this->main_instance->set_single_config('cf_bypass_file_robots', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_file_robots']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_file_robots', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_logged_in'])) {
        $this->main_instance->set_single_config('cf_bypass_logged_in', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_logged_in']));
      }

      // EDD
      if (isset($_POST['bs_cache_cf_bypass_edd_checkout_page'])) {
        $this->main_instance->set_single_config('cf_bypass_edd_checkout_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_edd_checkout_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_edd_checkout_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_edd_login_redirect_page'])) {
        $this->main_instance->set_single_config('cf_bypass_edd_login_redirect_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_edd_login_redirect_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_edd_login_redirect_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_edd_purchase_history_page'])) {
        $this->main_instance->set_single_config('cf_bypass_edd_purchase_history_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_edd_purchase_history_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_edd_purchase_history_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_edd_success_page'])) {
        $this->main_instance->set_single_config('cf_bypass_edd_success_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_edd_success_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_edd_success_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_edd_failure_page'])) {
        $this->main_instance->set_single_config('cf_bypass_edd_failure_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_edd_failure_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_edd_failure_page', 0);
      }

      if (isset($_POST['bs_cache_cf_auto_purge_edd_payment_add'])) {
        $this->main_instance->set_single_config('cf_auto_purge_edd_payment_add', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge_edd_payment_add']));
      }

      // WooCommerce
      if (isset($_POST['bs_cache_cf_auto_purge_woo_product_page'])) {
        $this->main_instance->set_single_config('cf_auto_purge_woo_product_page', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge_woo_product_page']));
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_cart_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_cart_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_cart_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_cart_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_account_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_account_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_account_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_account_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_checkout_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_checkout_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_checkout_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_checkout_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_checkout_pay_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_checkout_pay_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_checkout_pay_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_checkout_pay_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_shop_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_shop_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_shop_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_shop_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_pages'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_pages', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_pages']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_pages', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_product_tax_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_product_tax_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_product_tax_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_product_tax_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_product_tag_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_product_tag_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_product_tag_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_product_tag_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_product_cat_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_product_cat_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_product_cat_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_product_cat_page', 0);
      }

      if (isset($_POST['bs_cache_cf_bypass_woo_product_page'])) {
        $this->main_instance->set_single_config('cf_bypass_woo_product_page', (int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_product_page']));
      } else {
        $this->main_instance->set_single_config('cf_bypass_woo_product_page', 0);
      }

      if (isset($_POST['bs_cache_cf_auto_purge_woo_scheduled_sales'])) {
        $this->main_instance->set_single_config('cf_auto_purge_woo_scheduled_sales', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge_woo_scheduled_sales']));
      } else {
        $this->main_instance->set_single_config('cf_auto_purge_woo_scheduled_sales', 0);
      }

      if (isset($_POST['bs_cache_cf_optimize_woo_cookie'])) {
        $this->main_instance->set_single_config('cf_optimize_woo_cookie', (int) sanitize_text_field($_POST['bs_cache_cf_optimize_woo_cookie']));
      } else {
        $this->main_instance->set_single_config('cf_optimize_woo_cookie', 0);
      }

      // WP Recipe Maker
      if (isset($_POST['bs_cache_cf_wprm_purge_on_cache_flush'])) {
        $this->main_instance->set_single_config('cf_wprm_purge_on_cache_flush', (int) sanitize_text_field($_POST['bs_cache_cf_wprm_purge_on_cache_flush']));
      }

      // AUTOPTIMIZE
      if (isset($_POST['bs_cache_cf_autoptimize_purge_on_cache_flush'])) {
        $this->main_instance->set_single_config('cf_autoptimize_purge_on_cache_flush', (int) sanitize_text_field($_POST['bs_cache_cf_autoptimize_purge_on_cache_flush']));
      }

      // WP ROCKET
      if (isset($_POST['bs_cache_cf_wp_rocket_purge_on_domain_flush'])) {
        $this->main_instance->set_single_config('cf_wp_rocket_purge_on_domain_flush', (int) sanitize_text_field($_POST['bs_cache_cf_wp_rocket_purge_on_domain_flush']));
      } else {
        $this->main_instance->set_single_config('cf_wp_rocket_purge_on_domain_flush', 0);
      }

      if (isset($_POST['bs_cache_cf_wp_rocket_purge_on_rucss_job_complete'])) {
        $this->main_instance->set_single_config('cf_wp_rocket_purge_on_rucss_job_complete', (int) sanitize_text_field($_POST['bs_cache_cf_wp_rocket_purge_on_rucss_job_complete']));
      } else {
        $this->main_instance->set_single_config('cf_wp_rocket_purge_on_rucss_job_complete', 0);
      }

      // WP Super Cache
      if (isset($_POST['bs_cache_cf_wp_super_cache_on_cache_flush'])) {
        $this->main_instance->set_single_config('cf_wp_super_cache_on_cache_flush', (int) sanitize_text_field($_POST['bs_cache_cf_wp_super_cache_on_cache_flush']));
      } else {
        $this->main_instance->set_single_config('cf_wp_super_cache_on_cache_flush', 0);
      }

      // WP Asset Clean Up
      if (isset($_POST['bs_cache_cf_wpacu_purge_on_cache_flush'])) {
        $this->main_instance->set_single_config('cf_wpacu_purge_on_cache_flush', (int) sanitize_text_field($_POST['bs_cache_cf_wpacu_purge_on_cache_flush']));
      } else {
        $this->main_instance->set_single_config('cf_wpacu_purge_on_cache_flush', 0);
      }

      // YASR
      if (isset($_POST['bs_cache_cf_yasr_purge_on_rating'])) {
        $this->main_instance->set_single_config('cf_yasr_purge_on_rating', (int) sanitize_text_field($_POST['bs_cache_cf_yasr_purge_on_rating']));
      }

      // Cloudflare Prefetch URLs - https://developers.cloudflare.com/speed/optimization/content/prefetch-urls/
      if (isset($_POST['bs_cache_cf_prefetch_urls'])) {
        $this->main_instance->set_single_config('cf_prefetch_urls', (int) sanitize_text_field($_POST['bs_cache_cf_prefetch_urls']));
      }

      // Strip cookies
      if (isset($_POST['bs_cache_cf_strip_cookies'])) {
        $this->main_instance->set_single_config('cf_strip_cookies', (int) sanitize_text_field($_POST['bs_cache_cf_strip_cookies']));
      }

      //  Purge cache lock
      if (isset($_POST['bs_cache_cf_purge_cache_lock'])) {
        $this->main_instance->set_single_config('cf_purge_cache_lock', (int) sanitize_text_field($_POST['bs_cache_cf_purge_cache_lock']));
      }

      // Comments
      if (isset($_POST['bs_cache_cf_auto_purge_on_comments'])) {
        $this->main_instance->set_single_config('cf_auto_purge_on_comments', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge_on_comments']));
      }

      if (isset($_POST['bs_cache_cf_auto_purge_related_pages_on_comments'])) {
        $this->main_instance->set_single_config('cf_auto_purge_related_pages_on_comments', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge_related_pages_on_comments']));
      }

      // Purge OPcache cache on upgrader process complete
      if (isset($_POST['bs_cache_cf_auto_purge_opcache_on_upgrader_process_complete'])) {
        $this->main_instance->set_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge_opcache_on_upgrader_process_complete']));
      }

      // Purge CDN cache on upgrader process complete
      if (isset($_POST['bs_cache_cf_auto_purge_on_upgrader_process_complete'])) {
        $this->main_instance->set_single_config('cf_auto_purge_on_upgrader_process_complete', (int) sanitize_text_field($_POST['bs_cache_cf_auto_purge_on_upgrader_process_complete']));
      }

      // Prefetch URLs on mouse hover
      if (isset($_POST['bs_cache_cf_prefetch_urls_on_hover'])) {
        $this->main_instance->set_single_config('cf_prefetch_urls_on_hover', (int) sanitize_text_field($_POST['bs_cache_cf_prefetch_urls_on_hover']));
      }

      // Keep settings on deactivation
      if (isset($_POST['bs_cache_keep_settings_on_deactivation'])) {
        $this->main_instance->set_single_config('keep_settings_on_deactivation', (int) sanitize_text_field($_POST['bs_cache_keep_settings_on_deactivation']));
      }

      // URLs to exclude from cache
      if (isset($_POST['bs_cache_cf_excluded_urls'])) {

        $excluded_urls = [];
        $excluded_urls_received = sanitize_textarea_field($_POST['bs_cache_cf_excluded_urls']);

        if (strlen(trim($excluded_urls_received)) > 0) {
          $excluded_urls_received .= "\n";
        }

        $parsed_excluded_urls = explode("\n", $excluded_urls_received);

        if (isset($_POST['bs_cache_cf_bypass_woo_checkout_page']) && ((int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_checkout_page'])) > 0 && function_exists('wc_get_checkout_url')) {
          /** @disregard P1010 - This is WooCommerce core function **/
          $parsed_excluded_urls[] = wc_get_checkout_url() . '*';
        }

        if (isset($_POST['bs_cache_cf_bypass_woo_cart_page']) && ((int) sanitize_text_field($_POST['bs_cache_cf_bypass_woo_cart_page'])) > 0 && function_exists('wc_get_cart_url')) {
          /** @disregard P1010 - This is WooCommerce core function **/
          $parsed_excluded_urls[] = wc_get_cart_url() . '*';
        }

        if (isset($_POST['bs_cache_cf_bypass_edd_checkout_page']) && ((int) sanitize_text_field($_POST['bs_cache_cf_bypass_edd_checkout_page'])) > 0 && function_exists('edd_get_checkout_uri')) {
          /** @disregard P1010 - This is WooCommerce core function **/
          $parsed_excluded_urls[] = edd_get_checkout_uri() . '*';
        }

        foreach ($parsed_excluded_urls as $single_url) {

          if (trim($single_url) == '') continue;

          $parsed_url = wp_parse_url( str_replace(["\r", "\n"], '', $single_url) );

          if ($parsed_url && isset($parsed_url['path'])) {

            $uri = $parsed_url['path'];

            // Force trailing slash
            if (strlen($uri) > 1 && $uri[strlen($uri) - 1] !== '/' && $uri[strlen($uri) - 1] !== '*') {
              $uri .= '/';
            }

            if (isset($parsed_url['query'])) {
              $uri .= "?{$parsed_url['query']}";
            }

            if (!in_array($uri, $excluded_urls)) {
              $excluded_urls[] = $uri;
            }
          }
        }

        if (!empty($excluded_urls)) {
          $this->main_instance->set_single_config('cf_excluded_urls', $excluded_urls);
        } else {
          $this->main_instance->set_single_config('cf_excluded_urls', []);
        }
      }

      // Custom Post Types (CPT) to exclude from cache
      if (isset($_POST['bs_cache_cf_excluded_post_types'])) {

        $excluded_post_types = [];
        $excluded_post_types_received = sanitize_textarea_field($_POST['bs_cache_cf_excluded_post_types']);

        if (strlen(trim($excluded_post_types_received)) > 0) {
          $excluded_post_types_received .= "\n";
        }

        $parsed_excluded_post_types = explode("\n", $excluded_post_types_received);

        foreach ($parsed_excluded_post_types as $excluded_post_type) {

          if (trim($excluded_post_type) == '') continue;

          $excluded_post_types[] = str_replace(["\r", "\n"], '', $excluded_post_type);
        }

        if (!empty($excluded_post_types)) {
          $this->main_instance->set_single_config('cf_excluded_post_types', $excluded_post_types);
        } else {
          $this->main_instance->set_single_config('cf_excluded_post_types', []);
        }
      }

      // Purge cache URL secret key
      if (isset($_POST['bs_cache_cf_purge_url_secret_key'])) {
        $this->main_instance->set_single_config( 'cf_purge_url_secret_key', trim( sanitize_text_field( $_POST['bs_cache_cf_purge_url_secret_key'] ) ) );
      }

      // Remove purge option from toolbar
      if (isset($_POST['bs_cache_cf_remove_purge_option_toolbar'])) {
        $this->main_instance->set_single_config('cf_remove_purge_option_toolbar', (int) sanitize_text_field($_POST['bs_cache_cf_remove_purge_option_toolbar']));
      }

      // Disable metabox from single post/page
      if (isset($_POST['bs_cache_cf_disable_single_metabox'])) {
        $this->main_instance->set_single_config('cf_disable_single_metabox', (int) sanitize_text_field($_POST['bs_cache_cf_disable_single_metabox']));
      }

      // Purge roles
      if (isset($_POST['bs_cache_purge_roles']) && is_array($_POST['bs_cache_purge_roles']) && !empty($_POST['bs_cache_purge_roles'])) {
        $this->main_instance->set_single_config('cf_purge_roles', $_POST['bs_cache_purge_roles']);
      } else {
        $this->main_instance->set_single_config('cf_purge_roles', []);
      }

      // Saving configurations
      $this->main_instance->update_config();
      $success_msg = __('Settings updated successfully', 'bigscoots-cache');
    }

    $cronjob_url = add_query_arg([
      'bscache-purge-all' => '1',
      'bscache-sec-key' => $this->main_instance->get_single_config('cf_purge_url_secret_key', wp_generate_password(20, false, false))
    ], site_url());

    $wordpress_menus = wp_get_nav_menus();
    $wordpress_roles = $this->main_instance->get_wordpress_roles();

    require_once BS_CACHE_PLUGIN_PATH . 'libs/views/settings.php';
  }

  public function export_config() : void
  {
    if (isset($_GET['bs_cache_export_config']) && isset($_GET['export_nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['export_nonce'] ) ), 'bs_cache_export_config_nonce') && current_user_can('manage_options')) {

      $config = $this->main_instance->export_config();
      $filename = 'bs_cache_config.json';

      header('Content-Description: File Transfer');
      header('Content-Type: application/octet-stream');
      header("Content-Disposition: attachment; filename={$filename}");
      header('Content-Transfer-Encoding: binary');
      header('Connection: Keep-Alive');
      header('Expires: 0');
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header('Pragma: public');
      header('Content-Length: ' . strlen($config));

      exit($config);
    }
  }

  public function add_bs_cache_debug_info(array $args) : array
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
          $environment = $this->main_instance->get_environment_type();

          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [ // Unique key for each field
            // The bel of the field
            'label'   =>  esc_html($things_to_show[$i]),
            // The value of the field (string, integer, float or array)
            'value'   =>  $environment,
            // Additional field info that should be added to the copy/paste text. Otherwise false.
            'debug'   =>  $environment,
            // Set this to true if the value should not be added to copy/paste functionality.
            // You should hide product key and sensitive info. Basically anything that would be bad if
            // it ended up in a public forum.
            'private' =>  false
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

          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [ // Unique key for each field
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $plugin_cache_status,
            'debug'   =>  $plugin_cache_status,
            'private' =>  false
          ];
        break;

        case 'Plugin Version':
          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $this->main_instance->get_current_plugin_version(),
            'debug'   =>  $this->main_instance->get_current_plugin_version(),
            'private' =>  false
          ];
        break;

        case 'Plugin Setup Mode':
          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $this->main_instance->get_plan_name(),
            'debug'   =>  $this->main_instance->get_plan_name(),
            'private' =>  false
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

          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $website_using_cloudflare,
            'debug'   =>  $website_using_cloudflare,
            'private' =>  false
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

          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $cloudflare_setup_mode,
            'debug'   =>  $cloudflare_setup_mode,
            'private' =>  false
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

          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $cache_rule_status,
            'debug'   =>  $cache_rule_status,
            'private' =>  false
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

          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $home_url_cache_status,
            'debug'   =>  $home_url_cache_status,
            'private' =>  false
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

          $command_data[ 'bs_cache_' . strtolower( str_replace( ' ', '_', $things_to_show[$i] ) ) ] = [
            'label'   =>  esc_html($things_to_show[$i]),
            'value'   =>  $test_page_url_cache_status,
            'debug'   =>  $test_page_url_cache_status,
            'private' =>  false
          ];
        break;
      }
    }

    // Organize all the data to show on the Site Health page
    $args['bigscoots-cache'] = [
      'label'       =>  esc_html__( 'BigScoots Cache', 'bigscoots-cache' ),
      'description' =>  esc_html__( 'BigScoots Cache plugin working status.', 'bigscoots-cache' ),
      'fields'      =>  $command_data
    ];

    // Return the data
    return $args;
  }
}