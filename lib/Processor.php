<?php

namespace WpmlToPolylangMigration;

// Deny direct access
if (!defined('ABSPATH')) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

if (false === defined('WPML_TO_POLYLANG_SCRIPT_TIMEOUT_IN_SECONDS')) {
    define('WPML_TO_POLYLANG_SCRIPT_TIMEOUT_IN_SECONDS', 7200); // never use 0.
}
if (false === defined('WPML_TO_POLYLANG_QUERY_BATCH_SIZE')) {
    define('WPML_TO_POLYLANG_QUERY_BATCH_SIZE', 5000);
}

/**
 * Responsible for processing the actual import process from WPML to PolyLang.
 */
class Processor {

    /**
     * @var array
     */
    private static array $_wpmlSettings;

    /**
     * @return \PLL_Admin_Model
     */
    private static object $_polylangModel;

    /**
     * @var array
     */
    private static array $_polylangSettings;

    /**
     * Option name for WPML settings in the options table.
     */
    const OPTIONS_KEY_NAME_WPML = 'icl_sitepress_settings';

    /**
     * Option name for Polylang settings in the options table.
     */
    const OPTIONS_KEY_NAME_POLYLANG = 'polylang';

    /**
     * Constructor.
     */
    public function __construct() {
        self::$_wpmlSettings     = \get_option(self::OPTIONS_KEY_NAME_WPML);
        self::$_polylangModel    = PLL()->model;
        self::$_polylangSettings = \get_option(self::OPTIONS_KEY_NAME_POLYLANG) ?? [];

        // Sanity checks to ensure Polylang objects are available.
        if (empty(self::$_wpmlSettings) || empty(self::$_polylangModel) || !function_exists('PLL')) {
            Status::update(Status::STATUS_ERRORED);
            return;
        }

        // Flush since results will be cached as empty (since languages do not exist yet) from the user's visit on the Tools page.
        \wp_cache_flush();

        // Disable WP cache additions (prevents caching issues with migration)
        \wp_suspend_cache_addition(true);
    }

    /**
     * @return array
     */
    public static function getWpmlSettings(): array {
        return self::$_wpmlSettings;
    }

    /**
     * @return \PLL_Admin_Model
     */
    public static function getPolylangModel(): object {
        return self::$_polylangModel;
    }

    /**
     * @return array
     */
    public static function getPolylangSettings(): array {
        return self::$_polylangSettings;
    }

    /**
     * @param array $polylangSettings The full settings array for Polylang.
     */
    public static function setPolylangSettings(array $polylangSettings): void {
        self::$_polylangSettings = $polylangSettings;
        \update_option(self::OPTIONS_KEY_NAME_POLYLANG, $polylangSettings);

        // IMPORTANT: Options are cached in Polylang, we need to force them to the new settings (otherwise it will process with invalid option values).
        Processor::getPolylangModel()->options = $polylangSettings;
    }

    /**
     * Dispatches the different import steps.
     * @return void
     */
    public function run() {
        set_time_limit(WPML_TO_POLYLANG_SCRIPT_TIMEOUT_IN_SECONDS); // never use 0.

        if (empty(self::getPolylangModel()->get_languages_list())) {
            (new Processors\Languages())->process(); // Languages are REQUIRED fist as other processors require the language term ids.
        }
        (new Processors\Options())->process();
        (new Processors\PostTypes())->process();
        (new Processors\Taxonomies())->process();
        (new Processors\Menus())->process();
        (new Processors\NoLangObjects())->process();
        (new Processors\Strings())->process();

        // Complete the process,
        $this->complete();
    }

    /**
     * Completes the import process.
     * @return void
     */
    protected function complete() {
        // Re-enable WP cache additions
        \wp_suspend_cache_addition(false);

        // Force cache deletion.
        \wp_cache_flush();

        // Update language counts.
        /* @var $lang \PLL_Language */
        foreach (PLL()->model->get_languages_list() as $lang) {
            $lang->update_count(); // Doesn't seem to work... cache?
        }

        // Remove the Polylang wizard notice as it isn't needed or expected after import is complete.
        if (class_exists('PLL_Admin_Notices')) {
            \PLL_Admin_Notices::dismiss('wizard');
        }

        // Flush rewrite rules (WPML doesn't use them but Polylang does).
        \flush_rewrite_rules();

        // Flag as complete.
        Status::update(Status::STATUS_COMPLETED);
    }

}
