<?php
/**
 * WP Rest API Listings functions & actions.
 *
 * @since 1.0.0
 * @package GeoDirectory
 */
 
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Alter the query arguments for a request.
 *
 * This allows you to set extra arguments or defaults for a post
 * collection request.
 *
 * @param array $args Map of query var to query value.
 * @param WP_REST_Request $request Full details about the request.
 */
function geodir_rest_set_gd_var($args, $request) {
    if (empty($args['post_type'])) {
        return $args;
    }
        
    $gd_post_types = geodir_get_posttypes();

    if (in_array($args['post_type'], $gd_post_types)) {
        $args['is_gd_api'] = true;
    }

    return $args;
}
add_filter('rest_post_query', 'geodir_rest_set_gd_var', 10, 2);

function geodir_rest_post_table($post_type) {
    global $plugin_prefix;

    $table = $plugin_prefix . $post_type . '_detail';

    return $table;
}

/**
 * Get post custom fields.
 *
 * @since 1.5.4
 * @package GeoDirectory
 * @global object $wpdb WordPress Database object.
 * @global object $post The current post object.
 * @global string $plugin_prefix Geodirectory plugin table prefix.
 * @param int|string $post_id Optional. The post ID.
 * @return object|bool Returns full post details as an object. If no details returns false.
 */
function geodir_rest_gd_post_info($post_id = '') {
    global $wpdb, $plugin_prefix;

    $post_type = get_post_type($post_id);

    $all_postypes = geodir_get_posttypes();

    if (!in_array($post_type, $all_postypes)) {
        return false;
    }

    $table = $plugin_prefix . $post_type . '_detail';

    /**
     * Apply Filter to change Post info
     *
     * You can use this filter to change Post info.
     *
     * @since 1.5.4
     * @package GeoDirectory
     */
    $query = "SELECT pd.* FROM " . $table . " pd INNER JOIN " . $wpdb->posts . " as p ON p.ID = pd.post_id WHERE post_id = " . (int)$post_id . " AND p.post_type = '" . $post_type . "'";
    $row = $wpdb->get_row($query);

    return $row;

}

function geodir_rest_custom_gd_data($data, $post_id) {
    global $post, $geodir_date_time_format, $geodir_date_format, $geodir_time_format;

    $post_type = $post->post_type;
    if ($post_type == 'gd_event' && isset($post->event_date)) {
        $event_date = str_replace(' 00:00:00', '', $post->event_date);
        
        $start_datetime = $event_date . ' ' . $post->event_starttime;
        $end_datetime = $post->event_enddate . ' ' . $post->event_endtime;
        
        $date_format = $post->all_day ? $geodir_date_format : $geodir_date_time_format;
        
        $start_rendered = date_i18n($date_format, strtotime($start_datetime));
        $end_rendered = date_i18n($date_format, strtotime($end_datetime));
        
        $data['event_start_datetime'] = array('raw' => $start_datetime, 'rendered' => $start_rendered);
        $data['event_end_datetime'] = array('raw' => $end_datetime, 'rendered' => $end_rendered);
        $data['event_startdate'] = array('raw' => $event_date, 'rendered' => date_i18n($geodir_date_format, strtotime($event_date)));
        $data['event_enddate'] = array('raw' => $post->event_enddate, 'rendered' => date_i18n($geodir_date_format, strtotime($post->event_enddate)));
        $data['event_starttime'] = array('raw' => $post->event_starttime, 'rendered' => date_i18n($geodir_time_format, strtotime($post->event_starttime)));
        $data['event_endtime'] = array('raw' => $post->event_endtime, 'rendered' => date_i18n($geodir_time_format, strtotime($post->event_endtime)));
        $data['recurring'] = $post->recurring;
        $data['all_day'] = $post->all_day;
        $data['schedule_id'] = $post->schedule_id;
    }

    // post images
    $data['post_images'] = geodir_rest_get_post_images($post_id);

    if (is_plugin_active( 'geodir_payment_manager/geodir_payment_manager.php')) {
        // post package
        $package_info = geodir_post_package_info(array(), $post);
        
        if (!empty($package_info)) {
            $data['package_info'] = array(
                                        'package_id' => $package_info->pid,
                                        'package_title' => $package_info->title_desc,
                                        'package_amount' => $package_info->amount > 0 ? geodir_payment_price($package_info->amount) : 0,
                                    );
        }

        $payment_invoices = geodir_rest_get_payment_invoices($post_id);
        $data['payment_invoices'] = $payment_invoices;
    }
        
    return $data;
}
add_filter('geodir_rest_get_gd_data', 'geodir_rest_custom_gd_data', 0, 2);

function  geodir_rest_get_post_images($post_id) {
    $post_images = $post_images = geodir_get_images($post_id);
    $images = array();

    if (!empty($post_images)) {
        foreach ($post_images as $post_image) {
            $image = array();
            $image['src'] = $post_image->src;
            $image['title'] = $post_image->title;
            
            $images[] = $image;
        }
    }

    return $images;
}

