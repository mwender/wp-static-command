<?php
namespace wp_static_command\static_page_generator;

class Static_Page_Generator {
    protected $archive_dir;
    protected $timeout = 30;

    public function __construct($archive_dir) {
        $this->archive_dir = trailingslashit($archive_dir);

        if (!file_exists($this->archive_dir)) {
            wp_mkdir_p($this->archive_dir);
        }
    }

    /**
     * Generate static version of a page/post
     *
     * @param int|WP_Post $post Post ID or WP_Post object
     * @return array|WP_Error Result array or WP_Error on failure
     */
    public function generate($post) {
        $post = get_post($post);
        if (!$post) {
            return new \WP_Error('invalid_post', 'Invalid post provided');
        }

        // Get the URL
        $url = get_permalink($post);

        // Create temporary file
        $temp_file = wp_tempnam();

        // Fetch the content
        $response = $this->fetch_url($url, $temp_file);
        if (is_wp_error($response)) {
            unlink($temp_file);
            return $response;
        }

        // Process the content
        $content = file_get_contents($temp_file);
        $processed_content = $this->process_content($content, $url);

        // Generate file path
        $relative_path = $this->get_relative_path($url);
        $file_path = $this->archive_dir . $relative_path;

        // Create directory if it doesn't exist
        $dir = dirname($file_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Save the file
        $result = file_put_contents($file_path, $processed_content);
        unlink($temp_file);

        if ($result === false) {
            return new \WP_Error('save_failed', 'Failed to save static file');
        }

        return array(
            'url' => $url,
            'file_path' => $file_path,
            'relative_path' => $relative_path
        );
    }

    /**
     * Fetch URL content
     */
    protected function fetch_url($url, $temp_file) {
        $args = array(
            'timeout' => $this->timeout,
            'stream' => true,
            'filename' => $temp_file,
            'sslverify' => false,
            'blocking' => true,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error(
                'fetch_failed',
                'Failed to fetch URL: ' . wp_remote_retrieve_response_message($response)
            );
        }

        return $response;
    }

    /**
     * Process the content and replace URLs
     */
    protected function process_content($content, $base_url) {
        // Replace WordPress site URL with relative paths
        $site_url = get_site_url();
        $content = str_replace($site_url, '', $content);

        // Replace other URLs as needed
        $content = $this->replace_asset_urls($content);

        return $content;
    }

    /**
     * Replace asset URLs (images, scripts, styles)
     */
    protected function replace_asset_urls($content) {
        // Replace WordPress uploads URLs
        $upload_dir = wp_upload_dir();
        $content = str_replace(
            $upload_dir['baseurl'],
            '/wp-content/uploads',
            $content
        );

        // Replace other asset URLs as needed
        return $content;
    }

    /**
     * Get relative path for URL
     */
    protected function get_relative_path($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = ltrim($path, '/');

        if (empty($path)) {
            return 'index.html';
        }

        // Add index.html to directory URLs
        if (substr($path, -1) === '/') {
            $path .= 'index.html';
        } elseif (!pathinfo($path, PATHINFO_EXTENSION)) {
            $path .= '/index.html';
        }

        return $path;
    }
}