<?php
/**
 * @package GeoDirectory
 * @subpackage Geodir_API
 * @version 1.0.0
 */

/*
Plugin Name: GeoDirectory Rest API
Plugin URI: https://wpgeodirectory.com/
Description: GeoDirectory Rest API Integration :)
Author: GeoDirectory
Version: 0.0.3
Author URI: https://wpgeodirectory.com/
Update URL: https://github.com/GeoDirectory/geodir_api/
*/

// MUST have WordPress.
if ( !defined( 'WPINC' ) ) {
    exit( 'Do NOT access this file directly: ' . basename( __FILE__ ) );
}

if ( !defined( 'GEODIR_REST_VERSION' ) ) {
    define( 'GEODIR_REST_VERSION', '0.0.1' );
}

//GEODIRECTORY UPDATE CHECKS
if (is_admin()) {
    if (!function_exists('ayecode_show_update_plugin_requirement')) {//only load the update file if needed
        require_once('gd_update.php'); // require update script
    }
}


if ( !class_exists('Geodir_REST') ) {
    class Geodir_REST {
        private static $instance;
            
        public static function initialize() {
            if ( !defined( 'REST_API_VERSION' ) ) {
                return;
            }
            
            if ( !isset( self::$instance ) && !( self::$instance instanceof Geodir_REST ) ) {
                self::$instance = new Geodir_REST;
                self::$instance->actions();
            }
            
            self::$instance->define_constants();
            
            do_action( 'geodir_rest_loaded' );
        
            return self::$instance;
        }
        
        public function define_constants() {
            define( 'GEODIR_REST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
            define( 'GEODIR_REST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
            define( 'GEODIR_REST_SLUG', 'geodir' );
            define( 'GEODIR_REST_API_VERSION', '1' );
        }
        
        private function actions() {
            /* Internationalize the text strings used. */
            add_action( 'plugins_loaded', array( &$this, 'plugins_loaded' ) );
            
            /* Perform actions on admin initialization. */
            if ( is_admin() ) {
                global $wp_version;

                if ( version_compare( $wp_version, '4.7', '<' ) ) {
                    if ( is_plugin_active( 'geodir_api/geodir_api.php' ) ) {
                        deactivate_plugins( 'geodir_api/geodir_api.php' );
                        add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
                    }
                }
                
                add_action( 'admin_init', array( &$this, 'admin_init') );
            }
            add_action( 'init', array( &$this, 'init' ), 3 );
            
            add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
            if ( is_admin() ) {
                add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
            }

            register_activation_hook( __FILE__, array( 'Geodir_REST', 'activation' ) );
            register_deactivation_hook( __FILE__, array( 'Geodir_REST', 'deactivation' ) );
            register_uninstall_hook( __FILE__, array( 'Geodir_REST', 'uninstall' ) );
            
            add_action( 'rest_api_init', array( $this , 'rest_api_init' ) );
            add_action( 'rest_api_init', array( $this , 'setup_geodir_rest' ), 100 );
            /**
             * Fires after the setup of all Geodir_REST actions.
             *
             * @since 1.0.0
             *
             * @param GeoDir_Invoices $this. Current Geodir_REST instance. Passed by reference.
             */
            do_action_ref_array( 'geodir_rest_actions', array( &$this ) );
        }
        
        public function plugins_loaded() {
            /* Internationalize the text strings used. */
            $this->load_textdomain();

            /* Load the functions files. */
            $this->includes();
        }
        
        /**
         * Load the translation of the plugin.
         *
         * @since 1.0.0
         */
        public function load_textdomain() {
            $locale = apply_filters( 'plugin_locale', get_locale(), 'geodir_rest' );
            
            load_textdomain( 'geodir_rest', WP_LANG_DIR . '/geodir_api/geodir_api-' . $locale . '.mo' );
            load_plugin_textdomain( 'geodir_rest', false, GEODIR_REST_PLUGIN_DIR . 'languages' );
            
            /**
             * Define language constants.
             */
            require_once( GEODIR_REST_PLUGIN_DIR . 'language.php' );
        }
        
        public static function activation() {
        }
        
        public static function deactivation() {
        }
        
        public static function uninstall() {
        }
        
        public function includes() {
            require_once( GEODIR_REST_PLUGIN_DIR . 'includes/geodir-rest-functions.php' );
            require_once( GEODIR_REST_PLUGIN_DIR . 'includes/geodir-rest-listings-functions.php' );
            require_once( GEODIR_REST_PLUGIN_DIR . 'includes/geodir-rest-taxonomies-functions.php' );
            
            if (!is_admin()) {
                if ( !function_exists( 'is_plugin_active' ) ) {
                    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                }
                
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/endpoints/class-geodir-rest-taxonomies-controller.php' );
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/endpoints/class-geodir-rest-terms-controller.php' );
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/endpoints/class-geodir-rest-listings-controller.php' );
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/endpoints/class-geodir-rest-post-types-controller.php' );
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/endpoints/class-geodir-rest-reviews-controller.php' );
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/endpoints/class-geodir-rest-countries-controller.php' );
                if ( geodir_rest_is_active( 'location' ) ) {
                    require_once( GEODIR_REST_PLUGIN_DIR . 'includes/endpoints/class-geodir-rest-location-types-controller.php' );
                    require_once( GEODIR_REST_PLUGIN_DIR . 'includes/endpoints/class-geodir-rest-locations-controller.php' );
                }
            }
        }
        
        public function init() {
            global $wp_post_types, $wp_taxonomies;

            $gd_post_types = geodir_get_posttypes('array');

            if (!empty($gd_post_types)) {
                foreach ($gd_post_types as $post_type => $data) {
                    if (isset($wp_post_types[$post_type])) {
                        $wp_post_types[$post_type]->gd_listing = true;
                        $wp_post_types[$post_type]->show_in_rest = true;
                        $wp_post_types[$post_type]->rest_base = $data['has_archive'];
                        $wp_post_types[$post_type]->rest_controller_class = 'Geodir_REST_Listings_Controller';
                        
                        if (!empty($data['taxonomies'])) {
                            foreach ($data['taxonomies'] as $taxonomy) {
                                if (isset($wp_taxonomies[$taxonomy])) {
                                    $rest_base = $taxonomy;
                                    
                                    $wp_taxonomies[$taxonomy]->gd_taxonomy = true;
                                    $wp_taxonomies[$taxonomy]->show_in_rest = true;
                                    $wp_taxonomies[$taxonomy]->rest_base = $rest_base;
                                    $wp_taxonomies[$taxonomy]->rest_controller_class = 'Geodir_REST_Terms_Controller';
                                }
                            }
                        }
                    }
                }
            }
        }
        
        public function admin_init() {
        }
        
        public function enqueue_scripts() {
            $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
        }
        
        public function admin_enqueue_scripts() {
        }
        
        public function wp_version_notice() {
            global $wp_version;
            
            echo '<div class="error"><p>' . wp_sprintf( __( 'GeoDirectory Rest API requires at least WordPress version 4.7. You are using WordPress version %s. <a href="%s" target="_blank">Update Version</a>.', 'geodir_rest' ), $wp_version, network_admin_url( 'update-core.php' ) ) . '</p></div>';
        }
        
        public function rest_api_init() {
            $gd_post_types = geodir_get_posttypes();
            
            $this->geodir_register_fields();
            
            foreach ( $gd_post_types as $post_type ) {
                // listings
                add_filter( 'rest_' . $post_type . '_collection_params', 'geodir_rest_listing_collection_params', 10, 2 );
                add_filter( 'rest_' . $post_type . '_query', 'geodir_rest_listing_query', 10, 2 );
                
                // categories
                add_filter( 'rest_' . $post_type . 'category_collection_params', 'geodir_rest_taxonomy_collection_params', 10, 2 );
                add_filter( 'rest_' . $post_type . 'category_query', 'geodir_rest_taxonomy_query', 10, 2 );
                add_filter( 'rest_prepare_' . $post_type . 'category', 'geodir_rest_prepare_category_item_response', 10, 3 );
                
                // tags
                add_filter( 'rest_' . $post_type . '_tags_collection_params', 'geodir_rest_taxonomy_collection_params', 10, 2 );
                add_filter( 'rest_' . $post_type . '_tags_query', 'geodir_rest_taxonomy_query', 10, 2 );
            }
        }
        
        public function setup_geodir_rest() {
            if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                $this->setup_geodir_rest_endpoints();
                
                add_filter( 'comments_clauses', 'geodir_rest_comments_clauses', 10, 2 );
                add_filter( 'get_comment', 'geodir_rest_get_comment', 10, 1 );
                
                add_action( 'rest_insert_comment', array( $this, 'save_review' ), 10, 3 );
            }
        }
        
        private function setup_geodir_rest_endpoints() {
            global $wp_post_types;
            
            $gd_post_types = geodir_get_posttypes();

            foreach ( $wp_post_types as $post_type ) {
                if ( !( in_array( $post_type->name, $gd_post_types ) && !empty( $post_type->show_in_rest ) ) ) {
                    continue;
                }

                $class = ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : 'WP_REST_Posts_Controller';

                if ( ! class_exists( $class ) ) {
                    continue;
                }
                $controller = new $class( $post_type->name );

                if ( ! ( is_subclass_of( $controller, 'WP_REST_Posts_Controller' ) || is_subclass_of( $controller, 'WP_REST_Controller' ) ) ) {
                    continue;
                }
                
                $this->register_fields( $post_type->name );
            }
            
            // GeoDirectory Taxonomies.
            $controller = new Geodir_REST_Taxonomies_Controller;
            $controller->register_routes();
            
            // GeoDirectory Post types.
            $controller = new Geodir_REST_Post_Types_Controller;
            $controller->register_routes();

            $controller = new Geodir_REST_Reviews_Controller;
            $controller->register_routes();
            
            $controller = new Geodir_REST_Countries_Controller;
            $controller->register_routes();
            
            // Locations
            if ( geodir_rest_is_active( 'location' ) ) {
                $controller = new Geodir_REST_Location_Types_Controller;
                $controller->register_routes();
                
                $location_types = geodir_rest_get_location_types();
                
                if ( !empty( $location_types ) ) {
                    foreach ( $location_types as $type => $args ) {
                        $controller = new Geodir_REST_Locations_Controller( $type );
                        $controller->register_routes();
                    }
                }
            }
        }
        
        public function register_fields( $post_type ) {
            register_rest_field( $post_type, 'gd_data', array(
                'get_callback'    => array( $this, 'rest_prepare_geodir_response' ),
                'update_callback' => null,
                'schema'          => array( 'gd_data' => array(
                                            'description' => __( 'GeoDirectory listing fields data.' ),
                                            'type'        => 'object',
                                            'context'     => array( 'view' ),
                                        )
                                    ),
            ));
        }
        
        public function geodir_register_fields() {            
            global $wp_post_types;
             
            $gd_post_types = geodir_get_posttypes();

            foreach ( $wp_post_types as $post_type ) {
                if ( !( in_array( $post_type->name, $gd_post_types ) && !empty( $post_type->show_in_rest ) ) ) {
                    continue;
                }

                $class = ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : 'WP_REST_Posts_Controller';

                if ( ! class_exists( $class ) ) {
                    continue;
                }
                $controller = new $class( $post_type->name );

                if ( ! ( is_subclass_of( $controller, 'WP_REST_Posts_Controller' ) || is_subclass_of( $controller, 'WP_REST_Controller' ) ) ) {
                    continue;
                }
                
                $controller->register_listing_fields();
            }
        }
        
        public function rest_prepare_geodir_response( $post, $field_name, $request, $post_type ) {
            $post_id = $post['id'];
            $custom_fields = geodir_rest_get_custom_fields($post_type, true, 'htmlvar_name');
            $taxonomy = $post_type . 'category';

            $date_format = geodir_default_date_format();
            $time_format = get_option('time_format');
            $search_date_format = array('dd','d','DD','mm','m','MM','yy'); // jQuery UI datepicker format
            $replace_date_format = array('d','j','l','m','n','F','Y'); // PHP date format

            $data = array();

            $gd_post_info = geodir_rest_gd_post_info($post_id);
            if (!empty($gd_post_info)) {
                $uploads = wp_upload_dir();
                
                foreach ($gd_post_info as $field => $value) {
                    $field_data = array();
                    
                    $raw_value = $value != NULL ? $value : '';
                    $rendered_value = '';
                    
                    if ($field == $taxonomy) {
                        $values = explode(',', trim($raw_value, ','));
                        
                        if (!empty($values)) {
                            $rendered_value = array();
                            
                            foreach ($values as $term_id) {
                                $term = get_term_by('id', $term_id, $taxonomy);
                                
                                if (!empty($term)) {
                                    $rendered_value[] = $term->name;
                                }
                            }
                        }
                    } else if ($field == 'default_category') {
                        if ((int)$raw_value > 0 && $term = get_term_by('id', (int)$raw_value, $taxonomy)) {
                            $rendered_value = $term->name;
                        }
                    } else if ($field == 'featured_image') {
                        if ($raw_value != '') {
                            $rendered_value = $uploads['baseurl'] . $raw_value;
                        }
                    } else if (in_array($field, $custom_fields)) {
                        $field_info = geodir_rest_get_field_by_name($field, $post_type);
                        
                        if (!empty($field_info)) {
                            $extra_fields = $field_info->extra_fields != '' ? maybe_unserialize($field_info->extra_fields) : '';
                            $option_values = $field_info->option_values;
                            $option_values_arr = $option_values != '' ? geodir_string_values_to_options($option_values) : NULL;
                            
                            switch ($field_info->field_type) {
                                case 'time':
                                    if ($raw_value != '') {
                                        $rendered_value = date_i18n($time_format, strtotime($raw_value));
                                    }
                                break;
                                case 'datepicker':
                                    if ($raw_value != '' && $raw_value != '0000-00-00') {
                                        $date_format = isset($extra_fields['date_format']) && $extra_fields['date_format'] != '' ? $extra_fields['date_format'] : $date_format;
                                        $date_format = str_replace($search_date_format, $replace_date_format, $date_format);
                                        
                                        $rendered_value = $date_format == 'd/m/Y' ? str_replace('/', '-', $raw_value) : $raw_value; // PHP doesn't work well with dd/mm/yyyy format
                                        $rendered_value = date_i18n($date_format, strtotime($rendered_value));
                                    }
                                break;
                                case 'radio':
                                    if ($raw_value == 't' || $raw_value == '1') {
                                        $rendered_value =  __('Yes', 'geodirectory');
                                    } else if ($raw_value == 'f' || $raw_value == '0') {
                                        $rendered_value =  __('No', 'geodirectory');
                                    }
                                    
                                    if (strpos($option_values, '/') !== false && !empty($option_values_arr)) {
                                        foreach ($option_values_arr as $option_row) {
                                            $option_label = isset($option_row['label']) ? $option_row['label'] : '';
                                            $option_value = isset($option_row['value']) ? $option_row['value'] : $option_label;
                                            
                                            if ($option_value == $raw_value && $option_label != '') {
                                                $rendered_value = __($option_label, 'geodirectory');
                                                break;
                                            }
                                        }
                                    }
                                break;
                                case 'checkbox':
                                    if ((int)$raw_value == 1) {
                                        $rendered_value = __('Yes', 'geodirectory');
                                    }
                                break;
                                case 'select':
                                    if ($raw_value != '') {
                                        if (strpos($option_values, '/') !== false && !empty($option_values_arr)) {
                                            foreach ($option_values_arr as $option_row) {
                                                $option_label = isset($option_row['label']) ? $option_row['label'] : '';
                                                $option_value = isset($option_row['value']) ? $option_row['value'] : $option_label;
                                                
                                                if ($option_value == $raw_value && $option_label != '') {
                                                    $rendered_value = __($option_label, 'geodirectory');
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                break;
                                case 'multiselect':
                                    if ($raw_value != '') {
                                        $raw_value_arr = explode(',', $raw_value);
                                        
                                        $raw_values = array();
                                        if (strpos($option_values, '/') !== false) {
                                            foreach ($option_values_arr as $option_row) {
                                                $option_label = isset($option_row['label']) ? $option_row['label'] : '';
                                                $option_value = isset($option_row['value']) ? $option_row['value'] : $option_label;
                                                $option_label = $option_label == '' ? $option_value : $option_label;
                                                
                                                if (in_array($option_value, $raw_value_arr) && $option_label != '') {
                                                    $raw_values[] = $option_label;
                                                }
                                            }
                                        } else {
                                            $raw_values = $raw_value_arr;
                                        }
                                        
                                        $rendered_value = array();
                                        foreach ($raw_values as $label) {
                                            $rendered_value[] = __($label, 'geodirectory');
                                        }
                                    }
                                break;
                            }
                        }
                    }
                    
                    $field_data = $raw_value;
                    
                    if (!empty($rendered_value) && $rendered_value != $raw_value) {
                        $field_data = array();
                        $field_data['raw'] = $raw_value;
                        $field_data['rendered'] = $rendered_value;
                    }
                    
                    $data[$field] = $field_data;
                }

                $data = apply_filters('geodir_rest_get_gd_data', $data, $post_id);
            }

            return $data;
        }
        
        public function save_review( $comment, $request, $new = false ) {
            ob_start();
            
            if ( geodir_rest_is_active( 'reviewrating' ) ) {
                if ( !empty( $request['rating'] ) ) {
                    $_REQUEST['geodir_rating'] = $request['rating'];
                
                    geodir_reviewrating_save_rating( $comment->comment_ID );
                }
            } else {
                $_REQUEST['geodir_overallrating'] = $request['rating']['overall'];
				geodir_save_rating( $comment->comment_ID );
            }
            
            ob_get_clean();
        }
    }
}

global $geodir_rest;
$geodir_rest = new Geodir_REST();
$geodir_rest->initialize();