function geodir_rest_get_payment_invoices($post_id) {
    global $wpdb;

    $sql = $wpdb->prepare("SELECT * FROM " . INVOICE_TABLE . " WHERE post_id = %d ORDER BY date desc", array($post_id));
    $rows = $wpdb->get_results($sql);

    $invoices = array();

    if (!empty($rows)) {
        $date_time_format = geodir_default_date_format() . ' ' . get_option('time_format');
        
        foreach ($rows as $row) {			
            $invoice_status = $row->status;
            if ( in_array(strtolower($invoice_status), array('paid', 'active', 'subscription-payment', 'free'))) {
                $invoice_status = 'confirmed';
            } else if (in_array(strtolower($invoice_status), array('unpaid'))) {
                $invoice_status = 'pending';
            }
            
            $invoice = array();
            $invoice['id'] = $row->id;
            $invoice['title'] = $row->post_title;
            $invoice['invoice_type'] = geodir_payment_invoice_type_name($row->invoice_type);
            $invoice['package_id'] = $row->package_id;
            $invoice['paid_amount'] = geodir_payment_price($row->paied_amount);
            $invoice['alive_days'] = $row->alive_days;
            $invoice['amount'] = geodir_payment_price($row->amount);
            $invoice['coupon'] = $row->coupon_code;
            $invoice['discount'] = $row->discount > 0 ? geodir_payment_price($row->discount) : 0;
            $invoice['payment_method'] = geodir_payment_method_title($row->paymentmethod);
            $invoice['date'] = $row->date != '0000-00-00 00:00:00' ? date_i18n($date_time_format, strtotime($row->date)) : '';
            $invoice['date_updated'] = $row->date_updated != '0000-00-00 00:00:00' ? date_i18n($date_time_format, strtotime($row->date_updated)) : '';
            $invoice['status'] = geodir_payment_status_name($invoice_status);
            
            $invoices[] = $invoice;
        }
    }

    return $invoices;
}

function geodir_rest_register_custom_fields($post_type) {
    register_api_field($post_type,
        'gd_custom_fields',
        array(
            'get_callback' => 'geodir_rest_get_custom_fields_values',
            'update_callback' => null,
            'schema' => null,
        )
    );
}

function geodir_rest_get_custom_fields_values($object, $attribute, $args) {	
    $post_id = $object['id'];
    $post_type = $object['type'];

    $custom_fields = geodir_rest_get_custom_fields($post_type);

    $data = array();
    if (!empty($custom_fields)) {
        $gd_post_info = geodir_get_post_info($post_id);
        
        foreach ($custom_fields as $field) {
            $field_name = $field->htmlvar_name;
            $admin_title = $field->admin_title;
            $site_title = $field->site_title;
            $field_type = $field->field_type;
            
            $title = $site_title != '' ? $site_title : $admin_title;
            
            $raw_value = isset($gd_post_info->$field_name) ? $gd_post_info->$field_name : '';
            
            switch ($field_type) {
                case 'checkbox':
                    $rendered_value = (int)$raw_value == 1 ? __('Yes', 'geodirectory') : __('No', 'geodirectory');
                break;
                case 'radio':
                    if ($raw_value == 't' || $raw_value == '1') {
                        $rendered_value =  __('Yes', 'geodirectory');
                    } else if ($raw_value == 'f' || $raw_value == '0') {
                        $rendered_value =  __('No', 'geodirectory');
                    } else {
                        $rendered_value = $raw_value;
                    }
                break;
                default:
                    $rendered_value = $raw_value;
                break;
            }
            
            $field_data = array();
            $field_data['title'] = $title;
            $field_data['type'] = $field_type;
            $field_data['raw'] = $raw_value;
            
            if ($rendered_value != $raw_value) {
                $field_data['rendered'] = $rendered_value;
            }
            
            $data[$field_name] = $field_data;
        }
    }

    return $data;
}

function geodir_rest_get_custom_fields($post_type, $name_only = false, $select = '*' ) {
    global $wpdb;

    $sql = $wpdb->prepare("SELECT " . $select . " FROM " . GEODIR_CUSTOM_FIELDS_TABLE . " WHERE post_type=%s AND is_active='1' ORDER BY sort_order ASC", array( $post_type ) );
    $rows = $wpdb->get_results( $sql );

    if ($name_only && !empty($rows)) {
        $fields = array();
        
        foreach ($rows as $row) {
            $fields[] = $row->htmlvar_name;
        }
        
        $rows = $fields;
    }

    return $rows;
}

function geodir_rest_get_field_by_name($field_name, $post_type) {
    global $wpdb;

    $sql = $wpdb->prepare("SELECT * FROM " . GEODIR_CUSTOM_FIELDS_TABLE . " WHERE post_type=%s AND htmlvar_name=%s AND is_active='1' ORDER BY sort_order ASC", array( $post_type, $field_name ) );
    $rows = $wpdb->get_row( $sql );

    return $rows;
}

