# Feature Specification: Guided Product Tours for Filament Panels (v1)

**Feature Branch**: `001-tours-v1`

**Created**: 2026-08-10

**Status**: Draft

**Source of truth**: [`docs/superpowers/specs/2026-08-03-filament-tours-design.md`](../../docs/superpowers/specs/2026-08-03-filament-tours-design.md) — approved via brainstorming on 2026-08-03.

**Input**: User description: "Adopt docs/superpowers/specs/2026-08-03-filament-tours-design.md — do not re-derive requirements from it. spec.md cites it and lifts SC-1…SC-7 as acceptance. No RESERVED.md (single-feature repo, no parallel slices). Order tasks.md spike-first: prove the Filament v5 render hook + FilamentAsset path in a Testbench panel before value objects or registry. Pre-tag task: add /.specify and /.claude to .gitattributes export-ignore — currently 70 tracked files, 708K, would ship into every consumer's vendor/."

> **Adoption notice.** This specification **adopts** the approved design document; it does not re-derive it. Design decisions **D1–D7**, the state-driver contract (§5), the runtime flow (§6), the error table (§7), the testing plan (§8), and the non-goals (§9) are incorporated by reference and are **not** reopened here. Success criteria **SC-1 … SC-7** (design §10) are lifted verbatim below as this feature's acceptance criteria.
>
> **Precedence.** Where this file contradicts the design, the design wins and this file is the bug — *except* for the items recorded under **Clarifications** below, which are deliberate additions or resolutions agreed after the design was approved, and which win over the design. Those are the only sanctioned deviations; everything else defers.

## Clarifications

### Session 2026-08-10

- Q: When two or more tours both match the page a user opened and both are eligible to auto-start, what happens? → A: The payload carries every matching tour, but only the first eligible one auto-starts; the rest remain replayable on demand by id. Registration order decides "first".
- Q: Do the success criteria added by this cycle (packaging, spike-first sequencing) stay in acceptance? → A: Yes, and a third is added for escaped copy. SC-008, SC-009, and SC-010 are release-blocking acceptance alongside the design's SC-001…SC-007, but are grouped separately so their provenance stays visible: SC-001…SC-007 come from the approved design, SC-008…SC-010 from this cycle.
- Q: Is tour copy escaped or rendered as HTML? → A: Escaped. Headings and bodies render as text; markup in a string appears literally. No opt-out in v1. Rationale: hosts will interpolate values into copy, and rendering unescaped would turn any host-interpolated user data into stored XSS inside an authenticated panel.
- Q: Does a tour without the run-once flag auto-start? → A: Yes. Every applicable tour is an auto-start candidate; the run-once flag controls **persistence only** — with it, once ever; without it, every visit. A repeating tour is bounded by its predicate, not by a separate trigger flag. *(Resolves an internal ambiguity in the design: D2's wording reads as if run-once were the auto-start opt-in, while §4's `office-orientation` example — predicate, no run-once — only makes sense if it auto-starts. The example wins.)*

## User Scenarios & Testing *(mandatory)*

The "user" of this package is a **host-application developer** who installs it into a Filament panel, plus the **end user** of that panel who actually sees a tour. Both appear below; each story names which one it serves.

### User Story 1 - Define a tour and have it run itself (Priority: P1)

A host developer registers the package's plugin on a panel and declares one tour keyed to a page class, marked to run only once. An end user opening that page for the first time sees the tour walk them through the page's elements; on their next visit the page is quiet. The developer touches no build tooling to make this happen.

**Why this priority**: This is the whole product. Definition, resolution, and delivery all have to work together for even the smallest tour to appear, so the first vertical slice is also the MVP. Every other story is a refinement of this one.

**Independent Test**: Register one tour for a known page in a minimal test panel, visit that page, assert the tour payload and the tour asset are present and the tour runs; visit a non-matching page and assert nothing about tours reaches the HTML; visit the matching page a second time with the "seen" record in place and assert it does not run.

**Acceptance Scenarios**:

1. **Given** a tour declared for a page class and marked run-once, **When** a user opens that page for the first time, **Then** the tour starts and steps through its targets, with no host code beyond the tour definition. *(SC-001)*
2. **Given** the same tour, **When** the same user opens that page a second time, **Then** the tour does not start. *(SC-001)*
3. **Given** a tour declared for page A, **When** a user opens page B, **Then** the rendered HTML contains none of that tour — not its id, not its copy, not its selectors. *(SC-002)*
4. **Given** a fresh install of the package into a consuming application, **When** the developer registers the plugin and defines a tour, **Then** a working tour appears without the developer touching npm, a bundler config, or the panel theme. *(SC-004)*

