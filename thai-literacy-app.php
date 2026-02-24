<?php
/**
 * Plugin Name: Thai Literacy App
 * Description: Interactive Thai-reading literacy app for adult English speakers with configurable content and SRS tracking.
 * Version: 0.1.0
 * Author: Codex
 * Text Domain: thai-literacy-app
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TLA_PLUGIN_VERSION', '0.1.0');
define('TLA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TLA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TLA_PLUGIN_DIR . 'includes/class-thai-literacy-app.php';

function tla_run_plugin() {
    $plugin = new Thai_Literacy_App();
    $plugin->run();
}
add_action('plugins_loaded', 'tla_run_plugin');

register_activation_hook(__FILE__, ['Thai_Literacy_App', 'activate']);
