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

        // Get the permalink and prepare the file path
        $permalink = get_permalink($post_id);
        if (!$permalink) {
            WP_CLI::error("Could not generate permalink for post ID {$post_id}.");
        }

        $parsed_url = parse_url($permalink);
        $path = trim($parsed_url['path'], '/');
        $static_dir = ABSPATH . $path;

        if (!file_exists($static_dir)) {
            wp_mkdir_p($static_dir);
        }

        $html_file = trailingslashit($static_dir) . 'index.html';

        // Fetch the post content
        $html_content = $this->get_static_content($post_id);

        // Save the HTML content
        if (file_put_contents($html_file, $html_content) === false) {
            WP_CLI::error("Failed to write static file at {$html_file}.");
        }

        WP_CLI::success("Static file generated: {$html_file}");
    }

    /**
     * Retrieves the static HTML content for a post/page.
     *
     * @param int $post_id The ID of the post/page.
     * @return string The static HTML content.
     */
    private function get_static_content($post_id) {
        // Use output buffering to capture the rendered HTML
        ob_start();

        // Set up a new WP_Query for the specified post
        $query = new WP_Query([
            'p' => $post_id,
            'post_type' => 'any',
        ]);

        // Check if the post exists
        if ($query->have_posts()) {
            // Load the post data
            $query->the_post();

            // Include the theme's template loader to render the content
            include ABSPATH . WPINC . '/template-loader.php';
        }

        // Get the output and clean up
        $content = ob_get_clean();

        // Reset the post data
        wp_reset_postdata();

        return $content;
    }
}

