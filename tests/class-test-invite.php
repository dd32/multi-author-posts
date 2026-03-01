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

	public function test_get_invite_url_returns_null_when_none_created(): void {
		$this->assertNull( Invite::get_invite_url( $this->post_id ) );
	}

	public function test_create_invite_url_returns_url_with_token(): void {
		$url = Invite::create_invite_url( $this->post_id );
		$this->assertStringContainsString( 'map_invite=', $url );
	}

	public function test_get_invite_url_matches_created_url(): void {
		$created   = Invite::create_invite_url( $this->post_id );
		$retrieved = Invite::get_invite_url( $this->post_id );
		$this->assertSame( $created, $retrieved );
	}

	public function test_create_invite_url_generates_new_token_each_time(): void {
		$first  = Invite::create_invite_url( $this->post_id );
		$second = Invite::create_invite_url( $this->post_id );
		$this->assertNotSame( $first, $second );
	}

	public function test_revoke_invite_removes_url(): void {
		Invite::create_invite_url( $this->post_id );
		Invite::revoke_invite( $this->post_id );
		$this->assertNull( Invite::get_invite_url( $this->post_id ) );
	}

	public function test_get_post_by_valid_token(): void {
		Invite::create_invite_url( $this->post_id );
		$token = get_post_meta( $this->post_id, '_map_invite_token', true );
		$post  = Invite::get_post_by_token( $token );

		$this->assertNotNull( $post );
		$this->assertSame( $this->post_id, $post->ID );
	}

	public function test_get_post_by_invalid_token_returns_null(): void {
		$this->assertNull( Invite::get_post_by_token( 'not-a-real-token-xyz123' ) );
	}

	public function test_get_post_by_empty_token_returns_null(): void {
		$this->assertNull( Invite::get_post_by_token( '' ) );
	}

	public function test_shared_invite_can_be_used_by_multiple_users(): void {
		Invite::create_invite_url( $this->post_id );
		$token    = get_post_meta( $this->post_id, '_map_invite_token', true );
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
