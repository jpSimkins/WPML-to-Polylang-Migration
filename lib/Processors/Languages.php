<?php

namespace WpmlToPolylangMigration\Processors;

use WpmlToPolylangMigration\Processor,
    WpmlToPolylangMigration\Status;

// Deny direct access
if (!defined('ABSPATH')) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

/**
 * Handles the language creation.
 */
class Languages {

    /**
     * Creates the languages.
     * @return void
     */
    public function process(): void {

        Status::update(Status::STATUS_MIGRATING_LANGUAGES);

        // Fetch WPML languages.
        $wpmlLanguages = $this->getWPMLLanguages();
        $wpmlLanguages = $this->orderLanguages($wpmlLanguages);

        // Create Polylang languages.
        $predefinedLanguages = include POLYLANG_DIR . '/settings/languages.php';
        foreach ($wpmlLanguages as $lang) {
            $lang['term_group']     = 0;
            $lang['no_default_cat'] = 1; // Prevent the creation of a new default category.

            // We need a flag and can be more exhaustive for the rtl languages list.
            $lang['rtl']  = isset($predefinedLanguages[$lang['locale']]['dir']) && 'rtl' === $predefinedLanguages[$lang['locale']]['dir'] ? 1 : 0;
            $lang['flag'] = isset($predefinedLanguages[$lang['locale']]['flag']) ? $predefinedLanguages[$lang['locale']]['flag'] : '';

            Processor::getPolylangModel()->add_language($lang);
        }

        $this->cleanup();
    }

    /**
     * Deletes the language and translation group of the default category to avoid a conflict later.
     * @return void
     */
    private function cleanup(): void {
        $termIds = \get_terms(
            [
                'taxonomy'   => 'term_translations',
                'hide_empty' => false,
                'fields'     => 'ids',
            ]
        );

        if (is_array($termIds)) {
            foreach ($termIds as $termId) {
                \wp_delete_term($termId, 'term_translations');
            }
        }

        $defaultCat = \get_option('default_category');
        if (is_numeric($defaultCat)) {
            \wp_delete_object_term_relationships((int)$defaultCat, 'term_language');
        }

        Processor::getPolylangModel()->clean_languages_cache(); // Update the languages list.
    }

    /**
     * Gets the list of WPML languages from the database.
     *
     * @return string[][] Ordered list of languages.
     *
     * @type string $slug Language code.
     * @type string $locale Locale.
     * @type string $name Native language name.
     */
    private function getWPMLLanguages() {
        global $wpdb;

        $_sql = "
            SELECT 
                l.code AS slug, 
                l.default_locale AS locale, 
                lt.name
            FROM {$wpdb->prefix}icl_languages AS l
                INNER JOIN {$wpdb->prefix}icl_languages_translations AS lt ON (l.code = lt.language_code)
            WHERE l.active = 1 
              AND lt.language_code = lt.display_language_code
        ";

        return $wpdb->get_results($_sql, ARRAY_A);
    }

    /**
     * Mimics how WPML orders the languages.
     *
     * @param string[][] $languages The list of WPML languages.
     * @return string[][] Ordered list of languages.
     *
     * @see SitePress::order_languages().
     */
    private function orderLanguages($languages): array {
        $orderedLanguages = [];

        $settings = Processor::getWpmlSettings();

        if (is_array($settings['languages_order'])) {
            foreach ($settings['languages_order'] as $code) {
                if (isset($languages[$code])) {
                    $orderedLanguages[$code] = $languages[$code];
                    unset($languages[$code]);
                }
            }
        }

        if (!empty($languages)) {
            foreach ($languages as $code => $lang) {
                $orderedLanguages[$code] = $lang;
            }
        }

        return $orderedLanguages;
    }
}