function geodir_rest_get_listing_sorting( $post_type ) {
    $sort_options = geodir_get_sort_options( $post_type );
    
    $sorting = array();
    
    $default_orderby = 'post_date';
    $default_order = 'desc';
    
    if ( !empty( $sort_options ) ) {
        foreach ( $sort_options as $sort ) {
            $sort = stripslashes_deep( $sort ); // strip slashes
            
            $field_name = $sort->htmlvar_name;
            $label = __( $sort->site_title, 'geodirectory' );
            
            if ( $sort->field_type == 'random' ) {
                $field_name = 'random';
            }
            
            if ( $sort->htmlvar_name == 'comment_count' ) {
                $field_name = 'rating_count';
            }
            
            if ( (int)$sort->is_default == 1 ) {
                $default_order = $sort->sort_desc ? 'desc' : 'asc';
                $default_orderby = $field_name . '_' . $default_order;
            }
            
            if ( $sort->sort_asc ) {
                $label = $sort->asc_title ? __( $sort->asc_title ) : $label;
                $sorting[$field_name . '_asc'] = $label;
            }
            
            if ( $sort->sort_desc ) {
                $label = $sort->desc_title ? __( $sort->desc_title ) : $label;
                $sorting[$field_name . '_desc'] = $label;
            }
            
            if ( $field_name == 'random' ) {
                $label = $sort->desc_title ? __( $sort->desc_title ) : $label;
                $sorting['random'] = $label;
            }
            
            $orderby[] = $field_name ;
        }
    }
    
    $default_orderby = apply_filters( 'geodir_rest_get_listing_sorting_default_orderby', $default_orderby, $post_type );
    $default_order = apply_filters( 'geodir_rest_get_listing_sorting_default_order', $default_order, $post_type );
    $sorting = apply_filters( 'geodir_rest_get_listing_sorting', $sorting, $post_type );

    return array( 'sorting' => $sorting, 'default_sortby' => $default_orderby, 'default_sort' => $default_order );
}

