<!--
SYNC IMPACT REPORT — 2026-08-10
================================================================================
Version change: (unversioned template) → 1.0.0
Bump rationale: First ratification. Every principle is new; there is no prior
version to be incompatible with, so MAJOR is not warranted and 1.0.0 is the
initial adoption.

Modified principles: none (initial adoption)

Added sections:
  - I.   Test-First (NON-NEGOTIABLE)      [replaces PRINCIPLE_1 placeholder]
  - II.  The Host Owns Its Data and Its Words
  - III. A Small, Frozen Public Surface
  - IV.  Safe and Honest by Default
  - V.   Ship Runtime Only, Built and Pinned
  - Platform and Toolchain Constraints    [replaces SECTION_2 placeholder]
  - Development Workflow and Quality Gates[replaces SECTION_3 placeholder]
  - Governance

Removed sections: none

Templates and artifacts requiring updates:
  ✅ .specify/templates/plan-template.md   — "Constitution Check" gate section
       exists and is generic; no edit needed. Feature plans now derive real
       gates from this file instead of substituting AGENTS.md.
  ✅ .specify/templates/spec-template.md   — no constitution-driven mandatory
       sections added or removed; no edit needed.
  ✅ .specify/templates/tasks-template.md  — test-task guidance says tests are
       OPTIONAL. Principle I makes them mandatory for this project. Resolved
       per-feature rather than by editing the shared template: tasks.md for
       001-tours-v1 already states tests are required and cites R-006.
  ⚠ specs/001-tours-v1/plan.md            — its "Constitution Check" is an
       AGENTS.md substitute, explicitly labelled because this file was an
       unfilled template at the time. Now re-checkable against real gates.
       Flagged for /speckit-analyze.
  ⚠ README.md                             — still carries skeleton boilerplate
       ("This is where your description should go"), a Filament 4.x docs link,
       and a "publish and run the migrations" section that Principle II
       forbids. Scheduled as T077/T078 in tasks.md.
  ✅ AGENTS.md                             — its 27 rules are the operational
       expression of these principles; no contradictions found. Mapping is in
       the Governance section below.

Deferred TODOs: none. RATIFICATION_DATE is set to the repository's first-commit
date (2026-08-03), which is when the project and its design were adopted.
================================================================================
-->

# filament-tours Constitution

## Core Principles

### I. Test-First (NON-NEGOTIABLE)

Every behaviour change MUST land with the test that would have caught its absence, and that
test MUST be written and observed failing before the implementation exists.

The suite runs in random order and fails on warnings, risky tests, empty suites, and stray
output. Tests therefore MUST NOT depend on execution order and MUST NOT print.

Where a behaviour genuinely cannot be tested at this project's boundary — currently the
client-side tour behaviour, which would need a second toolchain for roughly sixty lines of
JavaScript — the gap MUST be named explicitly, carry a stated mitigation, and be reviewed
when the untested surface grows. An unnamed gap is a violation; a named one is a decision.

**Rationale**: A package consumed inside other people's admin panels cannot be debugged by
its author when it breaks. The test suite is the only place a regression can be caught before
a consumer finds it.

### II. The Host Owns Its Data and Its Words

This package MUST NOT ship a database migration, a translation file, or an authentication
mechanism. It MUST NOT decide where a host stores state, what language a host speaks, or who
a host's users are.

Persistence is reached only through a narrow contract the host implements. Copy is accepted
as plain strings the host has already translated. Routes inherit the host panel's existing
middleware and add nothing of their own.

**Rationale**: Every one of these is a decision the host has already made, usually
differently than we would. A package that makes them again forces a migration, a language
file, or an auth assumption onto an application that never asked for one — and that is how a
plugin becomes something a team has to work around instead of with.

### III. A Small, Frozen Public Surface

The public API MUST be enumerable in a single document, and that document MUST be the whole
surface — anything not listed is internal and may change without a major version.

Additions to the public surface MUST come from a design amendment, not from a commit. Helpers
that read better but encode another framework's internal markup MUST NOT be added: they break
on that framework's point releases and the breakage lands on consumers.

**Rationale**: Everything published becomes something someone depends on. A surface that grows
by convenience becomes a surface that cannot change, and this package's entire job is small
enough that it does not need a large one.

### IV. Safe and Honest by Default

Three obligations, in tension often enough to state together:

1. **Safe** — Content that reaches a browser MUST be escaped before it is sent and rendered as
   text when it arrives. Developer-authored strings are NOT trusted input: hosts interpolate
   record titles, user names, and tenant labels into them, and rendering unescaped would place
   stored XSS inside an authenticated admin panel. Escaping MUST NOT have a convenience opt-out.
2. **Forgiving to users** — A missing target, a moved element, a failed request MUST degrade
   rather than break. Users MUST NOT be shown a broken experience because a developer moved a
   button. Where a failure could go either way, fail in the direction that stays visible and
   self-corrects, not the direction that goes silent.
3. **Honest to developers** — Errors MUST name the specific offender. Diagnostics MUST NOT
   claim verification they did not perform: a check that cannot be made without a browser MUST
   be reported as unverified rather than implied as passing.

