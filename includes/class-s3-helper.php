<?php

class S3_Helper {

    public static function add_to_as3cf_items($attachment_id, $s3_key, $s3_client)
    {
        global $wpdb;

        $data = [
            'provider' => 'aws',
            'region' => $s3_client->get_region(),
            'bucket' => $s3_client->get_bucket(),
            'path' => $s3_key,
            'original_path' => $s3_key,
            'is_private' => 0,
            'source_type' => 'media-library',
            'source_id' => $attachment_id,
            'source_path' => wp_get_attachment_url($attachment_id),
            'original_source_path' => wp_get_attachment_url($attachment_id),
            'extra_info' => '',
            'originator' => 0,
            'is_verified' => 1,
        ];

        $wpdb->insert("{$wpdb->prefix}as3cf_items", $data);
    }

    public static function get_as3cf_attachment_url($attachment_id)
    {
        global $wpdb;
        $query = $wpdb->prepare("SELECT original_path FROM {$wpdb->prefix}as3cf_items WHERE source_id = %d", $attachment_id);
        return $wpdb->get_var($query);
    }

    public static function remove_from_as3cf_items($attachment_id)
    {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}as3cf_items", ['source_id' => $attachment_id]);
    }
}
