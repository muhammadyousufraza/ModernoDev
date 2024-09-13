<?php
  $error_msg       = '';
  $show_settings   = '';
  $switch_counter  = 0;
  $tab_active      = isset($_REQUEST['bs_cache_tab']) ? $_REQUEST['bs_cache_tab'] : false;
  $wizard_active   = isset($wizard_active) ? $wizard_active : null;

  // Get the details about the current logged-in user
  $current_user = wp_get_current_user();

  // Get the plan name
  $current_plan_name = $this->main_instance->get_plan_name();
?>
<div class="wrap">
  <h1 style="display:none">BigScoots Cache Settings</h1>
  <div id="bs_cache_main_content" class="width_sidebar" data-cache_enabled="<?php echo esc_attr($this->main_instance->get_single_config('cf_cache_enabled', 0)); ?>">

    <div class="bs-cache-settings-header">
      <img width="217" height="46" loading="eager" fetchpriority="high" decoding="async" src="<?php echo esc_url(BS_CACHE_PLUGIN_URL . 'assets/img/bigscoots-logo.svg') ?>" class="bigscoots-logo" alt="BigScoots Logo" />
      <h2 class="bs-cache-settings-page-title">Cache</h2>
    </div>

    <div class="plugin-details-holder">
      <?php
        // Check if the site is currently using O2O
        $site_using_o2o = false;
        $dynamic_cache_url_resp_headers = $this->main_instance->get_response_header( home_url('/?nocache=1') );

        if ( isset($dynamic_cache_url_resp_headers['success']) && $dynamic_cache_url_resp_headers['success'] ) {
          $dynamic_cache_url_resp_headers = $dynamic_cache_url_resp_headers['headers'];

          if ( 
            is_array( $dynamic_cache_url_resp_headers ) &&
            array_key_exists( 'x-bigscoots-cache-mode', $dynamic_cache_url_resp_headers ) &&
            $dynamic_cache_url_resp_headers['x-bigscoots-cache-mode'] === 'O2O' 
          ) {
            $site_using_o2o = true;
          }
        }

        // Generate plan name holder class
        $plan_name_holder_class = 'has-plan-' . strtolower($current_plan_name);
      ?>
      <p class="<?php echo esc_attr( trim("plan-name {$plan_name_holder_class}") ); ?>">
        <?php
          echo esc_html($current_plan_name . ( $site_using_o2o ? ' (O2O Mode)' : '' ));
        ?>
      </p>

      <?php if ( $current_plan_name !== 'Misconfigured' ) : ?>
        <?php
          // Get the plugin working status and show it
          $plugin_status = '';
          $plugin_status_holder_class = '';

          if ( !$this->objects['cache_controller']->is_cache_enabled() ) {
            $plugin_status = 'Cache Disabled';
            $plugin_status_holder_class = 'has-cache-disabled';
          } elseif ( $this->objects['cache_controller']->is_page_cache_disabled() ) {
            $plugin_status = 'Page Cache Disabled (only <abbr title="Images, CSS, JavaScript etc.">Static Files</abbr> are cached)';
            $plugin_status_holder_class = 'has-page-cache-disabled';
          } else {
            $plugin_status = 'Cache Enabled';
            $plugin_status_holder_class = 'has-cache-enabled';
          }
        ?>
        <p class="<?php echo esc_attr("plugin-status {$plugin_status_holder_class}"); ?>"><?php echo $plugin_status; ?></p>
      <?php endif; ?>
    </div>

    <?php
      if (!file_exists($this->main_instance->get_plugin_wp_content_directory())) {
        add_settings_error(
          'bs_cache_messages',  // Slug to identify the message
          'bs_cache_message',   // Unique ID for the message
          sprintf('Unable to create the directory %s', $this->main_instance->get_plugin_wp_content_directory()), // The message text
          'error' // Type of message
        );
      }

      if ( strlen($error_msg) > 0 ) {
        add_settings_error(
          'bs_cache_messages',  // Slug to identify the message
          'bs_cache_message',   // Unique ID for the message
          sprintf('Error: %s', $error_msg), // The message text
          'error' // Type of message
        );
      }

      if ( !$wizard_active && strlen($success_msg) > 0 ) {
        add_settings_error(
          'bs_cache_messages',  // Slug to identify the message
          'bs_cache_message',   // Unique ID for the message
          $success_msg, // The message text
          'updated' // Type of message
        );
      }

      // Display settings errors/messages
      settings_errors('bs_cache_messages');
    ?>

    <?php if (!$this->objects['cache_controller']->is_cache_enabled()) : ?>

      <div class="step">

        <h2><?php echo esc_html_e('Enable Page Caching', 'bigscoots-cache'); ?></h2>

        <form action="" method="post" id="bs_cache_form_enable_cache">
          <p class="submit"><input type="submit" name="bs_cache_submit_enable_page_cache" class="button button-primary" value="<?php esc_attr_e('Enable Page Caching Now', 'bigscoots-cache'); ?>"></p>
        </form>

      </div>

    <?php else : ?>

      <div id="bs_cache_actions">

        <h2><?php esc_html_e('Cache Actions', 'bigscoots-cache'); ?></h2>

        <div class="bs-cache-plugin-main-actions">
          <button type="button" id="bs_cache_purge_cache_everything" name="bs_cache_purge_cache_everything" class="clear-cache-btn button button-secondary">
            <span class="big-text"><?php esc_html_e('Clear Cache', 'bigscoots-cache'); ?></span><br>
            <span class="small-text"><?php esc_html_e('Including Images and CSS/JS', 'bigscoots-cache') ?></span>
          </button>
          <button type="button" id="bs_cache_purge_user_given_urls" name="bs_cache_purge_user_given_urls" class="clear-cache-btn button button-primary">
            <span class="big-text"><?php esc_html_e('Clear Cache', 'bigscoots-cache'); ?></span><br>
            <span class="small-text"><?php esc_html_e('For Specific Files (Based on URL)', 'bigscoots-cache'); ?></span>
          </button>
        </div>

        <div class="bs-cache-plugin-sub-actions">
          <form action="" method="post" id="bs_cache_form_purge_opcache">
            <p class="submit">
              <button type="submit" name="bs_cache_submit_purge_opcache" class="button small-btn button-primary">
                <?php esc_html_e('Clear Opcache', 'bigscoots-cache'); ?>
              </button>
            </p>
          </form>

          <form action="" method="post" id="bs_cache_form_disable_cache">
            <p class="submit">
              <button type="submit" name="bs_cache_submit_disable_page_cache" class="button small-btn button-primary">
                <?php esc_html_e('Disable Caching', 'bigscoots-cache'); ?>
              </button>
            </p>
          </form>

          <form action="" method="post" id="bs_cache_form_test_cache">
            <p class="submit">
              <button type="submit" name="bs_cache_submit_test_cache" class="button small-btn button-primary">
                <?php esc_html_e('Test Cache', 'bigscoots-cache'); ?>
              </button>
            </p>
          </form>
        </div>

        <p class="help-article-info"><?php esc_html_e('Unsure of which option to choose?', 'bigscoots-cache'); ?> <a href="http://help.bigscoots.com/en/articles/7942918-bigscoots-cache-clearing-options" target="_blank" rel="nofollow"><?php esc_html_e('Check the help article', 'bigscoots-cache'); ?></a> <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/></svg></p>
      </div>

    <?php endif; ?>

    <?php if ($current_user instanceof \WP_User && $current_user->user_login !== 'bigscoots') : ?>
      <div class="plugin_support_note description_section highlighted">
        <h3><?php esc_html_e('Important Note', 'bigscoots-cache'); ?></h3>
        <p><?php esc_html_e('We strongly advise opening a support ticket with our team if you encounter any problems with our caching or require specific exclusions. Modifying these settings on your own is not recommended.', 'bigscoots-cache'); ?></p>
        <div class="plugin-support-note-btn-holder">
          <a href="https://wpo.bigscoots.com/user/tickets/open" target="_blank" class="button small-btn button-primary-solid" rel="nofollow"><?php esc_html_e('Open Support Ticket', 'bigscoots-cache'); ?></a>
          <?php if (current_user_can('manage_options')) : ?>
            <?php
              // Get the details about the user to see if they can view the plugin settings
              $show_settings = get_user_meta($current_user->ID, 'bs_cache_plugin_settings_visible', true);
            ?>
            <button type="button" id="toggle-bs-cache-settings" class="button small-btn button-danger" data-user_id="<?php echo esc_attr($current_user->ID); ?>" data-show_settings="<?php echo esc_attr($show_settings ?: 'false'); ?>">
              <?php echo esc_html($show_settings === 'true' ? 'Hide Plugin Settings' : 'Show Plugin Settings'); ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ( ($show_settings === 'true') || ($current_user instanceof \WP_User && $current_user->user_login === 'bigscoots') ) : ?>
      <h2 id="bs_cache_tab_links" class="nav-tab-wrapper">
        <a data-tab="general" class="nav-tab <?php if (!$tab_active || $tab_active == '' || $tab_active == 'general') echo esc_attr('nav-tab-active'); ?>"><?php esc_html_e('General', 'bigscoots-cache'); ?></a>
        <a data-tab="cache" class="nav-tab <?php if ($tab_active == 'cache') echo esc_attr('nav-tab-active'); ?>"><?php esc_html_e('Cache', 'bigscoots-cache'); ?></a>
        <a data-tab="thirdparty" class="nav-tab <?php if ($tab_active == 'thirdparty') echo esc_attr('nav-tab-active'); ?>"><?php esc_html_e('Third Party', 'bigscoots-cache'); ?></a>
        <a data-tab="advanced" class="nav-tab <?php if ($tab_active == 'advanced') echo esc_attr('nav-tab-active'); ?>"><?php esc_html_e('Advanced', 'bigscoots-cache'); ?></a>
      </h2>

      <form id="bs_cache_plugin_settings" method="post" action="">

        <?php wp_nonce_field('bs_cache_settings_nonce', 'bs_cache_settings_nonce'); ?>

        <!-- GENERAL TAB -->
        <div class="bs_cache_tab <?php if (!$tab_active || $tab_active == 'general') echo 'active'; ?>" id="general">

          <div class="main_section_header first_section">
            <h3><?php esc_html_e('BigScoots Cache General Settings', 'bigscoots-cache'); ?></h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Log mode', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Turn on this setting to enable logging of all BigScoots cache operations for troubleshooting and support.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" data-mainoption="logs" class="conditional_item" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_log_enabled" value="1" <?php if ($this->main_instance->get_single_config('log_enabled', 0) > 0) echo esc_attr(esc_attr('checked')); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Enabled', 'bigscoots-cache'); ?></label>
                <input type="radio" data-mainoption="logs" class="conditional_item" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_log_enabled" value="0" <?php if ($this->main_instance->get_single_config('log_enabled', 0) === 0) echo esc_attr(esc_attr('checked')); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('Disabled', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

        </div>

        <!-- CACHE TAB -->
        <div class="bs_cache_tab <?php if ($tab_active == 'cache') echo esc_attr('active'); ?>" id="cache">

          <!-- Cache TTL -->
          <div class="main_section_header first_section">
            <h3><?php esc_html_e('Cache lifetime settings', 'bigscoots-cache'); ?></h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('CDN Cache-Control max-age', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Don\'t touch if you don\'t know what is it. Must be greater than zero. Recommended 31536000 (1 year)', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <input type="number" name="bs_cache_maxage" min="0" max="31536000" step="1" value="<?php echo esc_attr($this->main_instance->get_single_config('cf_maxage', 31536000)); ?>" />
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Browser Cache-Control max-age', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Don\'t touch if you don\'t know what is it. Must be greater than zero. Recommended a value between 60 and 600', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <input type="number" name="bs_cache_browser_maxage" min="0" max="31536000" step="1" value="<?php echo esc_attr($this->main_instance->get_single_config('cf_browser_maxage', 60)); ?>" />
            </div>
            <div class="clear"></div>
          </div>


          <div class="main_section_header">
            <h3><?php esc_html_e('Cache behavior settings', 'bigscoots-cache'); ?></h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge the BigScoots cache when something changes on the website', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div><input type="checkbox" name="bs_cache_cf_auto_purge" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_auto_purge', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Purge cache for related pages only', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_auto_purge_all" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_auto_purge_all', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Purge whole cache', 'bigscoots-cache'); ?></strong></div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Don\'t cache the following dynamic contents:', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div><input type="checkbox" name="bs_cache_cf_bypass_single_post" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_single_post', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Single posts (is_single)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_pages" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_pages', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Pages (is_page)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_front_page" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_front_page', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Front Page (is_front_page)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_home" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_home', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Home (is_home)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_archives" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_archives', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Archives (is_archive)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_tags" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_tags', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Tags (is_tag)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_category" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_category', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Categories (is_category)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_feeds" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_feeds', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Feeds (is_feed)', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_search_pages" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_search_pages', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Search Pages (is_search)', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_author_pages" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_author_pages', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Author Pages (is_author)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_amp" value="1" <?php echo esc_html($this->main_instance->get_single_config('cf_bypass_amp', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('AMP pages', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_ajax" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_ajax', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Ajax requests', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_query_var" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_query_var', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Pages with query args', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_wp_json_rest" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_wp_json_rest', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('WP JSON endpoints', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_redirects" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_redirects', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Redirections done by WordPress (theme/plugins/WP core)', 'bigscoots-cache'); ?></div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Don\'t cache the following static contents:', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div><input type="checkbox" name="bs_cache_cf_bypass_sitemap" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_sitemap', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('XML sitemaps', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_file_robots" value="1" <?php echo esc_attr($this->main_instance->get_single_config('cf_bypass_file_robots', 0) > 0 ? esc_attr('checked') : ''); ?> /> <?php esc_html_e('Robots.txt', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Prevent the following URIs to be cached', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('One URI per line. You can use the * for wildcard URLs.', 'bigscoots-cache'); ?></div>
              <div class="description"><?php esc_html_e('Example', 'bigscoots-cache'); ?>: /my-page<br />/my-main-page/my-sub-page<br />/my-main-page*</div>
            </div>
            <div class="right_column">
              <textarea name="bs_cache_cf_excluded_urls" spellcheck="false"><?php echo esc_textarea( (is_array($this->main_instance->get_single_config('cf_excluded_urls', [])) && !empty($this->main_instance->get_single_config('cf_excluded_urls', []))) ? implode("\n", $this->main_instance->get_single_config('cf_excluded_urls', [])) : '' ); ?></textarea>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Prevent purging cache for the following Custom Post Types (CPT)', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('One custom post type per line.', 'bigscoots-cache'); ?></div>
              <div class="description"><?php esc_html_e('Example', 'bigscoots-cache'); ?>: attachment<br />shop_order<br />shop_coupon</div>
            </div>
            <div class="right_column">
              <textarea name="bs_cache_cf_excluded_post_types" spellcheck="false"><?php echo esc_textarea( (is_array($this->main_instance->get_single_config('cf_excluded_post_types', [])) && !empty($this->main_instance->get_single_config('cf_excluded_post_types', []))) ? implode("\n", $this->main_instance->get_single_config('cf_excluded_post_types', [])) : '' ); ?></textarea>
            </div>
            <div class="clear"></div>
          </div>

          <?php
            /** @disregard P1011 - Constant set by bash script in WP Config **/
            if ( $current_plan_name === 'Performance+' || ($current_plan_name === 'Standard' && defined('BS_SITE_CF_SUPPORT_PREFIX_PURGE') && BS_SITE_CF_SUPPORT_PREFIX_PURGE) ) : 
          ?>
            <div class="main_section">
              <div class="left_column">
                <label><?php esc_html_e('Enable Prefetch URLs to improve Cache HIT Ratio', 'bigscoots-cache'); ?></label>
                <div class="description"><?php esc_html_e('When this option is enabled all the page, post, category and products URLs will be prefetched by BigScoots Cache to improve your Cache HIT ratio.', 'bigscoots-cache'); ?></div>
              </div>
              <div class="right_column">

                <div class="switch-field">
                  <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_prefetch_urls" value="1" <?php if ($this->main_instance->get_single_config('cf_prefetch_urls', 0) > 0) echo esc_attr(esc_attr('checked')); ?> />
                  <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                  <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_prefetch_urls" value="0" <?php if ($this->main_instance->get_single_config('cf_prefetch_urls', 0) === 0) echo esc_attr(esc_attr('checked')); ?> />
                  <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
                </div>

              </div>
              <div class="clear"></div>
            </div>
          <?php endif; ?>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Strip response cookies on pages that should be cached', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('The CDN will not cache pages when there are cookies in responses unless you strip out them to overwrite the behavior.', 'bigscoots-cache'); ?></div>
              <div class="description"><?php esc_html_e('If the cache does not work due to response cookies and you are sure that these cookies are not essential for the website to works, enable this option.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">

              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_strip_cookies" value="1" <?php if ($this->main_instance->get_single_config('cf_strip_cookies', 0) > 0) echo esc_attr(esc_attr('checked')); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_strip_cookies" value="0" <?php if ($this->main_instance->get_single_config('cf_strip_cookies', 0) === 0) echo esc_attr(esc_attr('checked')); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>

            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge single post cache when a new comment is approved or deleted', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('When a new approved comment is added or deleted clear cache for the post page only.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" data-mainoption="cf_auto_purge_on_comments" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" class="conditional_item" name="bs_cache_cf_auto_purge_on_comments" value="1" <?php if ($this->main_instance->get_single_config('cf_auto_purge_on_comments', 0) > 0) echo esc_attr(esc_attr('checked')); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" data-mainoption="cf_auto_purge_on_comments" id="switch_<?php echo esc_attr($switch_counter); ?>_right" class="conditional_item" name="bs_cache_cf_auto_purge_on_comments" value="0" <?php if ($this->main_instance->get_single_config('cf_auto_purge_on_comments', 0) === 0) echo esc_attr(esc_attr('checked')); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section cf_auto_purge_on_comments">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge related pages when a new comment is approved or deleted', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('When a new approved comment is added or deleted clear cache for related pages (e.g. Home Page, Category, Taxonomy etc.).', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_auto_purge_related_pages_on_comments" value="1" <?php if ($this->main_instance->get_single_config('cf_auto_purge_related_pages_on_comments', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_auto_purge_related_pages_on_comments" value="0" <?php if ($this->main_instance->get_single_config('cf_auto_purge_related_pages_on_comments', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge the PHP OPcache when themes, plugins or WordPress core has been updated', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" data-mainoption="cf_auto_opcache_on_update" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" class="conditional_item" name="bs_cache_cf_auto_purge_opcache_on_upgrader_process_complete" value="1" <?php if ($this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" data-mainoption="cf_auto_opcache_on_update" id="switch_<?php echo esc_attr($switch_counter); ?>_right" class="conditional_item" name="bs_cache_cf_auto_purge_opcache_on_upgrader_process_complete" value="0" <?php if ($this->main_instance->get_single_config('cf_auto_purge_opcache_on_upgrader_process_complete', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section cf_auto_opcache_on_update">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge the BigScoots CDN cache when themes, plugins or WordPress core has been updated', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_auto_purge_on_upgrader_process_complete" value="1" <?php if ($this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_auto_purge_on_upgrader_process_complete" value="0" <?php if ($this->main_instance->get_single_config('cf_auto_purge_on_upgrader_process_complete', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Posts per page', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Enter how many posts per page (or category) the theme shows to your users. It will be use to clean up the pagination on cache purge.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <input type="number" name="bs_cache_post_per_page" min="1" step="1" value="<?php echo esc_html($this->main_instance->get_single_config('cf_post_per_page', 10)); ?>" />
            </div>
            <div class="clear"></div>
          </div>

        </div>

        <!-- THIRD PARTY TAB -->
        <div class="bs_cache_tab <?php if ($tab_active == 'thirdparty') echo 'active'; ?>" id="thirdparty">

          <!-- WooCommerce Options -->
          <div class="main_section_header first_section">
            <h3>
              <?php esc_html_e('WooCommerce settings', 'bigscoots-cache'); ?>

              <?php if (function_exists('is_plugin_active')) : ?>
                <?php if (is_plugin_active('woocommerce/woocommerce.php')) : ?>
                  <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
                <?php else : ?>
                  <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
                <?php endif; ?>
              <?php elseif ($this->objects['backend']->is_plugin_active_alternative('woocommerce/woocommerce.php')) : ?>
                <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
              <?php else : ?>
                <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
              <?php endif; ?>
            </h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Don\'t cache the following WooCommerce page types', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_cart_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_cart_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Cart (is_cart)', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_checkout_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_checkout_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Checkout (is_checkout)', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_checkout_pay_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_checkout_pay_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Checkout\'s pay page (is_checkout_pay_page)', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_product_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_product_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Product (is_product)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_shop_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_shop_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Shop (is_shop)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_product_tax_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_product_tax_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Product taxonomy (is_product_taxonomy)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_product_tag_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_product_tag_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Product tag (is_product_tag)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_product_cat_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_product_cat_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Product category (is_product_category)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_pages" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_pages', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('WooCommerce page (is_woocommerce)', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_woo_account_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_woo_account_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('My Account page (is_account)', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge cache for product page and related categories when a successful order has been placed', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('An order is deemed successful if it is placed correctly and its payment is confirmed. If the payment isn\'t confirmed, the order won\'t be considered successful.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_auto_purge_woo_product_page" value="1" <?php if ($this->main_instance->get_single_config('cf_auto_purge_woo_product_page', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_auto_purge_woo_product_page" value="0" <?php if ($this->main_instance->get_single_config('cf_auto_purge_woo_product_page', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge cache for scheduled sales', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_auto_purge_woo_scheduled_sales" value="1" <?php if ($this->main_instance->get_single_config('cf_auto_purge_woo_scheduled_sales', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_auto_purge_woo_scheduled_sales" value="0" <?php if ($this->main_instance->get_single_config('cf_auto_purge_woo_scheduled_sales', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Enable Cache Friendly WooCommerce Cookie Handler', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Turn this feature on if WooCommerce is adding unnecessary cookies that prevent webpages from loading quickly from cache, you can enable this feature to fix this. This feature checks if a visitor isn\'t logged in and hasn\'t added anything to their cart. If it finds unnecessary cookies set by WooCommerce in such cases, it removes them. This allows your webpages to load faster because they can be served from the cache.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_optimize_woo_cookie" value="1" <?php if ($this->main_instance->get_single_config('cf_optimize_woo_cookie', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_optimize_woo_cookie" value="0" <?php if ($this->main_instance->get_single_config('cf_optimize_woo_cookie', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>


          <!-- EDD Options -->
          <div class="main_section_header first_section">
            <h3>
              <?php esc_html_e('Easy Digital Downloads settings', 'bigscoots-cache'); ?>

              <?php if (function_exists('is_plugin_active')) : ?>
                <?php if (is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' )) : ?>
                  <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
                <?php else : ?>
                  <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
                <?php endif; ?>
              <?php elseif ($this->objects['backend']->is_plugin_active_alternative('easy-digital-downloads/easy-digital-downloads.php') || $this->objects['backend']->is_plugin_active_alternative('easy-digital-downloads-pro/easy-digital-downloads.php')) : ?>
                <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
              <?php else : ?>
                <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
              <?php endif; ?>
            </h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Don\'t cache the following EDD page types', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div><input type="checkbox" name="bs_cache_cf_bypass_edd_checkout_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_edd_checkout_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Primary checkout page', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_edd_purchase_history_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_edd_purchase_history_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Purchase history page', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_edd_login_redirect_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_edd_login_redirect_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Login redirect page', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_edd_success_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_edd_success_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Success page', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_bypass_edd_failure_page" value="1" <?php echo $this->main_instance->get_single_config('cf_bypass_edd_failure_page', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('Failure page', 'bigscoots-cache'); ?></div>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge cache when a payment is inserted into the database', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_auto_purge_edd_payment_add" value="1" <?php if ($this->main_instance->get_single_config('cf_auto_purge_edd_payment_add', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_auto_purge_edd_payment_add" value="0" <?php if ($this->main_instance->get_single_config('cf_auto_purge_edd_payment_add', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <!-- WP Recipe Maker Options -->
          <div class="main_section_header">
            <h3>
              <?php esc_html_e('WP Recipe Maker settings', 'bigscoots-cache'); ?>

              <?php if (function_exists('is_plugin_active')) : ?>
                <?php if (is_plugin_active('wp-recipe-maker/wp-recipe-maker.php')) : ?>
                  <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
                <?php else : ?>
                  <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
                <?php endif; ?>
              <?php elseif ($this->objects['backend']->is_plugin_active_alternative('wp-recipe-maker/wp-recipe-maker.php')) : ?>
                <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
              <?php else : ?>
                <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
              <?php endif; ?>
            </h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically clear the cache when WP Recipe Maker update it\'s data.', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_wprm_purge_on_cache_flush" value="1" <?php if ($this->main_instance->get_single_config('cf_wprm_purge_on_cache_flush', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_wprm_purge_on_cache_flush" value="0" <?php if ($this->main_instance->get_single_config('cf_wprm_purge_on_cache_flush', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <!-- Autoptimize Options -->
          <div class="main_section_header">
            <h3>
              <?php esc_html_e('Autoptimize settings', 'bigscoots-cache'); ?>

              <?php if (function_exists('is_plugin_active')) : ?>
                <?php if (is_plugin_active('autoptimize/autoptimize.php')) : ?>
                  <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
                <?php else : ?>
                  <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
                <?php endif; ?>
              <?php elseif ($this->objects['backend']->is_plugin_active_alternative('autoptimize/autoptimize.php')) : ?>
                <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
              <?php else : ?>
                <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
              <?php endif; ?>
            </h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge the cache when Autoptimize flushs its cache', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_autoptimize_purge_on_cache_flush" value="1" <?php if ($this->main_instance->get_single_config('cf_autoptimize_purge_on_cache_flush', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_autoptimize_purge_on_cache_flush" value="0" <?php if ($this->main_instance->get_single_config('cf_autoptimize_purge_on_cache_flush', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>


          <!-- WP Rocket Options -->
          <div class="main_section_header">
            <h3>
              <?php esc_html_e('WP Rocket settings', 'bigscoots-cache'); ?>

              <?php if (function_exists('is_plugin_active')) : ?>
                <?php if (is_plugin_active('wp-rocket/wp-rocket.php')) : ?>
                  <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
                <?php else : ?>
                  <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
                <?php endif; ?>
              <?php elseif ($this->objects['backend']->is_plugin_active_alternative('wp-rocket/wp-rocket.php')) : ?>
                <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
              <?php else : ?>
                <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
              <?php endif; ?>
            </h3>
          </div>

          <?php if (function_exists('is_plugin_active')) : ?>
            <?php if (is_plugin_active('wp-rocket/wp-rocket.php')) : ?>
              <div class="description_section highlighted">
                <?php esc_html_e('It is strongly recommended to disable the page caching functions of other plugins.', 'bigscoots-cache'); ?>
              </div>
            <?php endif; ?>
          <?php elseif ($this->objects['backend']->is_plugin_active_alternative('wp-rocket/wp-rocket.php')) : ?>
            <div class="description_section highlighted">
              <?php esc_html_e('It is strongly recommended to disable the page caching functions of other plugins.', 'bigscoots-cache'); ?>
            </div>
          <?php endif; ?>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge the cache when', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div><input type="checkbox" name="bs_cache_cf_wp_rocket_purge_on_domain_flush" value="1" <?php echo $this->main_instance->get_single_config('cf_wp_rocket_purge_on_domain_flush', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('WP Rocket flushs all caches', 'bigscoots-cache'); ?></div>
              <div><input type="checkbox" name="bs_cache_cf_wp_rocket_purge_on_rucss_job_complete" value="1" <?php echo $this->main_instance->get_single_config('cf_wp_rocket_purge_on_rucss_job_complete', 0) > 0 ? esc_attr('checked') : ''; ?> /> <?php esc_html_e('RUCSS generation process ends', 'bigscoots-cache'); ?> - <strong><?php esc_html_e('(recommended)', 'bigscoots-cache'); ?></strong></div>
            </div>
            <div class="clear"></div>
          </div>

          <!-- WP Asset Clean Up Options -->
          <div class="main_section_header">
            <h3>
              <?php esc_html_e('WP Asset Clean Up settings', 'bigscoots-cache'); ?>

              <?php if (function_exists('is_plugin_active')) : ?>
                <?php if (is_plugin_active('wp-asset-clean-up/wpacu.php') || is_plugin_active('wp-asset-clean-up-pro/wpacu.php')) : ?>
                  <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
                <?php else : ?>
                  <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
                <?php endif; ?>
              <?php elseif ($this->objects['backend']->is_plugin_active_alternative('wp-asset-clean-up/wpacu.php') || $this->objects['backend']->is_plugin_active_alternative('wp-asset-clean-up-pro/wpacu.php')) : ?>
                <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
              <?php else : ?>
                <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
              <?php endif; ?>
            </h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge the cache when WP Asset Clean Up flushs its own cache', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_wpacu_purge_on_cache_flush" value="1" <?php if ($this->main_instance->get_single_config('cf_wpacu_purge_on_cache_flush', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_wpacu_purge_on_cache_flush" value="0" <?php if ($this->main_instance->get_single_config('cf_wpacu_purge_on_cache_flush', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>


          <!-- YASR Options -->
          <div class="main_section_header">
            <h3>
              <?php esc_html_e('Yet Another Stars Rating settings', 'bigscoots-cache'); ?>

              <?php if (function_exists('is_plugin_active')) : ?>
                <?php if (is_plugin_active('yet-another-stars-rating/yet-another-stars-rating.php') || is_plugin_active('yet-another-stars-rating-premium/yet-another-stars-rating.php')) : ?>
                  <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
                <?php else : ?>
                  <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
                <?php endif; ?>
              <?php elseif ($this->objects['backend']->is_plugin_active_alternative('yet-another-stars-rating/yet-another-stars-rating.php') || $this->objects['backend']->is_plugin_active_alternative('yet-another-stars-rating-premium/yet-another-stars-rating.php')) : ?>
                <span class="bs_cache_plugin_active"><?php esc_html_e('Active plugin', 'bigscoots-cache'); ?></span>
              <?php else : ?>
                <span class="bs_cache_plugin_inactive"><?php esc_html_e('Inactive plugin', 'bigscoots-cache'); ?></span>
              <?php endif; ?>
            </h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Automatically purge the page cache when a visitor votes', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_yasr_purge_on_rating" value="1" <?php if ($this->main_instance->get_single_config('cf_yasr_purge_on_rating', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_yasr_purge_on_rating" value="0" <?php if ($this->main_instance->get_single_config('cf_yasr_purge_on_rating', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

        </div>


        <!-- ADVANCED TAB -->
        <div class="bs_cache_tab <?php if ($tab_active == 'advanced') echo esc_attr('active'); ?>" id="advanced">

          <!-- Logs -->
          <div class="main_section_header first_section logs">
            <h3><?php esc_html_e('Logs', 'bigscoots-cache'); ?></h3>
          </div>

          <div class="main_section logs">
            <div class="left_column">
              <label><?php esc_html_e('Clear logs manually', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Delete all the logs currently stored and optimize the log table.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <button type="button" id="bs_cache_clear_logs" class="button button-primary"><?php esc_html_e('Clear logs now', 'bigscoots-cache'); ?></button>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section logs">
            <div class="left_column">
              <label><?php esc_html_e('Download logs', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <a href="<?php echo esc_url(add_query_arg(['bs_cache_download_log' => 1, 'download_nonce' =>  wp_create_nonce('bs_cache_download_log_nonce')], admin_url())); ?>" target="_blank">
                <button type="button" class="button button-primary"><?php esc_html_e('Download log file', 'bigscoots-cache'); ?></button>
              </a>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section logs">
            <div class="left_column">
              <label><?php esc_html_e('Max log file size in MB', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Automatically reset the log file when it exceeded the max file size. Set 0 to never reset it.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <input type="number" name="bs_cache_log_max_file_size" min="1" step="1" value="<?php echo esc_attr($this->main_instance->get_single_config('log_max_file_size', 2)); ?>" />
            </div>
            <div class="clear"></div>
          </div>


          <div class="main_section logs">
            <div class="left_column">
              <label><?php esc_html_e('Log verbosity', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <select name="bs_cache_log_verbosity">
                <option value="<?php echo esc_attr(BS_CACHE_LOGS_STANDARD_VERBOSITY); ?>" <?php if ($this->main_instance->get_single_config('log_verbosity', BS_CACHE_LOGS_STANDARD_VERBOSITY) === BS_CACHE_LOGS_STANDARD_VERBOSITY) echo 'selected'; ?>><?php esc_html_e('Standard', 'bigscoots-cache'); ?></option>
                <option value="<?php echo esc_attr(BS_CACHE_LOGS_HIGH_VERBOSITY); ?>" <?php if ($this->main_instance->get_single_config('log_verbosity', BS_CACHE_LOGS_STANDARD_VERBOSITY) === BS_CACHE_LOGS_HIGH_VERBOSITY) echo 'selected'; ?>><?php esc_html_e('High', 'bigscoots-cache'); ?></option>
              </select>
            </div>
            <div class="clear"></div>
          </div>


          <!-- Import/Export settings -->
          <div class="main_section_header">
            <h3><?php esc_html_e('Import/Export', 'bigscoots-cache'); ?></h3>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Export config file', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <a href="<?php echo esc_url(add_query_arg(['bs_cache_export_config' => 1, 'export_nonce' =>  wp_create_nonce('bs_cache_export_config_nonce')], admin_url())); ?>" target="_blank">
                <button type="button" class="button button-primary"><?php esc_html_e('Export', 'bigscoots-cache'); ?></button>
              </a>
            </div>
            <div class="clear"></div>
          </div>

          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Import config file', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Import the options of the previously exported configuration file.', 'bigscoots-cache'); ?></div>
              <br />
              <div class="description"><?php esc_html_e('<strong>Read here:</strong> after the import you will be forced to manually enable BigScoots Cache again.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <textarea name="bs_cache_import_config" id="bs_cache_import_config_content" placeholder="<?php esc_html_e('Copy and paste here the content of the bs_cache_config.json file', 'bigscoots-cache'); ?>" spellcheck="false"></textarea>
              <button type="button" id="bs_cache_import_config_start" class="button button-primary"><?php esc_html_e('Import', 'bigscoots-cache'); ?></button>
            </div>
            <div class="clear"></div>
          </div>

          <!-- Other settings -->
          <div class="main_section_header">
            <h3><?php esc_html_e('Other settings', 'bigscoots-cache'); ?></h3>
          </div>

          <!-- Clear cache cronjob -->
          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Purge the whole BigScoots cache via Cronjob', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <p><?php esc_html_e('If you want purge the whole BigScoots cache at specific intervals decided by you, you can create a cronjob that hits the following URL', 'bigscoots-cache'); ?>:</p>
              <p><strong><?php echo esc_url($cronjob_url); ?></strong></p>
            </div>
            <div class="clear"></div>
          </div>

          <!-- Purge cache URL secret key -->
          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Purge cache URL secret key', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Secret key to use to purge the whole BigScoots cache via URL. Don\'t touch if you don\'t know how to use it.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <input type="text" name="bs_cache_cf_purge_url_secret_key" value="<?php echo esc_attr( $this->main_instance->get_single_config('cf_purge_url_secret_key', wp_generate_password(20, false, false)) ); ?>" readonly />
            </div>
            <div class="clear"></div>
          </div>

          <!-- Remove purge option from the top toolbar -->
          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Remove purge option from toolbar', 'bigscoots-cache'); ?></label>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_remove_purge_option_toolbar" value="1" <?php if ($this->main_instance->get_single_config('cf_remove_purge_option_toolbar', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_remove_purge_option_toolbar" value="0" <?php if ($this->main_instance->get_single_config('cf_remove_purge_option_toolbar', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <!-- Disable cache metabox -->
          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Disable metaboxes on single pages and posts', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('If enabled, a metabox is displayed for each post type by allowing you to exclude specific pages/posts from the cache.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_disable_single_metabox" value="1" <?php if ($this->main_instance->get_single_config('cf_disable_single_metabox', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_disable_single_metabox" value="0" <?php if ($this->main_instance->get_single_config('cf_disable_single_metabox', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

          <!-- User role allowed to clear cache -->
          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Select user roles allowed to purge the cache', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Admins are always allowed.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <?php if (is_array($wordpress_roles) && !empty($wordpress_roles)) : foreach ($wordpress_roles as $single_role_name) : if ($single_role_name == 'administrator') continue; ?>
                  <div><input type="checkbox" name="bs_cache_purge_roles[]" value="<?php echo esc_attr($single_role_name); ?>" <?php echo in_array($single_role_name, $this->main_instance->get_single_config('cf_purge_roles', [])) ? esc_attr('checked') : ''; ?> /> <?php echo esc_html($single_role_name); ?></div>
              <?php endforeach;
              endif; ?>
            </div>
            <div class="clear"></div>
          </div>

          <!-- Prerender on Mouse Hover (Speculation Rules or instant.page library) -->
          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Auto prerender/prefetch URLs on mouse hover', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('If enabled, it prerender (if browser support Speculation Rules) or preloads a page right before a user clicks on it. If the browser doesn\'t support Speculation Rules, it will instant.page library for just-in-time preloading.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_cf_prefetch_urls_on_hover" value="1" <?php if ($this->main_instance->get_single_config('cf_prefetch_urls_on_hover', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_cf_prefetch_urls_on_hover" value="0" <?php if ($this->main_instance->get_single_config('cf_prefetch_urls_on_hover', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>

              <br />
              <div class="description highlighted"><?php esc_html_e('Purge the cache and wait about 30 seconds after enabling/disabling this option.', 'bigscoots-cache'); ?></div>
              <br />
            </div>
            <div class="clear"></div>
          </div>

          <!-- Keep settings stored on deactivation -->
          <div class="main_section">
            <div class="left_column">
              <label><?php esc_html_e('Keep settings on deactivation', 'bigscoots-cache'); ?></label>
              <div class="description"><?php esc_html_e('Keep settings on plugin deactivation.', 'bigscoots-cache'); ?></div>
            </div>
            <div class="right_column">
              <div class="switch-field">
                <input type="radio" id="switch_<?php echo esc_attr(++$switch_counter); ?>_left" name="bs_cache_keep_settings_on_deactivation" value="1" <?php if ($this->main_instance->get_single_config('keep_settings_on_deactivation', 0) > 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_left"><?php esc_html_e('Yes', 'bigscoots-cache'); ?></label>
                <input type="radio" id="switch_<?php echo esc_attr($switch_counter); ?>_right" name="bs_cache_keep_settings_on_deactivation" value="0" <?php if ($this->main_instance->get_single_config('keep_settings_on_deactivation', 0) === 0) echo esc_attr('checked'); ?> />
                <label for="switch_<?php echo esc_attr($switch_counter); ?>_right"><?php esc_html_e('No', 'bigscoots-cache'); ?></label>
              </div>
            </div>
            <div class="clear"></div>
          </div>

        </div>

        <input type="hidden" name="bs_cache_tab" value="" />

        <p class="submit"><input type="submit" name="bs_cache_submit_general" class="button button-primary" value="<?php esc_html_e('Update settings', 'bigscoots-cache'); ?>"></p>

      </form>
    <?php endif; ?>

  </div>
  <?php require_once BS_CACHE_PLUGIN_PATH . 'libs/views/sidebar.php'; ?>
</div>