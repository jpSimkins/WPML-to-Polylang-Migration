<?php
/**
 * WPML to Polylang Migration
 *
 * @package              wpml-to-polylang-migration
 *
 * @wordpress-plugin
 * Plugin name:          WPML to Polylang Migration
 * Description:          Import multilingual data from WPML into Polylang
 * Author:               Olympusat
 * Version:              1.0
 * Requires at least:    4.9
 * Requires PHP:         5.6
 * Text Domain:          wpml-to-polylang-migration
 * Domain Path:          /languages
 */

namespace WpmlToPolylangMigration;

// Deny direct access.
if (!defined('ABSPATH')) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

/**
 * A plugin to manage migrations from WPML to Polylang.
 */
class Plugin {

    public function __construct() {
        // PHP autoloader.
        spl_autoload_register(__CLASS__ . '::autoload');

        // WP integration.
        \register_deactivation_hook(plugin_basename(__FILE__), __CLASS__ . '::deactivation');
        \load_plugin_textdomain('wpml-to-polylang-migration', false, basename(dirname(__FILE__)) . '/languages'); // Plugin i18n.

        // Required to get the correct model for Polylang.
        \add_filter('pll_model', [$this, 'filterModel']);
    }

    /**
     * Uses PLL_Admin_Model to be able to create languages.
     * @return string
     */
    public function filterModel() {
        return 'PLL_Admin_Model';
    }

    /**
     * Initialize the plugin
     * @return void
     */
    public function init(): void {
        new Cron();
        new Tools_Page();
    }

    /**
     * Handles cleanup of deactivation.
     * @return void
     */
    public static function deactivation(): void {
        Cron::clear();
        Status::delete();
    }

    /**
     * Autoloads the plugin resources
     * @param string $class
     */
    public static function autoload(string $class): void {
        $class = ltrim($class, '\\');
        // Make sure we are in the current namespace
        if (strpos($class, __NAMESPACE__) !== 0) {
            return;
        }
        // Fix class
        $class = str_replace(__NAMESPACE__, '', $class);
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        // Use core files
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . ltrim($class, '/') . '.php';
        if (file_exists($path)) {
            require_once($path);
        }
    }
}

// Initialize the plugin
(new Plugin())->init();
