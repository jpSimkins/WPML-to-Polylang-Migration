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
 * Handles string translations.
 */
class Strings extends AbstractProcessorSteppable {

    /**
     * Returns the number of WPML strings translations.
     * @return int
     */
    public function getTotal(): int {
        global $wpdb;

        return (int)$wpdb->get_var(
            sprintf("
                SELECT 
                    COUNT(1)
				FROM {$wpdb->prefix}icl_strings AS s
				    INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON (st.string_id = s.id)
				WHERE s.context NOT IN ( '%s' )
				",
                implode("', '", esc_sql($this->_getDomains()))
            )
        );
    }

    /**
     * Gets the WPML Strings translations.
     * @return array
     */
    public function getData(): array {
        global $wpdb;

        $offset = ($this->getStep() * WPML_TO_POLYLANG_QUERY_BATCH_SIZE) - WPML_TO_POLYLANG_QUERY_BATCH_SIZE;

        /**
         * WPML string translations.
         * @var \stdClass[]
         */
        $results = $wpdb->get_results(
            sprintf("
                SELECT 
                    s.value AS string, 
                    st.language, 
                    st.value AS translation
				FROM {$wpdb->prefix}icl_strings AS s
				    INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON (st.string_id = s.id)
				WHERE s.context NOT IN ( '%s' )
				LIMIT %d, %d",
                implode("', '", esc_sql($this->_getDomains())),
                absint($offset),
                absint(WPML_TO_POLYLANG_QUERY_BATCH_SIZE)
            )
        );

        $stringTranslations = [];

        // Order them in a convenient way.
        foreach ($results as $st) {
            if (!empty($st->string) & !empty($st->translation)) {
                $stringTranslations[$st->language][] = [$st->string, $st->translation];
            }
        }

        return $stringTranslations;
    }

    /**
     * Processes the string translations.
     * @param array $data
     * @return void
     */
    public function processData(array $data): void {
        Status::update(Status::STATUS_MIGRATING_STRING_TRANSLATIONS, $this->getPercentage());

        if (empty($data)) {
            return;
        }

        foreach ($data as $lang => $strings) {
            $language = Processor::getPolylangModel()->get_language($lang);

            if (empty($language)) {
                continue;
            }

            $mo = new \PLL_MO();
            $mo->import_from_db($language); // Import strings saved in a previous step.

            foreach ($strings as $msg) {
                $mo->add_entry($mo->make_entry($msg[0], $msg[1]));
            }

            $mo->export_to_db($language);
        }
    }

    /**
     * Returns mo files text domains stored by WPML.
     * @return string[]
     */
    private function _getDomains() {
        global $wpdb;

        if (!$wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}icl_mo_files_domains'")) {
            return ['']; // A trick to avoid an empty NOT IN in sql query.
        }

        $domains = $wpdb->get_col("SELECT DISTINCT domain FROM {$wpdb->prefix}icl_mo_files_domains");

        if (empty($domains)) {
            return [''];
        }

        return $domains;
    }
}