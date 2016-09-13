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
Version: 0.0.1
Author URI: https://wpgeodirectory.com/
*/

// MUST have WordPress.
if ( !defined( 'WPINC' ) ) {
    exit( 'Do NOT access this file directly: ' . basename( __FILE__ ) );
}

if ( !defined( 'GEODIR_REST_VERSION' ) ) {
    define( 'GEODIR_REST_VERSION', '0.0.1' );
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
            
            add_action( 'rest_api_init' , array( $this , 'setup_geodir_rest' ) );
            
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
            if (!is_admin()) {
                if ( !function_exists( 'is_plugin_active' ) ) {
                    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                }
                
                if ( !is_plugin_active( 'rest-api/plugin.php' ) ) {
                    include_once( GEODIR_REST_PLUGIN_DIR . 'includes/rest-api/plugin.php' );
                }
                
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/class-geodir-rest-taxonomies-controller.php' );
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/class-geodir-rest-terms-controller.php' );
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/class-geodir-rest-listings-controller.php' );
                require_once( GEODIR_REST_PLUGIN_DIR . 'includes/class-geodir-rest-reviews-controller.php' );
            }
            
            include_once( GEODIR_REST_PLUGIN_DIR . 'includes/geodir-rest-functions.php' );
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
        
        public function setup_geodir_rest() {
            if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                $this->setup_geodir_rest_endpoints();
                
                add_filter( 'comments_clauses', 'geodir_rest_comments_clauses', 10, 2 );
                add_filter( 'get_comment', 'geodir_rest_get_comment', 10, 1 );
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

                $controller->register_routes();

                if ( post_type_supports( $post_type->name, 'revisions' ) ) {
                    $revisions_controller = new WP_REST_Revisions_Controller( $post_type->name );
                    $revisions_controller->register_routes();
                }
            }
            
            // GeoDirectory Taxonomies.
            $controller = new Geodir_REST_Taxonomies_Controller;
            $controller->register_routes();
            
            // Terms.
            foreach ( get_taxonomies( array( 'show_in_rest' => true, 'gd_taxonomy' => true ), 'object' ) as $taxonomy ) {
                $class = ! empty( $taxonomy->rest_controller_class ) ? $taxonomy->rest_controller_class : 'WP_REST_Terms_Controller';

                if ( ! class_exists( $class ) ) {
                    continue;
                }
                $controller = new $class( $taxonomy->name );
                if ( ! ( is_subclass_of( $controller, 'Geodir_REST_Terms_Controller' ) || is_subclass_of( $controller, 'WP_REST_Terms_Controller' ) ) ) {
                    continue;
                }

                $controller->register_routes();
            }

            $controller = new Geodir_REST_Reviews_Controller;
            $controller->register_routes();
        }
    }
}

global $geodir_rest;
$geodir_rest = new Geodir_REST();
$geodir_rest->initialize();