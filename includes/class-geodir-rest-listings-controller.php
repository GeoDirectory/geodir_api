<?php
/**
 * REST API: Geodir_REST_Listings_Controller class
 */

/**
 * Core class to access posts via the REST API.
 *
 *
 * @see WP_REST_Posts_Controller
 */
class Geodir_REST_Listings_Controller extends WP_REST_Posts_Controller {

	/**
	 * Post type.
	 *
	 * @access protected
	 * @var string
	 */
	protected $post_type;

	/**
	 * Instance of a post meta fields object.
	 *
	 * @access protected
	 * @var WP_REST_Post_Meta_Fields
	 */
	protected $meta;

	/**
	 * Constructor.
	 *
	 * @access public
	 *
	 * @param string $post_type Post type.
	 */

    public function __construct( $post_type ) {
        $this->post_type    = $post_type;
        $this->namespace    = GEODIR_REST_SLUG . '/v' . GEODIR_REST_API_VERSION;
        $obj                = get_post_type_object( $post_type );
        $this->rest_base    = ! empty( $obj->rest_base ) ? $obj->rest_base : $obj->name;
        
        $this->meta         = new WP_REST_Post_Meta_Fields( $this->post_type );
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
					'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
					'password' => array(
						'description' => __( 'The password for the post if it is password protected.' ),
						'type'        => 'string',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'force' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => __( 'Whether to bypass trash and force deletion.' ),
					),
				),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}
    
	/**
	 * Checks if a given post type can be viewed or managed.
	 *
	 * @access protected
	 *
	 * @param object|string $post_type Post type name or object.
	 * @return bool Whether the post type is allowed in REST.
	 */
	protected function check_is_post_type_allowed( $post_type ) {
		if ( ! is_object( $post_type ) ) {
			$post_type = get_post_type_object( $post_type );
		}

		if ( ! empty( $post_type ) && ! empty( $post_type->show_in_rest ) ) {
			return true;
		}

		return false;
	}
    
	/**
	 * Retrieves the query params for the posts collection.
	 *
	 * @access public
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['context']['default'] = 'view';

		if ( post_type_supports( $this->post_type, 'author' ) ) {
			$params['author'] = array(
				'description'         => __( 'Limit result set to posts assigned to specific authors.' ),
				'type'                => 'array',
				'items'               => array(
					'type'            => 'integer',
				),
				'default'             => array(),
			);
			$params['author_exclude'] = array(
				'description'         => __( 'Ensure result set excludes posts assigned to specific authors.' ),
				'type'                => 'array',
				'items'               => array(
					'type'            => 'integer',
				),
				'default'             => array(),
			);
		}

		$params['before'] = array(
			'description'        => __( 'Limit response to posts published before a given ISO8601 compliant date.' ),
			'type'               => 'string',
			'format'             => 'date-time',
		);

		$params['exclude'] = array(
			'description'        => __( 'Ensure result set excludes specific IDs.' ),
			'type'               => 'array',
			'items'              => array(
				'type'           => 'integer',
			),
			'default'            => array(),
		);

		$params['include'] = array(
			'description'        => __( 'Limit result set to specific IDs.' ),
			'type'               => 'array',
			'items'              => array(
				'type'           => 'integer',
			),
			'default'            => array(),
		);

		if ( 'page' === $this->post_type || post_type_supports( $this->post_type, 'page-attributes' ) ) {
			$params['menu_order'] = array(
				'description'        => __( 'Limit result set to posts with a specific menu_order value.' ),
				'type'               => 'integer',
			);
		}

		$params['offset'] = array(
			'description'        => __( 'Offset the result set by a specific number of items.' ),
			'type'               => 'integer',
		);
        
        $sort_options =     geodir_rest_get_listing_sorting( $this->post_type );
 
        $orderby            = array_keys( $sort_options['sorting'] );
        $orderby_rendered   = $sort_options['sorting'];
        $default_orderby    = $sort_options['default_sortby'];
        $default_order      = $sort_options['default_sort'];
        
		$params['order'] = array(
			'description'        => __( 'Order sort attribute ascending or descending.' ),
			'type'               => 'string',
            'default'            => $default_order,
			'enum'               => array( 'asc', 'desc' ),
		);

		$params['orderby'] = array(
			'description'        => __( 'Sort collection by object attribute.' ),
			'type'               => 'string',
            'default'            => $default_orderby,
            'enum'               => $orderby,
		);
        
        $params['orderby_rendered']    = array(
            'description'        => __( 'All sorting options for listings.' ),
            'type'               => 'array',
            'enum'               => $orderby_rendered
        );

		if ( 'page' === $this->post_type || post_type_supports( $this->post_type, 'page-attributes' ) ) {
			$params['orderby']['enum'][] = 'menu_order';
		}

		$post_type_obj = get_post_type_object( $this->post_type );

		if ( $post_type_obj->hierarchical || 'attachment' === $this->post_type ) {
			$params['parent'] = array(
				'description'       => __( 'Limit result set to those of particular parent IDs.' ),
				'type'              => 'array',
				'items'             => array(
					'type'          => 'integer',
				),
				'default'           => array(),
			);
			$params['parent_exclude'] = array(
				'description'       => __( 'Limit result set to all items except those of a particular parent ID.' ),
				'type'              => 'array',
				'items'             => array(
					'type'          => 'integer',
				),
				'default'           => array(),
			);
		}

		$params['slug'] = array(
			'description'       => __( 'Limit result set to posts with one or more specific slugs.' ),
			'type'              => 'array',
			'items'             => array(
				'type'          => 'string',
			),
			'sanitize_callback' => 'wp_parse_slug_list',
		);

		$params['status'] = array(
			'default'           => 'publish',
			'description'       => __( 'Limit result set to posts assigned one or more statuses.' ),
			'type'              => 'array',
			'items'             => array(
				'enum'          => array_merge( array_keys( get_post_stati() ), array( 'any' ) ),
				'type'          => 'string',
			),
			'sanitize_callback' => array( $this, 'sanitize_post_statuses' ),
		);

		$taxonomies = wp_list_filter( get_object_taxonomies( $this->post_type, 'objects' ), array( 'show_in_rest' => true ) );

		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

			$params[ $base ] = array(
				/* translators: %s: taxonomy name */
				'description'       => sprintf( __( 'Limit result set to all items that have the specified term assigned in the %s taxonomy.' ), $base ),
				'type'              => 'array',
				'items'             => array(
					'type'          => 'integer',
				),
				'default'           => array(),
			);

			$params[ $base . '_exclude' ] = array(
				/* translators: %s: taxonomy name */
				'description' => sprintf( __( 'Limit result set to all items except those that have the specified term assigned in the %s taxonomy.' ), $base ),
				'type'        => 'array',
				'items'       => array(
					'type'    => 'integer',
				),
				'default'           => array(),
			);
		}

		if ( 'post' === $this->post_type ) {
			$params['sticky'] = array(
				'description'       => __( 'Limit result set to items that are sticky.' ),
				'type'              => 'boolean',
			);
		}

		/**
		 * Filter collection parameters for the posts controller.
		 *
		 * The dynamic part of the filter `$this->post_type` refers to the post
		 * type slug for the controller.
		 *
		 * This filter registers the collection parameter, but does not map the
		 * collection parameter to an internal WP_Query parameter. Use the
		 * `rest_{$this->post_type}_query` filter to set WP_Query parameters.
		 *
		 *
		 * @param $params JSON Schema-formatted collection parameters.
		 * @param WP_Post_Type $post_type_obj Post type object.
		 */
		return apply_filters( "rest_{$this->post_type}_collection_params", $params, $post_type_obj );
	}
    
	/**
	 * Prepares links for the request.
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @param WP_Post $post Post object.
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $post ) {
		$base = sprintf( '%s/%s', $this->namespace, $this->rest_base );

		// Entity meta.
		$links = array(
			'self' => array(
				'href'   => rest_url( trailingslashit( $base ) . $post->ID ),
			),
			'collection' => array(
				'href'   => rest_url( $base ),
			),
			'about'      => array(
				'href'   => rest_url( 'wp/v2/types/' . $this->post_type ),
			),
		);

		if ( ( in_array( $post->post_type, array( 'post', 'page' ), true ) || post_type_supports( $post->post_type, 'author' ) )
			&& ! empty( $post->post_author ) ) {
			$links['author'] = array(
				'href'       => rest_url( 'wp/v2/users/' . $post->post_author ),
				'embeddable' => true,
			);
		}

		if ( in_array( $post->post_type, array( 'post', 'page' ), true ) || post_type_supports( $post->post_type, 'comments' ) ) {
			$replies_url = rest_url( 'wp/v2/comments' );
			$replies_url = add_query_arg( 'post', $post->ID, $replies_url );

			$links['replies'] = array(
				'href'       => $replies_url,
				'embeddable' => true,
			);
		}

		if ( in_array( $post->post_type, array( 'post', 'page' ), true ) || post_type_supports( $post->post_type, 'revisions' ) ) {
			$links['version-history'] = array(
				'href' => rest_url( trailingslashit( $base ) . $post->ID . '/revisions' ),
			);
		}

		$post_type_obj = get_post_type_object( $post->post_type );

		if ( $post_type_obj->hierarchical && ! empty( $post->post_parent ) ) {
			$links['up'] = array(
				'href'       => rest_url( trailingslashit( $base ) . (int) $post->post_parent ),
				'embeddable' => true,
			);
		}

		// If we have a featured media, add that.
		if ( $featured_media = get_post_thumbnail_id( $post->ID ) ) {
			$image_url = rest_url( 'wp/v2/media/' . $featured_media );

			$links['https://api.w.org/featuredmedia'] = array(
				'href'       => $image_url,
				'embeddable' => true,
			);
		}

		if ( ! in_array( $post->post_type, array( 'attachment', 'nav_menu_item', 'revision' ), true ) ) {
			$attachments_url = rest_url( 'wp/v2/media' );
			$attachments_url = add_query_arg( 'parent', $post->ID, $attachments_url );

			$links['https://api.w.org/attachment'] = array(
				'href' => $attachments_url,
			);
		}

		$taxonomies = get_object_taxonomies( $post->post_type );

		if ( ! empty( $taxonomies ) ) {
			$links['https://api.w.org/term'] = array();

			foreach ( $taxonomies as $tax ) {
				$taxonomy_obj = get_taxonomy( $tax );

				// Skip taxonomies that are not public.
				if ( empty( $taxonomy_obj->show_in_rest ) ) {
					continue;
				}

				$tax_base = ! empty( $taxonomy_obj->rest_base ) ? $taxonomy_obj->rest_base : $tax;

				$terms_url = add_query_arg(
					'post',
					$post->ID,
					rest_url( $this->namespace . '/' . $tax_base )
				);

				$links['https://api.w.org/term'][] = array(
					'href'       => $terms_url,
					'taxonomy'   => $tax,
					'embeddable' => true,
				);
			}
		}

		return $links;
	}
}
