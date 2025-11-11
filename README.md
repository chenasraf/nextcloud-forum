<!--
SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
SPDX-License-Identifier: CC0-1.0
-->

# Nextcloud Forum

A full-featured forum application for Nextcloud, allowing users to create discussion categories,
threads, and posts within their Nextcloud instance.

## Features

- **Category Management**: Organize discussions into categories with headers and custom permissions
- **Threaded Discussions**: Create and participate in threaded conversations
- **BBCode Support**: Rich text formatting with built-in and custom BBCode tags
- **File Attachments**: Attach files from Nextcloud to posts with secure permission-based access
- **Reactions**: React to posts with emoji reactions
- **User Roles & Permissions**: Fine-grained permission system for moderators and administrators
- **Read Markers**: Track unread posts and threads
- **Search**: Full-text search across threads and posts
- **Modern UI**: Built with Vue 3 and Nextcloud Vue components

## Installation

### From the Nextcloud App Store

Install Forum directly from your Nextcloud instance through the Apps page.

### Manual Installation

1. Download the latest release from the
   [releases page](https://github.com/yourusername/forum/releases)
2. Extract to your Nextcloud apps directory:

```bash
cd /path/to/nextcloud/custom_apps
tar xfv forum-vX.X.X.tar.gz
```

3. Enable the app from Nextcloud's Apps page or via command line:

```bash
php occ app:enable forum
```

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

### Automation

Most development processes are automated:

- **GitHub Actions** run tests, builds, and validations on each push or pull request.
- **Pre-commit formatting** is handled by [lint-staged](https://github.com/okonet/lint-staged),
  which automatically formats code before committing.

> 🛠️ The NPM package [husky](https://www.npmjs.com/package/husky) takes care of installing the
> pre-commit hook automatically after `pnpm install`.

---

### Manual Commands

While automation handles most workflows, the following commands are available for local development
and debugging:

#### Build the App

```bash
make
```

Installs dependencies and compiles frontend/backend assets.

#### Run Tests

```bash
make test
```

Runs unit and integration tests (if available).

#### Format & Lint

```bash
make format   # Auto-fix code style
make lint     # Check code quality
```

#### Generate OpenAPI Docs

```bash
make openapi
```

Output is saved to `build/openapi/openapi.json`.

#### Packaging for Release

```bash
make appstore    # Production build for Nextcloud app store
make source      # Full source package
make distclean   # Clean build artifacts and dependencies
```

#### Sign Releases

After uploading the archive to GitHub:

```bash
make sign
```

Downloads the `.tar.gz` release, verifies it, and prints a SHA-512 signature using your key at
`~/.nextcloud/certificates/forum.key`.

---

### Scaffolding

Generate boilerplate for common app pieces with:

```bash
pnpm gen <type> [name]
```

- **`name` is required** for every type **except** `migration`.
- Files are created from templates in `gen/<type>` and written to the configured output directory.
  Feel free to modify/remove any of these templates or add new ones.
- Generators never create subfolders (they write directly into the output path).

#### Available generators

| Type          | Purpose                                   | Output directory | Name required? | Template folder   | Notes                                             |
| ------------- | ----------------------------------------- | ---------------- | -------------- | ----------------- | ------------------------------------------------- |
| `component`   | Vue single-file component for reusable UI | `src/components` | ✅             | `gen/component`   | For user-facing building blocks.                  |
| `page`        | Vue page / route view                     | `src/pages`      | ✅             | `gen/page`        | Pair with your router.                            |
| `api`         | PHP controller (API endpoint)             | `lib/Controller` | ✅             | `gen/api`         | PSR-4 namespace: `OCA\<App>\Controller`.          |
| `service`     | PHP service class                         | `lib/Service`    | ✅             | `gen/service`     | Business logic; DI-friendly.                      |
| `util`        | PHP utility/helper                        | `lib/Util`       | ✅             | `gen/util`        | Pure helpers / small utilities.                   |
| `model`       | PHP DB model / entity                     | `lib/Db`         | ✅             | `gen/model`       | Pair with migrations.                             |
| `command`     | Nextcloud OCC console command             | `lib/Command`    | ✅             | `gen/command`     | Shows up in `occ`.                                |
| `task-queued` | Queued background job                     | `lib/Cron`       | ✅             | `gen/task-queued` | Extend queued job base.                           |
| `task-timed`  | Timed background job (cron)               | `lib/Cron`       | ✅             | `gen/task-timed`  | Scheduled execution.                              |
| `migration`   | Database migration                        | `lib/Migration`  | ❌             | `gen/migration`   | Auto-numbers version; injects `version` and `dt`. |

##### How migrations are numbered

The scaffolder looks at `lib/Migration`, finds the latest `VersionNNNN...` file, and **increments**
it for you. It also injects:

- `version` — the next numeric version
- `dt` — a timestamp like `YYYYMMDDHHmmss` (via `date-fns`)

You don’t pass a name for migrations.

#### Examples

Create a Vue component:

```bash
pnpm gen component UserListItem
# → src/components/UserListItem.vue
```

Create a Vue page:

```bash
pnpm gen page Settings
# → src/pages/Settings.vue
```

Create an API controller:

```bash
pnpm gen api Users
# → lib/Controller/UsersController.php
```

Create a service:

```bash
pnpm gen service MyService
# → lib/Service/MyService.php
```

Create a queued job:

```bash
pnpm gen task-queued UpdateUsers
# → lib/Cron/UpdateUsers.php
```

Create a migration (no name):

```bash
pnpm gen migration
# → lib/Migration/Version{NEXT}.php   (with injected {version} and {dt})
```

## Resources

### Nextcloud Development

- [Nextcloud App Development Guide](https://nextcloud.com/developer/)
- [Nextcloud Developer Manual](https://docs.nextcloud.com/server/latest/developer_manual/)
- [Nextcloud Vue Components](https://github.com/nextcloud/nextcloud-vue)
- [Publishing to the App Store](https://nextcloudappstore.readthedocs.io/en/latest/developer.html)

### Technologies Used

- **Frontend**: [Vue 3](https://vuejs.org/), [Vite](https://vitejs.dev/),
  [TypeScript](https://www.typescriptlang.org/)
- **Backend**: PHP 8.1+,
  [Nextcloud OCP API](https://docs.nextcloud.com/server/latest/developer_manual/)
- **Database**: SQLite, MySQL, or PostgreSQL (via Nextcloud)
- **BBCode Parsing**: [ChrisKonnertz/BBCode](https://github.com/chriskonnertz/bbcode)

## License

This app is licensed under the [AGPL-3.0-or-later](LICENSE) license.
