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
 * Handles the migrations for translatable post types.
 */
class PostTypes {

    /**
     * Loops over the translated post types and processes each one.
     * @return void
     */
    public function process() {
        Status::update(Status::STATUS_MIGRATING_POST_TYPES_STARTED);

        $_translatedContentTypes = $this->_getTranslatedPostTypes();
        if (!empty($_translatedContentTypes)) {
            foreach ($_translatedContentTypes as $contentType) {
                (new PostType($contentType))->process();
            }
        }
    }

    /**
     * Gets the translated post types.
     * Don't use the Polylang settings here as these are not enough.
     * @return string[]
     */
    private function _getTranslatedPostTypes() {
        // Fixed post types that are required.
        $_types = ['post', 'page', 'wp_block'];

        // Fetch WPML settings for the import process.
        $_wpmlSettings = Processor::getWpmlSettings();

        // Make sure we have the data from WPML.
        if (is_array($_wpmlSettings['custom_posts_sync_option'])) {
            $_iclTypes = array_keys($_wpmlSettings['custom_posts_sync_option']);
            $_iclTypes = array_filter($_iclTypes, 'is_string');
            $_types    = array_merge($_types, $_iclTypes);
            $_types    = array_unique($_types); // Remove duplicates or this will cause errors.
        }

        // Remove any post types that shouldn't be here.
        $_types = array_diff($_types, ['wp_template']);

        return $_types;
    }
}
