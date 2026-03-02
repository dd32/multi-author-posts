# Multi-Author Posts

A WordPress plugin that allows multiple authors to edit a single post via shared invite links. Compatible with WordPress collaborative editing.

[Test in WordPress Playground](https://playground.wordpress.net/?plugin=https://github.com/dd32/multi-author-posts&login=yes&url=/wp-admin/post-new.php)

## Features

- **Co-author management** -- Add or remove co-authors from any post via the block editor sidebar panel.
- **Shared invite links** -- Generate a shareable URL that lets any registered user join as a co-author.
- **Capability-aware** -- Co-authors can edit and read their assigned posts without gaining broader site permissions.
- **Multisite support** -- Invited users are automatically added to the site with a subscriber role so they can access the editor.
- **Author preservation** -- When a post's author is reassigned, the previous author is automatically kept as a co-author.

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
