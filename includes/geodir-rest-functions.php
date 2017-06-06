<?php
/**
 * WP Rest API functions & actions.
 *
 * @since 1.0.0
 * @package GeoDirectory
 */
 
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

function geodir_rest_log( $log, $title = '', $file = '', $line = '', $exit = false ) {
    $should_log = apply_filters( 'geodir_rest_log', WP_DEBUG );
    
    if ( true === $should_log ) {
        $label = '';
        if ( $file && $file !== '' ) {
            $label .= basename( $file ) . ( $line ? '(' . $line . ')' : '' );
        }
        
        if ( $title && $title !== '' ) {
            $label = $label !== '' ? $label . ' ' : '';
            $label .= $title . ' ';
        }
        
        $label = $label !== '' ? trim( $label ) . ' : ' : '';
        
        if ( is_array( $log ) || is_object( $log ) ) {
            error_log( $label . print_r( $log, true ) );
        } else {
            error_log( $label . $log );
        }
        
        if ( $exit ) {
            exit;
        }
    }
}

/**
 * Alter allowed query vars for the REST API.
 *
 * This filter allows you to add or remove query vars from the allowed
 * list for all requests, including unauthenticated ones. To alter the
 * vars for editors only, {@see rest_private_query_vars}.
 *
 * @param array $query_vars List of allowed query vars.
 */
function geodir_rest_query_vars($query_vars) {
    $query_vars[] = 'is_gd_api';

    return $query_vars;
}
add_filter('rest_query_vars', 'geodir_rest_query_vars', 10, 1);

function geodir_rest_comments_clauses( $clauses, $comment_query ) {
    global $wpdb;
    
    if ( empty( $comment_query->query_vars['geodir_rest_route'] ) ) {
        return $clauses;
    }
    
    $is_reviewrating = function_exists( 'geodir_reviewrating_comments_shorting' ) ? true : false;
    
    $comment_sorting = $comment_query->query_vars['orderby'];
    if ( $comment_sorting == 'comment_date' ) {
        $comment_sorting = $comment_query->query_vars['order'] == 'asc' ? 'oldest' : 'latest';
    }
     
    $_REQUEST['comment_sorting'] = $comment_sorting;
    
    if ( $is_reviewrating ) {
        $review_sorting = geodir_reviewrating_comments_shorting( $clauses );
    } else {
        $review_sorting = array( 'orderby' =>  $clauses['orderby'] );
    }
    
    $clauses['fields'] .= ", gdr.`id` AS review_id, gdr.`post_title`, gdr.`post_type`, gdr.`rating_ip`, gdr.`ratings`, gdr.`overall_rating`, gdr.`comment_images`, gdr.`wasthis_review`, gdr.`status` AS review_status, gdr.`post_status` AS review_post_status, gdr.`post_date`, gdr.`post_city` AS city, gdr.`post_region` AS region, gdr.`post_country` AS country, gdr.`post_latitude` AS latitude, gdr.`post_longitude` AS longitude, gdr.`comment_content` AS city";
    if ( $is_reviewrating ) {
        $clauses['fields'] .= ", gdr.`read_unread`, gdr.`total_images`";
    }
    $clauses['join'] .= " LEFT JOIN " . GEODIR_REVIEW_TABLE . " AS gdr ON gdr.comment_id=" . $wpdb->comments . ".comment_ID";
    $clauses['orderby'] = str_replace( GEODIR_REVIEW_TABLE, 'gdr', $review_sorting['orderby'] );
    
    if ( !empty( $clauses['where'] ) ) {
        $clauses['where'] = str_replace( ' user_id ',' ' . $wpdb->comments . '.user_id ', $clauses['where'] );
    }
    
    return $clauses;
}

