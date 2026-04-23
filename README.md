# Multi-Author Posts

A WordPress plugin that allows multiple authors to edit a single post via shared invite links. Compatible with WordPress collaborative editing.

[Test in WordPress Playground](https://playground.wordpress.net/#eyJsYW5kaW5nUGFnZSI6Ii93cC1hZG1pbi9wb3N0LW5ldy5waHAiLCJsb2dpbiI6dHJ1ZSwic3RlcHMiOlt7InN0ZXAiOiJpbnN0YWxsUGx1Z2luIiwicGx1Z2luRGF0YSI6eyJyZXNvdXJjZSI6ImdpdDpkaXJlY3RvcnkiLCJ1cmwiOiJodHRwczovL2dpdGh1Yi5jb20vZGQzMi9tdWx0aS1hdXRob3ItcG9zdHMiLCJyZWYiOiJIRUFEIiwicmVmVHlwZSI6InJlZm5hbWUifX0seyJzdGVwIjoid3AtY2xpIiwiY29tbWFuZCI6IndwIHVzZXIgY3JlYXRlIGFsaWNlIGFsaWNlQGV4YW1wbGUuY29tIC0tcm9sZT1hdXRob3IgLS1kaXNwbGF5X25hbWU9QWxpY2UgLS11c2VyX3Bhc3M9cGFzc3dvcmQifSx7InN0ZXAiOiJ3cC1jbGkiLCJjb21tYW5kIjoid3AgdXNlciBjcmVhdGUgYm9iIGJvYkBleGFtcGxlLmNvbSAtLXJvbGU9YXV0aG9yIC0tZGlzcGxheV9uYW1lPUJvYiAtLXVzZXJfcGFzcz1wYXNzd29yZCJ9LHsic3RlcCI6IndwLWNsaSIsImNvbW1hbmQiOiJ3cCB1c2VyIGNyZWF0ZSBjYXJvbCBjYXJvbEBleGFtcGxlLmNvbSAtLXJvbGU9ZWRpdG9yIC0tZGlzcGxheV9uYW1lPUNhcm9sIC0tdXNlcl9wYXNzPXBhc3N3b3JkIn1dfQ==)

## Features

- **Co-author management** -- Add or remove co-authors from any post via the block editor sidebar panel.
- **Shared invite links** -- Generate a shareable URL that lets any registered user join as a co-author. Links expire after 24 hours and are automatically revoked when the post is published; existing co-authors keep their access.
- **Capability-aware** -- Co-authors can edit and read their assigned posts without gaining broader site permissions.
- **Multisite support** -- Invited users are automatically added to the site with a subscriber role so they can access the editor.
- **Author preservation** -- When a post's author is reassigned, the previous author is automatically kept as a co-author.

## Co-author permissions

Co-authors have the same management trust as the original post author. They can:

- Add and remove other co-authors
- Generate, copy, and revoke the shared invite link
- Edit and read the post itself

The only things they **cannot** do:

- Reassign post authorship (requires `edit_others_posts`, enforced by WordPress core)
- Delete the post (the `map_meta_cap` filter explicitly excludes `delete_post`)

## Requirements

- WordPress 6.6+
- PHP 7.4+
- Node.js 20+ (for development)

## Development

```bash
# Install dependencies
npm install

# Start the development environment
npm run env:start

# Build the editor assets
npm run build

# Run JavaScript tests
npm run test:unit

# Run PHP tests (requires wp-env)
npm run test:php
```
