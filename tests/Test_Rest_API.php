<?php
/**
 * Tests for REST API endpoints.
 *
 * @package MultiAuthorPosts
 */

namespace MultiAuthorPosts\Tests;

use MultiAuthorPosts\Co_Authors;
use MultiAuthorPosts\Invite;
use WP_UnitTestCase;
use WP_REST_Request;

/**
 * @covers \MultiAuthorPosts\Rest_API
 */
class Test_Rest_API extends WP_UnitTestCase {

	private int $post_id;
	private int $author_id;
	private int $editor_id;
	private int $subscriber_id;

	public function set_up(): void {
		parent::set_up();
		$this->author_id     = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->editor_id     = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		// Default to a draft so the post-publish co-author gate doesn't
		// interfere with REST tests that aren't about publish behavior.
		$this->post_id       = self::factory()->post->create( array(
			'post_author' => $this->author_id,
			'post_status' => 'draft',
		) );
	}

	// -------------------------------------------------------------------------
	// GET co-authors
	// -------------------------------------------------------------------------

	public function test_get_co_authors_as_post_author(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		wp_set_current_user( $this->author_id );

		$request  = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 1, $data );
		$this->assertSame( $this->subscriber_id, $data[0]['id'] );
	}

	public function test_get_co_authors_as_co_author(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_co_authors_forbidden_for_unrelated_user(): void {
		$other = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $other );

		$request  = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// POST co-authors (direct add)
	// -------------------------------------------------------------------------

	public function test_add_co_author_directly_as_post_author(): void {
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'POST', '/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors' );
		$request->set_param( 'user_id', $this->editor_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( Co_Authors::is_co_author( $this->post_id, $this->editor_id ) );
	}

	public function test_add_co_author_directly_rejects_non_editor_user(): void {
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'POST', '/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors' );
		$request->set_param( 'user_id', $this->subscriber_id );
		$response = rest_get_server()->dispatch( $request );

		// Subscriber has no edit_posts cap → 400
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_co_author_can_add_other_co_authors(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'POST', '/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors' );
		$request->set_param( 'user_id', $this->editor_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( Co_Authors::is_co_author( $this->post_id, $this->editor_id ) );
	}

	public function test_co_author_can_remove_other_co_author(): void {
		$another = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		Co_Authors::add_co_author( $this->post_id, $another );
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request(
			'DELETE',
			'/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors/' . $another
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( Co_Authors::is_co_author( $this->post_id, $another ) );
	}

	// -------------------------------------------------------------------------
	// DELETE co-authors
	// -------------------------------------------------------------------------

	public function test_remove_co_author_as_post_author(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		wp_set_current_user( $this->author_id );

		$request  = new WP_REST_Request(
			'DELETE',
			'/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors/' . $this->subscriber_id
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( Co_Authors::is_co_author( $this->post_id, $this->subscriber_id ) );
	}

	public function test_remove_non_co_author_returns_404(): void {
		wp_set_current_user( $this->author_id );

		$request  = new WP_REST_Request(
			'DELETE',
			'/multi-author-posts/v1/posts/' . $this->post_id . '/co-authors/' . $this->subscriber_id
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// Invite
	// -------------------------------------------------------------------------

	public function test_get_invite_reports_inactive_when_none_created(): void {
		wp_set_current_user( $this->author_id );

		$request  = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/invite' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['active'] );
	}

	public function test_create_invite_returns_url(): void {
		wp_set_current_user( $this->author_id );

		$request  = new WP_REST_Request( 'POST', '/multi-author-posts/v1/posts/' . $this->post_id . '/invite' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertStringContainsString( 'map_invite=', $data['invite_url'] );
	}

	public function test_revoke_invite(): void {
		Invite::create_invite_url( $this->post_id );
		wp_set_current_user( $this->author_id );

		$request  = new WP_REST_Request( 'DELETE', '/multi-author-posts/v1/posts/' . $this->post_id . '/invite' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( Invite::get_invite_status( $this->post_id )['active'] );
	}

	public function test_co_author_can_manage_invites(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'POST', '/multi-author-posts/v1/posts/' . $this->post_id . '/invite' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_unrelated_subscriber_cannot_manage_invites(): void {
		$other = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $other );

		$request  = new WP_REST_Request( 'POST', '/multi-author-posts/v1/posts/' . $this->post_id . '/invite' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_cpt_specific_cap_is_checked(): void {
		register_post_type(
			'map_cpt',
			array(
				'public'          => true,
				'capability_type' => array( 'map_thing', 'map_things' ),
				'map_meta_cap'    => true,
			)
		);
		$cpt_post = self::factory()->post->create(
			array(
				'post_type'   => 'map_cpt',
				'post_author' => $this->author_id,
			)
		);

		// Editor has edit_others_posts but NOT edit_others_map_things.
		wp_set_current_user( $this->editor_id );

		$request  = new WP_REST_Request( 'POST', '/multi-author-posts/v1/posts/' . $cpt_post . '/invite' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );

		unregister_post_type( 'map_cpt' );
	}

	// -------------------------------------------------------------------------
	// Suggested authors
	// -------------------------------------------------------------------------

	public function test_suggested_authors_returns_empty_for_short_search(): void {
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/suggested-authors' );
		$request->set_param( 'search', 'a' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_suggested_authors_returns_empty_for_empty_search(): void {
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/suggested-authors' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_suggested_authors_does_not_search_by_email_for_authors(): void {
		$target = self::factory()->user->create(
			array(
				'role'         => 'author',
				'user_email'   => 'unique-test-email@example.com',
				'display_name' => 'Test Target',
			)
		);
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/suggested-authors' );
		$request->set_param( 'search', 'unique-test-email' );
		$response = rest_get_server()->dispatch( $request );

		$ids = array_column( $response->get_data(), 'id' );
		$this->assertNotContains( $target, $ids );
	}

	public function test_suggested_authors_searches_by_email_for_admins(): void {
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$target = self::factory()->user->create(
			array(
				'role'         => 'author',
				'user_email'   => 'admin-findable@example.com',
				'display_name' => 'Hidden Name',
			)
		);
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/suggested-authors' );
		$request->set_param( 'search', 'admin-findable' );
		$response = rest_get_server()->dispatch( $request );

		$ids = array_column( $response->get_data(), 'id' );
		$this->assertContains( $target, $ids );
	}

	public function test_suggested_authors_returns_results_for_valid_search(): void {
		$target = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Searchable Author',
			)
		);
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/suggested-authors' );
		$request->set_param( 'search', 'Searchable' );
		$response = rest_get_server()->dispatch( $request );

		$ids = array_column( $response->get_data(), 'id' );
		$this->assertContains( $target, $ids );
	}

	// -------------------------------------------------------------------------
	// Settings (post-publish co-author access)
	// -------------------------------------------------------------------------

	public function test_get_settings_returns_default_off_for_post_author(): void {
		wp_set_current_user( $this->author_id );

		$request  = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['allow_post_publish_edit'] );
		// The post author here is the Author role, which lacks edit_others_posts.
		$this->assertFalse( $data['can_edit_settings'] );
	}

	public function test_get_settings_reports_can_edit_settings_for_editor(): void {
		wp_set_current_user( $this->editor_id );

		$request  = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['can_edit_settings'] );
	}

	public function test_get_settings_visible_to_co_authors(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/multi-author-posts/v1/posts/' . $this->post_id . '/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['can_edit_settings'] );
	}

	public function test_update_settings_as_editor(): void {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', '/multi-author-posts/v1/posts/' . $this->post_id . '/settings' );
		$request->set_param( 'allow_post_publish_edit', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['allow_post_publish_edit'] );
		$this->assertTrue( Co_Authors::allows_post_publish_access( $this->post_id ) );
	}

	public function test_update_settings_forbidden_for_post_author_without_edit_others_posts(): void {
		// Author role: has edit_post for own posts, but NOT edit_others_posts.
		wp_set_current_user( $this->author_id );

		$request = new WP_REST_Request( 'PUT', '/multi-author-posts/v1/posts/' . $this->post_id . '/settings' );
		$request->set_param( 'allow_post_publish_edit', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( Co_Authors::allows_post_publish_access( $this->post_id ) );
	}

	public function test_update_settings_forbidden_for_co_author(): void {
		Co_Authors::add_co_author( $this->post_id, $this->subscriber_id );
		wp_set_current_user( $this->subscriber_id );

		$request = new WP_REST_Request( 'PUT', '/multi-author-posts/v1/posts/' . $this->post_id . '/settings' );
		$request->set_param( 'allow_post_publish_edit', true );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( Co_Authors::allows_post_publish_access( $this->post_id ) );
	}

	public function test_update_settings_can_disable_after_enabling(): void {
		wp_set_current_user( $this->editor_id );
		Co_Authors::set_post_publish_access( $this->post_id, true );

		$request = new WP_REST_Request( 'PUT', '/multi-author-posts/v1/posts/' . $this->post_id . '/settings' );
		$request->set_param( 'allow_post_publish_edit', false );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['allow_post_publish_edit'] );
		$this->assertFalse( Co_Authors::allows_post_publish_access( $this->post_id ) );
	}

	public function test_editor_can_manage_co_authors_via_edit_others_posts(): void {
		// An editor (who has edit_others_posts) can manage co-authors even
		// though they are not the post author.
		wp_set_current_user( $this->editor_id );

		$request  = new WP_REST_Request( 'POST', '/multi-author-posts/v1/posts/' . $this->post_id . '/invite' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
	}
}
