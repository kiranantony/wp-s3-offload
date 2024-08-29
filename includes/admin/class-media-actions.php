<?php

class Media_Actions {

    private $s3_client;
    private $upload_handler;

    public function __construct($s3_client, $upload_handler)
    {
        $this->s3_client = $s3_client;
        $this->upload_handler = $upload_handler;

        add_filter('media_row_actions', [$this, 'add_media_row_actions'], 10, 2);
        add_action('admin_post_offload_to_s3', [$this, 'handle_offload_to_s3']);
        add_action('admin_post_download_from_s3', [$this, 'handle_download_from_s3']);
        add_action('admin_notices', [$this, 's3_custom_admin_notices']);
    }

    public function add_media_row_actions($actions, $post)
    {
        if ($post->post_type !== 'attachment') {
            return $actions;
        }

        $is_offloaded = $this->is_attachment_offloaded($post->ID);

        if ($is_offloaded) {
            $actions['download_from_s3'] = '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=download_from_s3&attachment_id=' . $post->ID), 'download_from_s3_' . $post->ID) . '">' . __('Download from S3', 'wp-s3-offload') . '</a>';
        } else {
            $actions['offload_to_s3'] = '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=offload_to_s3&attachment_id=' . $post->ID), 'offload_to_s3_' . $post->ID) . '">' . __('Offload to S3', 'wp-s3-offload') . '</a>';
        }

        return $actions;
    }

    private function is_attachment_offloaded($attachment_id)
    {
        global $wpdb;
        $query = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}as3cf_items WHERE source_id = %d", $attachment_id);
        $count = $wpdb->get_var($query);
        return $count > 0;
    }

    public function handle_offload_to_s3()
    {
        $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;
        if (!$attachment_id || !check_admin_referer('offload_to_s3_' . $attachment_id)) {
            wp_die(__('Invalid request.', 'wp-s3-offload'));
        }

        $file_path = get_attached_file($attachment_id);
        $version = time(); // Using time() for versioning
        $upload_result = $this->s3_client->upload_file($file_path, $version);

        if ($upload_result && isset($upload_result['key'], $upload_result['version_id'])) {
            $s3_key = $upload_result['key'];
            $s3_version_id = $upload_result['version_id'];
            update_post_meta($attachment_id, 's3_key', $s3_key);
            update_post_meta($attachment_id, 's3_version_id', $s3_version_id);

            S3_Helper::add_to_as3cf_items($attachment_id, $s3_key, $this->s3_client);

            if (get_option('wp_s3_offload_remove_local')) {
                @unlink($file_path);
            }

            $this->handle_thumbnails_upload($attachment_id, $file_path, $version);

            wp_redirect(add_query_arg('message', 'offload_success', wp_get_referer()));
            exit;
        } else {
            wp_redirect(add_query_arg('message', 'offload_error', wp_get_referer()));
            exit;
        }
    }

    private function handle_thumbnails_upload($attachment_id, $file_path, $version)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_info) {
                $thumb_file = path_join(dirname($file_path), $size_info['file']);
                $this->s3_client->upload_file($thumb_file, $version);
                if (get_option('wp_s3_offload_remove_local')) {
                    @unlink($thumb_file);
                }
            }
        }
    }

    public function handle_download_from_s3()
    {
        $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;
        if (!$attachment_id || !check_admin_referer('download_from_s3_' . $attachment_id)) {
            wp_die(__('Invalid request.', 'wp-s3-offload'));
        }

        $s3_key = get_post_meta($attachment_id, 's3_key', true);
        $s3_version_id = get_post_meta($attachment_id, 's3_version_id', true);
        $upload_dir = wp_upload_dir();
        $local_path = $upload_dir['path'] . '/' . basename($s3_key);
        $result = $this->s3_client->download_file($s3_key, $local_path);

        if ($result) {
            // Temporarily remove the offload filter
            $this->upload_handler->remove_offload_filter();

            update_attached_file($attachment_id, $local_path);
            $this->s3_client->delete_file($s3_key, $s3_version_id);
            delete_post_meta($attachment_id, 's3_key');
            delete_post_meta($attachment_id, 's3_version_id');
            S3_Helper::remove_from_as3cf_items($attachment_id);
            $this->generate_thumbnails_locally($attachment_id, $local_path);

            // Re-add the offload filter
            $this->upload_handler->add_offload_filter();

            wp_redirect(add_query_arg('message', 'download_success', wp_get_referer()));
            exit;
        } else {
            wp_redirect(add_query_arg('message', 'download_error', wp_get_referer()));
            exit;
        }
    }

    private function generate_thumbnails_locally($attachment_id, $local_path)
    {
        $metadata = wp_generate_attachment_metadata($attachment_id, $local_path);
        if (is_array($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }

    public function s3_custom_admin_notices()
    {
        if (isset($_GET['message'])) {
            $message = '';
            switch ($_GET['message']) {
                case 'offload_success':
                    $message = __('The file has been successfully offloaded to S3.', 'wp-s3-offload');
                    break;
                case 'offload_error':
                    $message = __('There was an error offloading the file to S3.', 'wp-s3-offload');
                    break;
                case 'download_success':
                    $message = __('The file has been successfully downloaded from S3 and thumbnails generated.', 'wp-s3-offload');
                    break;
                case 'download_error':
                    $message = __('There was an error downloading the file from S3.', 'wp-s3-offload');
                    break;
            }

            if ($message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }
}
