<?php
/**
 * Tests for capability grants.
 *
 * @package MultiAuthorPosts
 */

namespace MultiAuthorPosts\Tests;

use MultiAuthorPosts\Co_Authors;
use WP_UnitTestCase;

/**
 * @covers \MultiAuthorPosts\Capabilities
 */
class Test_Capabilities extends WP_UnitTestCase {

	private int $post_id;
	private int $author_id;
	private int $subscriber_id;

	public function set_up(): void {
		parent::set_up();
		$this->author_id     = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->post_id       = self::factory()->post->create( array( 'post_author' => $this->author_id ) );
	}

	public function test_co_author_can_edit_post(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		$this->assertTrue( user_can( $this->subscriber_id, 'edit_post', $this->post_id ) );
	}

	public function test_non_co_author_cannot_edit_post(): void {
		$other = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertFalse( user_can( $other, 'edit_post', $this->post_id ) );
	}

	public function test_co_author_cannot_delete_post(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		$this->assertFalse( user_can( $this->subscriber_id, 'delete_post', $this->post_id ) );
	}

	public function test_co_author_can_read_post(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		$this->assertTrue( user_can( $this->subscriber_id, 'read_post', $this->post_id ) );
	}

	public function test_co_author_cannot_edit_a_different_post(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );

		$other_post = self::factory()->post->create( array( 'post_author' => $this->author_id ) );
		$this->assertFalse( user_can( $this->subscriber_id, 'edit_post', $other_post ) );
	}

	public function test_co_author_does_not_gain_global_edit_posts_cap(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		// 'edit_posts' (plural, no post_id) is not granted by our filter.
		$this->assertFalse( user_can( $this->subscriber_id, 'edit_posts' ) );
	}

	public function test_original_author_can_still_edit_post(): void {
		$this->assertTrue( user_can( $this->author_id, 'edit_post', $this->post_id ) );
	}
}
