<?php
use Aws\S3\S3Client;

class S3_Client {

    private $initialized = false;
    private $s3;
    private $bucket;
    private $region;
    private $custom_domain;

    public function __construct()
    {
        $this->bucket = get_option('wp_s3_offload_bucket');
        $this->region = get_option('wp_s3_offload_region', 'us-east-1');
        $this->custom_domain = get_option('wp_s3_offload_custom_domain');
    }

    private function initialize_s3_client()
    {
        if (!$this->initialized) {
            $key = get_option('wp_s3_offload_key');
            $secret = get_option('wp_s3_offload_secret');

            $this->s3 = new S3Client([
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);

            $this->initialized = true;
        }
    }
    
    public function get_bucket()
    {
        return $this->bucket;
    }
    
    public function get_region()
    {
        return $this->region;
    }
    
    public function get_url($key)
    {
        if (empty($key)) {
            return false; // Handle empty key
        }

        if ($this->custom_domain) {
            return 'https://' . $this->custom_domain . '/' . $key;
        }

        return 'https://' . $this->bucket . '.s3.' . $this->region . '.amazonaws.com/' . $key;
    }

    public function upload_file($file_path, $version)
    {
        $this->initialize_s3_client();

        // Get the current year and month
        $year = date('Y');
        $month = date('m');
        $filename = basename($file_path);

        // Construct the S3 key
        $key = "wp-content/uploads/$year/$month/$version/$filename";

        try {
            $result = $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SourceFile' => $file_path,
                'ACL' => 'public-read',
            ]);

            // Get the version ID from the result
            $version_id = $result->get('VersionId');

            return [
                'key' => $key,
                'version_id' => $version_id,
            ];
        } catch (Exception $e) {
            error_log('S3 upload error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete_file($key, $version_id)
    {
        $this->initialize_s3_client();

        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'VersionId' => $version_id, // Specify version ID to delete
            ]);
        } catch (Exception $e) {
            error_log('S3 delete error: ' . $e->getMessage());
        }
    }

    public function download_file($key, $destination)
    {
        $this->initialize_s3_client();

        try {
            $this->s3->getObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SaveAs' => $destination,
            ]);

            return $destination;
        } catch (Exception $e) {
            error_log('S3 download error: ' . $e->getMessage());
            return false;
        }
    }
}

