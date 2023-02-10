<?php

namespace WpmlToPolylangMigration;

// Deny direct access
if (!defined('ABSPATH')) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

/**
 * A status controller for the import process.
 */
class Status {

    const IMPORT_STATUS_FLAG = 'wpml-to-polylang-migration-migration-status';

    // Import statuses (value is irrelevant, just needs to be unique).
    const STATUS_WAITING_ON_CRON = 0;
    const STATUS_COMPLETED = 100;
    const STATUS_ERRORED = 400;

    const STATUS_MIGRATING_LANGUAGES = 10;
    const STATUS_MIGRATING_OPTIONS = 15;
    const STATUS_MIGRATING_POST_TYPES_STARTED = 20;
    const STATUS_MIGRATING_POST_TYPE_PROCESSING = 25;
    const STATUS_MIGRATING_TAXONOMIES_STARTED = 30;
    const STATUS_MIGRATING_TAXONOMY_PROCESSING = 35;
    const STATUS_MIGRATING_MENUS = 40;
    const STATUS_MIGRATING_OBJECTS_WITH_NO_LANGUAGE = 45;
    const STATUS_MIGRATING_OBJECTS_WITH_NO_LANGUAGE_POSTS = 50;
    const STATUS_MIGRATING_OBJECTS_WITH_NO_LANGUAGE_TERMS = 55;
    const STATUS_MIGRATING_STRING_TRANSLATIONS = 60;

    /**
     * Update the import status flag.
     * @param int $status The status of the importer.
     * @param ?int $percentage Percentage complete, if applicable.
     * @param ?string $contentType The content type this belongs to, if applicable.
     * @return void
     */
    public static function update(int $status, ?int $percentage = NULL, ?string $contentType = NULL): void {
        $_data = json_encode([
            'status'      => $status,
            'percentage'  => $percentage,
            'contentType' => $contentType
        ]);

        \update_option(self::IMPORT_STATUS_FLAG, $_data, false);

        // Output for cron log.
        print('wpml-to-polylang-migration: ' . strip_tags(self::get_as_text($status, $percentage, $contentType))) . PHP_EOL;
    }

    /**
     * Returns the import status.
     * @return ?array
     */
    public static function get(): ?array {
        $_tmp = \get_option(self::IMPORT_STATUS_FLAG);

        if ($_tmp !== false) {
            $_tmp = (array)json_decode($_tmp);
            // Add human-readable message to payload.
            $_tmp['message'] = self::get_as_text($_tmp['status'], $_tmp['percentage'], $_tmp['contentType']);
        }

        return false !== $_tmp ? $_tmp : NULL;
    }

    /**
     * Deletes the status flag from the database.
     * @return bool
     */
    public static function delete(): bool {
        return \delete_option(self::IMPORT_STATUS_FLAG);
    }

    /**
     * Returns the string for the text to show the user for a status.
     * @param int $status The status of the importer.
     * @param ?int $percentage Percentage complete, if applicable.
     * @param ?string $contentType The content type this belongs to, if applicable.
     * @return ?string
     */
    public static function get_as_text(int $status, ?int $percentage = NULL, ?string $contentType = NULL): ?string {
        switch ($status) {
            case self::STATUS_WAITING_ON_CRON:
                $_string = \__('Waiting for cron to take over the request', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_COMPLETED:
                $_string = \__('Import from WPML to Polylang should have been successful!', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_ERRORED:
                $_string = \__('An error occurred during the import, please check you logs', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_LANGUAGES:
                $_string = \__('Processing languages', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_OPTIONS:
                $_string = \__('Processing options', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_POST_TYPES_STARTED:
                $_string = \__('Started processing post types', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_POST_TYPE_PROCESSING:
                $_string = sprintf(
                    \__('Processing post type: <code>%s</code> - <strong>%d%%</strong>', 'wpml-to-polylang-migration'),
                    $contentType,
                    $percentage
                );
                break;
            case self::STATUS_MIGRATING_TAXONOMIES_STARTED:
                $_string = \__('Started processing taxonomies', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_TAXONOMY_PROCESSING:
                $_string = sprintf(
                    \__('Processing taxonomy: <code>%s</code> - <strong>%d%%</strong>', 'wpml-to-polylang-migration'),
                    $contentType,
                    $percentage
                );
                break;
            case self::STATUS_MIGRATING_MENUS:
                $_string = \__('Processing menus', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_OBJECTS_WITH_NO_LANGUAGE:
                $_string = \__('Processing objects with no translations', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_OBJECTS_WITH_NO_LANGUAGE_POSTS:
                $_string = \__('Processing objects with no translations: posts', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_OBJECTS_WITH_NO_LANGUAGE_TERMS:
                $_string = \__('Processing objects with no translations: terms', 'wpml-to-polylang-migration');
                break;
            case self::STATUS_MIGRATING_STRING_TRANSLATIONS:
                $_string = sprintf(
                    \__('Processing string translations: <strong>%d%%</strong>', 'wpml-to-polylang-migration'),
                    $percentage
                );
                break;
            // Catch all.
            default:
                $_string = $status;
                break;
        }

        return $_string;
    }
}
