<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://bueroblanko.de
 * @since      1.0.0
 *
 * @package    Code_Sync
 * @subpackage Code_Sync/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<?php 
if (CODE_SYNC_ADD_META_TAGS){
?>
<form action="" method="POST">
    <input type="hidden" name="action" value="save_meta_tags">
    <?php wp_nonce_field('save_meta_tags', 'meta_tags_nonce'); ?>
    <div class="form-group">
        <label for="title">Title</label>
        <input type="text" id="title" value="<?php echo $title; ?>" name="title" placeholder="Enter title" >
    </div>
    <div class="form-group">
        <label for="keywords">Keywords</label>
        <input type="text" id="keywords" value="<?php echo $keywords; ?>" name="keywords" placeholder="Enter keywords" >
    </div>
    <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description"  name="description" placeholder="Enter description" ><?php echo $description; ?></textarea>
    </div>

    <div class="form-group">
        <label for="og_image">OG Image URL</label>
        <input type="url" id="og_image" value="<?php echo $og_image; ?>" name="og_image" placeholder="https://example.com/image.jpg" >
        <p style="margin-top: 5px;color: gray; font-size: 12px"><b style="color: black;">Recommended: </b> 1200x630px </p>

    </div>

    <div class="form-actions">
        <?php submit_button('Save Meta Fields'); ?>

    </div>
</form>
<?php 
}

else {
    echo "<p>Some plugins that interfere with our plugin are active</p>";
}

?>

</div>