function geodir_rest_get_comment( $comment ) {
    global $wpdb;
    
    $comment_post_type = !empty( $comment->comment_post_ID ) ? get_post_type( $comment->comment_post_ID ) : '';
    
    if ( !geodir_rest_is_gd_post_type( $comment_post_type ) ) {
        return $comment;
    }

    $is_reviewrating = function_exists( 'geodir_reviewrating_comments_shorting' ) ? true : false;
    
    $fields = '';
    if ( $is_reviewrating ) {
        $fields = ", gdr.`read_unread`, gdr.`total_images`";
    }
    
    $query = "SELECT gdr.`id` AS review_id, gdr.`post_title`, gdr.`post_type`, gdr.`rating_ip`, gdr.`ratings`, gdr.`overall_rating`, gdr.`comment_images`, gdr.`wasthis_review`, gdr.`status` AS review_status, gdr.`post_status` AS review_post_status, gdr.`post_date`, gdr.`post_city` AS city, gdr.`post_region` AS region, gdr.`post_country` AS country, gdr.`post_latitude` AS latitude, gdr.`post_longitude` AS longitude, gdr.`comment_content` AS review " . $fields . " FROM " . GEODIR_REVIEW_TABLE . " AS gdr WHERE gdr.comment_id = " . (int)$comment->comment_ID;
    
    $review = $wpdb->get_row( $query );
    
    if (!empty($review)) {
        foreach ($review as $field => $value) {
            if (!isset($comment->{$field})) {
                $comment->{$field} = $value;
            }
        }
    }
    
    return $comment;
}

function geodir_rest_get_review_sorting() {
    $sorting = array(
                    'latest' => __( 'Latest', 'geodir_reviewratings' ),
                    'oldest' => __( 'Oldest', 'geodir_reviewratings' ),
                    'low_rating' => __( 'Lowest Rating', 'geodir_reviewratings' ),
                    'high_rating' => __( 'Highest Rating', 'geodir_reviewratings' )
               );
                   
    return apply_filters( 'geodir_reviews_rating_comment_shorting', $sorting );
}

function geodir_rest_data_type_to_field_type( $data_type ) {
    switch ( strtolower( $data_type ) ) {
        case 'float':
            $type = 'number';
            break;
        case 'int':
        case 'tinyint':
        case 'integer':
            $type = 'integer';
        case 'date':
        case 'time':
        case 'text':
        case 'varchar':
        default:
            $type = 'string';
            break;
    }
    
    return $type;
}

function geodir_rest_get_enum_values( $options ) {
    $values = array();
    
    if ( !empty( $options ) ) {
        foreach ( $options as $option ) {
            if ( isset( $option['value'] ) && $option['value'] !== '' && empty( $option['optgroup'] ) ) {
                $values[] = $option['value'];
            }            
        }
    }
    
    return $values;
}

function geodir_rest_is_active( $option ) {
    switch ( $option ) {
        case 'location':
            $return = defined( 'GEODIRLOCATION_VERSION' ) ? true : false;
            break;
        case 'neighbourhood':
            $return = geodir_rest_is_active( 'location' ) && get_option( 'location_neighbourhoods' ) ? true : false;
            break;
        case 'payment':
            $return = defined( 'GEODIRPAYMENT_VERSION' ) ? true : false;
            break;
        case 'event':
            $return = defined( 'GDEVENTS_VERSION' ) ? true : false;
            break;
        case 'event_recurring':
            $return = geodir_rest_is_active( 'event' ) && !( (bool)get_option( 'geodir_event_disable_recurring' ) ) ? true : false;
            break;
        case 'reviewrating':
            $return = defined( 'GEODIRREVIEWRATING_VERSION' ) ? true : false;
            break;
        case 'claim':
            $return = defined( 'GEODIRCLAIM_VERSION' ) && get_option( 'geodir_claim_enable' ) == 'yes' ? true : false;
            break;
        case 'advance_search':
            $return = defined( 'GEODIRADVANCESEARCH_VERSION' ) ? true : false;
            break;
        default:
            $return = false;
            break;
    }
    
    return $return;
}

