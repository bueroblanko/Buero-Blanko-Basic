<?php
// 
// <p>This code applies the 'convert_smilies' function to various WordPress filters to convert text smilies to image smilies.</p>
add_filter( 'widget_text', 'convert_smilies' );
add_filter( 'the_title', 'convert_smilies' );
add_filter( 'wp_title', 'convert_smilies' );
add_filter( 'get_bloginfo', 'convert_smilies' );