function geodir_rest_advance_fields_to_schema( $schema, $post_type, $package_id, $default ) {
    // Lisitng tags
    $tag_taxonomy = $post_type . '_tags';
    
    $schema[ $tag_taxonomy ]['type']          = 'object';
    $schema[ $tag_taxonomy ]['arg_options']   = array(
        'sanitize_callback' => null,
    );
    $schema[ $tag_taxonomy ]['properties']    = array(
        'raw' => array(
            'description' => __( 'Field value for the object, as it exists in the database.' ),
            'type'        => 'array',
            'context'     => array( 'edit' ),
            'items'       => array( 'type' => 'string' )
        ),
        'rendered' => array(
            'description' => __( 'Field value for the object, transformed for display.' ),
            'type'        => 'string',
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        ),
    );
    
    // Payment manager fields
    if ( geodir_rest_is_active( 'payment' ) ) {
        $default_package        = geodir_get_default_package( $post_type );
        
        $schema['package_id'] = array(
            'type'           => 'integer',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'Select package' ),
            'description'    => __( 'Select a lisitng package.' ),
            'required'       => true,
            'default'        => !empty( $default_package->pid ) ? (int)$default_package->pid : 0,
        );
    }
    
    // Event manager fields
    if ( $post_type == 'gd_event' && geodir_rest_is_active( 'event' ) ) {        
        $event_recurring = geodir_rest_is_active( 'event_recurring' );
        
        if ( $event_recurring ) {
            $schema['is_recurring'] = array(
                'type'           => 'integer',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Is Recurring?' ),
                'description'    => __( 'Listing is recurring or not?' ),
                'required'       => true,
                'default'        => 0,
                'enum'           => array( 1, 0 ),
                'items'          => array( 'type' => 'integer' ),
            );
        }
        
        $schema['event_start'] = array(
            'type'           => 'string',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'Start Date' ),
            'description'    => __( 'Select event start date' ),
        );
        
        $schema['event_end'] = array(
            'type'           => 'string',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'End Date' ),
            'description'    => __( 'Select event end date' ),
        );
        
        if ( $event_recurring ) {
            $schema['repeat_type'] = array(
                'type'           => 'string',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Repeats' ),
                'description'    => __( 'Repeat type' ),
                'default'        => 'custom',
                'enum'           => array( 'day', 'week', 'month', 'year', 'custom' ),
                'items'          => array( 'type' => 'string' ),
            );
            
            $schema['repeat_days'] = array(
                'type'           => 'array',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Repeat on' ),
                'description'    => __( 'Repeat days' ),
                'enum'           => array( 1, 2, 3, 4, 5, 6, 0 ),
                'items'          => array( 'type' => 'integer' ),
            );
            
            $schema['repeat_weeks'] = array(
                'type'           => 'array',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Repeat by' ),
                'description'    => __( 'Repeat weeks' ),
                'enum'           => array( 1, 2, 3, 4, 5 ),
                'items'          => array( 'type' => 'integer' ),
            );
            
            $schema['event_recurring_dates'] = array(
                'type'           => 'string',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Event Recurring Dates' ),
                'description'    => __( 'Select event recurring dates' ),
            );
            
            $repeat_x = array();
            for ( $i = 1; $i <= 30; $i++ ) {
                $repeat_x[] = $i;
            }
            
            
            $schema['repeat_x'] = array(
                'type'           => 'array',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Repeat interval' ),
                'description'    => __( 'Event repeat interval' ),
                'enum'           => $repeat_x,
                'default'        => 1,
                'items'          => array( 'type' => 'integer' ),
            );
            
            $schema['duration_x'] = array(
                'type'           => 'integer',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Event duration' ),
                'description'    => __( 'Event duration days' ),
                'default'        => 1,
            );
            
            $schema['repeat_end_type'] = array(
                'type'           => 'integer',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Recurring end type' ),
                'description'    => __( 'Select recurring end type' ),
                'default'        => 0,
                'enum'           => array( 1, 0 ),
                'items'          => array( 'type' => 'integer' ),
            );
            
            $schema['max_repeat'] = array(
                'type'           => 'integer',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Max Repeat' ),
                'description'    => __( 'Select max event repeat times' ),
                'default'        => 1,
            );
            
            $schema['repeat_end'] = array(
                'type'           => 'string',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Repeat end on' ),
                'description'    => __( 'Select repeat end date' ),
            );
        }
        
        $schema['all_day'] = array(
            'type'           => 'integer',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'All day' ),
            'description'    => __( 'Select to set event for all day' ),
            'required'       => true,
            'default'        => 0,
            'enum'           => array( 1, 0 ),
            'items'          => array( 'type' => 'integer' ),
        );
        
        $schema['starttime'] = array(
            'type'           => 'string',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'Start Time' ),
            'description'    => __( 'Select event start time' ),
        );
        
        $schema['endtime'] = array(
            'type'           => 'string',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'End Time' ),
            'description'    => __( 'Select event end time' ),
        );
            
        if ( $event_recurring ) {
            $schema['different_times'] = array(
                'type'           => 'integer',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Different Event Times' ),
                'description'    => __( 'Select to different dates have different start and end times' ),
                'required'       => true,
                'default'        => 0,
                'enum'           => array( 1, 0 ),
                'items'          => array( 'type' => 'integer' ),
            );
            
            $schema['starttimes'] = array(
                'type'           => 'array',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'Start Times' ),
                'description'    => __( 'Select event start times' ),
                'items'          => array( 'type' => 'string' ),
            );
            
            $schema['endtimes'] = array(
                'type'           => 'array',
                'context'        => array( 'view', 'edit' ),
                'title'          => __( 'End Times' ),
                'description'    => __( 'Select event end times' ),
                'items'          => array( 'type' => 'string' ),
            );
        }
        
        $schema['recurring_dates'] = array(
            'type'           => 'string',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'Recurring dates' ),
            'description'    => __( 'Select recurring dates' ),
            'readonly'       => true,
        );
        
        $schema['event_reg_desc'] = array(
            'type'           => 'string',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'Registration Info' ),
            'description'    => __( 'Basic HTML tags are allowed.' ),
        );
        
        $schema['event_reg_fees'] = array(
            'type'           => 'number',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'Registration Fees' ),
            'description'    => __( 'Enter registration fees.' ),
            'required'       => false,
        );
    }
    
    // Claim manager fields
    if ( geodir_rest_is_active( 'claim' ) ) {
        $schema['claimed'] = array(
            'type'           => 'integer',
            'context'        => array( 'view', 'edit' ),
            'title'          => __( 'Is Claimed?' ),
            'description'    => __( 'Listing is claimed or not?' ),
            'required'       => true,
            'default'        => 0,
            'enum'           => array( 1, 0 ),
            'items'          => array( 'type' => 'integer' ),
        );
    }
    
    return $schema;
}
add_filter( 'geodir_listing_item_schema', 'geodir_rest_advance_fields_to_schema', 10, 4 );

function geodir_rest_listing_collection_params( $params, $post_type_obj ) {
    $post_type          = $post_type_obj->name;
    
    // Listing sorting
    if ( !empty( $params['order'] ) || !empty( $params['orderby'] ) ) {
        $sort_options       = geodir_rest_get_listing_sorting( $post_type );
        
        $orderby            = array_keys( $sort_options['sorting'] );
        $default_orderby    = $sort_options['default_sortby'];
        $default_order      = $sort_options['default_sort'];
        
        $params['order']['default']     = $default_order;
        $params['orderby']['enum']      = $orderby;
        $params['orderby']['default']   = $default_orderby;
    }
    
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
    
    if ( $post_type == 'gd_event' ) {
        $params['event_type'] = array(
            'default'           => get_option('geodir_event_defalt_filter'),
            'description'       => __( 'Limit events to specific event type.' ),
            'type'              => 'array',
            'items'             => array(
                'enum'          => array( 'all', 'today', 'upcoming', 'past' ),
                'type'          => 'string',
            ),
            'sanitize_callback'  => 'sanitize_text_field',
        );
    }
        
    return $params;
}

/**
 * Filters the query arguments for a request.
 *
 * Enables adding extra arguments or setting defaults for a post collection request.
 *
 * @since 1.0.0
 *
 * @link https://developer.wordpress.org/reference/classes/wp_query/
 *
 * @param array           $args    Key value array of query var to query value.
 * @param WP_REST_Request $request The request used.
 */
