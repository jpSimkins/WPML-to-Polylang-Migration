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
 * Handles the migrations for translatable taxonomies.
 */
class Taxonomies {

    /**
     * Loops over the translated taxonomies and processes each one.
     * @return void
     */
    public function process() {
        Status::update(Status::STATUS_MIGRATING_TAXONOMIES_STARTED);

        $_translatedContentTypes = $this->_getTranslatedTaxonomies();
        if (!empty($_translatedContentTypes)) {
            foreach ($_translatedContentTypes as $contentType) {
                (new Taxonomy($contentType))->process();
            }
        }
    }

    /**
     * Gets the translated taxonomies.
     * Don't use the Polylang settings here as these are not enough.
     * @return array
     */
    protected function _getTranslatedTaxonomies(): array {
        $_taxonomies   = ['category', 'post_tag'];
        $_wpmlSettings = Processor::getWpmlSettings();

        if (is_array($_wpmlSettings['taxonomies_sync_option'])) {
            $iclTaxonomies = array_keys($_wpmlSettings['taxonomies_sync_option']);
            $iclTaxonomies = array_filter($iclTaxonomies, 'is_string');
            $_taxonomies   = array_merge($_taxonomies, $iclTaxonomies);
            $_taxonomies   = array_unique($_taxonomies); // Remove duplicates or this will cause errors
        }

        // Remove any post types that shouldn't be here.
        $_taxonomies = array_diff($_taxonomies, ['wp_theme', 'wp_template_part_area']);

        return $_taxonomies;
    }

}