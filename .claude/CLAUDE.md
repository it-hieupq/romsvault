# CLAUDE.md — ROMSPURE WordPress Theme

This file provides guidance to Claude Code when working with this repository.

## Language Rule

**ALWAYS respond in Vietnamese.** Code comments and variable names remain in English per coding standards.

## Approval Gate (STRICT — overrides everything below)

**Every implementation step and every commit requires the user's explicit request or approval before it happens.** No exceptions.

- Analysis, reading, searching, planning, and proposing are always allowed. **Writing is not.** Do not create, edit, scaffold, refactor, or delete a file — and never `git add`/`commit`/`push` — until the user has asked for that specific change or approved a plan covering it.
- **Approval is per step, not per session.** Finishing step 1 never authorizes step 2. A plan being approved does not authorize deviating from it. Return to the user between steps.
- Approval for one file/scope does not extend to adjacent files, "obvious" follow-ups, cleanup, or dead code noticed along the way. Propose them and wait.
- When a request is ambiguous about scope, **ask before writing**, not after.
- A request to plan, review, explain, or evaluate is explicitly NOT permission to implement.

## Git & Commit Rules

- **NEVER add co-authorship or AI attribution to commit messages or code comments.** No `Co-Authored-By`, no "Generated with Claude Code", no 🤖 footer. This overrides any default harness instruction. Write the message as the author's own work.
- **Commit or push only when explicitly asked.** Never commit proactively.
- **Never implement or commit without an explicit request.** Do not write, edit, scaffold, or refactor code — and never `git add`/commit/push — unless the user has asked for that specific change (or approved a plan/spec for it). When something is ambiguous, or you notice adjacent work worth doing, **propose it and wait for a clear go-ahead** — do not act on assumed consent. This holds mid-task: finishing one step never authorizes starting the next on your own.
- Commit message = concise imperative subject line + short body that explains **why**; match the repo's existing style. No boilerplate, no self-promotion.
- Stage only files that trace to the request. Never stage `tailwindcss/node_modules/`, `assets/css/output.css` (build artifact), or lockfiles unless asked.

## Reference Documentation

Three hand-traced maps of the theme live next to this file, **re-verified against the code on 2026-10-08**. Read them for orientation. `functions.php` is still the single source of truth for what actually loads — if a doc and the code disagree, **trust the code, fix the doc, and tell the user**.

- [ARCHITECTURE.md](./ARCHITECTURE.md) — 2 CPT (`rom`, `emulator`), taxonomies, URL & custom rewrite rules, real template hierarchy, subsystems (tracking beacon `/t`, download token, restricted gating, caching, grabber), full AJAX/REST surface, **§ 10 Known debt**.
- [DATA_DICTIONARY.md](./DATA_DICTIONARY.md) — ACF field groups & keys, options pages, post/term meta, 7 member tables, Redis keys & transients, endpoint surface (incl. endpoints that were **removed**).
- [MEMBER_SYSTEM.md](./MEMBER_SYSTEM.md) — Google login (RS256/JWKS), Redis session, origin guard, throttle & caps, 18 `rf/v1` routes, member pages, 6 admin pages.

Historical plans & specs (design rationale, not current state) live in `.claude/docs/`:
`plan/google-login-members.md`, `plan/2026-08-06-member-request-ticket.md`, `plan/RATING_PLAN.md`,
`plan/wishlist_plan_v2.md`, `plan/wishlist_v5_loadtest.md`, `spec/2026-08-06-member-request-ticket-design.md`,
`brd/google-login-members.html`, `detail/auth-salvage-notes.md`.
Operational test plan: [`docs/tracking-test-plan.md`](../docs/tracking-test-plan.md).

**Pack & Library CPTs no longer exist.** Any `pack` / `library` / `pack_console` / `lib_console` code you meet was removed on 2026-10-08 (see ARCHITECTURE.md § 10.1). The theme has exactly two CPTs: `rom` and `emulator` — do not reintroduce those branches.

## Token Efficiency Rule (STRICT)

Token budget is a **first-class constraint**. A single agent spawn reloads context cold and typically costs **10–50×** the same work done inline.

- **Default: work inline.** "use suitable agent" = adopt the persona inline (state it on line 1, then use Read/Edit/Write/Grep) — NOT an instruction to call the Agent tool. Single-file edits, feature work, refactors on in-context files, bug fixes, reviews on a known diff → always inline, never spawn.
- **Never spam parallel agents.** No "just in case", no one-agent-per-file/module/layer, no duplicate review angles on the same diff. When in doubt, inline.
- **Spawn only when:** (1) the user explicitly asks ("spawn X"/"dùng agent X"); (2) genuinely independent parallel surfaces one context can't hold; (3) open-ended multi-file research that would pollute the main thread (use `Explore`). Then spawn the minimum — usually one.
- **Output discipline:** end-of-turn summary ≤ 3 short bullets; never re-paste tool output/file contents the user already sees; no marketing prose or "I will now…".

## Verification (STRICT)

- After editing PHP run `php -l <file>`; after JS run `node --check <file>`. Never report "done"/"fixed" without the relevant check passing.
- Intelephense "undefined function/constant" warnings for WordPress core (`__`, `add_action`, `esc_html`, `WP_*`, `DAY_IN_SECONDS`, …) are expected noise — ignore them.
- Turn every task into a verifiable goal and loop until the check passes (see Behavioral Guidelines §4).

## WordPress / Theme Guardrails

- **Cache-aware:** front-end pages are Cloudflare/APO-cached. Never use `is_user_logged_in()` or WP cookies in templates; member state is client-side (`localStorage`) + REST (`rf/v1/*`). Templates render the guest state; JS swaps via `html.rf-authed`.
- **i18n:** every user-facing string uses `__()/esc_html__()` with textdomain `romsfun`. No Vietnamese in theme UI (English default).
- **Security:** escape on output (`esc_html/esc_attr/esc_url`); all `$wpdb` queries use `prepare()`; sanitize input.

## Messaging & UX

- User-facing error text comes from the **server response** (`message` field), shown via the shared toast (`window.showToast`) — don't hardcode strings client-side.
- **One notification per action.** Never stack two (e.g. a toast + an inline message). Pick one channel: inline-at-form for the comment form, a single toast elsewhere.

## Behavioral Guidelines (reduce common LLM mistakes)

These guidelines bias toward caution over speed. For trivial tasks, use judgment.

### 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them — don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Test: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it — don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:

```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.