---

### User Story 2 - A tour survives the page changing underneath it (Priority: P2)

Someone moves or renames an element that a tour step points at. The end user must not meet a broken or stalled tour, and the developer must be able to find out that the tour has rotted.

**Why this priority**: Rot is certain over the life of a panel, and the failure mode without this is user-visible breakage. It is second only because a tour has to run at all before it can degrade gracefully.

**Independent Test**: Define a tour whose middle step targets a selector absent from the page; assert the tour still runs, that the surviving steps are shown in order, and that the skip is reported to the browser console when the application is in debug mode.

**Acceptance Scenarios**:

1. **Given** a tour with three steps where the second step's target is not on the page, **When** the tour runs, **Then** the missing step is skipped, the first and third steps still run, and nothing errors for the user. *(SC-003)*
2. **Given** the same tour and the application in debug mode, **When** the tour runs, **Then** the skip is reported in the browser console, naming both the tour and the unmatched selector. *(SC-003)*
3. **Given** a tour where **no** step's target is present, **When** the page loads, **Then** the tour does not start and nothing is shown to the user.
4. **Given** a running tour, **When** the user navigates to another page without a full page load, **Then** the tour is torn down and no overlay survives onto the new page.

---

### User Story 3 - Replay a tour on demand (Priority: P3)

An end user who dismissed a tour, or who wants a refresher, triggers it again from a control the host developer placed on the page.

**Why this priority**: Real value, but strictly additive — a run-once tour is useful before replay exists. Its absence does not block the MVP.

**Independent Test**: Place the package's start-tour action on a page, activate it, and assert the named tour runs regardless of whether it was previously marked seen; separately, emit the documented start event from a client-side context and assert the same.

**Acceptance Scenarios**:

1. **Given** a tour already marked seen, **When** the user activates the start-tour control for that tour, **Then** the tour runs.
2. **Given** any client-side context on a page where the plugin is active, **When** the documented start event is emitted naming a tour id, **Then** that tour runs.
3. **Given** a start request naming an id no registered tour uses, **When** it is handled, **Then** nothing starts and the user sees no error.
4. **Given** two tours both applying to the current page, where the first has auto-started, **When** the user activates the control for the second, **Then** the second tour runs. *(FR-025)*

---

### User Story 4 - Move "seen" from the browser to the server (Priority: P3)

A host application decides that "this user has seen the orientation tour" must be a property of the user, not of the browser. It switches one configuration value and its tour definitions stay untouched.

**Why this priority**: The default browser-local behaviour is enough to ship, so this is an upgrade path rather than a launch requirement. It is specified now because the seam has to exist from the start — retrofitting it later would change every tour definition.

**Independent Test**: Run the package's resolution and rendering with the default configuration and assert no server-side record and no seen-recording endpoint exist; switch the configuration to a host-supplied driver and assert that tours the driver reports as seen are filtered out before rendering and that the recording endpoint exists and marks through the driver — with the tour definitions byte-identical across both runs.

**Acceptance Scenarios**:

1. **Given** tours defined once, **When** the state configuration changes from the browser-local default to a host-supplied driver, **Then** persistence moves server-side with no change to any tour definition. *(SC-005)*
2. **Given** a host-supplied driver reporting a tour as already seen, **When** the page renders, **Then** that tour is excluded from the payload before rendering, so its copy never reaches the browser.
3. **Given** the browser-local default, **When** the application's routes are inspected, **Then** no seen-recording endpoint is registered.
4. **Given** a host-supplied driver, **When** a user finishes or dismisses a tour, **Then** the seen-recording endpoint records it through that driver, and that endpoint is reachable only to users the panel already authenticates.

---

### User Story 5 - Find out what tours exist and catch a bad definition early (Priority: P4)

A developer inheriting the panel wants an inventory of its tours; a developer writing one wants a typo to fail loudly rather than silently show nothing.

**Why this priority**: Diagnostics and guardrails, valuable but not on the path to a first working tour.

**Independent Test**: Register a valid set of tours and assert the listing command reports each one with its page or predicate, step count, and run-once flag; separately, register each invalid definition in turn and assert registration fails with a message naming the offending tour.

**Acceptance Scenarios**:

