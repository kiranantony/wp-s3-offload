<?php

class Ajax_Handlers {

    private $s3_client;
    private $upload_handler;

    public function __construct($s3_client, $upload_handler)
    {
        $this->s3_client = $s3_client;
        $this->upload_handler = $upload_handler;

        add_action('wp_ajax_start_offload_to_s3', [$this, 'start_offload_to_s3']);
        add_action('wp_ajax_stop_offload_to_s3', [$this, 'stop_offload_to_s3']);
        add_action('wp_ajax_check_offload_progress', [$this, 'check_offload_progress']);
        add_action('wp_ajax_offload_next_attachment', [$this, 'offload_next_attachment']);
    }

    public function start_offload_to_s3()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wp-s3-offload')]);
        }

        wp_send_json_success(['message' => __('Offloading started.', 'wp-s3-offload')]);
    }

    public function stop_offload_to_s3()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wp-s3-offload')]);
        }

        wp_send_json_success(['message' => __('Offloading stopped.', 'wp-s3-offload')]);
    }

    public function check_offload_progress()
    {
        $total_files = $this->count_total_files();
        $offloaded_files = $this->count_offloaded_files();

        $percentage = $total_files > 0 ? round(($offloaded_files / $total_files) * 100, 2) : 0;

        $response = [
            'success' => true,
            'data' => [
                'percentage' => $percentage,
                'total' => $total_files,
                'offloaded' => $offloaded_files,
                'offloading' => true // Assuming offloading is ongoing
            ],
        ];

        wp_send_json($response);
    }

    public function offload_next_attachment()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'wp-s3-offload')]);
        }

        $attachment = $this->get_next_non_offloaded_attachment();
        if ($attachment) {
            $file_path = get_attached_file($attachment->ID);
            if (file_exists($file_path)) {
                $metadata = wp_get_attachment_metadata($attachment->ID);
                $this->upload_handler->offload_attachment($metadata, $attachment->ID);
                wp_send_json_success([
                    'message' => sprintf(__('Attachment ID %d offloaded.', 'wp-s3-offload'), $attachment->ID),
                    'offloaded_id' => $attachment->ID
                ]);
            } else {
                wp_send_json_error(['message' => sprintf(__('File for attachment ID %d does not exist.', 'wp-s3-offload'), $attachment->ID), 'attachment_id' => $attachment->ID]);
            }
        } else {
            wp_send_json_success(['message' => __('No more attachments to offload.', 'wp-s3-offload')]);
        }
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

    private function get_next_non_offloaded_attachment()
    {
        global $wpdb;
        $query = "
            SELECT p.ID 
            FROM {$wpdb->prefix}posts AS p 
            LEFT JOIN {$wpdb->prefix}as3cf_items AS ai ON p.ID = ai.source_id 
            WHERE p.post_type = 'attachment' 
            AND ai.id IS NULL 
            ORDER BY p.post_date DESC 
            LIMIT 1
        ";
        $result = $wpdb->get_row($query);
        return $result;
    }
}
