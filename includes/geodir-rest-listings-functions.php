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
    if ( isset( $post->distance ) && $post->distance !== '' ) {
        $unit_search = get_option('geodir_search_dist_1');
        $distance = round((float)$post->distance, 2);
        
        if ($distance == 0) {
            $unit_near = get_option('geodir_search_dist_2');
            
            if ( $unit_near == 'feet' ) {
                $unit = __('feet', 'geodirectory');
                $multiply = $unit_search == 'miles' ? 5280 : 3280.84;
            } else {
                $unit = __('meters', 'geodirectory');
                $multiply = $unit_search == 'miles' ? 1609.34 : 1000;
            }
            
            $distance = round((float)$post->distance * $multiply);
        } else {
            if ( $unit_search == 'miles' ) {
                $unit = __('miles', 'geodirectory');
            } else {
                $unit = __('km', 'geodirectory');
            }
        }
        
        $data['distance'] = array(
            'raw' => $post->distance,
            'rendered' => $distance . ' ' . $unit 
        );
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
            'description'    => __( 'Select a listing package.' ),
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
            'default'        => '0',
            'enum'           => array( '1', '0' ),
            'items'          => array( 'type' => 'integer' ),
        );
    }
    
    return $schema;
}
add_filter( 'geodir_listing_item_schema', 'geodir_rest_advance_fields_to_schema', 10, 4 );

