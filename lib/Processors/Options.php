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
 * Handles the options.
 */
class Options {

    /**
     * Processes the options.
     *
     * @return void
     */
    public function process() {
        Status::update(Status::STATUS_MIGRATING_OPTIONS);


        $wpml_settings = Processor::getWpmlSettings();

        if (!is_array($wpml_settings)) {
            return; // Something's wrong.
        }

        $options = Processor::getPolylangSettings();

        if (!is_array($options)) {
            $options = [];
        }

        $options['rewrite']       = 1; // Remove /language/ in permalinks.
        $options['hide_default']  = 1; // Remove URL language information for default language.
        $options['redirect_lang'] = 1; // Redirect the language page to the homepage.

        // Default language.
        $options['default_lang'] = $wpml_settings['default_language'];

        // Urls modifications.
        switch ($wpml_settings['language_negotiation_type']) {
            case 2:
                $options['force_lang'] = 3;
                break;
            case 1:
            case 3: // We do not support the language added as a parameter except for plain permalinks.
            default:
                $options['force_lang'] = 1;
                break;
        }

        // Domains.
        $options['domains'] = isset($wpml_settings['language_domains']) ? $wpml_settings['language_domains'] : [];

        // Post types.
        if (!empty($wpml_settings['custom_posts_sync_option'])) {
            $post_types = array_keys(array_filter($wpml_settings['custom_posts_sync_option']));
            $post_types = array_diff($post_types, \get_post_types(['_builtin' => true]));

            $options['post_types']    = $post_types;
            $options['media_support'] = (int)!empty($wpml_settings['custom_posts_sync_option']['attachment']);
        }

        // Clean the post_types cache since this was updated.
        Processor::getPolylangModel()->cache->clean('post_types');

        // Taxonomies.
        if (!empty($wpml_settings['taxonomies_sync_option'])) {
            $taxonomies = array_keys(array_filter($wpml_settings['taxonomies_sync_option']));
            $taxonomies = array_diff($taxonomies, \get_taxonomies(['_builtin' => true]));

            $options['taxonomies'] = $taxonomies;
        }

        // Clean the taxonomies cache since this was updated.
        Processor::getPolylangModel()->cache->clean('taxonomies');

        // Sync.
        $sync = [
            'sync_page_ordering'  => 'menu_order',
            'sync_page_parent'    => 'post_parent',
            'sync_page_template'  => '_wp_page_template',
            'sync_ping_status'    => 'ping_status',
            'sync_comment_status' => 'comment_status',
            'sync_sticky_flag'    => 'sticky_posts',
        ];

        $options['sync'] = [];
        foreach ($sync as $wpml_opt => $pll_opt) {
            if (!empty($wpml_settings[$wpml_opt])) {
                $options['sync'][] = $pll_opt;
            }
        }

        Processor::setPolylangSettings($options);

        // Default category in default language.
        \update_option('default_category', (int)$wpml_settings['default_categories'][$wpml_settings['default_language']]);
    }
}
