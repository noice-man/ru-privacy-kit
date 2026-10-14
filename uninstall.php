<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
if (is_multisite()) { return; } // Multisite deletion is not supported in beta; avoid accidental network-wide erasure.
delete_option('rupk_config');
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}rupk_consent_log");
wp_clear_scheduled_hook('rupk_daily_cleanup');
// User-reviewed legal pages are deliberately never removed.