<?php

namespace WpmlToPolylangMigration\Processors;

use WpmlToPolylangMigration\Processor;
use WpmlToPolylangMigration\Status;

// Deny direct access
if (!defined('ABSPATH')) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

/**
 * Handles migrating translatable menus.
 */
class Menus {

    /**
     * Processes the menus.
     * @return void
     */
    public function process() {
        Status::update(Status::STATUS_MIGRATING_MENUS);

        $_options = Processor::getPolylangSettings();

        $_theme        = \get_option('stylesheet');
        $_locations    = \get_nav_menu_locations();
        $_translations = $this->getWPMLTranslations();

        if (empty($_locations) || empty($_translations)) {
            return;
        }

        $_trLocations = [];

        // Associate translation ids to nav menu locations.
        foreach ($_locations as $location => $loc_menu_id) {
            if (empty($loc_menu_id)) {
                continue; // This eliminates our translated locations.
            }

            foreach ($_translations as $trid => $menus) {
                foreach ($menus as $menu_id) {
                    if ($menu_id === $loc_menu_id) {
                        $_trLocations[$trid] = $location;
                    }
                }
            }
        }

        // Build nav_menus option.
        foreach ($_translations as $trid => $menus) {
            if (isset($_trLocations[$trid])) {
                foreach ($menus as $lang => $menu_id) {
                    $_options['nav_menus'][$_theme][$_trLocations[$trid]][$lang] = $menu_id;
                }
            }
        }

        Processor::setPolylangSettings($_options);
    }

    /**
     * Gets the WPML menu translations.
     * @return int[][]
     */
    protected function getWPMLTranslations() {
        global $wpdb;

        $_results = $wpdb->get_results("
            SELECT 
                DISTINCT tt.term_id AS id,
                wpml.language_code,
                wpml.trid
			FROM {$wpdb->term_taxonomy} AS tt
			    INNER JOIN {$wpdb->prefix}icl_translations AS wpml ON (
			        wpml.element_id = tt.term_taxonomy_id
			        AND wpml.element_type = CONCAT('tax_', tt.taxonomy)
                )
			WHERE wpml.element_type = 'tax_nav_menu'
        ");

        $_translations = [];

        foreach ($_results as $mt) {
            if (!empty($mt->trid) && !empty($mt->language_code) && !empty($mt->id)) {
                $_translations[$mt->trid][$mt->language_code] = (int)$mt->id;
            }
        }

        return $_translations;
    }
}