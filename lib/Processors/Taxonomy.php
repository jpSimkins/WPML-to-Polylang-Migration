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
 * Handles the single Taxonomy process.
 */
class Taxonomy extends AbstractContentType {

    /**
     * Cache for Polylang language taxonomy term ids
     * @var array|null
     */
    private static ?array $_polylangLanguageTaxonomyTermIds = NULL;

    /**
     * Constructor
     * @param string $taxonomy
     */
    public function __construct(string $taxonomy) {
        // Call the parent first.
        parent::__construct();

        // Configure the content type.
        $this->setContentType($taxonomy);
        $this->setPolylangTranslationTaxonomyName('term_translations');
        $this->setStatus(Status::STATUS_MIGRATING_TAXONOMY_PROCESSING);
        $this->setWpmlElementType('tax_' . $taxonomy);
    }

    /**
     * Returns the number of WPML translations for this post type.
     * @return int
     */
    protected function getTotal(): int {
        global $wpdb;

        $_sql = $wpdb->prepare(
            "
            SELECT 
                COUNT( DISTINCT( wpml.trid ) )
            FROM {$wpdb->term_taxonomy} AS tt
                INNER JOIN {$wpdb->prefix}icl_translations AS wpml ON (
                    wpml.element_id = tt.term_taxonomy_id
                    AND wpml.element_type = CONCAT( 'tax_', tt.taxonomy )
                )
            WHERE tt.taxonomy = %s
            ",
            $this->getContentType(),
        );

        return (int)$wpdb->get_var($_sql);
    }

    /**
     * Gets the WPML term translation ids for this content type.
     * @return int[]
     */
    protected function getData(): array {
        global $wpdb;

        $_sql = sprintf(
            "
                SELECT 
                    DISTINCT wpml.trid
				FROM {$wpdb->term_taxonomy} AS tt
				    INNER JOIN {$wpdb->prefix}icl_translations AS wpml ON (
                        wpml.element_id = tt.term_taxonomy_id
				        AND wpml.element_type = CONCAT( 'tax_', tt.taxonomy )
                    )
                WHERE tt.taxonomy = '%s'
				LIMIT %d, %d
            ",
            esc_sql($this->getContentType()),
            absint($this->getStep() * WPML_TO_POLYLANG_QUERY_BATCH_SIZE - WPML_TO_POLYLANG_QUERY_BATCH_SIZE),
            absint(WPML_TO_POLYLANG_QUERY_BATCH_SIZE)
        );

        $_trids = $wpdb->get_col($_sql);

        return array_map('absint', $_trids);
    }

    /**
     * Gets the WPML term translations for this content type.
     * @param int[] $trids WPML translation ids.
     * @return array
     */
    protected function getWPMLTranslations(array $trids): array {
        global $wpdb;

        $_sql = sprintf(
            "
                SELECT 
                    DISTINCT tt.term_id AS id, 
                    wpml.language_code, 
                    wpml.trid
				FROM {$wpdb->term_taxonomy} AS tt
				    INNER JOIN {$wpdb->prefix}icl_translations AS wpml ON (
                        wpml.element_id = tt.term_taxonomy_id
				        AND wpml.element_type = CONCAT( 'tax_', tt.taxonomy )
                    )
                WHERE element_type = '%s'
                    AND wpml.trid IN ( %s )
				",
            esc_sql($this->getWpmlElementType()),
            implode(',', array_map('absint', $trids))
        );

        $results = $wpdb->get_results($_sql);

        $translations = [];

        // Remove empty ids and group translations by translation group.
        foreach ($results as $t) {
            if (!empty($t->trid) && !empty($t->language_code) && !empty($t->id)) {
                $translations['pll_wpml_' . $t->trid][$t->language_code] = (int)$t->id;
            }
        }

        return $translations;
    }

    /**
     * Gets the Polylang languages taxonomy term ids for this content type.
     * @return array
     */
    protected function getPolylangLanguageTaxonomyTermIds(): array {
        if (NULL === self::$_polylangLanguageTaxonomyTermIds) {
            self::$_polylangLanguageTaxonomyTermIds = [];
            foreach (Processor::getPolylangModel()->get_languages_list() as $lang) {
                self::$_polylangLanguageTaxonomyTermIds[$lang->slug] = $lang->tl_term_taxonomy_id; // NOTE: using $lang->tl_term_taxonomy_id.
            }
        }

        return self::$_polylangLanguageTaxonomyTermIds;
    }

}