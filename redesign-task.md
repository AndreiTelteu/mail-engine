# Inertia + Vue Migration and Product Redesign

## Objective

Replace the current server-driven interface with an Inertia.js + Vue 3 application, then redesign the entire product as a polished, dark-first personal email client.

The finished product is for one self-hosting developer who needs to search, read, and process mail quickly on the path to inbox zero. It must feel as familiar and dependable as Gmail while meeting the density, responsiveness, and operational care associated with Linear and Notion.

## Non-Negotiable Product Behavior

- Preserve the existing IMAP integration, encrypted credential storage, queued/scheduled synchronization, canonical email persistence, Typesense/Scout search, and database fallback.
- Preserve strict ownership checks: users can only configure, search, and read their own mailbox data.
- Preserve HTML email sanitization and iframe sandboxing.
- Preserve attachment metadata display; attachment content must not be stored or exposed.
- Preserve existing URLs and route names where possible:
  - `/`
  - `/dashboard`
  - `/emails`
  - `/emails/{emailId}`
  - `/settings/profile`
  - `/settings/mail`
  - `/settings/appearance`
  - `/settings/security`
- Keep Fortify’s authentication and two-factor-authentication capabilities intact.

## Migration Strategy

### 1. Establish the Inertia/Vue foundation

1. Add the Laravel Inertia adapter, Vue 3 adapter, and any required Vite integration.
2. Configure an Inertia middleware that shares:
   - authenticated user data required by navigation;
   - flash success, warning, and error messages;
   - validation errors;
   - any durable UI preferences needed by the application shell.
3. Create a Vue application entry point and a top-level authenticated layout.
4. Move all client styling into the Vite-managed CSS entry point, retaining Tailwind CSS v4’s CSS-first configuration.
5. Define the application’s TypeScript or JavaScript conventions before introducing page components. Prefer TypeScript if it can be adopted consistently across the whole client.

### 2. Convert routes and server boundaries

Use conventional Laravel controllers or Inertia response closures for page delivery. Keep business logic in the existing services, jobs, models, actions, and policies rather than moving it into controllers or Vue components.

Create explicit JSON endpoints only for interactions requiring incremental updates; page navigation and ordinary form submissions should use Inertia requests.

| Capability | Server responsibility | Vue responsibility |
| --- | --- | --- |
| Mail search | Authorize user, execute `EmailSearchService`, paginate results, format safe highlights | Debounced query, URL state, folder filter, pagination, keyboard navigation |
| Read email | Resolve only the owned email, produce sandboxed sanitized document | Reader pane/modal state, responsive detail layout, open-in-new-tab navigation |
| Sync mailbox | Dispatch existing sync operation and return status/flash data | Trigger action, loading state, surface recent result/log feedback |
| Mail settings | Validate, test IMAP connection, encrypt and persist settings | Form state, inline validation, test/save loading and result states |
| Account settings | Keep current Fortify-backed profile, password, appearance, and security behavior | Settings navigation and accessible forms |

Do not expose raw IMAP credentials, decrypted passwords, unsanitized message HTML, or cross-user email identifiers to the client.

### 3. Retire the old UI incrementally

1. Build the new Inertia/Vue page for each route with behavior parity.
2. Add or update feature tests before removing the superseded interface.
3. Remove old UI-specific code only after its replacement is covered and operating through the same route.
4. Do not change queue, scheduler, indexing, search-engine, or database behavior as part of visual work unless a migration requirement makes it necessary.

## Information Architecture

### Global shell

Build a responsive dark application shell with:

- a persistent desktop navigation rail and compact mobile navigation;
- a command-oriented global search entry point that routes to `/emails`;
- a visible but quiet mailbox/sync status indicator;
- account controls grouped away from daily mail actions;
- full keyboard focus visibility and a predictable tab order.

Primary destinations:

1. **Inbox / Search** — the daily workspace and default operational destination.
2. **Mail settings** — IMAP connection and sync configuration.
3. **Account settings** — profile, appearance, security, and two-factor authentication.

The dashboard should become a useful launch surface, not a metrics dashboard. It should direct the user to search mail or check connection health without invented statistics, activity feeds, or placeholder data.

### Mail workspace

Use a familiar responsive three-pane model:

- **Left:** navigation and folders.
- **Center:** query, filters, result count, and scannable message list.
- **Right:** selected message reader on wide screens.

On smaller screens, show the list and reader as separate navigable states with a clear back affordance. Do not force a desktop three-pane layout into a narrow viewport.

Each message row must make this reading order obvious:

1. sender;
2. subject;
3. useful preview;
4. folder, attachment presence, and sent time as secondary metadata.

Include explicit, polished states for:

- no indexed email yet;
- no results for the current query/filter;
- search loading;
- synchronization in progress;
- synchronization success;
- synchronization error;
- unavailable search service with database fallback messaging;
- missing or unauthorized email detail.

