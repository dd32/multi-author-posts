<?php
/**
 * Shared invite-link management.
 *
 * A single token is stored per post. Any registered network user who visits
 * the link while logged in is added as a co-author and redirected to the editor.
 *
 * @package MultiAuthorPosts
 */

namespace MultiAuthorPosts;

/**
 * Handles invite-token generation and invite-URL acceptance.
 */
class Invite {

	const TOKEN_META_KEY = '_map_invite_token';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_invite_request' ) );
	}

	/**
	 * Process a ?map_invite=<token> request.
	 *
	 * If the visitor is not logged in they are redirected to the login page with
	 * a redirect_to pointing back here. Once logged in the invite is processed
	 * and the user is forwarded to the post editor.
	 */
	public static function handle_invite_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['map_invite'] ) ? sanitize_text_field( wp_unslash( $_GET['map_invite'] ) ) : '';
		if ( '' === $token ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			$back = add_query_arg( 'map_invite', rawurlencode( $token ), home_url( '/' ) );
			wp_safe_redirect( wp_login_url( $back ) );
			exit;
		}

		$post = self::get_post_by_token( $token );
		if ( ! $post ) {
			wp_die(
				esc_html__( 'This invite link is invalid or has been revoked.', 'multi-author-posts' ),
				esc_html__( 'Invalid Invite', 'multi-author-posts' ),
				array( 'response' => 403 )
			);
		}

		$user_id = get_current_user_id();

		// On multisite, add the user as a subscriber so they can access wp-admin
		// for this site. Subscriber is the lowest role and does not grant any
		// additional capabilities beyond what our capability filter provides.
		if ( is_multisite() && ! is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
			add_user_to_blog( get_current_blog_id(), $user_id, 'subscriber' );
		}

		Co_Authors::add_co_author( $post->ID, $user_id );

		wp_safe_redirect( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) );
		exit;
	}

	/**
	 * Find a post by its invite token.
	 *
	 * @param string $token The invite token.
	 * @return \WP_Post|null
	 */
	public static function get_post_by_token( string $token ): ?\WP_Post {
		if ( '' === $token ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'draft', 'publish', 'pending', 'future', 'private' ),
				'posts_per_page' => 1,
				'meta_key'       => self::TOKEN_META_KEY,
				'meta_value'     => $token,
				'fields'         => 'all',
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Return the invite URL for a post, or null if none has been generated.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	public static function get_invite_url( int $post_id ): ?string {
		$token = get_post_meta( $post_id, self::TOKEN_META_KEY, true );
		if ( empty( $token ) ) {
			return null;
		}
		return self::build_url( $token );
	}

	/**
	 * Generate a new shared invite token for a post (replacing any existing one).
	 *
	 * @param int $post_id Post ID.
	 * @return string The new invite URL.
	 */
	public static function create_invite_url( int $post_id ): string {
		$token = wp_generate_password( 32, false );
		update_post_meta( $post_id, self::TOKEN_META_KEY, $token );
		return self::build_url( $token );
	}

	/**
	 * Delete the invite token for a post, invalidating any shared link.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function revoke_invite( int $post_id ): void {
		delete_post_meta( $post_id, self::TOKEN_META_KEY );
	}

	/**
	 * Build the invite URL from a raw token.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	private static function build_url( string $token ): string {
		return add_query_arg( 'map_invite', $token, home_url( '/' ) );
	}
}
