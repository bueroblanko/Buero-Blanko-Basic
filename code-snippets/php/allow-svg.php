<?php
// Allow SVG upload without a plugin (nur für Administratoren)
//* allow SVG
// SVG-Dateien können Schadcode (JavaScript) enthalten und werden hier nicht bereinigt.
// Deshalb dürfen nur Nutzer mit "manage_options" (Administratoren) SVGs hochladen.
// Bereits hochgeladene SVGs bleiben unverändert.
	function def_svg_upload_allowed() {
	  return function_exists( 'wp_get_current_user' ) && current_user_can( 'manage_options' );
	}
	function def_allow_svgimg_types($mimes) {
	  if ( def_svg_upload_allowed() ) {
	    $mimes['svg'] = 'image/svg+xml';
	  }
	  return $mimes;
	}
	add_filter('upload_mimes', 'def_allow_svgimg_types');
	add_filter( 'wp_check_filetype_and_ext', function($def_svg_filetype_ext_data, $file, $filename, $mimes) {
		if ( substr($filename, -4) === '.svg' && def_svg_upload_allowed() ) {
			$def_svg_filetype_ext_data['ext'] = 'svg';
			$def_svg_filetype_ext_data['type'] = 'image/svg+xml';
		}
		return $def_svg_filetype_ext_data;
	}, 100, 4 );
	function def_common_svg_media_thumbnails($response, $attachment, $meta){
		if($response['type'] === 'image' && $response['subtype'] === 'svg+xml' && class_exists('SimpleXMLElement'))
		{
		  try {
		      $path = get_attached_file($attachment->ID);
		      if(@file_exists($path))
		      {
		          $svg = new SimpleXMLElement(@file_get_contents($path));
		          $src = $response['url'];
		          $width = (int) $svg['width'];
		          $height = (int) $svg['height'];
		          //media gallery
		          $response['image'] = compact( 'src', 'width', 'height' );
		          $response['thumb'] = compact( 'src', 'width', 'height' );
		          //media single
		          $response['sizes']['full'] = array(
		              'height'        => $height,
		              'width'         => $width,
		              'url'           => $src,
		              'orientation'   => $height > $width ? 'portrait' : 'landscape',
		          );
		      }
		  }
		  catch(Exception $e){}
		}
		return $response;
	}
	add_filter('wp_prepare_attachment_for_js', 'def_common_svg_media_thumbnails', 10, 3);
//* End allow SVG