1. **Given** several registered tours, **When** the listing command runs, **Then** every registered tour is listed with its page (or an indication it uses a predicate) and its step count. *(SC-006)*
2. **Given** two tours sharing an id, **When** they are registered, **Then** registration fails with a message naming the offending tour. *(SC-007)*
3. **Given** a tour with no steps, **When** it is registered, **Then** registration fails with a message naming the offending tour. *(SC-007)*
4. **Given** a tour keyed to a class that does not exist, **When** it is registered, **Then** registration fails with a message naming the offending tour. *(SC-007)*

---

### User Story 6 - The package does not ship the workshop (Priority: P4)

A consuming application installs the package. Its dependency directory receives the package's runtime code and nothing else — no specification tooling, no agent configuration, no test suite.

**Why this priority**: Packaging hygiene, invisible until the first release, and cheap to fix before a tag exists. It is a story rather than a footnote because it is verifiable and it is a promise made to every consumer.

**Independent Test**: Produce the distribution archive the package registry would build from a tag and assert that the specification-tooling and agent-configuration directories are absent from it, while the runtime source, configuration, views, and built assets are present.

**Acceptance Scenarios**:

1. **Given** a tagged release, **When** a consuming application installs the package, **Then** the specification-tooling directory and the agent-configuration directory are absent from the installed files.
2. **Given** the same release, **When** the installed files are inspected, **Then** the runtime source, package configuration, views, and built front-end assets are all present.

---

### Edge Cases

The first eight are drawn from design §6 and §7 and are already decided there. The last four come from the clarification session and are decided here.

- **Step target absent** — skipped, not fatal; reported to the console under debug. *(design D4)*
- **No step survives filtering** — the tour does not start and nothing is shown.
- **Duplicate tour id, zero-step tour, or unknown page class** — registration fails, naming the tour. *(design §7)*
- **Client-side navigation while a tour is open** — the running tour is destroyed, so an overlay cannot outlive the page it describes.
- **A user opens a page they can reach, but a tour exists for a page they cannot** — the non-matching tour is never serialised, so its copy is not in their HTML. *(SC-002)*
- **Same user, second browser or device, browser-local state** — the run-once tour replays. This is an accepted cost of the default, not a defect. Hosts with a "must be acknowledged" requirement are directed to the server-side driver. *(design D2)*
- **Page-class resolution breaks on a framework upgrade** — the identified fragile point (design §6). A dedicated test asserts a known page class resolves, so an upgrade fails loudly at that test rather than silently showing no tours anywhere.
- **A predicate-only tour with no page key** — applies wherever the predicate says so; there is no wildcard syntax over class names. *(design D3)*
- **Two or more tours match the same page** — all are sent, one auto-starts (first in registration order), the rest wait to be replayed. Two overlays are never open at once. *(FR-024, FR-025)*
- **A tour without the run-once flag** — auto-starts on every visit to a matching page. This is intended, not a defect; a tour that should stop appearing either carries the flag or narrows its predicate. *(FR-026)*
- **The first matching tour is run-once and already seen** — it is not eligible, so the next eligible tour auto-starts instead. A seen tour does not block the ones behind it. *(FR-027)*
- **Copy containing markup, or a host-interpolated value containing markup** — displayed literally, never interpreted. A host interpolating user-controlled data into a heading or body cannot turn it into executable content. *(FR-028)*

## Requirements *(mandatory)*

FR-001 through FR-022 restate the adopted design and do not extend it. FR-023 and the **Clarified behaviour** group go beyond it — packaging, and decisions taken in the clarification session — and are marked as such where they appear.

### Functional Requirements

**Definition**

- **FR-001**: Tours MUST be declarable through a fluent definition surface consisting of exactly: a tour identified by id, optionally keyed to a page class, optionally guarded by a predicate, optionally marked run-once, holding an ordered list of steps; and a step identified by a raw selector, carrying optional heading copy, optional body copy, an optional side, and an optional alignment. Nothing beyond this surface is public in v1. *(design §4)*
- **FR-002**: Tours MUST be registered centrally per panel, not declared by the pages they describe, so that a tour can attach to a page the host application does not own. *(design D1)*
- **FR-003**: Step targets MUST be raw selectors. The package MUST NOT provide helpers that encode the panel framework's internal markup. *(design D5)*
- **FR-004**: Copy MUST be accepted as plain strings. The package MUST NOT ship translation files and MUST NOT own any host application's wording. *(design D6)*
- **FR-005**: Page targeting MUST be by class reference, with a predicate as the only escape hatch. The package MUST NOT accept route names and MUST NOT invent wildcard matching over class names. *(design D3)*

**Resolution**

