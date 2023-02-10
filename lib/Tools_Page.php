<?php

namespace WpmlToPolylangMigration;

// Deny direct access
if (!defined('ABSPATH')) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

/**
 * Builds the WPML Importer tools page.
 */
class Tools_Page {

    // AJAX hooks.
    const AJAX_ACTION_IMPORT = 'wpml-to-polylang-migration-importer';
    const AJAX_ACTION_STATUS_CHECK = 'wpml-to-polylang-migration-status-check';
    const AJAX_ACTION_FIX_LANG_TOTALS = 'wpml-to-polylang-migration-fix-lang-totals';

    // AJAX status check interval.
    const STATUS_CHECK_INTERVAL_IN_SECONDS = 5;

    public function __construct() {
        // Adds the link to the languages panel in the WordPress admin menu.
        add_action('admin_menu', array($this, 'add_menus'));

        // Use the correct pll_model (Polylang trick).
        add_filter('pll_model', [$this, 'pll_model']);

        // AJAX Hooks.
        add_action('wp_ajax_' . self::AJAX_ACTION_IMPORT, [$this, 'process_ajax_request_import',], 10, 0);
        add_action('wp_ajax_' . self::AJAX_ACTION_STATUS_CHECK, [$this, 'process_ajax_request_status',], 10, 0);
        add_action('wp_ajax_' . self::AJAX_ACTION_FIX_LANG_TOTALS, [$this, 'process_ajax_request_fix_lang_totals',], 10, 0);
    }

    /**
     * Uses PLL_Admin_Model to be able to create languages.
     * @return string
     */
    public function pll_model() {
        return 'PLL_Admin_Model';
    }

    /**
     * Adds the link to the tools panel in the WordPress admin menu.
     * @return void
     */
    public function add_menus() {
        $title = __('WPML to Polylang Migration', 'wpml-to-polylang-migration');

        add_submenu_page(
            'tools.php',
            $title,
            $title,
            'manage_options',
            'wpml-to-polylang-migration',
            array(
                $this,
                'tools_page',
            )
        );
    }

    /**
     * Displays the import page.
     * Processes the import action.
     * @return void
     */
    public function tools_page() {
        $_has_import_in_progress = Status::get() !== NULL;
        ?>
        <style>
            #wpml-to-polylang-migration-status {
                display: block;
            }
        </style>
        <div class="wrap">
            <h2> <?php esc_html_e('WPML to Polylang Migration', 'wpml-to-polylang-migration'); ?></h2>

            <?php
            global $sitepress, $wp_version;

            if ($_has_import_in_progress) :?>

