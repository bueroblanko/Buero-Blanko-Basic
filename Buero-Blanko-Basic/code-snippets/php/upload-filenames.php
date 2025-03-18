<?php
// 
// <p>This code converts the file name to lowercase in WordPress.</p>
add_filter( 'sanitize_file_name', 'mb_strtolower' );