**Rationale**: Silence is the expensive failure. A tour that quietly never appears again, a
validator that implies it checked something it could not, an error naming no culprit — each
costs more debugging time than a loud failure would have, and each erodes trust in the tool.

### V. Ship Runtime Only, Built and Pinned

Consumers MUST receive runtime code and nothing else. Front-end dependencies MUST be bundled
into a committed build artifact and pinned in exactly one place, so that installing this
package requires no package manager, no bundler configuration, and no theme change.

Development tooling — specifications, agent configuration, tests, build sources — MUST be
excluded from the distribution archive **before** the first release tag. A tag cannot be
retracted once published, so this is a release blocker, not polish.

**Rationale**: The cost of a shipped dependency is paid by every consumer, forever, and the
cost of shipped tooling is paid without anyone getting anything for it. Both are invisible to
the author and obvious to whoever inherits the `vendor/` directory.

## Platform and Toolchain Constraints

**Platform**: PHP 8.3+ and Filament v5. Support the full CI matrix, not the local version:
PHP 8.3 and 8.4 × Laravel 11, 12, and 13 × both `prefer-lowest` and `prefer-stable`, on Ubuntu
**and Windows**. A change that passes only on the newest combination is a broken change.

**Static analysis**: PHPStan at level 4 or higher over `src`, `config`, and `database`. The
level MUST NOT be lowered. The baseline is for inherited debt only — new code MUST NOT be
added to it to silence an error.

**Formatting**: Pint owns formatting. Code MUST NOT be hand-formatted against it; a push
touching PHP triggers a workflow that reformats and commits back, so manual styling is churn.

**Supply chain**: GitHub Actions MUST be pinned to a full commit SHA with the human-readable
version in a trailing comment, and every workflow MUST declare an explicit least-privilege
`permissions:` block. This is audited in CI and MUST NOT be bypassed.

**Comments**: Where a configuration or workaround is non-obvious, leave a comment explaining
**why**, not what. Match the density already present in the repository — comment the
surprising, not the routine.

## Development Workflow and Quality Gates

**Before implementation**: A feature's specification MUST be complete and its ambiguities
resolved before planning; planning MUST be complete before tasks; tasks MUST be ordered before
implementation begins. Each stage adopts its predecessor rather than re-deriving it.

**Prove fragile seams first**: Where a feature depends on another framework's internals, that
dependency MUST be proven end to end in a test harness **before** code is built on top of it.
Discovering a wrong assumption after the dependent code exists converts a spike into a rewrite.

**Before completion**: `composer test`, `composer analyse`, and `composer test:lint` MUST all
pass, and any built front-end artifact MUST be rebuilt and committed. A stale committed bundle
ships broken code that every local check reports as green.

**Commits**: Conventional Commits with a lowercase, descriptive subject. Say what changed and
why it mattered. Do not commit, push, tag, or release unless asked.

**Reporting**: Report outcomes faithfully. If tests fail, say so with the output. If a step was
skipped, say that. Work that was not run MUST NOT be described as verified.

## Governance

**Authority**: This constitution supersedes other practices in this repository. Where a
practice and a principle conflict, the principle wins and the practice is the bug.

**Relationship to `AGENTS.md`**: `AGENTS.md` is the operational expression of this document —
27 concrete, citable rules that agents load as a mandatory gate before planning. This file
says *why*; `AGENTS.md` says *exactly what, in this repository, with file references*. They
MUST NOT contradict each other. Where they appear to, this document governs and `AGENTS.md`
is corrected. Current mapping:

| Principle | AGENTS.md rules |
|---|---|
| I. Test-First | R-005…R-009 |
| II. Host Owns Data and Words | R-017, R-018, R-023 |
| III. Small, Frozen Surface | R-019, R-020 |
| IV. Safe and Honest | R-021, R-022, R-027 |
| V. Ship Runtime Only | R-004, R-015 |
| Platform and Toolchain | R-001, R-002, R-003, R-010, R-011, R-012, R-014 |
| Development Workflow | R-013, R-016, R-024, R-025, R-026 |

**Amendment procedure**: Amendments MUST be proposed as a documented change to this file,
stating the principle affected, the reason, and the migration for anything already built
against the previous wording. An amendment that loosens a principle MUST say what it is
trading away. Amendments take effect when merged, not when proposed.

**Versioning policy**: Semantic versioning of the constitution itself.

- **MAJOR** — a principle is removed, or redefined in a way that permits what it previously
  forbade.
- **MINOR** — a principle or section is added, or existing guidance is materially expanded.
- **PATCH** — clarification, wording, or typo fixes that do not change what is permitted.

**Compliance review**: Every implementation plan MUST include a Constitution Check evaluated
against this file, both before research and after design. A plan that cannot pass a gate MUST
justify the violation explicitly in its Complexity Tracking section rather than omitting the
gate. Substituting a different rule source for this file is permitted only when this file is
absent or unfilled, and MUST be labelled as a substitution rather than reported as a pass.

**Version**: 1.0.0 | **Ratified**: 2026-08-03 | **Last Amended**: 2026-08-10