function geodir_rest_listing_query( $args, $request ) {
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
    
    if ( geodir_rest_is_active( 'event' ) ) {        
        if ( !empty( $request['event_type'] ) ) {
            $args['event_type'] = $request['event_type'];
        }
    }
    
    if ( !empty( $request['country'] ) ) {
        $args['gd_country'] = $request['country'];
    }
    
    $keep_args = array( 'snear', 'all_near_me', 'near_me_range', 'user_lat', 'user_lon', 'my_location' );
    
    foreach ( $keep_args as $arg ) {
        if ( !isset( $args[$arg] ) && isset( $request[$arg] ) ) {
            $args[$arg] = $request[$arg]; 
        }
    }
    
    add_filter( 'posts_clauses_request', 'geodir_rest_listing_posts_clauses_request', 10, 2 );

    return $args;
}

function geodir_rest_listing_posts_clauses_request( $clauses, $WP_Query ) {
    global $wpdb;
    
    $query_vars = $WP_Query->query_vars;
    $post_type  = !empty( $query_vars['post_type'] ) ? $query_vars['post_type'] : '';
    
    if ( empty( $query_vars['is_gd_api'] ) || empty( $post_type ) ) {
        return $clauses;
    }
    
    $clauses['where']       = apply_filters( 'geodir_rest_listing_posts_clauses_where', $clauses['where'], $post_type, $query_vars );
    $clauses['groupby']     = apply_filters( 'geodir_rest_listing_posts_clauses_groupby', $clauses['groupby'], $post_type, $query_vars );
    $clauses['join']        = apply_filters( 'geodir_rest_listing_posts_clauses_join', $clauses['join'], $post_type, $query_vars );
    $clauses['orderby']     = apply_filters( 'geodir_rest_listing_posts_clauses_orderby', $clauses['orderby'], $post_type, $query_vars );
    $clauses['distinct']    = apply_filters( 'geodir_rest_listing_posts_clauses_distinct', $clauses['distinct'], $post_type, $query_vars );
    $clauses['fields']      = apply_filters( 'geodir_rest_listing_posts_clauses_fields', $clauses['fields'], $post_type, $query_vars );
    $clauses['limits']      = apply_filters( 'geodir_rest_listing_posts_clauses_limits', $clauses['limits'], $post_type, $query_vars );

    return apply_filters( 'geodir_rest_listing_posts_clauses_request', $clauses, $post_type, $query_vars );
}

function geodir_rest_listing_posts_clauses_orderby( $orderby, $post_type, $query_vars ) {
    $default_orderby = $orderby;
    $table = geodir_rest_post_table( $post_type );
    
    $sorting = !empty( $query_vars['orderby'] ) ? $query_vars['orderby'] : geodir_get_posts_default_sort( $post_type );

    if ( !empty( $query_vars['s'] ) ) {
        if ( !empty( $query_vars['snear'] ) && $sorting != 'farthest' ) {
            $sorting = 'nearest';
        }
    }

    switch ( $sorting ) {
        case 'az':
            $sort_by = 'post_title_asc';
            break;
        case 'za':
            $sort_by = 'post_title_desc';
            break;
        case 'newest':
            $sort_by = 'post_date_desc';
            break;
        case 'oldest':
            $sort_by = 'post_date_asc';
            break;
        case 'low_review':
            $sort_by = 'rating_count_asc';
            break;
        case 'high_review':
            $sort_by = 'rating_count_desc';
            break;
        case 'low_rating':
            $sort_by = 'overall_rating_asc';
            break;
        case 'high_rating':
            $sort_by = 'overall_rating_desc';
            break;
        case 'featured':
            $sort_by = 'is_featured_asc';
            break;
        case 'nearest':
            $sort_by = 'distance_asc';
            break;
        case 'farthest':
            $sort_by = 'distance_desc';
            break;
        default:
            $sort_by = $sorting;
            break;
    }
   
    $orderby = geodir_rest_listing_custom_orderby( $orderby, $sort_by, $post_type, $query_vars );

    if ( !empty( $query_vars['s'] ) ) {
        $keywords = explode( " ", $query_vars['s'] );
        
        if ( is_array( $keywords ) && $klimit = get_option( 'geodir_search_word_limit' ) ) {
            foreach ( $keywords as $kkey => $kword ) {
                if ( mb_strlen( $kword, 'UTF-8' ) <= $klimit ) {
                    unset( $keywords[$kkey] );
                }
            }
        }
        
        if ( $sorting == 'nearest' || $sorting == 'farthest' ) {
            if ( count( $keywords ) > 1 ) {
                $orderby = $orderby . " ( gd_titlematch * 2 + gd_featured * 5 + gd_exacttitle * 10 + gd_alltitlematch_part * 100 + gd_titlematch_part * 50 + gd_content * 1.5 ) DESC, ";
            } else {
                $orderby = $orderby . " ( gd_titlematch * 2 + gd_featured * 5 + gd_exacttitle * 10 + gd_content * 1.5 ) DESC, ";
            }
        } else {
            if ( count( $keywords ) > 1 ) {
                $orderby = "( gd_titlematch * 2 + gd_featured * 5 + gd_exacttitle * 10 + gd_alltitlematch_part * 100 + gd_titlematch_part * 50 + gd_content * 1.5 ) DESC, " . $orderby;
            } else {
                $orderby = "( gd_titlematch * 2 + gd_featured * 5 + gd_exacttitle * 10 + gd_content * 1.5 ) DESC, " . $orderby;
            }
        }
    }

    return apply_filters( 'geodir_rest_listing_posts_orderby_query', $orderby, $sorting, $default_orderby, $post_type, $query_vars );
}
add_filter( 'geodir_rest_listing_posts_clauses_orderby', 'geodir_rest_listing_posts_clauses_orderby', 100, 3 );

