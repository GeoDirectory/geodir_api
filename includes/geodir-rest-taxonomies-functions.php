<?php
/**
 * WP Rest API Listings Taxonomies functions & actions.
 *
 * @since 1.0.0
 * @package GeoDirectory
 */
 
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

function geodir_rest_taxonomy_collection_params( $params, $taxonomy ) {    
    // Listing location
    if ( geodir_rest_is_active( 'location' ) ) {
        $params['country'] = array(
            'description'        => __( 'Limit results to specific country.' ),
            'type'               => 'string',
            'sanitize_callback'  => 'sanitize_text_field',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        
        $params['region'] = array(
            'description'        => __( 'Limit results to specific region.' ),
            'type'               => 'string',
            'sanitize_callback'  => 'sanitize_text_field',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        
        $params['city'] = array(
            'description'        => __( 'Limit results to specific city.' ),
            'type'               => 'string',
            'sanitize_callback'  => 'sanitize_text_field',
            'validate_callback'  => 'rest_validate_request_arg',
        );
        
        if ( geodir_rest_is_active( 'neighbourhood' ) ) {
            $params['neighbourhood'] = array(
                'description'        => __( 'Limit results to specific neighbourhood.' ),
                'type'               => 'string',
                'sanitize_callback'  => 'sanitize_text_field',
                'validate_callback'  => 'rest_validate_request_arg',
            );
        }
    }
        
    return $params;
}

function geodir_rest_taxonomy_query( $args, $request ) {
    $args['is_gd_api']          = true;
    $args['is_geodir_loop']     = true;
    
    // Set location vars.
    if ( geodir_rest_is_active( 'location' ) ) {
        $args['gd_location'] = true;
        
        if ( !empty( $request['country'] ) ) {
            $args['gd_country'] = $request['country'];
        }
        
        if ( !empty( $request['region'] ) ) {
            $args['gd_region'] = $request['region'];
        }
        
        if ( !empty( $request['city'] ) ) {
            $args['gd_city']    = $request['city'];
        }
        
        if ( !empty( $request['neighbourhood'] ) && geodir_rest_is_active( 'neighbourhood' ) ) { 
            $args['gd_neighbourhood']    = $request['neighbourhood'];
        }
    }

    return $args;
}

/**
 * Filters a category item returned from the API.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Response  $response  The response object.
 * @param object            $item      The original term object.
 * @param WP_REST_Request   $request   Request used to generate the response.
 * @return WP_REST_Response Filtered category item response.
 */
function geodir_rest_prepare_category_item_response( $response, $item, $request ) {
    if ( !empty( $response->data ) && !empty( $item ) ) {
        $post_type = substr( $item->taxonomy, 0, -8 );
        
        $cat_icon = geodir_get_tax_meta( $item->term_id, 'ct_cat_icon', false, $post_type );
        $cat_icon = !empty( $cat_icon ) && isset( $cat_icon['src'] ) ? $cat_icon['src'] : '';
        
        $cat_image = geodir_get_default_catimage( $item->term_id, $post_type );
        $cat_image = !empty( $cat_image ) && isset( $cat_image['src'] ) ? $cat_image['src'] : '';
        
        $response->data['icon'] = $cat_icon;
        $response->data['image'] = $cat_image;
    }
    return $response;
}