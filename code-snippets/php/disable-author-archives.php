<?php
// Removes the author archive section and redirects all links to author archives to the homepage. Perfect for blogs where there is only a single author.
add_filter( 'author_link', function ( $link ) {
	return home_url( 'about' );
});

add_action( 'template_redirect', function () {
	if ( is_author() ) {
		wp_redirect( home_url( 'about' ) );
		exit;
	}
});