function geodir_rest_listing_events_orderby_query( $orderby, $sorting, $default_orderby, $post_type, $query_vars ) {
    global $wpdb;
    
    if ( empty( $orderby ) ) {
        return $orderby;
    }
    
    $table = geodir_rest_post_table( $post_type );
    $orderby = rtrim( trim( $orderby ), "," );
    
    if ( $post_type == 'gd_event' ) {
        $orderby .= ", " . EVENT_SCHEDULE . ".event_date ASC, " . EVENT_SCHEDULE . ".event_starttime ASC";
    }
   
    if ( strpos( $orderby, strtolower( $table . ".is_featured" )  ) === false ) {
        $orderby .= ", " . $table . ".is_featured ASC";
    }
    
    if ( strpos( $orderby, strtolower( $wpdb->posts . ".post_date" )  ) === false ) {
        $orderby .= ", " . $wpdb->posts . ".post_date DESC";
    }
    
    if ( strpos( $orderby, strtolower( $wpdb->posts . ".post_title" )  ) === false ) {
        $orderby .= ", " . $wpdb->posts . ".post_title ASC";
    }
    
    return $orderby;
}
add_filter( 'geodir_rest_listing_posts_orderby_query', 'geodir_rest_listing_events_orderby_query', 10, 5 );

function geodir_rest_listing_custom_orderby( $orderby, $sorting, $post_type, $query_vars ) {
    global $wpdb;

    if ( $sorting != '' ) {
        if ( $sorting == 'random' ) {
            $orderby = "RAND(), ";
        } else {
            $sorting_array = explode( '_', $sorting );

            if ( ( $count = count( $sorting_array ) ) > 1 ) {
                $table = geodir_rest_post_table( $post_type );
                $order = !empty( $sorting_array[$count - 1] ) ? strtoupper( $sorting_array[$count - 1] ) : '';

                array_pop( $sorting_array );
                
                if ( !empty( $sorting_array ) && ( $order == 'ASC' || $order == 'DESC' ) ) {
                    $sort_by = implode( '_', $sorting_array );
                    
                    switch ( $sort_by ) {
                        case 'post_title':
                        case 'post_date':
                            $orderby = "$wpdb->posts." . $sort_by . " " . $order . ", ";
                        break;
                        case 'comment_count':
                            $orderby = "$wpdb->posts." . $sort_by . " " . $order . ", ".$table . ".overall_rating " . $order . ", ";
                        break;
                        case 'overall_rating':
                            $orderby = $table . "." . $sort_by . " " . $order . ", " . $table . ".rating_count " . $order . ", ";
                        break;
                        case 'rating_count':
                            $orderby = $table . "." . $sort_by . " " . $order . ", " . $table . ".overall_rating " . $order . ", ";
                        break;
                        case 'distance':
                            $orderby = $sort_by . " " . $order . ", ";
                        break;
                        case 'random':
                            $orderby = "RAND(), ";
                        break;
                        default:
                            if ( geodir_column_exist( $table, $sort_by ) ) {
                                $orderby = $table . "." . $sort_by . " " . $order . ", ";
                            }
                        break;
                    }
                }
            }
        }
    }
    
    return $orderby;
}

