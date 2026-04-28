<?php
/**
 * Co-authors storage and retrieval.
 *
 * @package MultiAuthorPosts
 */

namespace MultiAuthorPosts;

/**
 * Manages the list of co-authors stored against each post.
 */
class Co_Authors {

	const META_KEY                     = '_map_co_author';
	const POST_PUBLISH_ACCESS_META_KEY = '_map_co_author_post_publish_access';

	/**
	 * Register post meta on init.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'post_updated', array( __CLASS__, 'preserve_previous_author' ), 10, 3 );
	}

	/**
	 * Register the co-authors post meta.
	 *
	 * Stored as one row per co-author (single = false) so concurrent adds
	 * don't race via a shared serialized array.
	 */
	public static function register_meta(): void {
		register_post_meta(
			'',
			self::META_KEY,
			array(
				'type'          => 'integer',
				'description'   => 'Co-author user ID (one row per co-author).',
				'single'        => false,
				'show_in_rest'  => false,
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		register_post_meta(
			'',
			self::POST_PUBLISH_ACCESS_META_KEY,
			array(
				'type'          => 'boolean',
				'description'   => 'Whether co-authors retain edit access after the post is published.',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return self::current_user_can_manage_settings( (int) $post_id );
				},
			)
		);
	}

	/**
	 * Whether co-authors retain edit access on a post once it is published.
	 *
	 * Defaults to false: publishing strips co-author edit access unless an
	 * editor/admin opts in for the specific post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function allows_post_publish_access( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::POST_PUBLISH_ACCESS_META_KEY, true );
	}

	/**
	 * Set the post-publish co-author access flag.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $allowed Whether co-authors retain edit access after publish.
	 */
	public static function set_post_publish_access( int $post_id, bool $allowed ): void {
		if ( $allowed ) {
			update_post_meta( $post_id, self::POST_PUBLISH_ACCESS_META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::POST_PUBLISH_ACCESS_META_KEY );
		}
	}

	/**
	 * Whether the current user can change co-author settings for a post.
	 *
	 * Gated on the post-type-specific edit_others_posts capability so that
	 * co-authors and lone post authors (e.g. an Author role) cannot toggle
	 * settings — only real site editors and admins.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function current_user_can_manage_settings( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		$pto = get_post_type_object( $post->post_type );
		$cap = $pto ? $pto->cap->edit_others_posts : 'edit_others_posts';
		return current_user_can( $cap );
	}

	/**
	 * Return the list of co-author user IDs for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public static function get_co_authors( int $post_id ): array {
		$values = get_post_meta( $post_id, self::META_KEY, false );
		if ( ! is_array( $values ) ) {
			return array();
		}
		$ids = array_map( 'intval', $values );
		// Dedupe — concurrent inserts for the same user could produce duplicate rows.
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Add a user as a co-author of a post.
	 *
	 * No-ops silently if the user is already the post author or already a co-author.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return bool Whether the operation succeeded.
	 */
	public static function add_co_author( int $post_id, int $user_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		if ( ! get_userdata( $user_id ) ) {
			return false;
		}

		// The post's original author never needs to be stored as a co-author.
		if ( (int) $post->post_author === $user_id ) {
			return true;
		}

		if ( self::is_co_author( $post_id, $user_id ) ) {
			return true;
		}

		return (bool) add_post_meta( $post_id, self::META_KEY, $user_id, false );
	}

	/**
	 * Remove a user from the co-author list of a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return bool Whether the operation succeeded.
	 */
	public static function remove_co_author( int $post_id, int $user_id ): bool {
		return delete_post_meta( $post_id, self::META_KEY, $user_id );
	}

	/**
	 * Check whether a user is a co-author of a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_co_author( int $post_id, int $user_id ): bool {
		return in_array( $user_id, self::get_co_authors( $post_id ), true );
	}

	/**
	 * Return enriched co-author data (id, name, avatar) for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array{id:int,name:string,avatar:string}>
	 */
	public static function get_co_author_data( int $post_id ): array {
		$result = array();
		foreach ( self::get_co_authors( $post_id ) as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			$result[] = array(
				'id'     => $user->ID,
				'name'   => $user->display_name,
				'avatar' => get_avatar_url( $user->ID, array( 'size' => 48 ) ),
			);
		}
		return $result;
	}

	/**
	 * When post authorship changes, preserve the previous author as a co-author.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post object after the update.
	 * @param \WP_Post $post_before Post object before the update.
	 */
	public static function preserve_previous_author( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		if ( (int) $post_before->post_author !== (int) $post_after->post_author ) {
			self::add_co_author( $post_id, (int) $post_before->post_author );
		}
	}
}
