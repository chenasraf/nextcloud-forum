<!--
SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
SPDX-License-Identifier: CC0-1.0
-->

# Nextcloud Forum

[![GitHub Release](https://img.shields.io/github/v/release/chenasraf/nextcloud-forum?color=blue)](https://github.com/chenasraf/nextcloud-forum/releases/latest)
[![PHPUnit MySQL](https://github.com/chenasraf/nextcloud-forum/actions/workflows/phpunit-mysql.yml/badge.svg)](https://github.com/chenasraf/nextcloud-forum/actions/workflows/phpunit-mysql.yml)
[![PHPUnit PostgreSQL](https://github.com/chenasraf/nextcloud-forum/actions/workflows/phpunit-pgsql.yml/badge.svg)](https://github.com/chenasraf/nextcloud-forum/actions/workflows/phpunit-pgsql.yml)

A full-featured forum application for Nextcloud, allowing users to create discussion categories,
threads, and posts within their Nextcloud instance.

![Screenshot](/screenshots/screenshot-01.png)

## ⚠️ Early Development Notice

**This app is in early stages of development.** While functional, you may encounter bugs or
incomplete features. Please report any issues on
[GitHub](https://github.com/chenasraf/nextcloud-forum/issues/new/choose) and consider backing up
your data regularly.

## Features

- **Category Management**: Organize discussions into categories with headers and custom permissions
- **Threaded Discussions**: Create and participate in threaded conversations
- **BBCode Support**: Rich text formatting with built-in and custom BBCode tags
- **File Attachments**: Attach files from Nextcloud to posts with secure permission-based access
- **Reactions**: React to posts with emoji reactions
- **User Roles & Permissions**: Fine-grained permission system for moderators and administrators
- **Guest Access**: Optional public access for unauthenticated users with configurable permissions
- **Read Markers**: Track unread posts and threads
- **Search**: Full-text search across threads and posts
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

## Development

For detailed development guides and documentation, visit the
[Development Wiki](https://github.com/chenasraf/nextcloud-forum/wiki/Development).

## License

This app is licensed under the [AGPL-3.0-or-later](LICENSE) license.
