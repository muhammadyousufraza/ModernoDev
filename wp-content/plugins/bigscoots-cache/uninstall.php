<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) wp_die(esc_html__('Permission denied', 'bigscoots-cache'));

global $wpdb;

delete_option('bs_cache_config');
delete_option('bs_cache_version');

// List of cache keys to remove on uninstall
$cache_keys_to_remove = [
  'purge_cache_on_theme_edit',
  'purge_cache_on_post_edit',
  'purge_cache_on_post_status_change',
  'purge_cache_on_comment_status_change',
  'purge_cache_when_comment_is_trashed',
  'purge_cache_when_comment_is_deleted',
  'purged_cache_on_theme_edit_done',
  'purged_cache_on_update_done',
  'purge_cache_rocket_rucss_front_page',
  'bs_cache_prefetch_manifest_file_exists',
  'opcache_status'
];

// Check if persistent object cache is being used by the website
$is_persistent_object_cache_enabled = wp_using_ext_object_cache() ?? false;

// Loop through the cache keys and remove them from object cache or transient based on what the site is using
foreach ($cache_keys_to_remove as $cache_keys) {
  if ($is_persistent_object_cache_enabled) {
    wp_cache_delete($cache_key, 'bigscoots-cache');
  } else {
    delete_transient($cache_keys);
  }
}

$parts = wp_parse_url(home_url());

if (file_exists(WP_CONTENT_DIR . "/bigscoots-cache/{$parts['host']}/debug.log")) {
  wp_delete_file(WP_CONTENT_DIR . "/bigscoots-cache/{$parts['host']}/debug.log");
}

$config_file_path = ABSPATH . 'wp-config.php';

$parts = wp_parse_url(home_url());
$plugin_storage_main_path = WP_CONTENT_DIR . '/bigscoots-cache/';
$plugin_storage_path = $plugin_storage_main_path . $parts['host'];

if (file_exists($config_file_path) && is_writable($config_file_path)) {

  // Get content of the config file.
  $config_file = explode("\n", file_get_contents($config_file_path));
  $config_file_count = count($config_file);
  $last_line = '';

  for ($i = 0; $i < $config_file_count; ++$i) {

    // Remove double empty line
    if ($i > 0 && trim($config_file[$i]) == '' && $last_line == '') {
      unset($config_file[$i]);
      continue;
    }

    $last_line = trim($config_file[$i]);

    if (!preg_match('/^define\(\s*\'([A-Z_]+)\',(.*)\)/', $config_file[$i], $match)) {
      continue;
    }

    if ('WP_CACHE' === $match[1] && strpos($config_file[$i], 'Added by BigScoots Cache') !== false) {
      unset($config_file[$i]);
      $last_line = '';
      continue;
    }
  }

  if (trim($config_file[$config_file_count - 1]) == '') {
    unset($config_file[$config_file_count - 1]);
  }

  // Insert the constant in wp-config.php file.
  $handle = @fopen($config_file_path, 'w');


  // Combine the modified content into a single string
  $modified_content = implode("\n", $config_file);

  foreach ($config_file as $line) {
    @fwrite($handle, $line . "\n");
  }

  @fclose($handle);
}

if (file_exists($plugin_storage_path)) {
  delete_directory_recursive($plugin_storage_path);
}

if (file_exists($plugin_storage_main_path) && is_directory_empty($plugin_storage_main_path)) {
  rmdir($plugin_storage_main_path);
}

function delete_directory_recursive(string $dir) : bool
{
  if (!class_exists('RecursiveDirectoryIterator') || !class_exists('RecursiveIteratorIterator')) {
    return false;
  }

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

function is_directory_empty(string $dir) : bool
{
  // Check if the directory exists
  if (!is_dir($dir)) {
    return false;
  }

  $handle = opendir($dir);

  while (false !== ($entry = readdir($handle))) {
    if ($entry != '.' && $entry != '..') {
      closedir($handle);
      return false;
    }
  }

  closedir($handle);

  return true;
}