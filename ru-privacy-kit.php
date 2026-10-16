<?php
/**
 * Plugin Name: RU Privacy Kit
 * Plugin URI: https://ворд-пресс.рф/
 * Description: Русскоязычный мастер политик, cookie-баннер и управление согласием и аналитикой.
 * Version: 1.0.0-beta.9
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: ворд-пресс.рф
 * License: GPL-2.0-or-later
 * Update URI: https://xn----ctbgf6biedka.xn--p1ai/plugins/ru-privacy-kit/
 * Text Domain: ru-privacy-kit
 */
if (!defined('ABSPATH')) { exit; }
define('RUPK_VERSION', '1.0.0-beta.9');
define('RUPK_DIR', plugin_dir_path(__FILE__));
define('RUPK_URL', plugin_dir_url(__FILE__));
require_once RUPK_DIR . 'includes/class-rupk-core.php';
require_once RUPK_DIR . 'includes/class-rupk-documents.php';
require_once RUPK_DIR . 'includes/class-rupk-publisher.php';
require_once RUPK_DIR . 'includes/class-rupk-admin.php';
require_once RUPK_DIR . 'includes/class-rupk-front.php';
require_once RUPK_DIR . 'vendor/wpr-updates-client.php';
register_activation_hook(__FILE__, ['RUPK_Core', 'activate']);
register_deactivation_hook(__FILE__, ['RUPK_Core', 'deactivate']);
add_action('plugins_loaded', static function () {
    if (class_exists('WPR_Updates_Client_V2',false)) {
        WPR_Updates_Client_V2::register(__FILE__, ['slug'=>'ru-privacy-kit','server'=>'https://xn----ctbgf6biedka.xn--p1ai','telemetry'=>true]);
    }
    RUPK_Core::init();
    if (is_admin()) { RUPK_Admin::init(); }
    RUPK_Front::init();
});