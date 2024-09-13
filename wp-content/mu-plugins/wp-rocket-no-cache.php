<?php
/**
 * Plugin Name: WP Rocket | Disable Page Caching
 * Description: Disables WP Rocket’s page cache while preserving other optimization features.
 * Plugin URI:  https://github.com/wp-media/wp-rocket-helpers/tree/master/cache/wp-rocket-no-cache/
 * Author:      WP Rocket Support Team
 * Author URI:  http://wp-rocket.me/
 * License:     GNU General Public License v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright SAS WP MEDIA 2018
 */

namespace WP_Rocket\Helpers\cache\no_cache;

// Standard plugin security, keep this line in place.
defined('ABSPATH') or die();

/**
 * Disable page caching in WP Rocket.
 * BigScoots - e7iL48AQkzh
 * @link http://docs.wp-rocket.me/article/61-disable-page-caching
 */
add_filter('do_rocket_generate_caching_files', '__return_false');
add_filter('rocket_generate_advanced_cache_file', '__return_false');
add_filter('rocket_disable_htaccess', '__return_false');


namespace WP_Rocket\Helpers\static_files\preload\change_parameters;

defined('ABSPATH') or die();

function preload_batch_size($value)
{

  $value = 10;

  return $value;
}
add_filter('rocket_preload_cache_pending_jobs_cron_rows_count', __NAMESPACE__ . '\preload_batch_size');

function preload_cron_interval($interval)
{

  // change this value, default is 60 seconds:
  $interval = 120;

  return $interval;
}
add_filter('rocket_preload_pending_jobs_cron_interval', __NAMESPACE__ . '\preload_cron_interval');

function preload_requests_delay($delay_between)
{

  // Edit this value, change the number of seconds
  $seconds = 3;
  // finish editing

  // All done, don't change this part. 
  $delay_between = $seconds * 1000000;

  return $delay_between;
}
add_filter('rocket_preload_delay_between_requests', __NAMESPACE__ . '\preload_requests_delay');
