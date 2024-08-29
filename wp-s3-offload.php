<?php

/*
  Plugin Name: WP S3 Offload
  Description: Offloads media files to AWS S3.
  Version: 1.0
  Author: Kiran Antony
 */

// Include AWS SDK autoloader
require_once plugin_dir_path(__FILE__) . 'aws-sdk-php/aws-autoloader.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-s3-client.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-upload-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-settings-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-s3-helper.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/class-media-actions.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/class-admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ajax-handlers.php';

// Initialize the plugin
add_action('plugins_loaded', 'wp_s3_offload_init');

function wp_s3_offload_init()
{
    $s3_client = new S3_Client();
    $upload_handler = new Upload_Handler($s3_client);
    new Settings_Page();
    new Media_Actions($s3_client, $upload_handler);
    new Admin_Page();
    new Ajax_Handlers($s3_client, $upload_handler);
}
