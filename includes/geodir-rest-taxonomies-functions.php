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