function geodir_rest_get_countries( $params = array() ) {
    global $wpdb;
    
    $defaults = array(
        'fields'        => 'CountryId AS id, Country AS name, ISO2 as iso2, Country AS title',
        'search'        => '',
        'translated'    => true,
        'order'         => 'Country',
        'orderby'      => 'ASC'
    );
    
    $args = wp_parse_args( $params, $defaults );
    
    $where = '';
    if ( !empty( $args['search'] ) ) {
        $where .= "AND Country LIKE '" . wp_slash( $args['search'] ) . "%' ";
    }
    
    if ( !empty( $args['where'] ) ) {
        $where .= $args['where'];
    }
    
    $sql = "SELECT " . $args['fields'] . " FROM " . GEODIR_COUNTRIES_TABLE . " WHERE 1 " . $where . " ORDER BY " . $args['order'] . " " . $args['orderby'];
    $items = $wpdb->get_results( $sql );
    
    if ( empty( $args['translated'] ) ) {
        return $items;
    }
    
    if ( !empty( $items ) ) {
        foreach ( $items as $key => $item ) {
            $items[ $key ]->title = __( $item->name, 'geodirectory' );
        }
    }
    
    return $items;
}

function geodir_rest_country_by_id( $value ) {
    $rows = geodir_rest_get_countries( array( 'where' => "AND CountryId = '" . (int)$value . "'" ) );
    
    if ( !empty( $rows ) ) {
        return $rows[0];
    }
}

function geodir_rest_country_by_name( $value ) {
    $rows = geodir_rest_get_countries( array( 'where' => "AND Country LIKE '" . wp_slash( $value ) . "'" ) );
    
    if ( !empty( $rows ) ) {
        return $rows[0];
    }
}

function geodir_rest_country_by_iso2( $value ) {
    $rows = geodir_rest_get_countries( array( 'where' => "AND ISO2 LIKE '" . wp_slash( $value ) . "'" ) );

    if ( !empty( $rows ) ) {
        return $rows[0];
    }
    
    return NULL;
}

function geodir_rest_get_locations( $params = array() ) {
    global $wpdb;

    $defaults = array(
        'fields'        => '*',
        'what'          => 'city',
        'number'        => '',
        'offset'        => '',
        'search'        => '',
        'where'         => '',
        'order'         => '',
        'ordertype'     => '',
        'count'         => false,
        'orderby'       => '',
    );
    
    if ( empty( $params['what'] ) || ( !empty( $params['what'] ) && !in_array( $params['what'], array( 'country', 'region', 'city', 'neighbourhood' ) ) ) ) {
        $params['what'] = 'city';
    }
    
    if ( $params['what'] == 'city' ) {
        $defaults['orderby'] .= 'city ASC, region ASC, ';
    } else if ( $params['what'] == 'region' ) {
        $defaults['orderby'] .= 'region ASC, ';
    }
    
    $defaults['orderby'] .= 'country ASC';
    
    $args = wp_parse_args( $params, $defaults );

    $number = absint( $args['number'] );
    $offset = absint( $args['offset'] );

    $limits = '';
    if ( ! empty( $number ) ) {
        if ( $offset ) {
            $limits = 'LIMIT ' . $offset . ',' . $number;
        } else {
            $limits = 'LIMIT ' . $number;
        }
    }
    
    $where = '';
    
    if ( !empty( $args['where'] ) ) {
        $where .= $args['where'];
    }
    
    $groupby = '';

    switch ( $args['what'] ) {
        case 'city':
            $groupby = 'CONCAT(city_slug, "_", region_slug, "_", country_slug)';
        break;
        case 'region':
            $groupby = 'CONCAT(region_slug, "_", country_slug)';
        break;
        default:
        case 'country':
            $groupby = 'country_slug';
        break;
    }
    
    $groupby = trim( $groupby ) != '' ? ' GROUP BY ' . $groupby : '';
    
    $orderby = $args['order'] . " " . $args['ordertype'];
    
    if ( !empty( $args['orderby'] ) ) {
        $orderby = $args['orderby'];
    }
    
    $orderby = trim( $orderby ) != '' ? ' ORDER BY ' . $orderby : '';
    
    if ( $args['count'] ) {
        $fields = 'COUNT(*)';
        $groupby = '';
        $limits = '';
        $orderby = '';
    } else {
        $fields = $args['fields'] . ', ' . $args['what'] . ' AS name, ' . $args['what'] . '_slug AS slug';
    }
    
    $sql = "SELECT " . $fields . " FROM " . POST_LOCATION_TABLE . " WHERE 1 " . $where . " " . $groupby . $orderby . " ". $limits;

    return $args['count'] ? (int)$wpdb->get_var( $sql ) : $wpdb->get_results( $sql );
}

