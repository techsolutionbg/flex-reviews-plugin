# Flex Reviews

Product comments and star ratings for Flex Commerce, with moderation, threaded replies, spam protection and optional email notifications.

Version: **0.1.3** · ID: `flex/reviews` · Minimum Flex CMS version: **0.1.55**.

GitHub repository: `techsolutionbg/flex-reviews-plugin`.

## Requirements and installation

Requires an installed Flex CMS with PHP 8.3+, its database, mailer and Extension API V1. **Flex Commerce** must be installed first: installation fails if the `flex_commerce_products` table is missing. The product page template must render the `commerce.product.tabs` filter, which Flex Commerce provides. **Flex Accounts** is optional; when active, signed-in customers comment with their account identity.

Place the package in `plugins/flex/reviews/`, with `plugin.json` directly inside that folder. From the Flex CMS root:

```sh
php bin/flex plugin:install flex/reviews
php bin/flex plugin:activate flex/reviews
```

With Docker, prefix the commands with `docker compose exec app`. Installation, activation and updates create the missing `flex_reviews` and `flex_reviews_settings` tables and add newer columns without recreating existing data.

## What it adds

- A **Comments** tab on the product page, showing the number of approved comments. Quick product previews keep their original tabs.
- A public comment form with an optional 1–5 star rating.
- Threaded replies from customers and from the store.
- An administration page **Comments** in the shop group, with moderation, search, a thread view, store replies and a settings screen.
- Optional email notifications for the administrator and for thread participants.

## Moderation

Comments have one of the statuses `pending`, `approved`, `spam` or `trash`. The administration list filters by status (including `all`), searches product names, comment text and author names, and pages through 25 records at a time. Up to 100 comments can be moderated in one bulk action.

Store replies are published immediately under the name of the store. Approving a pending reply triggers the reply notification for that thread. Hiding a root comment hides the whole thread; moderating a single reply keeps the rest of the thread visible.

## Settings

| Setting | Default | Notes |
| --- | --- | --- |
| `enabled` | on | Shows the Comments tab and accepts new comments |
| `ratings` | on | Optional 1–5 rating on root comments |
| `guests` | on | Allows comments without signing in |
| `moderation` | on | New root comments wait for approval |
| `trusted_authors` | off | Auto-approves signed-in authors with an earlier approved comment |
| `one_per_product` | on | One pending or approved root comment per author and product |
| `replies` | on | Enables threads and store replies |
| `user_replies` / `guest_replies` | on / on | Who may reply |
| `reply_moderation` | on | New replies wait for approval |
| `per_page` | 10 | 1–50 root comments per page |
| `replies_per_page` | 3 | 1–50 replies per thread page |
| `sort` | `newest` | `newest` or `oldest` |
| `min_length` / `max_length` | 10 / 3000 | Maximum up to 10000 characters |
| `interval_seconds` | 60 | 10–86400 seconds between comments from one author or IP |
| `max_links` | 0 | 0–10 links allowed in a comment |
| `notify_admin` / `admin_email` | off / empty | Email the administrator about new comments |
| `notify_reply` | off | Email opted-in participants about new replies |
| `delete_on_uninstall` | off | Drop the plugin tables on uninstall |

Trusted-author approval applies only to signed-in users, because guest email ownership is not verified. Verified-purchase badges are not implemented yet.

## Privacy and security

- Author emails are stored for notifications but never returned by public endpoints.
- IP addresses and author identities are stored only as HMAC-SHA256 hashes based on the application key.
- Comment text is escaped and never rendered as HTML.
- A hidden honeypot field, link limits and time-based throttling reject common spam.
- Signed-in identities are read from the server-side session, not from submitted form fields.
- Reply emails are sent only to approved participants who opted in, never to the reply's own author, and at most once per published reply.

Notifications use the platform mail settings. A delivery failure is logged and does not lose the comment.

## Routes

Public routes are relative to `/plugins/flex/reviews`:

| Method and path | Action |
| --- | --- |
| `GET /product/{id}` | Approved comments for an active product, a CSRF token and the signed-in identity |
| `POST /product/{id}` | Submit a comment or reply; requires the CSRF token |
| `GET /thread/{id}` | Approved replies in a visible thread |

Administrative routes are relative to `/admin/plugins/flex/reviews` and protected by the platform authentication, super-admin and CSRF middleware:

| Method and path | Action |
| --- | --- |
| `GET /comments` | List with `status`, `q` and `page` parameters |
| `POST /comments` | Change status of `ids`, or post a store reply to one comment |
| `GET /thread/{id}` | Full thread, including unpublished entries |
| `GET /settings` / `PUT /settings` | Read or save settings |

Validation errors return 422, missing products or comments 404, throttling 429 and duplicate comments 409. JSON responses use `Cache-Control: no-store`.

## Data and uninstalling

Replies are separate records linked by `root_id` and `parent_id`; store replies carry a protected `is_store` flag. Store replies from older versions, kept in the legacy `reply` column, are migrated into threaded records during installation or update. The migration is transactional and can safely run again.

Deactivation keeps all data. Uninstalling drops `flex_reviews` and `flex_reviews_settings` only when `delete_on_uninstall` is enabled. Back up these tables together with the platform database.

## Structure

```text
plugin.json                  Version, permissions, autoload and assets
src/ReviewsPlugin.php        Registration, lifecycle and product tab
src/ReviewStore.php          Schema, migration, validation, settings and queries
src/PublicController.php     Public JSON API
src/AdminController.php      Administrative JSON API
src/ReviewIdentity.php       Accounts or platform user identity
src/Notifications.php        Email notifications
templates/                   Product tab and reply templates
assets/admin.js              Administration page
assets/reviews.js            Public form and threads
assets/reviews.css           Public styles
tests/                       PHP tests and admin smoke check
```

## Development checks

Run from the Flex CMS root with Composer dependencies installed:

```sh
docker compose exec -T app vendor/bin/phpunit plugins/flex/reviews/tests/ReviewStoreTest.php
node plugins/flex/reviews/tests/admin-host-smoke.mjs
```

When this README was written, **13 PHP tests with 62 assertions** and the admin smoke check passed.

## Package boundaries

The plugin uses Flex CMS classes and services and is not a standalone PHP application. It depends on Flex Commerce for products and for the product tab integration.

This plugin is licensed under the GNU General Public License, version 2 or (at your option) any later version (`GPL-2.0-or-later`). The full text of version 2 is in [`LICENSE`](LICENSE).
