<?php

class Upload_Handler {

    private $s3_client;

    public function __construct($s3_client)
    {
        $this->s3_client = $s3_client;

        add_filter('wp_generate_attachment_metadata', [$this, 'offload_attachment'], 10, 2);
        add_action('delete_attachment', [$this, 'handle_delete']);
        add_filter('wp_get_attachment_url', [$this, 'get_attachment_url'], 10, 2);
    }

    public function offload_attachment($metadata, $attachment_id)
    {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) {
            return $metadata;
        }

        $version = $this->generate_version_id(); // Custom version ID (if needed)
        // Upload the main file to S3 and get key and version ID
        $upload_result = $this->s3_client->upload_file($file_path, $version);

        if ($upload_result) {
            $s3_key = $upload_result['key'];
            $s3_version_id = $upload_result['version_id'];

            // Update post meta with the S3 key and version ID
            update_post_meta($attachment_id, 's3_key', $s3_key);
            update_post_meta($attachment_id, 's3_version_id', $s3_version_id);
            S3_Helper::add_to_as3cf_items($attachment_id, $s3_key, $this->s3_client);

            // Optionally delete local file if configured
            if (get_option('wp_s3_offload_remove_local')) {
                @unlink($file_path);
            }
        }

        // Check if there are thumbnails to upload
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_info) {
                // Generate path to thumbnail
                $thumb_file = path_join(dirname($file_path), $size_info['file']);
                // Upload the thumbnail to S3
                $this->s3_client->upload_file($thumb_file, $version);
                // Optionally delete local thumbnail file if configured
                if (get_option('wp_s3_offload_remove_local')) {
                    @unlink($thumb_file);
                }
            }
        }

        return $metadata;
    }

    public function handle_delete($post_id)
    {
        $s3_key = get_post_meta($post_id, 's3_key', true);
        $s3_version_id = get_post_meta($post_id, 's3_version_id', true); // Retrieve version ID

        if ($s3_key) {
            // Delete the file from S3 using version ID
            $this->s3_client->delete_file($s3_key, $s3_version_id);

            // Remove S3 key and version ID meta
            delete_post_meta($post_id, 's3_key');
            delete_post_meta($post_id, 's3_version_id');
        }

        S3_Helper::remove_from_as3cf_items($post_id);
    }

    public function get_attachment_url($url, $attachment_id)
    {
        $s3_key = get_post_meta($attachment_id, 's3_key', true);
        if ($s3_key) {
            // Return the S3 URL
            return $this->s3_client->get_url($s3_key);
        }

        // Check AS3CF items table for existing offloaded files
        $as3cf_url = S3_Helper::get_as3cf_attachment_url($attachment_id);
        if ($as3cf_url) {
            return $this->s3_client->get_url($as3cf_url);
        }

        // Return the original URL if not offloaded
        return $url;
    }

    private function generate_version_id()
    {
        return time(); // Use current timestamp as version ID, can be customized
    }

    public function remove_offload_filter()
    {
        remove_filter('wp_generate_attachment_metadata', [$this, 'offload_attachment'], 10);
    }

    public function add_offload_filter()
    {
        add_filter('wp_generate_attachment_metadata', [$this, 'offload_attachment'], 10, 2);
    }
}