function geodir_rest_location_by_id( $value ) {
    $rows = geodir_rest_get_locations( array( 'where' => "AND location_id = '" . (int)$value . "'" ) );
    
    if ( !empty( $rows ) ) {
        return $rows[0];
    }
    
    return NULL;
}

function geodir_rest_location_by_slug( $params = array() ) {
    $defaults = array(
        'what'          => 'city',
        'country'       => '',
        'region'        => '',
        'city'          => '',
        'neighbourhood' => '',
    );
    
    $args = wp_parse_args( $params, $defaults );
    
    $where = '';
    
    if ( empty( $args[ $args['what'] ] ) ) {
        return NULL;
    }
    
    $where = '';
    
    if ( !empty( $args['country'] ) ) {
        $where .= " AND ( country_slug LIKE '" . wp_slash( $args['country'] ) . "' OR country_slug = '" . wp_slash( $args['country'] ) . "' )";
    }
    
    if ( !empty( $args['region'] ) ) {
        $where .= " AND ( region_slug LIKE '" . wp_slash( $args['region'] ) . "' OR region_slug = '" . wp_slash( $args['region'] ) . "' )";
    }
    
    if ( !empty( $args['city'] ) ) {
        $where .= " AND ( city_slug LIKE '" . wp_slash( $args['city'] ) . "' OR city_slug = '" . wp_slash( $args['city'] ) . "' )";
    }
    
    if ( empty( $where ) ) {
        return NULL;
    }
    
    $rows = geodir_rest_get_locations( array( 'what' => $args['what'], 'where' => $where, 'orderby' => 'is_default DESC' ) );
    
    if ( !empty( $rows ) ) {
        return $rows[0];
    }
    
    return NULL;
}

function geodir_rest_get_location_types() {
    $types = array();
        
    $types['country'] = array(
        'name'          => 'countries',
        'title'         => __( 'Countries' ),
        'description'   => __( 'All countries.' ),
        'rest_base'     => 'locations/countries',
        'fields'        => array( 'name', 'slug', 'title', 'is_default' )
    );
    
    $types['region'] = array(
        'name'          => 'regions',
        'title'         => __( 'Regions' ),
        'description'   => __( 'All regions.' ),
        'rest_base'     => 'locations/regions',
        'fields'        => array( 'name', 'slug', 'title', 'country', 'country_slug', 'is_default' )
    );
    
    $types['city'] = array(
        'name'          => 'cities',
        'title'         => __( 'Cities' ),
        'description'   => __( 'All cities.' ),
        'rest_base'     => 'locations/cities',
        'fields'        => array( 'id', 'name', 'slug', 'title', 'region', 'region_slug', 'country', 'country_slug', 'latitude', 'longitude', 'is_default' )
    );
    
    if ( geodir_rest_is_active( 'neighbourhood' ) ) {    
        $types['neighbourhood'] = array(
            'name'          => 'neighbourhoods',
            'title'         => __( 'Neighbourhoods' ),
            'description'   => __( 'All neighbourhoods.' ),
            'rest_base'     => 'locations/neighbourhoods',
        );
    }
    
    return $types;
}


