<?php

namespace WpmlToPolylangMigration\Processors\Traits;

use WpmlToPolylangMigration\Processor,
    WpmlToPolylangMigration\Status;

// Deny direct access
if (!defined('ABSPATH')) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

/**
 * Common functionality for all content types.
 * Content types are: post types or taxonomies.
 */
trait TContentType {

    /**
     * The content type being processed.
     * @var string
     */
    private string $contentType;

    /**
     * The taxonomy name for the translation type.
     * @var string
     */
    private string $polylangTranslationTaxonomyName;

    /**
     * The status for processing this content type.
     * @var int
     */
    private int $status;

    /**
     * WPML's element type.
     * @var string
     */
    private string $wpmlElementType;

    /**
     * @return string
     */
    protected function getContentType(): string {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     * @return void
     */
    protected function setContentType(string $contentType): void {
        $this->contentType = $contentType;
    }

    /**
     * @param string $wpmlElementType
     */
    protected function setWpmlElementType(string $wpmlElementType): void {
        $this->wpmlElementType = $wpmlElementType;
    }

    /**
     * @return string
     */
    protected function getPolylangTranslationTaxonomyName(): string {
        return $this->polylangTranslationTaxonomyName;
    }

    /**
     * @return int
     */
    protected function getStatus(): int {
        return $this->status;
    }

    /**
     * @param int $status
     */
    protected function setStatus(int $status): void {
        $this->status = $status;
    }

    /**
     * @return string
     */
    protected function getWpmlElementType(): string {
        return $this->wpmlElementType;
    }

    /**
     * @param string $polylangTranslationTaxonomyName
     */
    protected function setPolylangTranslationTaxonomyName(string $polylangTranslationTaxonomyName): void {
        $this->polylangTranslationTaxonomyName = $polylangTranslationTaxonomyName;
    }

    /**
     * Processes the post type migration steppable (paginated).
     * @param array $data
     * @return void
     */
    protected function processData(array $data): void {
        Status::update($this->getStatus(), $this->getPercentage(), $this->getContentType());

        if (!empty($data)) {
            $this->_processTrids($data);

            // Free memory.
            $_trids = NULL;
            unset($_trids);
            time_nanosleep(0, 10000000);
        }
    }

    /**
     * Processes an array of trids.
     * @param array $trids
     * @return void
     */
    private function _processTrids(array $trids): void {
        $_translations = $this->getWPMLTranslations($trids);
        if (!empty($_translations)) {
            $this->_processLanguages($_translations);
            $this->_processTranslations($_translations);
        }
    }

    /**
     * Creates the relationship between the terms and languages.
     * @param array $translations WPML translations.
     * @return void
     */
    private function _processLanguages(array $translations): void {
        global $wpdb;

        $_relations = [];
        foreach ($translations as $t) {
            foreach ($t as $language_code => $id) {
                if (!empty($this->polylangLanguages[$language_code])) {
                    $_relations[] = sprintf('(%d, %d)', $id, $this->polylangLanguages[$language_code]);
                }
            }
        }
        $_relations = array_unique($_relations);

        if (!empty($_relations)) {
            $wpdb->query("INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES " . implode(',', $_relations));
        }
    }

    /**
     * Creates translation groups.
     * @param array $translations WPML translations.
     * @return void
     */
    private function _processTranslations(array $translations): void {
        global $wpdb;

        // Create translation terms.
        $_terms = [];
        foreach (array_keys($translations) as $name) {
            $_terms[] = $wpdb->prepare('(%s, %s)', $name, $name);
        }
        $_terms = array_unique($_terms);

        // Insert terms.
        if (!empty($_terms)) {
            $wpdb->query("INSERT INTO $wpdb->terms (slug, name) VALUES " . implode(',', $_terms));
        }
        // Terms are created, now we need to fetch them to use their IDs.

        // Get all terms with their term_id.
        $_terms = $wpdb->get_results(
            sprintf(
                "SELECT term_id, slug FROM $wpdb->terms WHERE slug IN ( '%s' )",
                implode("', '", esc_sql(array_keys($translations)))
            )
        );

        // Create translations for taxonomy terms.
        $_tts = [];
        foreach ($_terms as $term) {
            $_tts[] = $wpdb->prepare(
                '(%d, %s, %s, %d)',
                $term->term_id,
                $this->getPolylangTranslationTaxonomyName(),
                serialize($translations[$term->slug]),
                count($translations[$term->slug])
            );
        }
        $_tts = array_unique($_tts);

        // Insert term taxonomy part of terms.
        if (!empty($_tts)) {
            $wpdb->query("INSERT INTO $wpdb->term_taxonomy (term_id, taxonomy, description, count) VALUES " . implode(',', $_tts));
        }

        unset($_terms, $_tts); // Free some memory.

        // Get all terms with their term taxonomy id.
        $_terms = $wpdb->get_results(
            sprintf(
                "SELECT tt.term_taxonomy_id, t.slug FROM $wpdb->terms AS t
				INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = '%s'
				AND t.slug IN ( '%s' )",
                $this->getPolylangTranslationTaxonomyName(),
                implode("', '", esc_sql(array_keys($translations)))
            )
        );

        // Create term relationships.
        $_trs = [];
        if (is_array($_terms)) {
            foreach ($_terms as $term) {
                foreach ($translations[$term->slug] as $object_id) {
                    $_trs[] = sprintf('(%d, %d)', (int)$object_id, (int)$term->term_taxonomy_id);
                }
            }
        }
        $_trs = array_unique($_trs);

        // Insert term relationships.
        if (!empty($_trs)) {
            $wpdb->query("INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id) VALUES " . implode(',', $_trs)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
    }
}