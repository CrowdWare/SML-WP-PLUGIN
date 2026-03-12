# ForgeCrowdBook (WordPress Plugin)

`ForgeCrowdBook` is the main product: a collaborative, moderated book platform inside WordPress.

SML/Twig/Markdown compilation is still included, but it is supporting tooling, not the core focus.

## CrowdBook first: core features

- Public routes: `/books`, `/book/{book-id}`, `/dashboard`, `/editor`, `/login`, `/register`
- Author flow:
  - create books from frontend dashboard
  - write/edit chapters in Markdown
  - upload inline images
  - submit to moderation
- Moderation flow:
  - pending/published/rejected chapter states
  - versioned updates for published chapters (live stays visible until publish)
  - admin moderation pages for chapters and books
- Book flow:
  - prologue + branch cards + chapter-by-chapter reader navigation
  - cover image upload with resize + preview
  - book moderation states (`pending`, `active`, `archived`)
  - versioned book updates (`pending` update over active live version)
- Security/quality:
  - spam checks for chapter submissions
  - likes are account-based (one like per user per chapter)

## Install

1. Copy `sml-wp-plugin` into `wp-content/plugins/`.
2. Optional: run `composer install` for Twig support.
3. Activate plugin `SML Pages MVP` in WordPress admin.
4. Open `/dashboard` (frontend) to create books and content.

## CrowdBook admin pages

- `CrowdBook -> Übersicht`
- `CrowdBook -> Bücher`
- `CrowdBook -> Kapitel Moderation`
- `CrowdBook -> User`

## SML compiler (secondary tooling)

The plugin still ships the original SML compiler stack for page generation:

- custom post types like `sml_page`, `sml_template`, `sml_markdown_part`
- Monaco editor integration
- optional Twig templates + Markdown parts
- static HTML compilation pipeline

This is useful for custom page rendering and templating around CrowdBook content.

## Repository

- Codeberg: `https://codeberg.org/CrowdWare/ForgeCrowdBook`
