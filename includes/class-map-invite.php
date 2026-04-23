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

	const TOKEN_META_KEY   = '_map_invite_token';
	const CREATED_META_KEY = '_map_invite_created';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'handle_invite_request' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'revoke_on_publish' ), 10, 3 );
	}

	/**
	 * Auto-revoke an active invite when a post transitions to `publish`.
	 *
	 * Existing co-authors keep their access; the shared link simply stops
	 * admitting new people. The author can regenerate a fresh link if more
	 * collaborators need to be added post-publish.
	 */
	public static function revoke_on_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			self::revoke_invite( $post->ID );
		}
	}

	/**
	 * Return the TTL (in seconds) for invite tokens.
	 *
	 * Filter `map_invite_ttl` to override; defaults to 24 hours.
	 */
	public static function get_ttl(): int {
		return (int) apply_filters( 'map_invite_ttl', DAY_IN_SECONDS );
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

		$statuses = array_values( array_filter(
			get_post_stati( array( 'internal' => false ) ),
			fn( $status ) => ! in_array( $status, array( 'trash', 'auto-draft' ), true )
		) );

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => $statuses,
				'posts_per_page' => 1,
				'meta_key'       => self::TOKEN_META_KEY,
				'meta_value'     => self::hash_token( $token ),
				'fields'         => 'all',
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$post    = $posts[0];
		$created = (int) get_post_meta( $post->ID, self::CREATED_META_KEY, true );
		if ( ! $created || ( time() - $created ) > self::get_ttl() ) {
			// Expired — clean up and reject.
			self::revoke_invite( $post->ID );
			return null;
		}

		return $post;
	}

	/**
	 * Return invite status for a post.
	 *
	 * The plaintext token is never returned after creation; the sidebar gets
	 * the URL exactly once, at generation time.
	 *
	 * @param int $post_id Post ID.
	 * @return array{active:bool,created:?int,expires:?int}
	 */
	public static function get_invite_status( int $post_id ): array {
		$hash    = get_post_meta( $post_id, self::TOKEN_META_KEY, true );
		$created = (int) get_post_meta( $post_id, self::CREATED_META_KEY, true );

		if ( empty( $hash ) || ! $created ) {
			return array( 'active' => false, 'created' => null, 'expires' => null );
		}

		$expires = $created + self::get_ttl();
		if ( time() > $expires ) {
			self::revoke_invite( $post_id );
			return array( 'active' => false, 'created' => null, 'expires' => null );
		}

		return array(
			'active'  => true,
			'created' => $created,
			'expires' => $expires,
		);
	}

	/**
	 * Generate a new shared invite token for a post (replacing any existing one).
	 *
	 * The returned plaintext URL is the only opportunity to see it; only a
	 * hash is persisted.
	 *
	 * @param int $post_id Post ID.
	 * @return string The new invite URL.
	 */
	public static function create_invite_url( int $post_id ): string {
		$token = wp_generate_password( 32, false );
		update_post_meta( $post_id, self::TOKEN_META_KEY, self::hash_token( $token ) );
		update_post_meta( $post_id, self::CREATED_META_KEY, time() );
		return self::build_url( $token );
	}

	/**
	 * Delete the invite token for a post, invalidating any shared link.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function revoke_invite( int $post_id ): void {
		delete_post_meta( $post_id, self::TOKEN_META_KEY );
		delete_post_meta( $post_id, self::CREATED_META_KEY );
	}

	/**
	 * Hash a plaintext token for storage / lookup.
	 *
	 * @param string $token Plaintext token.
	 * @return string
	 */
	private static function hash_token( string $token ): string {
		return hash( 'sha256', $token );
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
