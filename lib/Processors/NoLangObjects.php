<?php

namespace WpmlToPolylangMigration\Processors;

use WpmlToPolylangMigration\Processor,
    WpmlToPolylangMigration\Status;

// Deny direct access
if (!defined('ABSPATH')) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

/**
 * Handles objects with no language.
 */
class NoLangObjects {

    /**
     * Processes objects with no language.
     * @return void
     */
    public function process() {
        Status::update(Status::STATUS_MIGRATING_OBJECTS_WITH_NO_LANGUAGE);

        $wpml_settings = Processor::getWpmlSettings();

        if (empty($wpml_settings)) {
            return; // Something's wrong.
        }

        // TODO make this steppable too. A bit more work and it's not really necessary but it's nice to have it.

        /**
         * BUG: Seems the count for the languages is not being updated
         * This seems to be a caching issue... cannot figure this out.
         * Works fine if you run this same code again or use Polylang's link: admin.php?page=mlang&pll_action=content-default-lang&noheader=true
         */

        // Use Polylang internals to do this for us.
        do {
            $_noLangData = Processor::getPolylangModel()->get_objects_with_no_lang(WPML_TO_POLYLANG_QUERY_BATCH_SIZE);

            if (!empty($_noLangData['posts'])) {
                Processor::getPolylangModel()->set_language_in_mass('post', $_noLangData['posts'], $wpml_settings['default_language']);
            }

            if (!empty($_noLangData['terms'])) {
                Processor::getPolylangModel()->set_language_in_mass('term', $_noLangData['terms'], $wpml_settings['default_language']);
            }
        } while (!empty($_noLangData));
    }
}