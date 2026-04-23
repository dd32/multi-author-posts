<?php
/**
 * Tests for the shared invite-link system.
 *
 * @package MultiAuthorPosts
 */

namespace MultiAuthorPosts\Tests;

use MultiAuthorPosts\Co_Authors;
use MultiAuthorPosts\Invite;
use WP_UnitTestCase;

/**
 * @covers \MultiAuthorPosts\Invite
 */
class Test_Invite extends WP_UnitTestCase {

	private int $post_id;
	private int $author_id;

	public function set_up(): void {
		parent::set_up();
		$this->author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->post_id   = self::factory()->post->create( array( 'post_author' => $this->author_id ) );
	}

	private function extract_token( string $url ): string {
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( (string) $query, $args );
		return (string) ( $args['map_invite'] ?? '' );
	}

	public function test_invite_status_inactive_when_none_created(): void {
		$status = Invite::get_invite_status( $this->post_id );
		$this->assertFalse( $status['active'] );
	}

	public function test_create_invite_url_returns_url_with_token(): void {
		$url = Invite::create_invite_url( $this->post_id );
		$this->assertStringContainsString( 'map_invite=', $url );
	}

	public function test_stored_meta_is_not_the_raw_token(): void {
		$url    = Invite::create_invite_url( $this->post_id );
		$token  = $this->extract_token( $url );
		$stored = get_post_meta( $this->post_id, '_map_invite_token', true );

		$this->assertNotSame( $token, $stored );
		$this->assertSame( hash( 'sha256', $token ), $stored );
	}

	public function test_create_invite_url_generates_new_token_each_time(): void {
		$first  = Invite::create_invite_url( $this->post_id );
		$second = Invite::create_invite_url( $this->post_id );
		$this->assertNotSame( $first, $second );
	}

	public function test_revoke_invite_clears_storage(): void {
		Invite::create_invite_url( $this->post_id );
		Invite::revoke_invite( $this->post_id );

		$this->assertFalse( Invite::get_invite_status( $this->post_id )['active'] );
		$this->assertSame( '', get_post_meta( $this->post_id, '_map_invite_token', true ) );
	}

	public function test_get_post_by_valid_token(): void {
		$url   = Invite::create_invite_url( $this->post_id );
		$token = $this->extract_token( $url );

		$post = Invite::get_post_by_token( $token );
		$this->assertNotNull( $post );
		$this->assertSame( $this->post_id, $post->ID );
	}

	public function test_get_post_by_invalid_token_returns_null(): void {
		$this->assertNull( Invite::get_post_by_token( 'not-a-real-token-xyz123' ) );
	}

	public function test_get_post_by_empty_token_returns_null(): void {
		$this->assertNull( Invite::get_post_by_token( '' ) );
	}

	public function test_tampered_token_returns_null(): void {
		$url   = Invite::create_invite_url( $this->post_id );
		$token = $this->extract_token( $url );

		$this->assertNull( Invite::get_post_by_token( substr( $token, 0, -1 ) ) );
	}

	public function test_get_post_by_token_resolves_custom_post_status(): void {
		register_post_status(
			'in-review',
			array(
				'label'                     => 'In Review',
				'public'                    => false,
				'internal'                  => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			)
		);

		$url   = Invite::create_invite_url( $this->post_id );
		$token = $this->extract_token( $url );

		wp_update_post(
			array(
				'ID'          => $this->post_id,
				'post_status' => 'in-review',
			)
		);

		$post = Invite::get_post_by_token( $token );
		$this->assertNotNull( $post );
		$this->assertSame( $this->post_id, $post->ID );
	}

	public function test_get_post_by_token_does_not_resolve_trashed_post(): void {
		$url   = Invite::create_invite_url( $this->post_id );
		$token = $this->extract_token( $url );

		wp_trash_post( $this->post_id );

		$this->assertNull( Invite::get_post_by_token( $token ) );
	}

	public function test_token_expires_after_ttl(): void {
		$url   = Invite::create_invite_url( $this->post_id );
		$token = $this->extract_token( $url );

		// Backdate creation well past the default 24h TTL.
		update_post_meta( $this->post_id, '_map_invite_created', time() - ( 2 * DAY_IN_SECONDS ) );

		$this->assertNull( Invite::get_post_by_token( $token ) );
	}

	public function test_token_within_ttl_is_accepted(): void {
		$url   = Invite::create_invite_url( $this->post_id );
		$token = $this->extract_token( $url );

		update_post_meta( $this->post_id, '_map_invite_created', time() - HOUR_IN_SECONDS );

		$this->assertNotNull( Invite::get_post_by_token( $token ) );
	}

	public function test_ttl_filter_can_shorten_expiry(): void {
		$url   = Invite::create_invite_url( $this->post_id );
		$token = $this->extract_token( $url );

		update_post_meta( $this->post_id, '_map_invite_created', time() - ( 10 * MINUTE_IN_SECONDS ) );

		$filter = fn() => 5 * MINUTE_IN_SECONDS;
		add_filter( 'map_invite_ttl', $filter );
		$result = Invite::get_post_by_token( $token );
		remove_filter( 'map_invite_ttl', $filter );

		$this->assertNull( $result );
	}

	public function test_expired_token_is_cleaned_up_on_lookup(): void {
		Invite::create_invite_url( $this->post_id );
		update_post_meta( $this->post_id, '_map_invite_created', time() - ( 2 * DAY_IN_SECONDS ) );

		Invite::get_invite_status( $this->post_id );

		$this->assertSame( '', get_post_meta( $this->post_id, '_map_invite_token', true ) );
	}

	public function test_invite_is_revoked_when_post_is_published(): void {
		Invite::init();

		$draft = self::factory()->post->create(
			array(
				'post_author' => $this->author_id,
				'post_status' => 'draft',
			)
		);
		Invite::create_invite_url( $draft );
		$this->assertTrue( Invite::get_invite_status( $draft )['active'] );

		wp_update_post(
			array(
				'ID'          => $draft,
				'post_status' => 'publish',
			)
		);

		$this->assertFalse( Invite::get_invite_status( $draft )['active'] );
	}

	public function test_publish_to_publish_does_not_revoke(): void {
		Invite::init();

		$published = self::factory()->post->create(
			array(
				'post_author' => $this->author_id,
				'post_status' => 'publish',
			)
		);
		Invite::create_invite_url( $published );

		wp_update_post(
			array(
				'ID'         => $published,
				'post_title' => 'edited',
			)
		);

		$this->assertTrue( Invite::get_invite_status( $published )['active'] );
	}

	public function test_shared_invite_can_be_used_by_multiple_users(): void {
		$url      = Invite::create_invite_url( $this->post_id );
		$token    = $this->extract_token( $url );
		$user_one = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$user_two = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Simulate both users redeeming the same token.
		$post = Invite::get_post_by_token( $token );
		Co_Authors::add_co_author( $post->ID, $user_one );
		Co_Authors::add_co_author( $post->ID, $user_two );

		$this->assertTrue( Co_Authors::is_co_author( $this->post_id, $user_one ) );
		$this->assertTrue( Co_Authors::is_co_author( $this->post_id, $user_two ) );
	}
}