function geodir_rest_listing_collection_params( $params, $post_type_obj ) {
    global $geodir_search_fields;
    
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
    
    // Filter options
    $params['featured_only'] = array(
        'description'  => __( 'Whether to show only featured listings.' ),
        'type'         => 'boolean',
        'default'      => false,
    );
    
    $params['pics_only'] = array(
        'description'  => __( 'Whether to show only listings with photos.' ),
        'type'         => 'boolean',
        'default'      => false,
    );
    
    $params['videos_only'] = array(
        'description'  => __( 'Whether to show only listings with videos.' ),
        'type'         => 'boolean',
        'default'      => false,
    );
    
    $params['special_only'] = array(
        'description'  => __( 'Whether to show only listings with special offers.' ),
        'type'         => 'boolean',
        'default'      => false,
    );
    
    $params['favorites_only'] = array(
        'description'  => __( 'Whether to show only listings favorited by user.' ),
        'type'         => 'boolean',
        'default'      => false,
    );
    
    $params['favorites_by_user'] = array(
        'description'  => __( 'Filter the listings favorited by user. Should be user ID or empty.' ),
        'type'         => 'int',
        'default'      => NULL,
    );
    
    $params['latitude'] = array(
        'description'  => __( 'Filter by latitude.' ),
        'type'         => 'string',
        'default'      => NULL,
    );
    
    $params['longitude'] = array(
        'description'  => __( 'Filter by longitude.' ),
        'type'         => 'string',
        'default'      => NULL,
    );
    
    if ( geodir_rest_is_active( 'advance_search' ) ) {
        if ( empty( $geodir_search_fields[ $post_type ] ) ) {
            $fields = geodir_rest_advance_search_fields( $post_type, true );
            $geodir_search_fields[ $post_type ] = $fields;
        } else {
            $fields = $geodir_search_fields[ $post_type ];
        }

        if ( !empty( $fields ) ) {
            $search_fields = array();
            
            foreach ( $fields as $field ) {
                if ( $field->field_site_type == 'fieldset' || empty( $field->site_htmlvar_name ) || empty( $field->field_site_type ) ) {
                    continue;
                }
                $title = !empty( $field->front_search_title ) ? $field->front_search_title : $field->field_site_name;
                $description = !empty( $field->field_desc ) ? $field->field_desc : '';
                $description = !empty( $description ) ? $title . ': ' . $description : $title;
                
                $search_field = array();
                $search_field['name'] = $field->site_htmlvar_name;
                $search_field['description'] = $description;
                $search_field['type'] = 'string';
                $search_field['default'] = NULL;

                $search_field = apply_filters( 'geodir_rest_register_advance_search_field_' . $field->field_site_type, $search_field, $field );

                if ( !empty( $search_field ) ) {
                    $htmlvar_name = !empty( $search_field['name'] ) ? $search_field['name'] : $field->site_htmlvar_name;
                    $search_fields[ $htmlvar_name ] = $search_field;
                    if ( !empty( $search_field['append'] ) ) {
                        $search_fields[ $search_field['append']['name'] ] = $search_field['append'];
                    }
                }
            }
            
            if ( !empty( $search_fields ) ) {
                $search_fields = apply_filters( 'geodir_rest_register_advance_search_fields', $search_fields, $post_type );
                $params = array_merge( $params, $search_fields );
            }
        }
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
    
    $keep_args = array( 'sdist', 'sort_by', 'latitude', 'longitude' ); // GPS search
    
    foreach ( $keep_args as $arg ) {
        if ( !isset( $args[$arg] ) && isset( $request[$arg] ) ) {
            $args[$arg] = $request[$arg]; 
        }
    }

    add_filter( 'posts_clauses_request', 'geodir_rest_listing_posts_clauses_request', 10, 2 );

    return apply_filters( 'geodir_rest_listing_query', $args, $request );
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
    if ( !empty($query_vars['sort_by']) && in_array($query_vars['sort_by'], array('nearest', 'farthest')) ) {
        $sorting = $query_vars['sort_by'];
    }
    
    if ( ($sorting == 'nearest' || $sorting == 'farthest') && (empty($query_vars['latitude']) || empty($query_vars['longitude'])) ) {
        $sorting = geodir_get_posts_default_sort( $post_type );
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
                if ( geodir_utf8_strlen( $kword, 'UTF-8' ) <= $klimit ) {
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
    
    $my_lat = !empty( $query_vars['latitude'] ) ? $query_vars['latitude'] : '';
    $my_lon = !empty( $query_vars['longitude'] ) ? $query_vars['longitude'] : '';
        
    if ( !empty($my_lat) && !empty($my_lon) ) {
        $distance_radius = geodir_getDistanceRadius( get_option( 'geodir_search_dist_1' ) );

        $fields .= ", ( " . $distance_radius . " * 2 * ASIN( SQRT( POWER( SIN( ( ABS( " . $my_lat . " ) - ABS( " . $table . ".post_latitude ) ) * PI() / 180 / 2 ), 2 ) + COS( ABS( " . $my_lat . " ) * PI() / 180 ) * COS( ABS( " . $table . ".post_latitude ) * PI() / 180 ) * POWER( SIN( ( " . $my_lon . " - " . $table . ".post_longitude ) * PI() / 180 / 2 ), 2 ) ) ) ) AS distance";
    }
    
    if ( !empty( $query_vars['s'] ) && $search = $query_vars['s'] ) {
        $keywords = explode( ' ', $search );
        
        if ( is_array( $keywords ) && $klimit = get_option( 'geodir_search_word_limit' ) ) {
            foreach( $keywords as $kkey => $kword ) {
                if ( geodir_utf8_strlen( $kword, 'UTF-8' ) <= $klimit ) {
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
    if ( geodir_wpml_is_post_type_translated( $post_type ) && defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
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

function geodir_rest_listing_posts_custom_fields_where( $where, $post_type, $query_vars ) {
    global $wpdb, $geodir_api_request, $geodir_search_fields;
    
    if ( empty( $query_vars['is_gd_api'] ) || empty( $post_type ) || empty( $geodir_api_request ) ) {
        return $where;
    }
    
    $get_params = $geodir_api_request->get_query_params();
    $post_params = $geodir_api_request->get_body_params();
        
    $listing_table = geodir_rest_post_table( $post_type );
    
    $location_allowed = function_exists( 'geodir_cpt_no_location' ) && geodir_cpt_no_location( $post_type ) ? false : true;
    if ( $location_allowed && !empty($query_vars['latitude']) && !empty($query_vars['longitude']) ) {
        $latitude = $query_vars['latitude'];
        $longitude = $query_vars['longitude'];
        
        $near_me_range = !empty($query_vars['sdist']) && (float)$query_vars['sdist'] > 0 ? (float)$query_vars['sdist'] : (float)get_option('geodir_search_dist');
        $near_me_range = $near_me_range > 0 ? $near_me_range : 40;
        
        $distance_radius = geodir_getDistanceRadius(get_option('geodir_search_dist_1'));

        $where .= " AND ( ( " . $distance_radius . " * 2 * ASIN( SQRT( POWER( SIN( ( ABS( " . $latitude . " ) - ABS( " . $listing_table . ".post_latitude ) ) * PI() / 180 / 2 ), 2 ) + COS( ABS( " . $latitude . " ) * PI() / 180 ) * COS( ABS( " . $listing_table . ".post_latitude ) * PI() / 180 ) * POWER( SIN( ( " . $longitude . " - " . $listing_table . ".post_longitude ) * PI() / 180 / 2 ), 2 ) ) ) ) <= " . $near_me_range . " ) ";
    }
    
    if ( !empty( $query_vars['featured_only'] ) ) {
        $where .= " AND " . $listing_table . ".is_featured = '1'";
    }
    
    if ( !empty( $query_vars['pics_only'] ) ) {
        $where .= " AND " . $listing_table . ".featured_image != '' AND " . $listing_table . ".featured_image IS NOT NULL";
    }
    
    if ( !empty( $query_vars['videos_only'] ) ) {
        $where .= " AND " . $listing_table . ".geodir_video != '' AND " . $listing_table . ".geodir_video IS NOT NULL";
    }
    
    if ( !empty( $query_vars['special_only'] ) ) {
        $where .= " AND " . $listing_table . ".geodir_special_offers != '' AND " . $listing_table . ".geodir_special_offers IS NOT NULL";
    }
    
    if ( geodir_rest_is_active( 'advance_search' ) && ( !empty( $get_params ) || !empty( $post_params ) ) ) {
        if ( empty( $geodir_search_fields[ $post_type ] ) ) {
            $fields = geodir_rest_advance_search_fields( $post_type, true );
            $geodir_search_fields[ $post_type ] = $fields;
        } else {
            $fields = $geodir_search_fields[ $post_type ];
        }
        
        if ( !empty( $fields ) ) {
            $adv_search_where = '';
            
            foreach ( $fields as $field ) {
                $htmlvar_name = !empty( $field->site_htmlvar_name ) ? $field->site_htmlvar_name : '';
                $input_type = !empty( $field->field_input_type ) ? strtoupper( $field->field_input_type ) : '';
                $search_condition = !empty( $field->search_condition ) ? strtoupper( $field->search_condition ) : '';
                $data_type = !empty( $field->field_data_type ) ? strtoupper( $field->field_data_type ) : '';
                $extra_fields = !empty( $field->extra_fields ) ? $field->extra_fields : array();
                $search_operator = !empty( $extra_fields ) && !empty( $extra_fields['search_operator'] ) && $extra_fields['search_operator'] == 'OR' ? 'OR' : 'AND';

                $skip_fields = apply_filters( 'geodir_rest_get_listing_search_skip_fields', array( 'dist' ), $post_type );
                
                if ( empty( $htmlvar_name ) || (!empty($htmlvar_name) && in_array($htmlvar_name, $skip_fields)) ) {
                    continue;
                }
                
                switch ( $input_type ) {
                    case 'RANGE': {
                        switch ( $search_condition ) {
                            case 'SINGLE':
                            case 'RADIO': {
                                if ( isset( $get_params[ 's' . $htmlvar_name ] ) && $get_params[ 's' . $htmlvar_name ] !== '' ) {
                                    $value = esc_attr( $get_params[ 's' . $htmlvar_name ] );
                                    $adv_search_where .= " AND " . $listing_table . '.' . $htmlvar_name . " = '" . $value . "'";
                                }
                            }
                            break;
                            case 'FROM': {
                                if ( isset( $get_params[ 'smin' . $htmlvar_name ] ) && $get_params[ 'smin' . $htmlvar_name ] !== '' ) {
                                    $value = esc_attr( $get_params[ 'smin' . $htmlvar_name ] );
                                    $adv_search_where .= " AND " . $listing_table . '.' . $htmlvar_name . " >= '" . $value . "'";
                                }
                                
                                if ( isset( $get_params[ 'smax' . $htmlvar_name ] ) && $get_params[ 'smax' . $htmlvar_name ] !== '' ) {
                                    $value = esc_attr( $get_params[ 'smax' . $htmlvar_name ] );
                                    $adv_search_where .= " AND " . $listing_table . '.' . $htmlvar_name . " <= '" . $value . "'";
                                }
                            }
                            break;
                            default : {
                                if ( isset( $get_params[ 's' . $htmlvar_name ] ) && $get_params[ 's' . $htmlvar_name ] !== '' ) {
                                    $value = esc_attr( $get_params[ 's' . $htmlvar_name ] );
                                    $values = explode( '-', $value );
                                    
                                    $first_value = isset( $values[0] ) ? ( $values[0] ) : '';
                                    $second_value = isset( $values[0] ) ? trim( $values[1] ) : '';
                                    
                                    $condition = $second_value != '' ? substr( $second_value, 0, 4 ) : '';
                                    
                                    if ( $condition == 'Less' ) {
                                        if ( $first_value !== '' ) {
                                            $adv_search_where .= " AND " . $listing_table . '.' . $htmlvar_name . " <= '" . $first_value . "'";
                                        }
                                    } else if ( $condition == 'More' ) {
                                        if ( $first_value !== '' ) {
                                            $adv_search_where .= " AND " . $listing_table . '.' . $htmlvar_name . " >= '" . $first_value . "'";
                                        }
                                    } else if ( $first_value !== '' && $second_value !== '' ) {
                                        $adv_search_where .= " AND " . $listing_table . '.' . $htmlvar_name . " BETWEEN '" . $first_value . "' AND '" . $second_value . "'";
                                    }
                                }
                            }
                            break;
                        }
                    }
                    break;
                    case 'DATE': {
                        $is_single = $search_condition == 'SINGLE' ? true : false;
                        $min_value = '';
                        $max_value = '';
                        
                        if ( $htmlvar_name == 'event' ) {
                            if ( isset( $get_params[ 'event_start' ] ) && $get_params[ 'event_start' ] !== '' ) {
                                $min_value = esc_attr( $get_params[ 'event_start' ] );
                            }
                            
                            if ( isset( $get_params[ 'event_end' ] ) && $get_params[ 'event_end' ] !== '' ) {
                                $max_value = esc_attr( $get_params[ 'event_end' ] );
                            }
                        } else {
                            switch ( $search_condition ) {
                                case 'SINGLE':
                                default : {
                                    if ( isset( $get_params[ 's' . $htmlvar_name ] ) && $get_params[ 's' . $htmlvar_name ] !== '' ) {
                                        $min_value = esc_attr( $get_params[ 's' . $htmlvar_name ] );
                                    }
                                }
                                break;
                                case 'FROM': {
                                    if ( isset( $get_params[ 'smin' . $htmlvar_name ] ) && $get_params[ 'smin' . $htmlvar_name ] !== '' ) {
                                        $min_value = esc_attr( $get_params[ 'smin' . $htmlvar_name ] );
                                    }
                                    
                                    if ( isset( $get_params[ 'smax' . $htmlvar_name ] ) && $get_params[ 'smax' . $htmlvar_name ] !== '' ) {
                                        $max_value = esc_attr( $get_params[ 'smax' . $htmlvar_name ] );
                                    }
                                }
                                break;
                            }
                            
                            if ( $min_value !== '' || $max_value !== '' ) {
                                $field_name = $listing_table . '.' . $htmlvar_name;
                                
                                if ( $data_type == 'TIME' ) {
                                    $min_value = $min_value !== '' ? "'" . $min_value . ":00'" : '';
                                    $max_value = $max_value !== '' ? "'" . $max_value . ":00'" : '';
                                } else {
                                    $field_name = "UNIX_TIMESTAMP( " . $listing_table . '.' . $htmlvar_name . " )";
                                    
                                    $field_info = geodir_rest_get_field_info_by_name( $htmlvar_name, $post_type, true );
                                    $date_format = !empty( $field_info->extra_fields['date_format'] ) ? $field_info->extra_fields['date_format'] : 'Y-m-d';
                                    
                                    $min_value = $min_value !== '' ? geodir_date( $min_value, 'Y-m-d', $date_format ) : '';
                                    $max_value = $max_value !== '' ? geodir_date( $max_value, 'Y-m-d', $date_format ) : '';
                                    
                                    $min_value = $min_value !== '' ? "UNIX_TIMESTAMP( " . $min_value . " )" : '';
                                    $max_value = $max_value !== '' ? "UNIX_TIMESTAMP( " . $max_value . " )" : '';
                                }
                                
                                if ( $is_single ) {
                                    $adv_search_where .= " AND " . $field_name . " = " . $min_value;
                                } else {
                                    if ( $min_value !== '' ) {
                                        $adv_search_where .= " AND " . $field_name . " >= " . $min_value;
                                    }
                                    
                                    if ( $max_value !== '' ) {
                                        $adv_search_where .= " AND " . $field_name . " <= " . $max_value;
                                    }
                                }
                            }
                        }
                    }
                    break;
                    default: {
                        $htmlvar_name = $htmlvar_name == 'post' ? 'post_address' : $htmlvar_name;
                        
                        if ( isset( $get_params[ 's' . $htmlvar_name ] ) ) {
                            $value = $get_params[ 's' . $htmlvar_name ];
                            
                            if ( is_array( $value ) && !empty( $value ) ) {
                                $search_values = array();
                                
                                foreach ( $value as $field_value ) {
                                    if ( $field_value !== '' ) {
                                        $search_values[] = $wpdb->prepare( "FIND_IN_SET( %s, " . $listing_table . "." . $htmlvar_name . " )", $field_value );
                                    }
                                }

                                if ( !empty( $search_values ) ) {
                                    $adv_search_where .= " AND ( " . implode( " " . $search_operator . " ", $search_values ) . " )";
                                }
                            } else if ( !is_array( $value ) && $value !== '' ) {
                                $adv_search_where .= " AND " . $listing_table . "." . $htmlvar_name . " LIKE '%" . esc_attr( $value ) . "%'";
                            }
                        }
                    }
                    break;
                }
            }
        }
        
        if ( !empty( $adv_search_where ) ) {
            $adv_search_where = apply_filters( 'geodir_rest_listing_advance_search_where', $adv_search_where, $post_type );
            
            $where .= $adv_search_where;
        }
    }
    
    return $where;
}
add_filter( 'geodir_rest_listing_posts_clauses_where', 'geodir_rest_listing_posts_custom_fields_where', 11, 3 );

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

function geodir_rest_get_search_field_schema( $schema, $field, $terms = array() ) {
    $post_type = $field->post_type;
    $name = $field->site_htmlvar_name;
    $field_type = !empty( $field->field_site_type ) ? strtolower( $field->field_site_type ) : '';
    $input_type = !empty( $field->field_input_type ) ? strtolower( $field->field_input_type ) : '';
    $expand_search = !empty( $field->expand_search ) && in_array( $input_type, array( 'link', 'check', 'radio', 'range' ) ) ? absint( $field->expand_search ) : 0;
    $search_condition = !empty( $field->search_condition ) ? strtolower( $field->search_condition ) : '';
    $expand_value = $field->expand_custom_value;

    switch ( $input_type ) {
        case 'check':
        case 'link':
        case 'radio':
        case 'select': {
            $field_info = geodir_rest_get_field_info_by_name( $name, $post_type, true );
            
            $values = array( '' );
            if ( $field_type == 'taxonomy' ) {
                if ( !empty( $terms ) ) {
                    $values = array_merge( $values, array_values( $terms ) );
                }
            } else {
                if ( !empty( $field_info->options ) ) {
                    foreach ( $field_info->options as $option ) {
                        if ( empty( $option['optgroup'] ) && isset( $option['value'] ) && $option['value'] !== '' ) {
                            $values[] = $option['value'];
                        }
                    }
                }
            }
            
            $schema['name'] = 's' . $name;
            if ( $input_type == 'check' ) {
                $schema['type'] = 'array';
                $schema['items'] = array(
                    'enum' => $values,
                    'type' => 'string',
                );
            } else {
                $schema['type'] = 'string';
                $schema['enum'] = $values;
            }
            $schema['default'] = "";
        }
        break;
        case 'range': {
            $min_value_f = absint( $field->search_min_value );
            $min_value = !empty( $field->search_min_value ) && (int)$field->search_min_value > 0 ? absint( $field->search_min_value ) : 10;
            $max_value = !empty( $field->search_max_value ) && (int)$field->search_max_value > 0 ? absint( $field->search_max_value ) : 50;
            $difference = !empty( $field->search_diff_value ) && (int)$field->search_diff_value > 0 ? absint( $field->search_diff_value ) : 10;
            $range_mode = $field->searching_range_mode;
            
            $i = $min_value_f;
            $j = $min_value_f;
            $k = 0;
            
            switch ( $search_condition ) {
                case 'single': {
                    $schema['name'] = 's' . $name;
                }
                break;
                case 'from': {
                    $start_text = __( 'Start search value', 'geodiradvancesearch' );
                    $end_text = __( 'End search value', 'geodiradvancesearch' );
                    
                    $schema['name'] = 'smin' . $name;
                    $schema['append'] = $schema;
                    $schema['append']['name'] = 'smax' . $name;
                    
                    $schema['description'] = !empty( $schema['description'] ) ? $schema['description'] . ' (' . $start_text . ')' : $start_text;
                    $schema['append']['description'] = !empty( $schema['append']['description'] ) ? $schema['append']['description'] . ' (' . $end_text . ')' : $end_text;
                }
                break;
                case 'link':
                case 'select': {
                    $values = array( '' );
                    
                    while ( $i <= $max_value ) {
                        if ( $k == 0 ) {
                            $value = $min_value . '-Less';
                            $k++;
                        } else {
                            if ( $i <= $max_value ) {
                                $value = $j . '-' . $i;
                                if ( $difference == 1 && $range_mode == 1 ) {
                                    $value = $j . '-Less';
                                }
                            } else {
                                $value = $j . '-' . $i;
                                if ( $difference == 1 && $range_mode == 1 ) {
                                    $value         = $j . '-Less';
                                }
                            }
                            $j = $i;
                        }
                        
                        $i = $i + $difference;
                        
                        if ( $i > $max_value ) {
                            $value = $max_value . '-More';
                        }
                        
                        $values[] = $value;
                    }
                    
                    $schema['name'] = 's' . $name;
                    $schema['type'] = 'string';
                    $schema['enum'] = $values;
                    $schema['default'] = "";
                }
                break;
                case 'radio': {
                    $values = array( '' );
                    
                    for ( $i = $difference; $i <= $max_value; $i = $i + $difference ) {
                        $values[] = (string)$i;
                    }
                    
                    $schema['name'] = 's' . $name;
                    $schema['type'] = 'string';
                    $schema['enum'] = $values;
                    
                    if ( $name == 'dist' ) {
                        $schema['default'] = (string)get_option('geodir_search_dist');
                    } else {
                        $schema['default'] = "";
                    }
                }
                break;
            }
        }
        break;
        case 'date':
        default: {
            $schema['name'] = 's' . $name;
            
            if ( $field_type == 'checkbox' || $name == 'geodir_special_offers' ) {
                $schema['type'] = 'boolean';
                $schema['default'] = false;
            }
        }
        break;
    }
    
    return $schema;
}

function geodir_rest_process_query_vars( $args, $request ) {
    global $geodir_api_request;
    
    $geodir_api_request = $request;
    
    $boolean_args = array( 'featured_only', 'pics_only', 'videos_only', 'special_only', 'favorites_only' );
    
    foreach ( $boolean_args as $arg ) {
        if ( !empty( $request[$arg] ) ) {
            $args[$arg] = (bool)$request[$arg]; 
        }
    }
    
    if ( !empty( $args['favorites_only'] ) ) {
        $args['favorites_by_user'] = !empty( $request['favorites_by_user'] ) ? absint( $request['favorites_by_user'] ) : 0;
        
        if ( $args['favorites_by_user'] > 0 ) {
        } else if ( $current_user_id = get_current_user_id() ) {
            $args['favorites_by_user'] = $current_user_id;
        } else {
            $args['favorites_by_user'] = 0;
        }
        
        $favorites = $args['favorites_by_user'] > 0 ? get_user_meta( $args['favorites_by_user'], 'gd_user_favourite_post', true ) : array();
        $favorites = !empty( $favorites ) && is_array( $favorites ) ? $favorites : array( '0' );
        
        $args['post__in'] = !empty( $args['post__in'] ) ? array_merge( $args['post__in'], $favorites ) : $favorites;
    }

    return $args;
}
add_filter( 'geodir_rest_listing_query', 'geodir_rest_process_query_vars', 10, 2 );

function geodir_rest_register_advance_search_field_address( $schema, $field ) {
    $schema = geodir_rest_get_search_field_schema( $schema, $field );
    
    if ( !empty( $schema ) ) {
        $schema['name'] = 's' . $field->site_htmlvar_name . '_address';
    }
    
    return $schema;
}
add_filter( 'geodir_rest_register_advance_search_field_address', 'geodir_rest_register_advance_search_field_address', 10, 2 );

function geodir_rest_register_advance_search_field_checkbox( $schema, $field ) {    
    return geodir_rest_get_search_field_schema( $schema, $field );
}
add_filter( 'geodir_rest_register_advance_search_field_checkbox', 'geodir_rest_register_advance_search_field_checkbox', 10, 2 );

function geodir_rest_register_advance_search_field_datepicker( $schema, $field ) {
    $name = $field->site_htmlvar_name;
    
    $start_text = __( 'Start search date', 'geodiradvancesearch' );
    $end_text = __( 'End search date', 'geodiradvancesearch' );
            
    if ( $name == 'event' ) {
        if ( $field->search_condition == 'SINGLE' ) {
            $schema['name'] = 'event_start';
        } else if ( $field->search_condition == 'FROM' ) {
            $schema['name'] = 'event_start';
            $schema['append'] = $schema;
            $schema['append']['name'] = 'event_end';
            
            $schema['description'] = !empty( $schema['description'] ) ? $schema['description'] . ' (' . $start_text . ')' : $start_text;
            $schema['append']['description'] = !empty( $schema['append']['description'] ) ? $schema['append']['description'] . ' (' . $end_text . ')' : $end_text;
        }
    } else {
        if ( $field->search_condition == 'SINGLE' ) {
            $schema['name'] = 's' . $name;
        } else if ( $field->search_condition == 'FROM' ) {
            $schema['name'] = 'smin' . $name;
            $schema['append'] = $schema;
            $schema['append']['name'] = 'smax' . $name;
            
            $schema['description'] = !empty( $schema['description'] ) ? $schema['description'] . ' (' . $start_text . ')' : $start_text;
            $schema['append']['description'] = !empty( $schema['append']['description'] ) ? $schema['append']['description'] . ' (' . $end_text . ')' : $end_text;
        }
    }
    return $schema;
}
add_filter( 'geodir_rest_register_advance_search_field_datepicker', 'geodir_rest_register_advance_search_field_datepicker', 10, 2 );

function geodir_rest_register_advance_search_field_distance( $schema, $field ) {
    $field_schema = geodir_rest_get_search_field_schema( $schema, $field );
    
    if ( !empty( $field->extra_fields ) && !empty( $field->extra_fields['is_sort'] ) ) {
        $values = array( '' );
        
        if ( !empty( $field->extra_fields['asc'] ) ) {
            $values[] = 'nearest';
        }
        
        if ( !empty( $field->extra_fields['desc'] ) ) {
            $values[] = 'farthest';
        }
        
        $schema['name'] = 'sort_by';
        $schema['type'] = 'string';
        $schema['enum'] = $values;
        $schema['default'] = '';
        
        if ( !empty( $field_schema ) ) {
            $schema['append'] = $field_schema;
        }
    }
    
    return $schema;
}
add_filter( 'geodir_rest_register_advance_search_field_distance', 'geodir_rest_register_advance_search_field_distance', 10, 2 );

function geodir_rest_register_advance_search_field_multiselect( $schema, $field ) {
    return geodir_rest_get_search_field_schema( $schema, $field );
}
add_filter( 'geodir_rest_register_advance_search_field_multiselect', 'geodir_rest_register_advance_search_field_multiselect', 10, 2 );

function geodir_rest_register_advance_search_field_radio( $schema, $field ) {
    return geodir_rest_get_search_field_schema( $schema, $field );
}
add_filter( 'geodir_rest_register_advance_search_field_radio', 'geodir_rest_register_advance_search_field_radio', 10, 2 );

function geodir_rest_register_advance_search_field_select( $schema, $field ) {
    return geodir_rest_get_search_field_schema( $schema, $field );
}
add_filter( 'geodir_rest_register_advance_search_field_select', 'geodir_rest_register_advance_search_field_select', 10, 2 );

function geodir_rest_register_advance_search_field_taxonomy( $schema, $field ) {
    if ( $field->field_input_type == 'SELECT' ) {
        $args = array( 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => true, 'fields' => 'ids' );
    } else {
        $args = array( 'orderby' => 'count', 'order' => 'DESC', 'hide_empty' => true, 'fields' => 'ids' );
    }

    $args = apply_filters( 'geodir_rest_filter_term_args', $args, $field->site_htmlvar_name );
    
    $terms = apply_filters( 'geodir_rest_filter_terms', get_terms( $field->site_htmlvar_name, $args ) );

    return geodir_rest_get_search_field_schema( $schema, $field, $terms );
}
add_filter( 'geodir_rest_register_advance_search_field_taxonomy', 'geodir_rest_register_advance_search_field_taxonomy', 10, 2 );

function geodir_rest_register_advance_search_field_text( $schema, $field ) {
    return geodir_rest_get_search_field_schema( $schema, $field );
}
add_filter( 'geodir_rest_register_advance_search_field_text', 'geodir_rest_register_advance_search_field_text', 10, 2 );

function geodir_rest_register_advance_search_field_textarea( $schema, $field ) {
    $schema['name'] = 's' . $field->site_htmlvar_name;
    
    return $schema;
}
add_filter( 'geodir_rest_register_advance_search_field_textarea', 'geodir_rest_register_advance_search_field_textarea', 10, 2 );

function geodir_rest_register_advance_search_field_time( $schema, $field ) {
    return geodir_rest_get_search_field_schema( $schema, $field );
}
add_filter( 'geodir_rest_register_advance_search_field_time', 'geodir_rest_register_advance_search_field_time', 10, 2 );