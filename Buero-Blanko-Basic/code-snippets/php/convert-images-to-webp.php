<?php
// 
// <p>The code automatically converts uploaded images (JPEG, PNG, GIF) to WebP format in WordPress.</p>
/**
 * Convert Uploaded Images to WebP Format
 *
 * This snippet converts uploaded images (JPEG, PNG, GIF) to WebP format
 * automatically in WordPress. Ideal for use in a theme's functions.php file,
 * or with plugins like Code Snippets or WPCodeBox.
 *
 * @package    WordPress_Custom_Functions
 * @autor      Mark Harris
 * @link       www.christchurchwebsolutions.co.uk
 *
 * Usage Instructions:
 * - Add this snippet to your theme's functions.php file, or add it as a new
 *   snippet in Code Snippets or WPCodeBox.
 * - The snippet hooks into WordPress's image upload process and converts
 *   uploaded images to the WebP format.
 *
 * Optional Configuration:
 * - By default, the original image file is deleted after conversion to WebP.
 *   If you prefer to keep the original image file, simply comment out or remove
 *   the line '@unlink( $file_path );' in the wpturbo_handle_upload_convert_to_webp function.
 *   This will preserve the original uploaded image file alongside the WebP version.
 */
 
add_filter('wp_handle_upload', 'wpturbo_handle_upload_convert_to_webp');
 
function wpturbo_handle_upload_convert_to_webp($upload) {
    if ($upload['type'] == 'image/jpeg' || $upload['type'] == 'image/png' || $upload['type'] == 'image/gif') {
        $file_path = $upload['file'];
 
        // Check if ImageMagick or GD is available
        if (extension_loaded('imagick') || extension_loaded('gd')) {
            $image_editor = wp_get_image_editor($file_path);
            if (!is_wp_error($image_editor)) {
                $file_info = pathinfo($file_path);
                $dirname   = $file_info['dirname'];
                $filename  = $file_info['filename'];
 
                // Create a unique file path for the WebP image
                $def_filename = wp_unique_filename($dirname, $filename . '.webp');
                $new_file_path = $dirname . '/' . $def_filename;
 
                // Attempt to save the image in WebP format
                $saved_image = $image_editor->save($new_file_path, 'image/webp');
                if (!is_wp_error($saved_image) && file_exists($saved_image['path'])) {
                    // Success: replace the uploaded image with the WebP image
                    $upload['file'] = $saved_image['path'];
                    $upload['url']  = str_replace(basename($upload['url']), basename($saved_image['path']), $upload['url']);
                    $upload['type'] = 'image/webp';
 
                    // Optionally remove the original image
                    @unlink($file_path);
                }
            }
        }
    }
 
    return $upload;
}