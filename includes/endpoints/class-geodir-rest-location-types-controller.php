<?php
/**
 * REST API: Geodir_REST_Locations_Controller class
 *
 */

/**
 * Core class used to manage location types via the REST API.
 *
 *
 * @see WP_REST_Controller
 */
class Geodir_REST_Location_Types_Controller extends WP_REST_Controller {

    /**
     * Object type.
     *
     * @access public
     * @var string
     */
    public $object_type = 'geodir_location_type';

    /**
     * Route base for countries.
     *
     * @access public
     * @var    string
     */
    public $rest_base = 'locations';
    public $locations_base = 'locations';
    
    /**
     * Constructor.
     *
     * @access public
     */
    public function __construct() {
        
        $this->namespace = GEODIR_REST_SLUG . '/v' . GEODIR_REST_API_VERSION;
    }

    /**
     * Registers the routes for the objects of the controller.
     *
     * @access public
     *
     * @see register_rest_route()
     */
    public function register_routes() {

        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'         => WP_REST_Server::READABLE,
                'callback'        => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'            => $this->get_collection_params(),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
    }

    /**
     * Checks whether a given request has permission to read locations.
     *
     * @access public
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
     */
    public function get_items_permissions_check( $request ) {
        if ( !$this->show_in_rest()) {
            return new WP_Error( 'rest_cannot_view', __( 'Sorry, you are not allowed to view locations.' ), array( 'status' => rest_authorization_required_code() ) );
        }
        return true;
    }

    /**
     * Retrieves all public location types.
     *
     * @access public
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response Response object on success, or WP_Error object on failure.
     */
    public function get_items( $request ) {

        $location_types = geodir_rest_get_location_types();
        
        $data = array();
        foreach ( $location_types as $location_type => $value ) {
            $type = $this->prepare_item_for_response( (object)$value, $request );
            $type = $this->prepare_response_for_collection( $type );
            $data[ $location_type ] = $type;
        }

        if ( empty( $data ) ) {
            // Response should still be returned as a JSON object when it is empty.
            $data = (object) $data;
        }

        return rest_ensure_response( $data );
    }

    /**
     * Checks if a given request has access to a location type.
     *
     * @access public
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return true|WP_Error True if the request has read access for the item, otherwise false or WP_Error object.
     */
    public function get_item_permissions_check( $request ) {

        if ( !$this->show_in_rest() ) {
            if ( empty( $tax_obj->show_in_rest ) ) {
                return false;
            }
            
            return new WP_Error( 'rest_cannot_view', __( 'Sorry, you are not allowed to view locations.' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    /**
     * Prepares a location_type object for serialization.
     *
     * @access public
     *
     * @param stdClass        $taxonomy Taxonomy data.
     * @param WP_REST_Request $request  Full details about the request.
     * @return WP_REST_Response Response object.
     */
    public function prepare_item_for_response( $location_type, $request ) {
        $data = array(
            'name'         => $location_type->name,
            'title'        => $location_type->title,
            'description'  => $location_type->description,
            'rest_base'    => $location_type->rest_base,
        );

        $context    = 'view';
        $data       = $this->add_additional_fields_to_object( $data, $request );
        $data       = $this->filter_response_by_context( $data, $context );

        // Wrap the data in a response object.
        $response = rest_ensure_response( $data );

        $response->add_links( array(
            'collection'                => array(
                'href'                  => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
            ),
            'https://api.w.org/items'   => array(
                'href'                  => rest_url( sprintf( '%s/%s', $this->namespace, $location_type->rest_base ) ),
            ),
        ) );

        /**
         * Filters a location type returned from the REST API.
         *
         * @param WP_REST_Response $response The response object.
         * @param object           $location The original location object.
         * @param WP_REST_Request  $request  Request used to generate the response.
         */
        return apply_filters( 'geodir_rest_prepare_location_type', $response, $location_type, $request );
    }

    /**
     * Retrieves the location type's schema, conforming to JSON Schema.
     *
     * @access public
     *
     * @return array Item schema data.
     */
    public function get_item_schema() {
        $schema = array(
            '$schema'              => 'http://json-schema.org/schema#',
            'title'                => $this->object_type,
            'type'                 => 'object',
            'properties'           => array(
                'name'             => array(
                    'description'  => __( 'An alphanumeric identifier for the location type.' ),
                    'type'         => 'string',
                    'context'      => array( 'view' ),
                    'readonly'     => true,
                ),
                'title'             => array(
                    'description'  => __( 'The title for the location type.' ),
                    'type'         => 'string',
                    'context'      => array( 'view' ),
                    'readonly'     => true,
                ),
                'description'      => array(
                    'description'  => __( 'A human-readable description of the location type.' ),
                    'type'         => 'string',
                    'context'      => array( 'view' ),
                    'readonly'     => true,
                ),
                'rest_base'            => array(
                    'description'  => __( 'REST base route for the location type.' ),
                    'type'         => 'string',
                    'context'      => array( 'view' ),
                    'readonly'     => true,
                ),
            ),
        );
        return $this->add_additional_fields_schema( $schema );
    }

    /**
     * Retrieves the query params for collections.
     *
     * @access public
     *
     * @return array Collection parameters.
     */
    public function get_collection_params() {
        $params = array();
        $params['context'] = $this->get_context_param( array( 'default' => 'view' ) );

        return $params;
    }
    
    public function show_in_rest() {
        return true;
    }

}