- **FR-006**: The package MUST decide server-side which tours apply to the page being rendered, by page-class match or predicate, before anything is sent to the browser.
- **FR-007**: Tours that do not apply MUST NOT be serialised into the response, so a user's HTML never contains tour copy for pages they cannot reach.
- **FR-008**: When a server-side state driver is configured, tours it reports as already seen MUST be removed from the payload before rendering.

**Delivery**

- **FR-009**: The package MUST bundle its own built front-end asset and register it through the panel framework's asset system, so that consuming applications configure no package manager and no bundler. *(design D7)*
- **FR-010**: The package MUST inject its payload through the panel's own render-hook mechanism, at the end of the document body.
- **FR-011**: The rendered payload MUST carry, for each applicable tour, its id, its ordered steps, and its run-once flag. Where more than one tour applies, the payload MUST preserve registration order, because that order decides which tour auto-starts (FR-024).
- **FR-012**: On client-side navigation away from a page, any running tour MUST be destroyed.
- **FR-013**: A tour MUST be startable on demand, both from a panel action placed by the host developer and from a documented client-side event naming a tour id.

**Degradation and diagnostics**

- **FR-014**: A step whose target does not resolve MUST be skipped; the remaining steps MUST still run.
- **FR-015**: A skipped step MUST be reported to the browser console, naming the tour and the unmatched selector, when the application is in debug mode.
- **FR-016**: A tour with no surviving steps MUST NOT start.
- **FR-017**: Registration MUST fail with an exception naming the offending tour when an id is duplicated, when a tour has zero steps, or when a named page class does not exist.
- **FR-018**: The package MUST provide a command listing every registered tour with its id, its page or an indication that it uses a predicate, its step count, and its run-once flag. The listing MUST NOT claim to validate selectors, which cannot be checked without a browser.

**State**

- **FR-019**: The package MUST expose a two-method state contract — ask whether a tour has been seen, record that it has — and MUST select the implementation from a single configuration value that is either the browser-local default or a host class reference. *(design §5)*
- **FR-020**: Under the browser-local default, the package MUST hold no server-side state and MUST NOT register a seen-recording route; the run-once decision is made in the browser under a key scoped by panel and tour.
- **FR-021**: When a host driver is configured, the package MUST register exactly one seen-recording route, behind the panel's own authentication middleware, which records through that driver.
- **FR-022**: The package MUST NOT ship a database migration. Persistence shape is the host application's decision. *(design §1)*

**Packaging**

- **FR-023**: The distribution archive built from a release tag MUST exclude **development tooling and build sources** — specification tooling, agent configuration, tests, documentation, and unbuilt front-end sources — and MUST retain the runtime source, package configuration, views, and **built** assets.

**Clarified behaviour** *(added by the clarification session above; numbered from the end so nothing is renumbered)*

- **FR-024**: When more than one tour applies to a page, **at most one MUST auto-start** — the first eligible tour in registration order. Concurrent or stacked auto-starts are a defect.
- **FR-025**: Tours that apply but do not auto-start MUST still be present in the payload and MUST be startable on demand by id, through the same controls as any other replay (FR-013).
- **FR-026**: Every applicable tour is an auto-start candidate. The run-once flag MUST control **persistence only**, not triggering: a tour carrying it auto-starts once and never again; a tour without it auto-starts on every visit. The package MUST NOT add a separate trigger flag — a repeating tour is bounded by its predicate.
- **FR-027**: A tour is **eligible** to auto-start when it survived resolution (page-class match or predicate), at least one of its steps resolved on the page, and — if it carries the run-once flag — it has not been recorded as seen. FR-024 selects the first eligible tour by this definition.
- **FR-028**: Heading and body copy MUST be escaped before display, so that markup contained in a copy string is shown literally and never interpreted. The package MUST NOT offer a raw-markup opt-out in v1. This holds even though copy is developer-authored, because hosts interpolate values into it and the underlying tour engine would otherwise interpret them.

### Key Entities

- **Tour**: A named, ordered walkthrough. Attributes: id (unique within a panel), optional page-class key, optional applicability predicate, run-once flag, ordered steps (at least one). Pure value object — validates itself, has no behaviour.
- **Step**: One stop in a tour. Attributes: target selector, optional heading, optional body, optional side, optional alignment. Side and alignment pass through to the tour engine untouched.
- **Tour registry**: Holds the panel's tours and answers "which apply to this page, for this user?". Depends on tours and on the state contract; nothing depends on its internals.
- **State contract**: Answers and records "seen", for one tour id. Two implementations are in scope: the browser-local default (no server state) and whatever class the host supplies.
- **Rendered payload**: The applicable tours for one request — ids, steps, run-once flags — and nothing else.