function geodir_rest_listing_posts_clauses_fields( $fields, $post_type, $query_vars ) {
    global $wpdb;
    
    if ( empty( $query_vars['is_gd_api'] ) || empty( $post_type ) ) {
        return $fields;
    }
    
    $table      = geodir_rest_post_table( $post_type );
    
    $fields     = $fields != '' ? $fields . ", " : '';
    
    $fields     .= $table . ".*";
    
    if ( $post_type == 'gd_event' && geodir_rest_is_active( 'event' ) ) {
        $fields .= ", " . EVENT_SCHEDULE . ".*";
    }
    
    $snear = isset( $query_vars['snear'] ) ? trim( $query_vars['snear'] ) : '';
        
    if ( ( $snear != '' || !empty( $query_vars['all_near_me'] ) ) ) {
        $distance_radius = geodir_getDistanceRadius( get_option( 'geodir_search_dist_1' ) );
        
        $my_lat = !empty( $query_vars['user_lat'] ) ? $query_vars['user_lat'] : '';
        $my_lon = !empty( $query_vars['user_lon'] ) ? $query_vars['user_lon'] : '';
        
        if ( empty( $my_lat ) || empty( $my_lon ) ) {
            $default_location = geodir_get_default_location();
            
            $my_lat = !empty( $default_location->city_latitude ) ? $default_location->city_latitude : '0';
            $my_lon = !empty( $default_location->city_longitude ) ? $default_location->city_longitude : '0';
        }

        $fields .= ", ( " . $distance_radius . " * 2 * ASIN( SQRT( POWER( SIN( ( ABS( " . $my_lat . " ) - ABS( " . $table . ".post_latitude ) ) * PI() / 180 / 2 ), 2 ) + COS( ABS( " . $my_lat . " ) * PI() / 180 ) * COS( ABS( " . $table . ".post_latitude ) * PI() / 180 ) * POWER( SIN( ( " . $my_lon . " - " . $table . ".post_longitude ) * PI() / 180 / 2 ), 2 ) ) ) ) AS distance";
    }
    
    if ( !empty( $query_vars['s'] ) && $search = $query_vars['s'] ) {
        $keywords = explode( ' ', $search );
        
        if ( is_array( $keywords ) && $klimit = get_option( 'geodir_search_word_limit' ) ) {
            foreach( $keywords as $kkey => $kword ) {
                if ( mb_strlen( $kword, 'UTF-8' ) <= $klimit ) {
                    unset( $keywords[$kkey] );
                }
            }
        }
    
        $gd_titlematch_part = '';
        
        if ( count( $keywords ) > 1 ) {
            $parts = array( 'AND' => 'gd_alltitlematch_part', 'OR' => 'gd_titlematch_part' );
            
            foreach ( $parts as $key => $part ) {
                $gd_titlematch_part .= " CASE WHEN ";
                $count = 0;
                
                foreach ( $keywords as $keyword ) {
                    $keyword = wp_specialchars_decode( trim( $keyword ), ENT_QUOTES );
                    $count++;
                    
                    if ( $count < count( $keywords ) ) {
                        $gd_titlematch_part .= "( " . $wpdb->posts . ".post_title LIKE '" . $keyword . "' OR " . $wpdb->posts . ".post_title LIKE '" . $keyword . "%%' OR " . $wpdb->posts . ".post_title LIKE '%% " . $keyword . "%%' ) " . $key . " ";
                    } else {
                        $gd_titlematch_part .= "( " . $wpdb->posts . ".post_title LIKE '" . $keyword . "' OR " . $wpdb->posts . ".post_title LIKE '" . $keyword . "%%' OR " . $wpdb->posts . ".post_title LIKE '%% " . $keyword . "%%' ) ";
                    }
                }
                
                $gd_titlematch_part .= "THEN 1 ELSE 0 END AS " . $part . ",";
            }
        }
        
        $search = stripslashes_deep( $search );
        $search = wp_specialchars_decode( $search, ENT_QUOTES );
        
        $fields .= $wpdb->prepare( ", CASE WHEN " . $table . ".is_featured = '1' THEN 1 ELSE 0 END AS gd_featured, CASE WHEN " . $wpdb->posts . ".post_title LIKE %s THEN 1 ELSE 0 END AS gd_exacttitle, " . $gd_titlematch_part . " CASE WHEN ( " . $wpdb->posts . ".post_title LIKE %s OR " . $wpdb->posts . ".post_title LIKE %s OR " . $wpdb->posts . ".post_title LIKE %s ) THEN 1 ELSE 0 END AS gd_titlematch, CASE WHEN ( " . $wpdb->posts . ".post_content LIKE %s OR " . $wpdb->posts . ".post_content LIKE %s OR " . $wpdb->posts . ".post_content LIKE %s OR " . $wpdb->posts . ".post_content LIKE %s ) THEN 1 ELSE 0 END AS gd_content", array( $search, $search, $search . '%', '% ' . $search . '%', $search, $search . ' %', '% ' . $search . ' %', '% ' . $search ) );
    }
    
    return $fields;
}
add_filter( 'geodir_rest_listing_posts_clauses_fields', 'geodir_rest_listing_posts_clauses_fields', 10, 3 );

function geodir_rest_listing_posts_clauses_join( $join, $post_type, $query_vars ) {
    global $wpdb;
    
    if ( empty( $query_vars['is_gd_api'] ) || empty( $post_type ) ) {
        return $join;
    }
    
    $table = geodir_rest_post_table( $post_type );
        
    ########### WPML ###########
    if ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
        $join .= " JOIN " . $wpdb->prefix . "icl_translations AS icl_t ON icl_t.element_id = " . $wpdb->posts . ".ID";
    }
    ########### WPML ###########

    $join .= " INNER JOIN " . $table . " ON ( " . $table . ".post_id = " . $wpdb->posts . ".ID )";
    
    if ( $post_type == 'gd_event' && geodir_rest_is_active( 'event' ) ) {
        $join .= " INNER JOIN " . EVENT_SCHEDULE ." ON ( " . EVENT_SCHEDULE . ".event_id = " . $wpdb->posts . ".ID )";
    }
    
    return $join;
}
add_filter( 'geodir_rest_listing_posts_clauses_join', 'geodir_rest_listing_posts_clauses_join', 10, 3 );

