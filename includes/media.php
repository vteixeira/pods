<?php
/**
 * @package Pods\Global\Functions\Media
 */
/**
 * Get the Attachment ID for a specific image field
 *
 * @param array|int|string $image The image field array, ID, or guid
 *
 * @return int Attachment ID
 *
 * @since 2.0.5
 */
function pods_image_id_from_field ( $image ) {
    $id = 0;

    if ( !empty( $image ) ) {
        if ( is_array( $image ) ) {
            if ( isset( $image[ 0 ] ) )
                $id = pods_image_id_from_field( $image[ 0 ] );
            elseif ( isset( $image[ 'ID' ] ) )
                $id = $image[ 'ID' ];
            elseif ( isset( $image[ 'guid' ] ) )
                $id = pods_image_id_from_field( $image[ 'guid' ] );
        }
        else {
            if ( false === strpos( $image, '.' ) && is_numeric( $image ) ) {
                $id = $image;

                $the_post_type = get_post_type( $id );

                if ( false === $the_post_type )
                    $id = 0;
                elseif ( 'attachment' != $the_post_type )
                    $id = get_post_thumbnail_id( $id );
            }
            else {
                $guid = pods_query( "SELECT `ID` FROM @wp_posts WHERE `post_type` = 'attachment' AND `guid` = %s", array( $image ) );

                if ( !empty( $guid ) )
                    $id = $guid[ 0 ]->ID;
            }
        }
    }

    $id = (int) $id;

    return $id;
}

/**
 * Get the <img> HTML for a specific image field
 *
 * @param array|int|string $image The image field array, ID, or guid
 * @param string $size Image size to use
 * @param int $default Default image to show if image not found, can be field array, ID, or guid
 * @param string|array $attributes <img> Attributes array or string (passed to wp_get_attachment_image
 *
 * @return string <img> HTML or empty if image not found
 *
 * @since 2.0.5
 */
function pods_image ( $image, $size = 'thumbnail', $default = 0, $attributes = '' ) {
    $html = '';

    $id = pods_image_id_from_field( $image );
    $default = pods_image_id_from_field( $default );

    if ( 0 < $id )
        $html = wp_get_attachment_image( $id, $size, false, $attributes );

    if ( empty( $html ) && 0 < $default )
        $html = wp_get_attachment_image( $id, $size, false, $attributes );

    return $html;
}

/**
 * Get the Image URL for a specific image field
 *
 * @param array|int|string $image The image field array, ID, or guid
 * @param string $size Image size to use
 * @param int $default Default image to show if image not found, can be field array, ID, or guid
 *
 * @return string Image URL or empty if image not found
 *
 * @since 2.0.5
 */
function pods_image_url ( $image, $size = 'thumbnail', $default = 0 ) {
    $url = '';

    $id = pods_image_id_from_field( $image );
    $default = pods_image_id_from_field( $default );

    if ( 0 < $id ) {
        $src = wp_get_attachment_image_src( $id, $size );

        if ( !empty( $src ) )
            $url = $src[ 0 ];
    }

    if ( empty( $url ) && 0 < $default ) {
        $src = wp_get_attachment_image_src( $default, $size );

        if ( !empty( $src ) )
            $url = $src[ 0 ];
    }

    return $url;
}

/**
 * Import media from a specific URL, saving as an attachment
 *
 * @param string $url URL to media for import
 * @param int $post_parent ID of post parent, default none
 * @param boolean $featured Whether to set it as the featured (post thumbnail) of the post parent
 *
 * @return int Attachment ID
 *
 * @since 2.3.0
 */
function pods_attachment_import ( $url, $post_parent = null, $featured = false ) {
    $filename = substr( $url, ( strrpos( $url, '/' ) ) + 1 );

    if ( !( ( $uploads = wp_upload_dir( current_time( 'mysql' ) ) ) && false === $uploads[ 'error' ] ) )
        return;

    $filename = wp_unique_filename( $uploads[ 'path' ], $filename );
    $new_file = $uploads[ 'path' ] . '/' . $filename;

    $file_data = @file_get_contents( $url );

    if ( !$file_data )
        return;

    file_put_contents( $new_file, $file_data );

    $stat = stat( dirname( $new_file ) );
    $perms = $stat[ 'mode' ] & 0000666;
    @chmod( $new_file, $perms );

    $wp_filetype = wp_check_filetype( $filename );

    if ( !$wp_filetype[ 'type' ] || !$wp_filetype[ 'ext' ] )
        return;

    $attachment = array(
        'post_mime_type' => $wp_filetype[ 'type' ],
        'guid' => $uploads[ 'url' ] . '/' . $wp_filetype[ 'filename' ],
        'post_parent' => null,
        'post_title' => '',
        'post_content' => '',
    );

    $attachment_id = wp_insert_attachment( $attachment, $wp_filetype[ 'file' ], $post_parent );

    if ( is_wp_error( $attachment_id ) )
        return;

    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    wp_update_attachment_metadata( $attachment_id, $meta_data = wp_generate_attachment_metadata( $attachment_id, $new_file ) );

    if ( 0 < $post_parent && $featured )
        update_post_meta( $post_parent, '_thumbnail_id', $attachment_id );
}