## Success Criteria *(mandatory)*

All ten criteria below are release-blocking acceptance for this feature. They are split by origin: the first group is lifted verbatim from design §10, with the design's own labels kept in parentheses so traceability survives renumbering; the second group originates in this cycle.

### Measurable Outcomes — adopted from the design

- **SC-001** *(design SC-1)*: A tour defined for a given page and marked run-once runs on first visit to that page and not on the second, with no host code beyond the definition.
- **SC-002** *(design SC-2)*: A tour whose page does not match contributes **nothing** to the rendered HTML.
- **SC-003** *(design SC-3)*: A step whose selector is absent is skipped, the remaining steps still run, and the skip is reported in the console under debug mode.
- **SC-004** *(design SC-4)*: A consuming application installs the package and sees a working tour **without touching npm, a bundler, or its panel theme**.
- **SC-005** *(design SC-5)*: Switching the state configuration from the browser-local default to a host driver moves persistence server-side with no change to any tour definition.
- **SC-006** *(design SC-6)*: The listing command lists every registered tour with its page and step count.
- **SC-007** *(design SC-7)*: A duplicate id, an empty step list, or an unknown page class fails at registration with a message naming the offending tour.

### Measurable Outcomes — added by this cycle

These three did not appear in the approved design and originate here. They are **equally release-blocking**; the separate heading exists so their provenance stays visible and they are never mistaken for design §10. Confirmed in the clarification session above.

- **SC-008**: The archive a consuming application installs contains no development tooling or build sources — no specification tooling, agent configuration, tests, or unbuilt front-end sources — while still containing the built assets.
- **SC-009**: The panel render-hook and asset-registration path is demonstrated end to end in a test panel **before** any value object or registry code is written (see CD-3). Unlike the others, this constrains *sequence* rather than outcome, and is verified by the order of work rather than by the finished artifact.
- **SC-010**: Markup placed in a tour's heading or body — directly or through a host-interpolated value — is displayed literally and is never interpreted as executable content.

## Out of Scope

Adopted from design §9 and not reopened:

- Steps that drive interaction — opening a modal, switching a tab. v1 targets only elements present when the page loads.
- A tour-authoring interface.
- Completion analytics.
- Tours spanning multiple pages.
- Shipped translation files.
- Any database migration.
- A JavaScript test suite. **Named gap**, accepted with a stated mitigation: the first consumer's browser journey exercises the tour engine against real markup. If the client-side code grows past driving the tour engine, a JS test toolchain should be added rather than stretching this justification. *(design §8)*

## Carried Directives

Process instructions that arrived with this feature and must survive into `/speckit-plan` and `/speckit-tasks`. They are recorded here so no later step re-derives them.

- **CD-1 — Adopt, do not re-derive.** The design document is approved. Planning consumes it; it does not reopen D1–D7 or the non-goals.
- **CD-2 — No `RESERVED.md`.** Single-feature repository, no parallel slices, so there is nothing to reserve against.
- **CD-3 — Spike first.** `tasks.md` MUST order the panel render-hook and asset-registration path **first** — proven end to end in a test panel — before any value object or registry work begins. This is the design's identified fragile point (§6); proving it late would invalidate work already built on top of it.
- **CD-4 — Pre-tag packaging task.** Add the specification-tooling and agent-configuration directories to the export-ignore list **before the first release tag**. Measured on this branch: 70 tracked files, roughly half a megabyte, currently unlisted and therefore bound for every consumer's dependency directory. Satisfies FR-023 / SC-008.

## Assumptions

- The approved design document dated 2026-08-03 is current and authoritative; this specification adopts it rather than re-deriving requirements from it, per CD-1.
- The repository skeleton already in place — its service provider, asset build step, and test harness — is the starting point, not something this feature replaces.
- The project constitution (`.specify/memory/constitution.md`) was an unfilled template when this specification was written and was ratified as **v1.0.0** on 2026-08-10, after the fact. Its five principles were derived partly from this feature's own design, so no conflict is expected — but this spec has not been re-validated against it line by line. `/speckit-analyze` covers that.
- "User" in the acceptance scenarios means an authenticated panel user; the package inherits whatever authentication the panel already applies and adds none of its own.
- The first consumer application carries the browser-level proof of the client-side behaviour, per the named testing gap; this package's own suite stops at the server boundary in v1.
- Browser-local run-once state is per-browser by design. Any host requirement shaped like "confirm you have read this" is out of scope for the default and must use a server-side driver.
