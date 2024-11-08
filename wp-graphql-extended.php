<?php
/**
 * Plugin Name: WP GraphQL Extended
 * Plugin URI: https://github.com/istogram/wp-graphql-extended
 * Description: Extended GraphQL functionality for WordPress with JWT authentication, SEO framework integration, pagination, and debugging
 * Version: 1.0.0
 * Author: istogram
 * Author URI: https://istogram.com
 * License: MIT
 * Text Domain: wp-graphql-extended
 * Requires PHP: 7.4
 * Requires at least: 5.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_GRAPHQL_EXTENDED_VERSION', '1.0.0');
define('WP_GRAPHQL_EXTENDED_PATH', plugin_dir_path(__FILE__));
define('WP_GRAPHQL_EXTENDED_URL', plugin_dir_url(__FILE__));

// Function to find the correct autoloader
function wp_graphql_extended_find_autoloader() {
    // Get the actual filesystem path of the plugin directory
    $plugin_real_path = realpath(WP_GRAPHQL_EXTENDED_PATH);
    
    // In development, determine Bedrock root from WordPress ABSPATH
    $wp_path = realpath(ABSPATH);
    $bedrock_root = preg_replace('#/web/wp/?$#', '', $wp_path);
    
    // Possible autoloader locations
    $possible_paths = [
        // Bedrock's vendor directory (main project)
        $bedrock_root . '/vendor/autoload.php',
        
        // Plugin's own vendor directory
        $plugin_real_path . '/vendor/autoload.php',
        
        // Standard WordPress locations
        defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR . '/vendor/autoload.php' : null,
        defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR . '/vendor/autoload.php' : null
    ];

    // Find the first existing autoloader
    foreach ($possible_paths as $path) {
        if ($path && file_exists($path)) {
            return $path;
        }
    }

    return false;
}

// Try to load the autoloader
$autoloader = wp_graphql_extended_find_autoloader();

if (!$autoloader) {
    // Only show admin notice if we're in the admin area
    if (is_admin()) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('WordPress GraphQL Extended requires Composer autoloader. Please ensure the plugin is properly installed via Composer.', 'wp-graphql-extended'); ?></p>
                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                    <p><?php _e('Plugin path: ' . WP_GRAPHQL_EXTENDED_PATH, 'wp-graphql-extended'); ?></p>
                <?php endif; ?>
            </div>
            <?php
        });
    }
    return;
}

// Load the autoloader
require_once $autoloader;

// Initialize the plugin
add_action('plugins_loaded', function() {
    // Check if WPGraphQL is active
    if (!class_exists('WPGraphQL')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('WordPress GraphQL Extended requires WPGraphQL plugin to be installed and activated.', 'wp-graphql-extended'); ?></p>
            </div>
            <?php
        });
        return;
    }

    try {
        // Initialize plugin
        new \Istogram\GraphQLExtended\Plugin();
    } catch (\Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WP GraphQL Extended initialization error: ' . $e->getMessage());
        }
        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Error initializing WordPress GraphQL Extended: ' . esc_html($e->getMessage()), 'wp-graphql-extended'); ?></p>
            </div>
            <?php
        });
    }
});