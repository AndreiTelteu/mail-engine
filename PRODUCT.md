# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

The primary user is an individual developer managing their own email. They need to quickly find and read messages so they can work through their inbox toward inbox zero. The product has no team or administrator workflow.

## Product Purpose

This is a private, self-hosted email client. It connects to the user's IMAP mailbox, indexes messages, and makes them fast to search and read from one application.

## Positioning

The product combines direct IMAP mailbox access with a private local search index: canonical email data is retained in the application's database and full-text retrieval is served through Typesense.

## Operating Context

Users configure an IMAP mailbox, validate the connection, and then work through indexed messages. Background scheduling discovers messages across mailbox folders; queued work indexes them. Users search, filter by folder, and read messages in an in-page view or dedicated URL.

## Capabilities and Constraints

- IMAP mailbox configuration is per user, and passwords are encrypted at rest.
- The application indexes mail metadata and plain-text/HTML bodies; it retains attachment metadata but does not store attachment contents.
- Search is user-scoped and uses Laravel Scout with Typesense, with a database fallback.
- HTML email content is sanitized before rendering.
- Mail synchronization and indexing run in scheduled and queued background jobs.
- The existing implementation uses Laravel, Livewire, Flux UI, Laravel Scout, Typesense, and `webklex/php-imap`.

## Brand Commitments

No branding or existing visual identity must be preserved.

## Evidence on Hand

- Product requirements: `.kiro/specs/mail-indexer-searcher/requirements.md`
- Current feature and operational documentation: `README.md`
- No customer proof, testimonials, or brand assets are available. Future work must not fabricate them.

## Product Principles

1. Make retrieving and reading a specific message immediate and low-friction.
2. Keep the user in control of private mailbox data through self-hosting and per-user isolation.
3. Automate mailbox synchronization without blocking everyday email work.
4. Treat email content as untrusted and protect users when displaying it.
