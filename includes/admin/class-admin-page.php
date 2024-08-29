<?php

class Admin_Page {

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_offload_media_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function add_offload_media_page()
    {
        add_media_page(
                __('Offload Media to S3', 'wp-s3-offload'),
                __('Offload Media to S3', 'wp-s3-offload'),
                'manage_options',
                'offload-media-to-s3',
                [$this, 'offload_media_page_callback']
        );
    }

    public function offload_media_page_callback()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Offload Media to S3', 'wp-s3-offload');?></h1>
            <p id="offload-stats"><?php echo $this->get_offload_stats();?></p>
            <div id="offload-progress" style="width: 100%; background: #e0e0e0; height: 30px; border-radius: 5px; margin: 20px 0;">
                <div id="offload-progress-bar" style="width: 0; background: #4caf50; height: 100%; border-radius: 5px;"></div>
            </div>
            <button id="start-offload" class="button button-primary"><?php _e('Start Offload', 'wp-s3-offload');?></button>
            <button id="stop-offload" class="button button-secondary"><?php _e('Stop Offload', 'wp-s3-offload');?></button>
            <div id="offload-status"></div>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                let offloading = false;

                $('#start-offload').on('click', function () {
                    offloading = true;
                    $.post(ajaxurl, {action: 'start_offload_to_s3'}, function (response) {
                        $('#offload-status').html(response.data.message);
                        offloadNextAttachment();
                    });
                });

                $('#stop-offload').on('click', function () {
                    offloading = false;
                    $.post(ajaxurl, {action: 'stop_offload_to_s3'}, function (response) {
                        $('#offload-status').append('<p>' + response.data.message + '</p>');
                    });
                });

                function updateProgressBar(percentage, total, offloaded) {
                    $('#offload-progress-bar').css('width', percentage + '%');
                    $('#offload-stats').text('Total files: ' + total + ', Offloaded files: ' + offloaded);
                }

                function checkProgress() {
                    $.post(ajaxurl, {action: 'check_offload_progress'}, function (response) {
                        if (response.success) {
                            updateProgressBar(response.data.percentage, response.data.total, response.data.offloaded);
                        }
                    });
                }

                function offloadNextAttachment() {
                    if (!offloading)
                        return;

                    $.post(ajaxurl, {action: 'offload_next_attachment'}, function (response) {
                        checkProgress();
                        if (response.success && offloading) {
                            $('#offload-status').append('<p>Success: ' + response.data.message + '</p>');
                        } else {
                            $('#offload-status').append('<p>Error: ' + response.data.message + '</p>');
                        }
                        setTimeout(offloadNextAttachment, 1000); // Delay between each offload request
                    });
                }

                checkProgress();
            });
        </script>
        <?php
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('wp-s3-offload-admin-styles', plugin_dir_url(dirname(__FILE__, 2)) . 'css/admin-styles.css');
        wp_enqueue_script('jquery');
    }

    private function get_offload_stats()
    {
        $total_files = $this->count_total_files();
        $offloaded_files = $this->count_offloaded_files();

        return __('Total files: ', 'wp-s3-offload') . $total_files . ', ' . __('Offloaded files: ', 'wp-s3-offload') . $offloaded_files;
    }

    private function count_total_files()
    {
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
        ];
        $attachments = new WP_Query($args);
        return $attachments->found_posts;
    }

    private function count_offloaded_files()
    {
        global $wpdb;
        $query_as3cf = "SELECT COUNT(ai.id) FROM {$wpdb->prefix}as3cf_items AS ai JOIN {$wpdb->prefix}posts AS p ON ai.source_id = p.ID WHERE p.post_type = 'attachment'";
        return $wpdb->get_var($query_as3cf);
    }
}
