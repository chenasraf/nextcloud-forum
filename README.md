<!--
SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
SPDX-License-Identifier: CC0-1.0
-->

# Nextcloud Forum

[![GitHub Release](https://img.shields.io/github/v/release/chenasraf/nextcloud-forum?color=blue)](https://github.com/chenasraf/nextcloud-forum/releases/latest)
[![Build NPM](https://github.com/chenasraf/nextcloud-forum/actions/workflows/build-npm.yml/badge.svg)](https://github.com/chenasraf/nextcloud-forum/actions/workflows/build-npm.yml)
[![Lint PHP](https://github.com/chenasraf/nextcloud-forum/actions/workflows/lint-php.yml/badge.svg)](https://github.com/chenasraf/nextcloud-forum/actions/workflows/lint-php.yml)
[![Frontend Tests](https://github.com/chenasraf/nextcloud-forum/actions/workflows/vitest.yml/badge.svg)](https://github.com/chenasraf/nextcloud-forum/actions/workflows/vitest.yml)
[![PHPUnit MySQL](https://github.com/chenasraf/nextcloud-forum/actions/workflows/phpunit-mysql.yml/badge.svg)](https://github.com/chenasraf/nextcloud-forum/actions/workflows/phpunit-mysql.yml)
[![PHPUnit PostgreSQL](https://github.com/chenasraf/nextcloud-forum/actions/workflows/phpunit-pgsql.yml/badge.svg)](https://github.com/chenasraf/nextcloud-forum/actions/workflows/phpunit-pgsql.yml)

A full-featured forum application for Nextcloud, allowing users to create discussion categories,
threads, and posts within their Nextcloud instance.

![Screenshot](/screenshots/screenshot-01.png)

## Features

- **Category Management**: Organize discussions with headers, categories, customizable colors, and
  drag-and-drop reordering
- **Threaded Discussions**: Create and reply to organized discussion threads with pagination
- **BBCode Formatting**: Rich text with built-in and custom BBCode tags, toolbar with overflow menu
- **File Attachments**: Attach files from Nextcloud storage or upload via drag-and-drop
- **Notifications**: Subscribe to threads, get notified on replies and @mentions
- **Reactions**: React to posts with emoji reactions
- **Roles & Teams**: Fine-grained permissions per role or Nextcloud Team, per category (view, post,
  reply, moderate)
- **Granular Management Permissions**: Separate controls for dashboard, account management, roles,
  categories, and BBCodes
- **Guest Access**: Optional public access for unauthenticated visitors with configurable
  permissions
- **Edit History**: View post revision history with configurable visibility and per-account privacy
  controls
- **Read Markers**: Track unread posts at thread and category level
- **Bookmarks**: Save threads for quick access
- **Search**: Advanced search with boolean operators and category filtering
- **Reusable Templates**: Save and insert frequently used content snippets
- **Signatures**: BBCode-formatted signatures on posts
- **Thread Drafts**: Auto-saved drafts per category
- **User Profiles**: Post history, statistics, and role badges
- **Dashboard Widgets**: Recent activity, top threads, and top categories on the Nextcloud dashboard
- **Direct Post Links**: Link directly to a specific post within a thread
- **Moderation Tools**: Lock, pin, hide, and move threads; review and restore deleted content from
  the moderation page
- **Server Administration**: Repair seeds, rebuild statistics, and assign roles from the Nextcloud
  admin panel
- **Modern UI**: Built with Vue 3 and Nextcloud Vue components

## Documentation

For detailed usage instructions, administration guides, and more, visit the
[Forum Wiki](https://github.com/chenasraf/nextcloud-forum/wiki).

## Installation

### From the Nextcloud App Store

Install Forum directly from your Nextcloud instance through the Apps page.

### Manual Installation

1. Download the latest release from the [releases page](https://github.com/chenasraf/forum/releases)
2. Extract to your Nextcloud apps directory:

```bash
cd /path/to/nextcloud/custom_apps
tar xfv forum-vX.X.X.tar.gz
```

3. Enable the app from Nextcloud's Apps page or via command line:

```bash
php occ app:enable forum
```

## Administration

For detailed administration guides, usage instructions, and more, visit the
[Administration Wiki](https://github.com/chenasraf/nextcloud-forum/wiki/Administration).

### OCC Commands

The Forum app provides several OCC commands for administration and maintenance, including commands
for repairing installations, rebuilding statistics, and managing user roles.

For a complete list of available commands, usage examples, and detailed documentation, see the
[OCC Commands Wiki page](https://github.com/chenasraf/nextcloud-forum/wiki/OCC-Commands).

## Troubleshooting

For troubleshooting common issues, visit the
[Troubleshooting Wiki page](https://github.com/chenasraf/nextcloud-forum/wiki/Troubleshooting).

## Contributing

I am developing this app on my free time, so any support, whether code, issues, or just stars is
very helpful to sustaining its life. If you are feeling incredibly generous and would like to donate
just a small amount to help sustain this project, I would be very very thankful!

<a href='https://ko-fi.com/casraf' target='_blank'>
  <img height='36' style='border:0px;height:36px;'
    src='https://cdn.ko-fi.com/cdn/kofi1.png?v=3'
    alt='Buy Me a Coffee at ko-fi.com' />
</a>

I welcome any issues or pull requests on GitHub. If you find a bug, or would like a new feature,
don't hesitate to open an appropriate issue and I will do my best to reply promptly.

### Translations

If you're interested in translating this app, please visit the
[Forum app resource on Transifex](https://app.transifex.com/nextcloud/nextcloud/forum/).

For more information about translations, including how to join the Nextcloud project, see
[Translate Nextcloud](https://nextcloud.com/translation/).

Translation resources are synced daily and updates are available on the next release of the app
after they are synced.

## Development

For detailed development guides and documentation, visit the
[Development Wiki](https://github.com/chenasraf/nextcloud-forum/wiki/Development).

## License

This app is licensed under the [AGPL-3.0-or-later](LICENSE) license.
