<?php
/**
 * REST API endpoints for the Multi-Author Posts plugin.
 *
 * @package MultiAuthorPosts
 */

namespace MultiAuthorPosts;

/**
 * Registers and handles all /multi-author-posts/v1/ REST routes.
 *
 * Routes
 * ------
 * GET    /posts/<id>/co-authors              List co-authors (edit_post required)
 * POST   /posts/<id>/co-authors              Add an existing site author directly
 * DELETE /posts/<id>/co-authors/<user_id>    Remove a co-author (post author / admin only)
 * GET    /posts/<id>/invite                  Return current shared invite URL (post author / admin only)
 * POST   /posts/<id>/invite                  Generate / refresh invite URL (post author / admin only)
 * DELETE /posts/<id>/invite                  Revoke invite URL (post author / admin only)
 */
class Rest_API {

	const NAMESPACE = 'multi-author-posts/v1';

	/**
	 * Register the REST hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 */
	public static function register_routes(): void {
		// Co-authors collection.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/co-authors',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_co_authors' ),
					'permission_callback' => array( __CLASS__, 'can_edit_post' ),
					'args'                => self::post_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'add_co_author' ),
					'permission_callback' => array( __CLASS__, 'can_manage_co_authors' ),
					'args'                => array_merge(
						self::post_id_arg(),
						array(
							'user_id' => array(
								'description'       => 'ID of the user to add as co-author.',
								'type'              => 'integer',
								'required'          => true,
								'sanitize_callback' => 'absint',
								'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
							),
						)
					),
				),
			)
		);

		// Co-author item.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/co-authors/(?P<user_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'remove_co_author' ),
				'permission_callback' => array( __CLASS__, 'can_manage_co_authors' ),
				'args'                => array_merge( self::post_id_arg(), self::user_id_arg() ),
			)
		);

		// Invite link.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/invite',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_invite' ),
					'permission_callback' => array( __CLASS__, 'can_manage_co_authors' ),
					'args'                => self::post_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_invite' ),
					'permission_callback' => array( __CLASS__, 'can_manage_co_authors' ),
					'args'                => self::post_id_arg(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'revoke_invite' ),
					'permission_callback' => array( __CLASS__, 'can_manage_co_authors' ),
					'args'                => self::post_id_arg(),
				),
			)
		);

		// Search site authors / editors for direct-add.
		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<post_id>\d+)/suggested-authors',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_suggested_authors' ),
				'permission_callback' => array( __CLASS__, 'can_manage_co_authors' ),
				'args'                => array_merge(
					self::post_id_arg(),
					array(
						'search' => array(
							'description'       => 'Search term to filter users by name or email.',
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					)
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Require edit_post capability for the requested post.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public static function can_edit_post( \WP_REST_Request $request ) {
		$post = self::get_post_from_request( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view co-authors for this post.', 'multi-author-posts' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Require that the current user is the post author or can edit others' posts.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|\WP_Error
	 */
	public static function can_manage_co_authors( \WP_REST_Request $request ) {
		$post = self::get_post_from_request( $request );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$user_id   = get_current_user_id();
		$is_author = $user_id && ( (int) $post->post_author === $user_id );

		if ( ! $is_author && ! current_user_can( 'edit_others_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage co-authors for this post.', 'multi-author-posts' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Route handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /posts/<id>/co-authors
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_co_authors( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		return new \WP_REST_Response( Co_Authors::get_co_author_data( $post_id ), 200 );
	}

	/**
	 * POST /posts/<id>/co-authors  — directly add an existing site author/editor.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function add_co_author( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$user_id = (int) $request->get_param( 'user_id' );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error(
				'rest_user_not_found',
				__( 'User not found.', 'multi-author-posts' ),
				array( 'status' => 404 )
			);
		}

		// Only allow adding users who already have edit_posts on this site,
		// or who are network users (multisite). Invite links handle the
		// "no site role" path; this endpoint is for direct add of known authors.
		if ( ! user_can( $user_id, 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_user_cannot_edit',
				__( 'The user does not have authoring capabilities on this site. Share an invite link instead.', 'multi-author-posts' ),
				array( 'status' => 400 )
			);
		}

		Co_Authors::add_co_author( $post_id, $user_id );
		return new \WP_REST_Response( Co_Authors::get_co_author_data( $post_id ), 200 );
	}

	/**
	 * DELETE /posts/<id>/co-authors/<user_id>
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function remove_co_author( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$user_id = (int) $request->get_param( 'user_id' );

		if ( ! Co_Authors::is_co_author( $post_id, $user_id ) ) {
			return new \WP_Error(
				'rest_not_co_author',
				__( 'The user is not a co-author of this post.', 'multi-author-posts' ),
				array( 'status' => 404 )
			);
		}

		Co_Authors::remove_co_author( $post_id, $user_id );
		return new \WP_REST_Response( array( 'removed_user_id' => $user_id ), 200 );
	}

	/**
	 * GET /posts/<id>/invite
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_invite( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		return new \WP_REST_Response( array( 'invite_url' => Invite::get_invite_url( $post_id ) ), 200 );
	}

	/**
	 * POST /posts/<id>/invite  — generate (or refresh) the shared invite URL.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function create_invite( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id    = (int) $request->get_param( 'post_id' );
		$invite_url = Invite::create_invite_url( $post_id );
		return new \WP_REST_Response( array( 'invite_url' => $invite_url ), 200 );
	}

	/**
	 * DELETE /posts/<id>/invite
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function revoke_invite( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		Invite::revoke_invite( $post_id );
		return new \WP_REST_Response( array( 'revoked' => true ), 200 );
	}

	/**
	 * GET /posts/<id>/suggested-authors?search=<term>
	 *
	 * Returns users on this site who can edit posts and are not already
	 * co-authors (or the post author). Used by the direct-add UI.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_suggested_authors( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$search  = (string) $request->get_param( 'search' );

		// Require a minimum search length to prevent user enumeration.
		// On large networks, require a longer search term to limit scope.
		$min_length = \wp_is_large_network( 'users' ) ? 5 : 2;
		if ( mb_strlen( $search ) < $min_length ) {
			return new \WP_REST_Response( array(), 200 );
		}

		$post           = get_post( $post_id );
		$existing_ids   = Co_Authors::get_co_authors( $post_id );
		$existing_ids[] = (int) $post->post_author;

		$args = array(
			'capability'     => array( 'edit_posts' ),
			'number'         => 20,
			'exclude'        => $existing_ids,
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
		);

		$users  = get_users( $args );
		$result = array();
		foreach ( $users as $user ) {
			$result[] = array(
				'id'     => $user->ID,
				'name'   => $user->display_name,
				'avatar' => get_avatar_url( $user->ID, array( 'size' => 48 ) ),
			);
		}

		return new \WP_REST_Response( $result, 200 );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Fetch the WP_Post for the post_id URL param, or a WP_Error if not found.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_Post|\WP_Error
	 */
	private static function get_post_from_request( \WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'rest_post_not_found',
				__( 'Post not found.', 'multi-author-posts' ),
				array( 'status' => 404 )
			);
		}
		return $post;
	}

	/**
	 * Shared post_id route argument definition.
	 *
	 * @return array<string,mixed>
	 */
	private static function post_id_arg(): array {
		return array(
			'post_id' => array(
				'description'       => 'Post ID.',
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
			),
		);
	}

	/**
	 * Shared user_id route argument definition.
	 *
	 * @return array<string,mixed>
	 */
	private static function user_id_arg(): array {
		return array(
			'user_id' => array(
				'description'       => 'User ID.',
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
			),
		);
	}
}
