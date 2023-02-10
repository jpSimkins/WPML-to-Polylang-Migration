<?php

namespace WpmlToPolylangMigration\Processors;

// Deny direct access
if (!defined('ABSPATH')) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

/**
 * Abstract class for steppable processors.
 * Steppable is a paginated processor to handle processing large volumes
 */
abstract class AbstractProcessorSteppable {

    /**
     * Current step being processed.
     * @var int
     */
    protected int $step = 1;

    /**
     * Cache for the total.
     * @var ?int
     */
    protected ?int $total = NULL;

    /**
     * Returns the total number of items to process.
     * @return int
     */
    abstract protected function getTotal(): int;

    /**
     * Returns the data to process.
     * @return array
     */
    abstract protected function getData(): array;

    /**
     * Processes the data.
     * @param array $data
     * @return void
     */
    abstract protected function processData(array $data): void;

    /**
     * Process as steppable.
     * @return void
     */
    public function process(): void {
        // Do the processes in pages (steps).
        while ($data = $this->getData()) {
            $this->processData($data);
            $this->incrementStep(); // Do this last.
        }
    }

    /**
     * @return int
     */
    protected function getStep(): int {
        return $this->step;
    }

    /**
     * @return void
     */
    protected function incrementStep(): void {
        $this->step++;
    }

    /**
     * Returns the action completion percentage.
     * @return int
     */
    protected function getPercentage(): int {
        $percentage = 100.00;

        if ($this->total === NULL) {
            $this->total = $this->getTotal();
        }

        if ($this->total) {
            $percentage = ($this->getStep() * WPML_TO_POLYLANG_QUERY_BATCH_SIZE) / $this->total * 100;
            $percentage = round(floatval($percentage), 2);
        }

        return (int)min($percentage, 100.00);
    }

}
