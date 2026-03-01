<?php
/**
 * Tests for Co_Authors storage class.
 *
 * @package MultiAuthorPosts
 */

namespace MultiAuthorPosts\Tests;

use MultiAuthorPosts\Co_Authors;
use WP_UnitTestCase;

/**
 * @covers \MultiAuthorPosts\Co_Authors
 */
class Test_Co_Authors extends WP_UnitTestCase {

	private int $post_id;
	private int $author_id;
	private int $user_id;

	public function set_up(): void {
		parent::set_up();
		$this->author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->user_id   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->post_id   = self::factory()->post->create( array( 'post_author' => $this->author_id ) );
	}

	public function test_get_co_authors_returns_empty_array_by_default(): void {
		$this->assertSame( array(), Co_Authors::get_co_authors( $this->post_id ) );
	}

	public function test_add_co_author_returns_true(): void {
		$this->assertTrue( Co_Authors::add_co_author( $this->post_id, $this->user_id ) );
	}

	public function test_add_co_author_stores_user_id(): void {
		Co_Authors::add_co_author( $this->post_id, $this->user_id );
		$this->assertContains( $this->user_id, Co_Authors::get_co_authors( $this->post_id ) );
	}

	public function test_add_co_author_is_idempotent(): void {
		Co_Authors::add_co_author( $this->post_id, $this->user_id );
		Co_Authors::add_co_author( $this->post_id, $this->user_id );
		$this->assertCount( 1, Co_Authors::get_co_authors( $this->post_id ) );
	}

	public function test_post_author_is_not_stored_as_co_author(): void {
		Co_Authors::add_co_author( $this->post_id, $this->author_id );
		$this->assertNotContains( $this->author_id, Co_Authors::get_co_authors( $this->post_id ) );
	}

	public function test_remove_co_author(): void {
		Co_Authors::add_co_author( $this->post_id, $this->user_id );
		Co_Authors::remove_co_author( $this->post_id, $this->user_id );
		$this->assertNotContains( $this->user_id, Co_Authors::get_co_authors( $this->post_id ) );
	}

	public function test_is_co_author_false_before_add(): void {
		$this->assertFalse( Co_Authors::is_co_author( $this->post_id, $this->user_id ) );
	}

	public function test_is_co_author_true_after_add(): void {
		Co_Authors::add_co_author( $this->post_id, $this->user_id );
		$this->assertTrue( Co_Authors::is_co_author( $this->post_id, $this->user_id ) );
	}

	public function test_is_co_author_false_after_remove(): void {
		Co_Authors::add_co_author( $this->post_id, $this->user_id );
		Co_Authors::remove_co_author( $this->post_id, $this->user_id );
		$this->assertFalse( Co_Authors::is_co_author( $this->post_id, $this->user_id ) );
	}

	public function test_get_co_author_data_returns_enriched_array(): void {
		Co_Authors::add_co_author( $this->post_id, $this->user_id );
		$data = Co_Authors::get_co_author_data( $this->post_id );

		$this->assertCount( 1, $data );
		$this->assertSame( $this->user_id, $data[0]['id'] );
		$this->assertArrayHasKey( 'name', $data[0] );
		$this->assertArrayHasKey( 'avatar', $data[0] );
	}

	public function test_multiple_co_authors(): void {
		$second_user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Co_Authors::add_co_author( $this->post_id, $this->user_id );
		Co_Authors::add_co_author( $this->post_id, $second_user );

		$co_authors = Co_Authors::get_co_authors( $this->post_id );
		$this->assertCount( 2, $co_authors );
		$this->assertContains( $this->user_id, $co_authors );
		$this->assertContains( $second_user, $co_authors );
	}
}
