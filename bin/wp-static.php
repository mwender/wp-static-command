<?php
// Register the WP-CLI command
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('static', 'Static_Command');
}

class Static_Command {

    /**
     * Generates a static HTML file for a given post/page.
     *
     * ## OPTIONS
     *
     * <post_id>
     * : The ID of the post/page to generate a static file for.
     *
     * ## EXAMPLES
     *
     *     wp static 21
     *
     * @param array $args Command arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke($args, $assoc_args) {
        list($post_id) = $args;

        // Validate post ID
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_status, ['publish', 'private'])) {
            WP_CLI::error("Post with ID {$post_id} not found or is not published/private.");
        }

        // Include the Static_Page_Generator class
        require_once __DIR__ . '/static-page-generator.php';

        // Instantiate the Static_Page_Generator
        $generator = new Static_Page_Generator();
        $result = $generator->generate($post_id);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        } else {
            WP_CLI::success("Static file generated for post ID {$post_id}. \n - URL: {$result['relative_path']}\n - File: {$result['file_path']}");
        }
    }
}

