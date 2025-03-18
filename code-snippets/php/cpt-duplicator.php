<?php
// Snippet to add functionality to duplicate posts and pages. This was inspired by the post duplication snippet found on codesnippets.cloud (see link)
/**
 * Duplicate WordPress Posts, Pages, and Custom Post Types as Drafts
 *
 * This code snippet enables the duplication of WordPress posts, pages, and all registered custom post types (CPTs).
 * It adds a 'Duplicate' link to the row actions for each item in the admin dashboard. When clicked, this link
 * triggers the duplication of the selected item, creating a new draft with the same content, custom fields,
 * and taxonomies.
 *
 * Functions:
 * 1. `duplicate_post_as_draft`: Handles the duplication process. It copies the post data, including title,
 *    content, excerpt, and custom fields, and creates a new post with a 'draft' status. It also maintains
 *    the taxonomy terms (like categories and tags) from the original post.
 * 2. `duplicate_post_link`: Adds the 'Duplicate' action link to the WordPress admin interface for each post,
 *    page, and custom post type. This link uses WordPress's built-in nonce functionality for security.
 * 3. `apply_duplicate_post_link_to_cpts`: Dynamically applies the duplicate post link function to all public
 *    post types, ensuring the 'Duplicate' link appears for any custom post types registered on the site.
 *
 * Author: Mark Harris
 * URI: https://www.christchurchwebsolutions.co.uk
 */
 
function duplicate_post_as_draft() {
    global $wpdb;
 
    // Verify the nonce for security
    $nonce_action = 'duplicate_post_as_draft';
    $nonce_name = 'duplicate_nonce';
    if (!isset($_GET[$nonce_name]) || !wp_verify_nonce($_GET[$nonce_name], $nonce_action)) {
        wp_die(esc_html__('Security check failed.', 'wpturbo'));
    }
 
    // Check if the 'post' parameter is set in either GET or POST request
    $post_id = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT) ?: filter_input(INPUT_POST, 'post', FILTER_SANITIZE_NUMBER_INT);
 
    if (!$post_id) {
        wp_die(esc_html__('No post to duplicate has been supplied!', 'wpturbo'));
    }
 
    // Check if the post exists
    $post = get_post($post_id);
    if (!$post) {
        wp_die(esc_html(sprintf(__('Post creation failed, could not find original post: %s', 'wpturbo'), $post_id)));
    }
 
    $current_user = wp_get_current_user();
    $new_post_author = $current_user->ID;
 
    $args = [
        "comment_status" => $post->comment_status,
        "ping_status" => $post->ping_status,
        "post_author" => $new_post_author,
        "post_content" => $post->post_content,
        "post_excerpt" => $post->post_excerpt,
        "post_name" => $post->post_name,
        "post_parent" => $post->post_parent,
        "post_password" => $post->post_password,
        "post_status" => "draft",
        "post_title" => $post->post_title . " (Copy)",
        "post_type" => $post->post_type,
        "to_ping" => $post->to_ping,
        "menu_order" => $post->menu_order
    ];
 
    $new_post_id = wp_insert_post($args);
 
    $taxonomies = get_object_taxonomies($post->post_type);
    foreach ($taxonomies as $taxonomy) {
        $post_terms = wp_get_object_terms($post_id, $taxonomy, ["fields" => "slugs"]);
        wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
    }
 
    $post_meta_infos = $wpdb->get_results(
        $wpdb->prepare("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d", $post_id)
    );
    if (count($post_meta_infos) != 0) {
        foreach ($post_meta_infos as $meta_info) {
            $meta_key = $meta_info->meta_key;
            $meta_value = sanitize_meta($meta_info->meta_key, $meta_info->meta_value, "post");
            $wpdb->insert($wpdb->postmeta, [
                "post_id" => $new_post_id,
                "meta_key" => $meta_key,
                "meta_value" => $meta_value
            ]);
        }
    }
 
    // Redirect to the post list screen and show a success message
    $redirect_url = admin_url("edit.php?post_type=" . $post->post_type);
    wp_redirect(add_query_arg("message", "101", $redirect_url));
    exit();
}
 
add_action("admin_action_duplicate_post_as_draft", "duplicate_post_as_draft");
 
function duplicate_post_link($actions, $post) {
    if (current_user_can('edit_posts')) {
        $actions['duplicate'] = '<a href="' .
            wp_nonce_url(
                admin_url("admin.php?action=duplicate_post_as_draft&post=" . $post->ID),
                'duplicate_post_as_draft',
                'duplicate_nonce'
            ) .
            '" title="' . esc_attr__('Duplicate this item', 'wpturbo') .
            '" rel="permalink">' . esc_html__('Duplicate', 'wpturbo') . '</a>';
    }
    return $actions;
}
 
add_filter("post_row_actions", "duplicate_post_link", 10, 2);
add_filter("page_row_actions", "duplicate_post_link", 10, 2);
 
function apply_duplicate_post_link_to_cpts() {
    $post_types = get_post_types(["public" => true], "names");
    foreach ($post_types as $post_type) {
        add_filter("{$post_type}_row_actions", "duplicate_post_link", 10, 2);
    }
}
 
add_action("admin_init", "apply_duplicate_post_link_to_cpts");
 
function show_duplicate_admin_notice() {
    if (isset($_GET['message']) && $_GET['message'] === '101') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html('Post duplicated successfully.') . '</p></div>';
    }
}
 
add_action('admin_notices', 'show_duplicate_admin_notice');