function geodir_rest_get_location_type( $type ) {
    $location_types = geodir_rest_get_location_types();
    
    if ( isset( $location_types[ $type ] ) ) {
        return $location_types[ $type ];
    }
    
    return NULL;
}

function geodir_rest_advance_search_fields( $post_type, $formatted = false ) {
    global $wpdb;
    
    if ( !geodir_rest_is_active( 'advance_search' ) ) {
        return NULL;
    }
    
    $sql = $wpdb->prepare( "SELECT * FROM `" . GEODIR_ADVANCE_SEARCH_TABLE . "` WHERE post_type = %s ORDER BY sort_order ASC", array( $post_type ) );
    $search_fields = $wpdb->get_results( $sql );
    
    if ( $formatted && !empty( $search_fields ) ) {
        foreach ( $search_fields as $key => $search_field ) {
            if ( !empty( $search_field->field_site_name ) ) {
                $search_field->field_site_name = stripslashes( __( $search_field->field_site_name, 'geodirectory' ) );
            }
            
            if ( !empty( $search_field->front_search_title ) ) {
                $search_field->front_search_title = stripslashes( __( $search_field->front_search_title, 'geodirectory' ) );
            }
            
            if ( !empty( $search_field->first_search_text ) ) {
                $search_field->first_search_text = stripslashes( __( $search_field->first_search_text, 'geodirectory' ) );
            }
            
            if ( !empty( $search_field->last_search_text ) ) {
                $search_field->last_search_text = stripslashes( __( $search_field->last_search_text, 'geodirectory' ) );
            }
            
            if ( !empty( $search_field->field_desc ) ) {
                $search_field->field_desc = stripslashes( __( $search_field->field_desc, 'geodirectory' ) );
            }
            
            $search_field->extra_fields = !empty( $search_field->extra_fields ) ? maybe_unserialize( $search_field->extra_fields ) : array();
            
            $search_fields[$key] = $search_field;
        }
    }

    return apply_filters( 'geodir_rest_advance_search_fields', $search_fields, $post_type );
}

function geodir_rest_get_field_info_by_name( $name, $post_type, $formatted = false ) {
    global $wpdb;
    
    $sql = $wpdb->prepare( "SELECT * FROM " . GEODIR_CUSTOM_FIELDS_TABLE . " WHERE post_type = %s AND htmlvar_name=%s", array( $post_type, $name ) );
    $field_info =  $wpdb->get_row( $sql );
    
    if ( $formatted && !empty( $field_info ) ) {
        if ( !empty( $field_info->admin_title ) ) {
            $field_info->admin_title = stripslashes( __( $field_info->admin_title, 'geodirectory' ) );
        }
        
        if ( !empty( $field_info->admin_desc ) ) {
            $field_info->admin_desc = stripslashes( __( $field_info->admin_desc, 'geodirectory' ) );
        }
        
        if ( !empty( $field_info->site_title ) ) {
            $field_info->site_title = stripslashes( __( $field_info->site_title, 'geodirectory' ) );
        }
        
        if ( !empty( $field_info->clabels ) ) {
            $field_info->clabels = stripslashes( __( $field_info->clabels, 'geodirectory' ) );
        }
        
        if ( !empty( $field_info->required_msg ) ) {
            $field_info->required_msg = stripslashes( __( $field_info->required_msg, 'geodirectory' ) );
        }
        
        if ( !empty( $field_info->validation_msg ) ) {
            $field_info->validation_msg = stripslashes( __( $field_info->validation_msg, 'geodirectory' ) );
        }
        
        $field_info->options = !empty( $field_info->option_values ) ? geodir_string_values_to_options( $field_info->option_values, true ) : NULL; 
        $field_info->extra_fields = !empty( $field_info->extra_fields ) ? maybe_unserialize( $field_info->extra_fields ) : array();
    }
    
    return $field_info;
}

