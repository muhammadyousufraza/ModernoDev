<?php
namespace BigScoots\Cache;

defined('ABSPATH') || wp_die('Cheatin&#8217; uh?');

class Logs
{
  private \BigScoots_Cache $main_instance;
  private bool $is_logging_enabled = false;
  private string $log_file_path = '';
  private int $verbosity = 1; // 1: standard, 2: high

  public function __construct($log_file_path, $logging_enabled, $max_file_size, $main_instance)
  {
    $this->log_file_path       = $log_file_path;
    $this->is_logging_enabled  = $logging_enabled;
    $this->main_instance       = $main_instance;

    // Reset log if it exceeded the max file size
    if ($max_file_size > 0 && file_exists($log_file_path) && (filesize($log_file_path) / 1024 / 1024) >= $max_file_size) {
      $this->reset_log();
    }

    $this->actions();
  }

  private function actions() : void
  {
    // Download logs
    add_action('init', [$this, 'download_logs']);
  }

  public function enable_logging() : void
  {
    $this->is_logging_enabled = true;
  }

  public function disable_logging() : void
  {
    $this->is_logging_enabled = false;
  }

  public function set_verbosity(int $verbosity) : void
  {
    if ($verbosity !== (int) BS_CACHE_LOGS_STANDARD_VERBOSITY && $verbosity !== (int) BS_CACHE_LOGS_HIGH_VERBOSITY) {
      $verbosity = (int) BS_CACHE_LOGS_STANDARD_VERBOSITY;
    }

    $this->verbosity = $verbosity;
  }

  public function get_verbosity() : int
  {
    return $this->verbosity;
  }

  public function add_log(string $identifier, string $message) : void
  {
    if ($this->is_logging_enabled && $this->log_file_path) {

      $log = sprintf('[%s] [%s] - %s', current_time('Y-m-d H:i:s', get_option('gmt_offset')), $identifier, $message) . PHP_EOL;

      error_log($log, 3, $this->log_file_path);
    }
  }

  public function reset_log() : void
  {
    if ($this->log_file_path) {
      file_put_contents($this->log_file_path, '');
    }
  }

  public function download_logs() : void
  {
    if (isset($_GET['bs_cache_download_log']) && isset($_GET['download_nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['download_nonce'] ) ), 'bs_cache_download_log_nonce') && file_exists($this->log_file_path) && current_user_can('manage_options')) {
      header('Content-Description: File Transfer');
      header('Content-Type: application/octet-stream');
      header('Content-Disposition: attachment; filename=debug.log');
      header('Content-Transfer-Encoding: binary');
      header('Connection: Keep-Alive');
      header('Expires: 0');
      header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0, s-maxage=0');
      header('Pragma: public');
      header('Content-Length: ' . filesize($this->log_file_path));
      readfile($this->log_file_path);
      exit;
    }
  }
}