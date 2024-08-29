<?php

class Settings_Page {

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
    }

    public function add_settings_page()
    {
        add_options_page(
                'WP S3 Offload Settings',
                'WP S3 Offload',
                'manage_options',
                'wp-s3-offload',
                [$this, 'create_settings_page']
        );
    }

    public function create_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>WP S3 Offload Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_s3_offload_settings_group');
                do_settings_sections('wp-s3-offload');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings()
    {
        register_setting('wp_s3_offload_settings_group', 'wp_s3_offload_bucket');
        register_setting('wp_s3_offload_settings_group', 'wp_s3_offload_region');
        register_setting('wp_s3_offload_settings_group', 'wp_s3_offload_key');
        register_setting('wp_s3_offload_settings_group', 'wp_s3_offload_secret');
        register_setting('wp_s3_offload_settings_group', 'wp_s3_offload_remove_local');
        register_setting('wp_s3_offload_settings_group', 'wp_s3_offload_custom_domain');

        add_settings_section(
                'wp_s3_offload_settings_section',
                'AWS S3 Settings',
                null,
                'wp-s3-offload'
        );

        add_settings_field(
                'wp_s3_offload_bucket',
                'S3 Bucket Name',
                [$this, 'bucket_field_html'],
                'wp-s3-offload',
                'wp_s3_offload_settings_section'
        );

        add_settings_field(
                'wp_s3_offload_region',
                'S3 Region',
                [$this, 'region_field_html'],
                'wp-s3-offload',
                'wp_s3_offload_settings_section'
        );

        add_settings_field(
                'wp_s3_offload_key',
                'AWS Access Key',
                [$this, 'key_field_html'],
                'wp-s3-offload',
                'wp_s3_offload_settings_section'
        );

        add_settings_field(
                'wp_s3_offload_secret',
                'AWS Secret Key',
                [$this, 'secret_field_html'],
                'wp-s3-offload',
                'wp_s3_offload_settings_section'
        );

        add_settings_field(
                'wp_s3_offload_remove_local',
                'Remove Local Files After Offload',
                [$this, 'remove_local_field_html'],
                'wp-s3-offload',
                'wp_s3_offload_settings_section'
        );

        add_settings_field(
                'wp_s3_offload_custom_domain',
                'Custom Domain',
                [$this, 'custom_domain_field_html'],
                'wp-s3-offload',
                'wp_s3_offload_settings_section'
        );
    }

    public function bucket_field_html()
    {
        $value = get_option('wp_s3_offload_bucket');
        echo '<input type="text" id="wp_s3_offload_bucket" name="wp_s3_offload_bucket" value="' . esc_attr($value) . '" />';
    }

    public function region_field_html()
    {
        $value = get_option('wp_s3_offload_region');
        echo '<input type="text" id="wp_s3_offload_region" name="wp_s3_offload_region" value="' . esc_attr($value) . '" />';
    }

    public function key_field_html()
    {
        $value = get_option('wp_s3_offload_key');
        echo '<input type="text" id="wp_s3_offload_key" name="wp_s3_offload_key" value="' . esc_attr($value) . '" />';
    }

    public function secret_field_html()
    {
        $value = get_option('wp_s3_offload_secret');
        echo '<input type="password" id="wp_s3_offload_secret" name="wp_s3_offload_secret" value="' . esc_attr($value) . '" />';
    }

    public function remove_local_field_html()
    {
        $checked = get_option('wp_s3_offload_remove_local') ? 'checked' : '';
        echo '<input type="checkbox" id="wp_s3_offload_remove_local" name="wp_s3_offload_remove_local" ' . $checked . ' />';
    }

    public function custom_domain_field_html()
    {
        $value = get_option('wp_s3_offload_custom_domain');
        echo '<input type="text" id="wp_s3_offload_custom_domain" name="wp_s3_offload_custom_domain" value="' . esc_attr($value) . '" />';
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="options-general.php?page=wp-s3-offload">' . __('Settings', 'wp-s3-offload') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
