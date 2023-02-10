<?php

namespace WpmlToPolylangMigration\Processors;

use WpmlToPolylangMigration\Status;

// Deny direct access
if (!defined('ABSPATH')) {
    header("HTTP/1.0 404 Not Found");
    exit();
}

/**
 * Abstract Content Type Processor.
 */
abstract class AbstractContentType extends AbstractProcessorSteppable {
    use Traits\TContentType;

    /**
     * Cache for Polylang's language term taxonomies.
     * @var ?array
     */
    protected ?array $polylangLanguages = NULL;

    /**
     * Gets the WPML term translations for this content type.
     * @param int[] $trids WPML translation ids.
     * @return array
     */
    abstract protected function getWPMLTranslations(array $trids): array;

    /**
     * Gets the Polylang languages taxonomy term ids for this content type.
     * @return array
     */
    abstract protected function getPolylangLanguageTaxonomyTermIds(): array;


    public function __construct() {
        if ($this->polylangLanguages === NULL) {
            $this->polylangLanguages = $this->getPolylangLanguageTaxonomyTermIds();
        }
    }

    /**
     * Content type specific process.
     * @return void
     */
    public function process(): void {
        // Make sure we have languages.
        if (empty($this->polylangLanguages)) {
            Status::update(Status::STATUS_ERRORED);
            return;
        }

        // MUST call parent process.
        parent::process();
    }
}