### Mail settings

Make connection setup approachable but efficient:

- clearly group server connection fields;
- make encryption selection understandable;
- explain background synchronization in practical language;
- distinguish **Test connection** from **Save settings**;
- disable and label controls while a request is in progress;
- place validation and connection errors next to the relevant control and describe recovery.

### Reader

The reader must prioritize readable email content:

- concise header with sender, subject, folder, date, and recipient details;
- a dedicated reading surface for sanitized HTML content;
- attachment metadata presented as secondary information;
- an obvious close/back action and a separate open-in-new-tab action;
- preserve the existing safe iframe boundary rather than injecting remote email markup into the Vue DOM.

## Visual Direction

### Design target

Use the category-standard direction intentionally:

- **Scene:** an individual developer checks mail in a low-light workspace and needs to stay oriented for long sessions.
- **Theme:** dark-first, graphite surfaces, restrained tonal layering, and clear high-contrast text.
- **Character:** calm, familiar, compact, and operational—not retro-terminal, neon, overly playful, or marketing-led.
- **Color:** a zinc/graphite foundation with one restrained functional accent. Accent color communicates focus, selection, success, or the primary action; it is never decoration.
- **Typography:** highly legible UI typography with clear hierarchy. Sender and subject lead; previews and timestamps recede.
- **Depth:** use surface levels, borders, and state changes before shadows. Shadows, when necessary, should be low and soft.

### Layout and interaction rules

- Build with an 8px-derived spacing rhythm and preserve dense but touch-safe controls.
- Use 12–16px corner radii for surfaces and fields; reserve full-round shapes for small metadata chips.
- Avoid oversized headings, hero sections, empty metric cards, decorative gradients, and placeholder illustrations.
- Make hover, selected, focus-visible, disabled, loading, error, and empty states feel designed rather than incidental.
- Use short motion only where it helps orientation: pane transitions, selected-message changes, and sync feedback. Respect `prefers-reduced-motion`.
- Theme browser surfaces: selection, caret, scrollbars, focus rings, and numeric metadata.

## Vue Component Boundaries

Prefer small composable components with page-level data ownership:

- `AppShell`
- `AppNavigation`
- `MailboxLayout`
- `FolderList`
- `SearchToolbar`
- `EmailList`
- `EmailListItem`
- `EmailReader`
- `EmailMetadata`
- `AttachmentList`
- `SyncStatus`
- `ImapSettingsForm`
- `SettingsNavigation`

Keep data fetching and route-state synchronization in page-level composables. Keep presentational components free of direct HTTP knowledge wherever possible.

## Accessibility Requirements

- Meet WCAG 2.2 AA contrast for text, controls, focus indicators, and status messages.
- Use semantic landmarks, real buttons for actions, associated labels for fields, and descriptive accessible names for icon-only controls.
- Support keyboard-only search, filtering, message selection, reader exit, and account navigation.
- Announce async connection-test and synchronization outcomes without interrupting the user unnecessarily.
- Do not rely on color alone for sync state, errors, selected messages, or attachment presence.

## Testing and Verification

### Feature and integration tests

- Update route and authorization tests for Inertia responses.
- Test that search remains user-scoped, supports folder filtering, keeps query state in the URL, and uses the intended fallback behavior.
- Test that email detail cannot be accessed across users.
- Test IMAP settings validation, test-connection failure/success, encrypted persistence, and save behavior.
- Preserve coverage for scheduled synchronization, indexing retries, failure tracking, and cleanup commands.

### Browser tests

Add browser coverage for the complete daily workflow:

1. sign in;
2. navigate to the mail workspace;
3. search and filter messages;
4. select and read a message;
5. return to the list without losing context;
6. open settings, test a connection, and save;
7. verify no JavaScript errors at desktop and mobile widths.

### Quality gates

- Run the targeted Pest suite after each migration phase.
- Run Laravel Pint after PHP changes.
- Run the production Vite build after client changes.
- Inspect the authenticated shell, mail workspace, reader, and settings at desktop and mobile widths in one bounded visual review.
- Do not remove the old interface until automated tests and the replacement workflow pass.

## Delivery Sequence

1. Inertia/Vue foundation and shared app shell.
2. Mail search/list/reader workflow with all state handling.
3. IMAP settings and sync feedback workflow.
4. Account settings and authentication screens.
5. Mobile adaptation, keyboard interaction, and accessibility pass.
6. Browser tests, visual review, cleanup of superseded UI code, and documentation refresh.

## Definition of Done

The migration is complete when every existing user route is served through Inertia/Vue, all mail and settings workflows retain their security and behavioral guarantees, the interface is cohesive across desktop and mobile, and targeted automated/browser checks pass without JavaScript errors.