function geodir_rest_listing_location_where( $where, $post_type, $query_vars ) {
    global $wpdb;
    
    if ( empty( $query_vars['is_gd_api'] ) || empty( $post_type ) || empty( $query_vars['gd_location'] ) ) {
        return $where;
    }
    
    $allow_filter_location  = apply_filters( 'geodir_rest_listing_allow_filter_location', true, $post_type, $query_vars );
    
    if ( !$allow_filter_location ) {
        return $where;
    }
    
    $listing_table      = geodir_rest_post_table( $post_type );
    
    $gd_country         = !empty( $query_vars['gd_country'] ) ? $query_vars['gd_country'] : '';
    $gd_region          = !empty( $query_vars['gd_region'] ) ? $query_vars['gd_region'] : '';
    $gd_city            = !empty( $query_vars['gd_city'] ) ? $query_vars['gd_city'] : '';
    $gd_neighbourhood   = !empty( $query_vars['gd_neighbourhood'] ) ? $query_vars['gd_neighbourhood'] : '';
    
    if ( $gd_country || $gd_region || $gd_city ) {
        $post_locations = array();
        
        if ( !empty( $gd_city ) && !empty( $gd_region ) && !empty( $gd_country ) ) {
            $post_locations[] = "[" . $gd_city . "],[" . $gd_region . "],[" . $gd_country . "]";
        } else if ( !empty( $gd_city ) && !empty( $gd_region ) && empty( $gd_country ) ) {
            $post_locations[] = "[" . $gd_city . "],[" . $gd_region . "],%";
        } else if ( !empty( $gd_city ) && empty( $gd_region ) && !empty( $gd_country ) ) {
            $post_locations[] = "[" . $gd_city . "],%";
            $post_locations[] = "%,[" . $gd_country . "]";
        } else if ( empty( $gd_city ) && !empty( $gd_region ) && !empty( $gd_country ) ) {
            $post_locations[] = "%,[" . $gd_region . "],[" . $gd_country . "]";
        } else if ( !empty( $gd_city ) && empty( $gd_region ) && empty( $gd_country ) ) {
            $post_locations[] = "[" . $gd_city . "],%";
        } else if ( empty( $gd_city ) && !empty( $gd_region ) && empty( $gd_country ) ) {
            $post_locations[] = "%,[" . $gd_region . "],%";
        } else if ( empty( $gd_city ) && empty( $gd_region ) && !empty( $gd_country ) ) {
            $post_locations[] = "%,[" . $gd_country . "]";
        }
    
        foreach ( $post_locations as $post_location ) {
            $where .= " AND " . $listing_table . ".post_locations LIKE '" . $post_location . "'";
        }
    }
    
    if ( !empty( $gd_neighbourhood ) ) {
        if ( is_array( $gd_neighbourhood ) ) {
            $gd_neighbourhoods = array_fill( 0, count( $gd_neighbourhood ), '%s' );
            $gd_neighbourhoods = implode( ',', $gd_neighbourhoods );

            $where .= $wpdb->prepare( " AND " . $listing_table . ".post_neighbourhood IN (" . $gd_neighbourhoods . ")", $gd_neighbourhood );
        } else {
            $where .= $wpdb->prepare( " AND " . $listing_table . ".post_neighbourhood LIKE %s", $gd_neighbourhood );
        }
    }
    
    return $where;
}
add_filter( 'geodir_rest_listing_posts_clauses_where', 'geodir_rest_listing_location_where', 10, 3 );

function geodir_rest_listing_events_filter( $where, $post_type, $query_vars ) {
    global $wpdb;
    
    if ( empty( $query_vars['is_gd_api'] ) || $post_type != 'gd_event' || empty( $query_vars['event_type'] ) ) {
        return $where;
    }
    
    $today = date_i18n( 'Y-m-d' );
    
    if ( $query_vars['event_type'] == 'today' ) {
        $where .= " AND ( " . EVENT_SCHEDULE . ".event_date LIKE '" . $today . "%%' OR ( " . EVENT_SCHEDULE . ".event_date <= '" . $today . "' AND " . EVENT_SCHEDULE . ".event_enddate >= '" . $today . "' ) ) ";
    } else if ( $query_vars['event_type'] == 'upcoming' ) {
        $where .= " AND ( " . EVENT_SCHEDULE . ".event_date >= '" . $today . "' OR ( " . EVENT_SCHEDULE . ".event_date <= '" . $today . "' AND " . EVENT_SCHEDULE . ".event_enddate >= '" . $today . "' ) ) ";
    } else if ( $query_vars['event_type'] == 'past' ) {
        $where .= " AND " . EVENT_SCHEDULE . ".event_date < '" . $today . "' ";
    }
    
    return $where;
}
add_filter( 'geodir_rest_listing_posts_clauses_where', 'geodir_rest_listing_events_filter', 11, 3 );