                <style>
                    #wpml-to-polylang-migration-nag {
                        border: 1px solid #c3c4c7;
                        border-left-width: 4px;
                        border-left-color: #F70000;
                        border-right-width: 4px;
                        border-right-color: #F70000;
                        background-color: #FFCCCB;
                        box-shadow: 0 1px 1px rgb(0 0 0 / 4%);
                        margin: 5px 15px 2px;
                    }

                    #wpml-to-polylang-migration-nag p {
                        text-align: center;
                        font-size: 1.1em;
                        font-weight: bold;
                        margin: 10px 10px 6px;
                    }
                </style>
                <div id="wpml-to-polylang-migration-nag">
                    <p><?php _e('Import is already in progress.', 'wpml-to-polylang-migration'); ?></p>
                </div>
            <?php endif;

            // Checks.
            $min_wp_version  = '4.9';
            $min_pll_version = '2.8';
            $checks          = array();

            $checks['wp_version'] = array(
                /* translators: %s is the WordPress version */
                sprintf(__('You are using WordPress %s or later', 'wpml-to-polylang-migration'), $min_wp_version),
                version_compare($wp_version, $min_wp_version, '>=') ? 1 : 0,
            );

            $checks['wpml_installed'] = array(
                __('WPML is installed on this website', 'wpml-to-polylang-migration'),
                false !== get_option('icl_sitepress_settings') ? 1 : 0,
            );

            $checks['wpml_deactivated'] = array(
                __('WPML is deactivated', 'wpml-to-polylang-migration'),
                empty($sitepress) ? 1 : 0,
            );

            $checks['polylang_activated'] = array(
                /* translators: %s is the Polylang version */
                sprintf(__('Polylang %s or later is activated', 'wpml-to-polylang-migration'), $min_pll_version),
                defined('POLYLANG_VERSION') && version_compare(POLYLANG_VERSION, $min_pll_version, '>=') ? 1 : 0,
            );

            if ($checks['polylang_activated'][1]) {
                $checks['polylang_languages_created'] = array(
                    __('No language has been created with Polylang', 'wpml-to-polylang-migration'),
                    $GLOBALS['polylang']->model->get_languages_list() ? 0 : 1,
                );
            }

            ?>
            <div class="form-wrap">
                <form id="import" method="post" action="">
                    <input type="hidden" name="pll_action" value="import"/>
                    <table class="form-table">
                        <?php
                        // Output the checks statuses.
                        foreach ($checks as $checkKey => $checkData) {
                            printf(
                                '<tr><th style="width:300px">%s</th><td style="color:%s">%s</td></tr>',
                                esc_html($checkData[0]),
                                $checkData[1] ? 'green' : 'red',
                                esc_html($checkData[1] ? __('OK', 'wpml-to-polylang-migration') : __('KO', 'wpml-to-polylang-migration'))
                            );
                        }
                        ?>
                    </table>
                    <?php
                    // Allow for the migration to be triggered.
                    $attr = empty($deactivated) ? array() : array('disabled' => 'disabled');
                    submit_button(__('Import', 'wpml-to-polylang-migration'), 'primary', 'submit', true, $attr); // Since WP 3.1.
                    ?>
                </form>
            </div>
            <div id="wpml-to-polylang-migration-status"></div>
            <br/>

            <hr/>
            <div id="wpml-to-polylang-migration-tools">
                <h4><?php _e('Tools', 'wpml-to-polylang-migration'); ?></h4>
                <ul>
                    <li id="wpml-to-polylang-migration-tools-fix-lang-totals">
                        <div class="status"></div>
                        <p id="fix-lang-totals-cage" style="margin-left: 30px;">
                            <a href="javascript:void(0);"
                               id="fix-lang-totals"><?php _e('Fix language totals', 'wpml-to-polylang-migration'); ?></a>
                            &nbsp;-&nbsp;
                            <?php _e('Language totals will not be correct due to WP internals. Run this AFTER you complete the migration.', 'wpml-to-polylang-migration'); ?>
                        </p>
                    </li>
                </ul>
            </div>

            <script type="text/javascript">
                let button_submit = jQuery('#submit');
                let status_cage = jQuery('#wpml-to-polylang-migration-status');
                let has_import_in_progress = <?php echo ($_has_import_in_progress) ? 'true' : 'false'; ?>;

                // Check if we are already performing an import.
                if (has_import_in_progress) {
                    // Disable the submit button to prevent issues with multiple submissions.
                    button_submit.prop('disabled', true);
                    _buildStatusDetails();
                    _check_WPML_to_PolyLang_importer_status();
                }

                /**
                 * Hack to get the language totals to be updated properly...
                 */
                jQuery('a#fix-lang-totals').on("click", function (e) {
                    e.preventDefault(); // do not allow an actual submission.

                    jQuery.ajax({
                        "type": 'POST',
                        "url": ajaxurl,
                        "headers": {
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache',
                            'Expires': '0'
                        },
                        "cache": false,
                        "dataType": 'json',
                        "data": {
                            "action": "<?php echo esc_attr(self::AJAX_ACTION_FIX_LANG_TOTALS); ?>",
                            "_wpnonce": "<?php echo esc_attr(wp_create_nonce(self::AJAX_ACTION_FIX_LANG_TOTALS)); ?>",
                        },
                        "beforeSend": function () {
                            // Add spinner.
                            let _spinner = jQuery('<span>', {
                                    class: 'spinner',
                                })
                                    .css('visibility', 'visible')
                                    .css('display', 'inline-block')
                                    .css('position', 'absolute')
                                    .css('left', '-5px')
                                    .css('margin-top', '0')
                                    .css('vertical-align', 'middle')
                            ;
                            jQuery('li#wpml-to-polylang-migration-tools-fix-lang-totals div.status').empty().append(_spinner);

                        },
                        "success": function (json, textStatus, jqXHR) {
                            let _done = jQuery('<span>', {
                                    class: 'dashicons dashicons-yes',
                                })
                                    .css('color', 'green')
                                    .css('visibility', 'visible')
                                    .css('display', 'inline-block')
                                    .css('position', 'absolute')
                                    .css('left', '0')
                                    .css('vertical-align', 'middle')
                            ;
                            jQuery('li#wpml-to-polylang-migration-tools-fix-lang-totals div.status').empty().append(_done);
                        },
                    });
                });


                // AJAX sumission to trigger the import process.
                button_submit.on("click", function (e) {
                    e.preventDefault(); // do not allow an actual submission.

                    jQuery.ajax({
                        "type": 'POST',
                        "url": ajaxurl,
                        "headers": {
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache',
                            'Expires': '0'
                        },
                        "cache": false,
                        "dataType": 'json',
                        "data": {
                            "action": "<?php echo esc_attr(self::AJAX_ACTION_IMPORT); ?>",
                            "_wpnonce": "<?php echo esc_attr(wp_create_nonce(self::AJAX_ACTION_IMPORT)); ?>",
                        },
                        "beforeSend": function () {
                            // Disable the submit button to prevent issues with multiple submissions.
                            button_submit.prop('disabled', true);
                            _buildStatusDetails();
                            _trigger_check_interval();
                        },
                        "success": function () {
                            // _trigger_check_interval();
                        },
                    });
                });

                // Builds the status details to show the user the status of the import.
                function _buildStatusDetails() {
                    // Add spinner.
                    let _spinner = jQuery('<div>', {
                            id: 'wpml-to-polylang-migration-spinner',
                            class: 'spinner',
                            title: 'Processing',
                        })
                            .css('visibility', 'visible')
                            .css('display', 'inline-block')
                            .css('position', 'absolute')
                            .css('left', '0')
                            .css('vertical-align', 'middle')
                    ;
                    status_cage.append(_spinner);

                    // Add status message.
                    let Status = jQuery('<p>', {
                        id: 'wpml-to-polylang-migration-spinner-status',
                        text: "<?php echo esc_attr(Status::get_as_text(Status::STATUS_WAITING_ON_CRON)); ?>"
                    });
                    Status
                        .css('display', 'inline-block')
                        .css('position', 'absolute')
                        .css('left', '40px')
                        .css('vertical-align', 'middle')
                        .css('line-height', '.5em')
                    ;
                    status_cage.append(Status);
                }

                // Triggers the interval to check for the status of the import process.
                function _trigger_check_interval() {
                    setTimeout(_check_WPML_to_PolyLang_importer_status, <?php echo (int)self::STATUS_CHECK_INTERVAL_IN_SECONDS * 1000; ?>);
                }

                // The request to check the status of the import process.
                function _check_WPML_to_PolyLang_importer_status() {
                    jQuery.ajax({
                        "type": 'GET',
                        "url": ajaxurl,
                        "headers": {
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache',
                            'Expires': '0'
                        },
                        "cache": false,
                        "dataType": 'json',
                        "data": {
                            "action": "<?php echo esc_attr(self::AJAX_ACTION_STATUS_CHECK); ?>",
                            "_wpnonce": "<?php echo esc_attr(wp_create_nonce(self::AJAX_ACTION_STATUS_CHECK)); ?>",
                        },
                        "success": function (json, textStatus, jqXHR) {
                            if (undefined !== json.status && undefined !== json.message) {
                                // Update the status message.
                                jQuery("#wpml-to-polylang-migration-spinner-status").html(json.message);
                                // Return to normal when complete.
                                if (json.status == <?php echo esc_attr(Status::STATUS_COMPLETED); ?>) {
                                    status_cage.empty();
                                    let complete_message = jQuery('<h3>', {
                                        id: 'wpml-to-polylang-migration-spinner-status',
                                        text: json.message
                                    });
                                    complete_message
                                        .css('display', 'inline-block')
                                        .css('color', 'green')
                                    ;
                                    status_cage.append(complete_message);
                                } else {
                                    _trigger_check_interval();
                                }
                            }
                        },
                    });
                }
            </script>
        </div><!-- wrap -->
        <?php
    }

    /**
     * Process the AJAX request to start the import process.
     * @return void
     * @throws \Exception This is not needed since it is caught but phpcs want this here (false-flag).
     */
    public function process_ajax_request_import() {
        try {
            check_ajax_referer(self::AJAX_ACTION_IMPORT);

            if (false === current_user_can('manage_options')) {
                $string = __('You do not have permissions to perform the import ', 'wpml-to-polylang-migration');
                throw new \Exception($string);
            }

            // Output to trigger UI change.
            Status::update(Status::STATUS_WAITING_ON_CRON);

            // Schedule the cron event.
            Cron::schedule();

            // Prepare the response.
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            header('Content-type: application/json charset=utf-8');

            // Send the response.
            echo \wp_json_encode(array('started' => time()));
            exit();
        } catch (\Exception $e) {
            Status::update(Status::STATUS_ERRORED);
            die(esc_attr($e->getMessage()));
        }
    }

    /**
     * Processes the AJAX request for import status checks.
     * @return void
     * @throws \Exception This is not needed since it is caught but phpcs want this here (false-flag).
     */
    public function process_ajax_request_status() {
        try {
            check_ajax_referer(self::AJAX_ACTION_STATUS_CHECK);

            if (false === current_user_can('manage_options')) {
                $string = __('You do not have permissions to check the import status', 'wpml-to-polylang-migration');
                throw new \Exception($string);
            }

//            \wp_cache_flush();

            $_status = Status::get();

            // Remove if we are complete, no need to keep this in the DB.
            if (!empty($_status) && Status::STATUS_COMPLETED === $_status['status']) {
                Status::delete();
            }

            // Prepare the response.
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            header('Content-type: application/json charset=utf-8');

            // Send the response.
            echo wp_json_encode($_status);
            exit();
        } catch (\Exception $e) {
            Status::update(Status::STATUS_ERRORED);
            die(esc_attr($e->getMessage()));
        }
    }

    /**
     * Processes the AJAX request for import status checks.
     * @return void
     * @throws \Exception This is not needed since it is caught but phpcs want this here (false-flag).
     */
    public function process_ajax_request_fix_lang_totals() {
        try {
            check_ajax_referer(self::AJAX_ACTION_FIX_LANG_TOTALS);

            if (false === current_user_can('manage_options')) {
                $string = __('You do not have permissions to fix the language totals', 'wpml-to-polylang-migration');
                throw new \Exception($string);
            }

            /*
             * This is literally the same code as in the migration processor...
             * Due to WP internals, caching or transients, this does not update for custom post types.
             * Running it outside the migration processor solves this issue.
             * I spent too long trying to make this work in the processor and this is me giving up....
             */

            // Update language counts.
            /* @var $lang \PLL_Language */
            foreach (PLL()->model->get_languages_list() as $lang) {
                $lang->update_count(); // Doesn't seem to work... cache?
            }

            // Prepare the response.
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            header('Content-type: application/json charset=utf-8');

            // Send the response.
            echo wp_json_encode(true);
            exit();
        } catch (\Exception $e) {
            Status::update(Status::STATUS_ERRORED);
            die(esc_attr($e->getMessage()));
        }
    }


}
