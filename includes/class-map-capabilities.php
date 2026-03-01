<?php
/**
 * Capability grants for co-authors.
 *
 * @package MultiAuthorPosts
 */

namespace MultiAuthorPosts;

/**
 * Hooks into WordPress capability checks to grant co-authors permission
 * to edit (but not delete) the posts they have been invited to.
 *
 * Works for multisite users who have no role on the current site: returning
 * an empty primitive-caps array from the map_meta_cap filter means WordPress
 * considers the capability satisfied without requiring any site-level role.
 */
class Capabilities {

	/**
	 * Register capability filters.
	 */
	public static function init(): void {
		add_filter( 'map_meta_cap', array( __CLASS__, 'handle_co_author_caps' ), 10, 4 );
	}

	/**
	 * Grant co-authors the ability to edit and read (but not delete) their posts.
	 *
	 * @param string[] $caps    Required primitive capabilities.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User whose capability is being checked.
	 * @param mixed[]  $args    Extra arguments (first element is post ID for post caps).
	 * @return string[]
	 */
	public static function handle_co_author_caps( array $caps, string $cap, int $user_id, array $args ): array {
		// Only intercept post-specific capabilities (not delete_post).
		if ( ! in_array( $cap, array( 'edit_post', 'read_post', 'collab_edit_post' ), true ) ) {
			return $caps;
		}

		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( ! $post_id ) {
			return $caps;
		}

		if ( Co_Authors::is_co_author( $post_id, $user_id ) ) {
			// Returning an empty array grants the capability unconditionally,
			// which is intentional here: co-authors are explicitly trusted for
			// this specific post regardless of their site role.
			return array();
		}

		return $caps;
	}
}
