<?php
/*
 * Plugin Name: Cloudflare Image Resize
 * Plugin URI:  https://servebolt.com
 * Description: Implementation of CF image resizing
 * Version:     0.1
 * Author:      Thomas Audunhus
 * Text Domain: cfimg
 * Domain Path: /languages
 */

function _get_all_image_sizes() {
    global $_wp_additional_image_sizes;

    $default_image_sizes = get_intermediate_image_sizes();

    foreach ( $default_image_sizes as $size ) {
        $image_sizes[ $size ][ 'width' ] = intval( get_option( "{$size}_size_w" ) );
        $image_sizes[ $size ][ 'height' ] = intval( get_option( "{$size}_size_h" ) );
        $image_sizes[ $size ][ 'crop' ] = get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false;
    }

    if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
        $image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
    }

    return $image_sizes;
}


/**
 * Preventing WordPress from generating the sizes
 *
 * @param [type] $sizes
 * @return void
 */
function add_image_insert_override($sizes){
    $orig_sizes = _get_all_image_sizes();

    foreach ($orig_sizes as $key => $value){
        unset( $sizes[$key]);
    }
    return $orig_sizes;
}
add_filter('intermediate_image_sizes_advanced', 'add_image_insert_override' );

function re_add_image_sizes($data){
    $sizes = array();
    $reg_sizes = _get_all_image_sizes();

    $file_origin = '';

    foreach($reg_sizes as $key => $value ) {
        $sizes[$key] = $value;
        $sizes[$key]['file'] = substr($data['file'], strrpos($data['file'], '/') + 1);
        
    }
    $data['sizes'] = $sizes;
    return $data;
}
add_filter('wp_get_attachment_metadata', 're_add_image_sizes');


function alter_srcset($sources){
    foreach ($sources as $key => $value){
        $url = wp_parse_url( $value['url'] );
        
        $cfparams = '';
        if($value['descriptor'] === 'w'){
            $cfparams .= 'width=' . $value['value'];
        }elseif($value['descriptor'] === 'h'){
            $cfparams .= 'height=' . $value['value'];
        }
        $cfparams .= ',quality=60,format=auto,onerror=redirect';
        $newurl = $url['scheme'] . '://' . $url['host'] . '/cdn-cgi/image/' . $cfparams . $url['path'];
        $sources[$key]['url'] = $newurl;
    }
    return $sources;
}
add_filter('wp_calculate_image_srcset', 'alter_srcset');

function alter_single_img( $image ){

    $url = wp_parse_url( $image[0] );
    
    $cfparams = '';
    if($image[1] > 1920) $cfparams .= 'width=1920,';
    if($image[2] > 1080) $cfparams .= 'height=1080,';
    $cfparams .= 'width=' . $image[1] . ',';
    
    $cfparams .= 'quality=60,format=auto,onerror=redirect';
    $newurl = $url['scheme'] . '://' . $url['host'] . '/cdn-cgi/image/' . $cfparams . $url['path'];

    $image[0] = $newurl;

    return $image;
}
add_filter('wp_get_attachment_image_src', 'alter_single_img');
