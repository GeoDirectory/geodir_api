<?php
/**
 * REST API: Geodir_REST_Terms_Controller class
 *
 */

/**
 * Core class used to managed terms associated with a taxonomy via the REST API.
 *
 * @see WP_REST_Terms_Controller
 */
class Geodir_REST_Terms_Controller extends WP_REST_Terms_Controller {
	/**
	 * Taxonomy key.
	 *
	 * @access protected
	 * @var string
	 */
	protected $taxonomy;

	/**
	 * Instance of a term meta fields object.
	 *
	 * @access protected
	 * @var WP_REST_Term_Meta_Fields
	 */
	protected $meta;

	/**
	 * Column to have the terms be sorted by.
	 *
	 * @access protected
	 * @var string
	 */
	protected $sort_column;

	/**
	 * Number of terms that were found.
	 *
	 * @access protected
	 * @var int
	 */
	protected $total_terms;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param string $taxonomy Taxonomy key.
	 */
	public function __construct( $taxonomy ) {       
		$this->taxonomy     = $taxonomy;
		$this->namespace    = GEODIR_REST_SLUG . '/v' . GEODIR_REST_API_VERSION;
		$tax_obj            = get_taxonomy( $taxonomy );
		$this->rest_base    = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $tax_obj->name;
		
		$this->meta = new WP_REST_Term_Meta_Fields( $taxonomy );
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
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                 => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'force' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Required to be true, as terms do not support trashing.' ),
					),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}
    
	/**
	 * Prepares links for the request.
	 *
	 * @access protected
	 *
	 * @param object $term Term object.
	 * @return array Links for the given term.
	 */
	protected function prepare_links( $term ) {
		$base = $this->namespace . '/' . $this->rest_base;
		$links = array(
			'self'       => array(
				'href' => rest_url( trailingslashit( $base ) . $term->term_id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'about'      => array(
                'href' => rest_url( sprintf( '%s/taxonomies/%s', $this->namespace, $this->taxonomy ) ),
			),
		);

		if ( $term->parent ) {
			$parent_term = get_term( (int) $term->parent, $term->taxonomy );

			if ( $parent_term ) {
				$links['up'] = array(
					'href'       => rest_url( trailingslashit( $base ) . $parent_term->term_id ),
					'embeddable' => true,
				);
			}
		}

		$taxonomy_obj = get_taxonomy( $term->taxonomy );

		if ( empty( $taxonomy_obj->object_type ) ) {
			return $links;
		}

		$post_type_links = array();

		foreach ( $taxonomy_obj->object_type as $type ) {
			$post_type_object = get_post_type_object( $type );

			if ( empty( $post_type_object->show_in_rest ) ) {
				continue;
			}

			$rest_base = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name;
			$post_type_links[] = array(
				'href' => add_query_arg( $this->rest_base, $term->term_id, rest_url( sprintf( '%s/%s', $this->namespace, $rest_base ) ) ),
			);
		}

		if ( ! empty( $post_type_links ) ) {
			$links['https://api.w.org/post_type'] = $post_type_links;
		}

		return $links;
	}
    
	/**
	 * Retrieves the query params for collections.
	 *
	 * @access public
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();
		$taxonomy = get_taxonomy( $this->taxonomy );

		$query_params['context']['default'] = 'view';

		$query_params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.' ),
			'type'              => 'array',
			'items'             => array(
				'type'          => 'integer',
			),
			'default'           => array(),
		);

		$query_params['include'] = array(
			'description'       => __( 'Limit result set to specific IDs.' ),
			'type'              => 'array',
			'items'             => array(
				'type'          => 'integer',
			),
			'default'           => array(),
		);

		if ( ! $taxonomy->hierarchical ) {
			$query_params['offset'] = array(
				'description'       => __( 'Offset the result set by a specific number of items.' ),
				'type'              => 'integer',
			);
		}

		$query_params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.' ),
			'type'              => 'string',
			'default'           => 'asc',
			'enum'              => array(
				'asc',
				'desc',
			),
		);

		$query_params['orderby'] = array(
			'description'       => __( 'Sort collection by term attribute.' ),
			'type'              => 'string',
			'default'           => 'name',
			'enum'              => array(
				'id',
				'include',
				'name',
				'slug',
				'term_group',
				'description',
				'count',
			),
		);

		$query_params['hide_empty'] = array(
			'description'       => __( 'Whether to hide terms not assigned to any posts.' ),
			'type'              => 'boolean',
			'default'           => false,
		);

		if ( $taxonomy->hierarchical ) {
			$query_params['parent'] = array(
				'description'       => __( 'Limit result set to terms assigned to a specific parent.' ),
				'type'              => 'integer',
			);
		}

		$query_params['post'] = array(
			'description'       => __( 'Limit result set to terms assigned to a specific post.' ),
			'type'              => 'integer',
			'default'           => null,
		);

		$query_params['slug'] = array(
			'description'       => __( 'Limit result set to terms with a specific slug.' ),
			'type'              => 'string',
		);
        
        $query_params = geodir_rest_taxonomy_collection_params( $query_params, $taxonomy );

		/**
		 * Filter collection parameters for the terms controller.
		 *
		 * The dynamic part of the filter `$this->taxonomy` refers to the taxonomy
		 * slug for the controller.
		 *
		 * This filter registers the collection parameter, but does not map the
		 * collection parameter to an internal WP_Term_Query parameter.  Use the
		 * `rest_{$this->taxonomy}_query` filter to set WP_Term_Query parameters.
		 *
		 *
		 * @param $params JSON Schema-formatted collection parameters.
		 * @param WP_Taxonomy $taxonomy_obj Taxonomy object.
		 */
		return apply_filters( 'rest_{$this->taxonomy}_collection_params', $query_params, $taxonomy );
	}

	/**
	 * Checks that the taxonomy is valid.
	 *
	 * @access protected
	 *
	 * @param string $taxonomy Taxonomy to check.
	 * @return bool Whether the taxonomy is allowed for REST management.
	 */
	protected function check_is_taxonomy_allowed( $taxonomy ) {
		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( $taxonomy_obj && ! empty( $taxonomy_obj->show_in_rest ) ) {
			return true;
		}
		return false;
	}
}