function geodir_rest_validate_request_arg( $value, $request, $param ) {
    $attributes = $request->get_attributes();
    if ( ! isset( $attributes['args'][ $param ] ) || ! is_array( $attributes['args'][ $param ] ) ) {
        return true;
    }
    $args = $attributes['args'][ $param ];

    return geodir_rest_validate_value_from_schema( $value, $args, $param );
}

function geodir_rest_validate_value_from_schema( $value, $args, $param = '' ) {
    if ( 'array' === $args['type'] ) {
        if ( ! is_array( $value ) ) {
            $value = preg_split( '/[\s,]+/', $value );
        }
        if ( ! wp_is_numeric_array( $value ) ) {
            /* translators: 1: parameter, 2: type name */
            return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s is not of typeggg %2$s.' ), $param, 'array' ) );
        }
        foreach ( $value as $index => $v ) {
            $is_valid = geodir_rest_validate_value_from_schema( $v, $args['items'], $param . '[' . $index . ']' );
            if ( is_wp_error( $is_valid ) ) {
                return $is_valid;
            }
        }
    }
    if ( ! empty( $args['enum'] ) ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $index => $v ) {
                if ( ! in_array( $v, $args['enum'] ) ) {
                    /* translators: 1: parameter, 2: value, 3: list of valid values */
                    return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s value "%2$s" is not one of %3$s.' ), $param, $v, implode( ', ', $args['enum'] ) ) );
                }
            }
        } else {
            if ( ! in_array( $value, $args['enum'] ) ) {
                /* translators: 1: parameter, 2: list of valid values */
                return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s is not one of %2$s.' ), $param, implode( ', ', $args['enum'] ) ) );
            }
        }
    }

    if ( in_array( $args['type'], array( 'integer', 'number' ) ) && ! is_numeric( $value ) ) {
        /* translators: 1: parameter, 2: type name */
        return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s is not of type %2$s.' ), $param, $args['type'] ) );
    }

    if ( 'integer' === $args['type'] && round( floatval( $value ) ) !== floatval( $value ) ) {
        /* translators: 1: parameter, 2: type name */
        return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s is not of type %2$s.' ), $param, 'integer' ) );
    }

    if ( 'boolean' === $args['type'] && ! rest_is_boolean( $value ) ) {
        /* translators: 1: parameter, 2: type name */
        return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s is not of type %2$s.' ), $value, 'boolean' ) );
    }

    if ( 'string' === $args['type'] && ! is_string( $value ) ) {
        /* translators: 1: parameter, 2: type name */
        return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s is not of type %2$s.' ), $param, 'string' ) );
    }

    if ( isset( $args['format'] ) ) {
        switch ( $args['format'] ) {
            case 'date-time' :
                if ( !empty( $args[ 'date_format' ] ) ) {
                    if ( $value && $value != geodir_date( $value, $args[ 'date_format' ], $args[ 'date_format' ] ) ) {
                        return new WP_Error( 'rest_invalid_date', sprintf( __( 'Invalid date. Valid format is %1$s.' ), $args[ 'date_format' ], 'string' ) );
                    }
                } else {
                    if ( ! rest_parse_date( $value ) ) {
                        return new WP_Error( 'rest_invalid_date', __( 'Invalid date.' ) );
                    }
                }
                break;

            case 'email' :
                // is_email() checks for 3 characters (a@b), but
                // wp_handle_comment_submission() requires 6 characters (a@b.co)
                //
                // https://core.trac.wordpress.org/ticket/38506
                if ( ! is_email( $value ) || strlen( $value ) < 6 ) {
                    return new WP_Error( 'rest_invalid_email', __( 'Invalid email address.' ) );
                }
                break;
            case 'ip' :
                if ( ! rest_is_ip_address( $value ) ) {
                    /* translators: %s: IP address */
                    return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not a valid IP address.' ), $value ) );
                }
                break;
        }
    }

    if ( in_array( $args['type'], array( 'number', 'integer' ), true ) && ( isset( $args['minimum'] ) || isset( $args['maximum'] ) ) ) {
        if ( isset( $args['minimum'] ) && ! isset( $args['maximum'] ) ) {
            if ( ! empty( $args['exclusiveMinimum'] ) && $value <= $args['minimum'] ) {
                /* translators: 1: parameter, 2: minimum number */
                return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s must be greater than %2$d (exclusive)' ), $param, $args['minimum'] ) );
            } elseif ( empty( $args['exclusiveMinimum'] ) && $value < $args['minimum'] ) {
                /* translators: 1: parameter, 2: minimum number */
                return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s must be greater than %2$d (inclusive)' ), $param, $args['minimum'] ) );
            }
        } elseif ( isset( $args['maximum'] ) && ! isset( $args['minimum'] ) ) {
            if ( ! empty( $args['exclusiveMaximum'] ) && $value >= $args['maximum'] ) {
                /* translators: 1: parameter, 2: maximum number */
                return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s must be less than %2$d (exclusive)' ), $param, $args['maximum'] ) );
            } elseif ( empty( $args['exclusiveMaximum'] ) && $value > $args['maximum'] ) {
                /* translators: 1: parameter, 2: maximum number */
                return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s must be less than %2$d (inclusive)' ), $param, $args['maximum'] ) );
            }
        } elseif ( isset( $args['maximum'] ) && isset( $args['minimum'] ) ) {
            if ( ! empty( $args['exclusiveMinimum'] ) && ! empty( $args['exclusiveMaximum'] ) ) {
                if ( $value >= $args['maximum'] || $value <= $args['minimum'] ) {
                    /* translators: 1: parameter, 2: minimum number, 3: maximum number */
                    return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s must be between %2$d (exclusive) and %3$d (exclusive)' ), $param, $args['minimum'], $args['maximum'] ) );
                }
            } elseif ( empty( $args['exclusiveMinimum'] ) && ! empty( $args['exclusiveMaximum'] ) ) {
                if ( $value >= $args['maximum'] || $value < $args['minimum'] ) {
                    /* translators: 1: parameter, 2: minimum number, 3: maximum number */
                    return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s must be between %2$d (inclusive) and %3$d (exclusive)' ), $param, $args['minimum'], $args['maximum'] ) );
                }
            } elseif ( ! empty( $args['exclusiveMinimum'] ) && empty( $args['exclusiveMaximum'] ) ) {
                if ( $value > $args['maximum'] || $value <= $args['minimum'] ) {
                    /* translators: 1: parameter, 2: minimum number, 3: maximum number */
                    return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s must be between %2$d (exclusive) and %3$d (inclusive)' ), $param, $args['minimum'], $args['maximum'] ) );
                }
            } elseif ( empty( $args['exclusiveMinimum'] ) && empty( $args['exclusiveMaximum'] ) ) {
                if ( $value > $args['maximum'] || $value < $args['minimum'] ) {
                    /* translators: 1: parameter, 2: minimum number, 3: maximum number */
                    return new WP_Error( 'rest_invalid_param', sprintf( __( '%1$s must be between %2$d (inclusive) and %3$d (inclusive)' ), $param, $args['minimum'], $args['maximum'] ) );
                }
            }
        }
    }

    return true;
}

function geodir_rest_is_gd_post_type( $post_type ) {
    global $gd_post_types;
    
    if ( empty( $post_type ) ) {
        return false;
    }
    
    if ( empty( $gd_post_types ) ) {
        $gd_post_types = geodir_get_posttypes();
    }
    
    if ( !empty( $gd_post_types ) && in_array( $post_type, $gd_post_types ) ) {
        return true;
    }
    
    return false;
}