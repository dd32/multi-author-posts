=== Multi-Author Posts ===
Contributors: dd32
Tags: collaboration, co-authors, editor, invite, multi-author
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let multiple people edit one post. Add co-authors directly, or share a one-link invite — works alongside WordPress collaborative editing.

== Description ==

WordPress treats every post as having a single author. Multi-Author Posts adds first-class co-authors: any number of additional users who can read and edit the post alongside the original author, without changing roles or granting site-wide permissions.

= How it works =

1. Open a post in the block editor.
2. In the **Co-Authors** sidebar panel, add users by name — or generate a shared invite link.
3. Anyone with the invite link who is logged in to the site becomes a co-author of that one post. On multisite, invitees are auto-added to the site as Subscribers so they can reach the editor.
4. When the post is published, the invite link is auto-revoked. Existing co-authors keep their access; new joiners can't sneak in after the fact.

= Features =

* **Co-author management.** Add or remove co-authors from any post via the block-editor sidebar panel.
* **Shared invite links.** Generate a shareable URL that lets any registered user join as a co-author. Links expire after 24 hours and are auto-revoked on publish.
* **Capability-aware.** Co-authors gain `edit_post` / `read_post` on the assigned post only — no broader site permissions, no role changes.
* **Post-publish edit gate.** Co-authors lose edit access once the post is published. Site editors and admins can opt in per-post to keep co-author access after publish.
* **Multisite support.** Invitees are auto-added to the site with the Subscriber role so they can access the editor.
* **Author preservation.** When a post's author is reassigned, the previous author is automatically retained as a co-author so they don't lose access.

= Co-author permissions =

Co-authors have the same management trust as the original post author. They can:

* Add and remove other co-authors
* Generate, copy, and revoke the shared invite link
* Edit and read the post itself

They **cannot**:

* Reassign post authorship (requires `edit_others_posts`, enforced by core).
* Delete the post (the `map_meta_cap` filter explicitly excludes `delete_post`).
* Change the per-post "Allow co-authors to edit after publish" toggle (requires `edit_others_posts`).

= Editing after publish =

By default, publishing a post revokes co-authors' edit access — only the post author and site editors/admins can continue editing. Site editors and administrators can flip this per-post via the **"Allow co-authors to edit after publish"** toggle in the Co-Authors sidebar panel. Co-authors and Author-role post authors do not see this toggle.

== Frequently Asked Questions ==

= Does this require WordPress collaborative editing? =

No. The plugin works on any 6.6+ install. It composes cleanly with collaborative editing — co-authors are exactly the set of users core treats as having `edit_post` for the post.

= What happens to the invite link when the post is published? =

It's auto-revoked. Existing co-authors keep their access; new visits to the link see an "invalid or revoked" page. Site editors can generate a fresh link if needed.

= How long does an invite link last? =

24 hours from creation, or until the post is published — whichever comes first.

= Can co-authors delete the post or reassign authorship? =

No. Those capabilities require `edit_others_posts` and are deliberately excluded from co-author grants.

= Where's development happening? =

On GitHub at [dd32/multi-author-posts](https://github.com/dd32/multi-author-posts). That's where the source, issues, pull requests, and release tags live.

= I have a bug report or feature request =

File it on [GitHub](https://github.com/dd32/multi-author-posts/issues). General support questions belong in the [WordPress.org Support Forums](https://wordpress.org/support/plugin/multi-author-posts/).

== Changelog ==

= 0.1 =
* Initial alpha release.
