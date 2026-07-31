# Phase Workflow

## Contents

- Operating rules
- Capability inventory and plan
- Phase state and evidence gate
- Permissions and blockers
- Testing and reporting
- Enablement and changelog
- Phase sequence
- Final reconciliation

## Operating rules

Use this workflow for every registrar implementation, regardless of the AI agent or development environment.

1. Review all relevant registrar and Clientexec evidence.
2. Append the initial Phase 0 entry to `output/DEVELOPMENT-CHANGELOG.md` before the first module-code change.
3. Read the entire changelog before every resumed session and phase.
4. Inventory every registrar capability.
5. Share the complete scope-based implementation plan with the kit user.
6. Summarize every unsupported, blocked, deferred, or out-of-scope capability.
7. Obtain plan approval before implementation.
8. Run the evidence gate before every included phase.
9. Implement and validate one phase at a time.
10. Pause for the kit user's staging or sandbox result before opening a dependent phase.
11. Keep unverified capabilities disabled.
12. Reconcile the capability inventory, implementation, tests, and enablement before release.

Keep implementation simple and phase-scoped. Apply the code-quality, security, credential, filesystem, and error-handling rules from the other skill references during every phase.

## Capability inventory and plan

Inventory business capabilities rather than merely counting endpoints. One capability may use several endpoints, and one endpoint may serve several capabilities.

Record:

| Capability | Registrar support | Clientexec support | Evidence | Scope decision | Phase |
|---|---|---|---|---|---|
| Example | Yes/No/Unknown | Yes/No/Unknown | Source and version | Implement/Unsupported/Out of scope/Blocked/Deferred | Phase or N/A |

Classify every capability as exactly one of:

- Implement
- Unsupported by Clientexec
- Unsupported by the registrar
- Outside the kit user's approved scope
- Blocked by missing evidence
- Deferred by the kit user

Leave no capability unclassified. Include all non-implemented capabilities in a limitation summary at the end of the plan.

Create the final production acceptance checklist as part of this plan. Give each applicable mandatory test a stable uppercase ID, owner (`Agent` or `Kit user`), environment, preconditions, steps, expected result, and initial `Pending` status. Always include `SETUP-RENDER-001`, `CONFIG-SAVE-001`, `ENDPOINT-HIDDEN-001`, `ERROR-SAFETY-001`, and `RELEASE-SMOKE-001`, plus every enabled capability and conditional registrar requirement. When nameservers are required for registration or activation, also include `NS-SETUP-001`, `NS-MISSING-001`, and `NS-ACTIVE-001`.

Record the ordered comma-separated lists as `Mandatory test IDs` and `Kit-user test IDs` in the development changelog. The kit-user list must include `SETUP-RENDER-001`, `CONFIG-SAVE-001`, `ENDPOINT-HIDDEN-001`, and `RELEASE-SMOKE-001`; add `NS-SETUP-001` and `NS-ACTIVE-001` when nameservers are required. Add tests when the scope expands; never silently remove an ID. An intentionally removed capability requires an append-only scope correction explaining why its test is no longer applicable.

Use this default order, omitting phases outside the approved scope:

1. Setup page, authentication configuration, and skeleton
2. Domain availability
3. Registration
4. General domain information
5. Nameserver management
6. DNS management
7. Contact management
8. Auto-renew and registrar lock
9. Renewal
10. Transfer
11. Transfer key, direct EPP display, and privacy
12. Domain import
13. TLD and price import
14. Actions and metadata
15. Final validation and packaging

Adjacent small phases may be combined only after kit-user approval. Keep evidence gates, test results, enablement decisions, and changelog entries separate for each combined capability. Split a combined phase when either capability becomes nontrivial or failure attribution would become unclear.

## Phase state and evidence gate

Use these states:

```text
Planned
→ Evidence review
→ Evidence ready / Blocked
→ User approval when required
→ Implementing
→ Locally validated
→ Awaiting kit-user test
→ Passed / Failed / Skipped
```

Keep at most one phase in `Implementing`. Enter implementation only from `Evidence ready`.

Before every phase:

1. Read the complete development changelog and reconcile prior decisions, changes, validation, blockers, and next actions.
2. Re-read the relevant current official registrar documentation.
3. Cross-check official schemas, SDKs, Postman collections, examples, and support clarifications.
4. Recheck the target Clientexec callback, action, feature flag, parameter shape, return contract, and version compatibility.
5. Recheck the capability matrix and dependency results.
6. Identify request fields, response fields, status codes, errors, permissions, limits, costs, and idempotency behavior.
7. For registration, identify whether nameservers are required to submit or activate, their count and format, registrar defaults, TLD differences, Clientexec parameter shape, activation timing, status confirmation, and timeout.
8. Identify local tests, kit-user tests, mutation permissions, and rollback requirements.
9. Record contradictions, missing evidence, and unresolved decisions.
10. Record each source's version or retrieval date, relevant scope, environment, account tier, and limitations.
11. Define the phase entry criteria, exit criteria, and planned staging enablement.

Classify each required fact as:

- Resolved
- Blocking unknown
- Non-blocking documented assumption
- Requires sandbox evidence
- Requires kit-user decision
- Requires registrar-support clarification

Treat an assumption as non-blocking only when it cannot change request semantics, response interpretation, authorization, ownership, cost, mutation behavior, feature enablement, or data preservation. Otherwise block the phase.

Use this evidence order:

1. Current official registrar documentation or protocol specification
2. Current official schema or maintained SDK
3. Official Postman collection or registrar support response
4. Sanitized sandbox fixtures
5. Read-only local inspection or diagnostic commands
6. Registrar sandbox read-only request
7. Sanitized evidence supplied by the kit user
8. New registrar-support clarification

Treat production modules, community examples, and one observed response only as leads until verified.

Present this pre-phase report:

```text
Phase:
Functions:
Registrar capability:
Clientexec integration point:
Target Clientexec/PHP:

Sources rechecked:
Source versions/retrieval dates:
Source scope and limitations:
Required request contract:
Required response contract:
Required permissions:
Mutation involved: Yes / No

Dependencies:
Dependency results:
Resolved evidence:
Blocking unknowns:
Documented assumptions:
Kit-user decisions required:
Registrar-support clarification required:
Sandbox evidence required:

Required local tests:
Required kit-user tests:
Rollback plan required: Yes / No
Phase entry criteria:
Phase exit criteria:
Planned staging enablement:

Evidence gate result: Ready / Blocked
```

Do not implement a blocked phase.

## Permissions and blockers

Use read-only inspection only for troubleshooting. Never use a command that can change Clientexec core, server state, database state, another plugin, or an out-of-module file. Never recommend a core or server-side edit as a module solution. Keep the capability blocked when a verified module-local implementation is impossible.

Before any command or request:

- Confirm it is necessary for the current phase.
- Confirm the command and every invoked component are demonstrably read-only.
- Preserve the module-directory and exact-changelog write boundary.
- Prevent credentials or personal data from entering commands, output, fixtures, or reports.
- Reject exposed credentials.
- Prefer the registrar sandbox for approved phase tests.
- Obtain permission for registration, renewal, transfer, contact, nameserver, DNS, lock, privacy, EPP, or any other phase-test mutation.
- Obtain permission for live, billable, irreversible, or difficult-to-recover phase tests.
- Sanitize retained evidence.

Before providing or running a troubleshooting command:

1. Identify the module function and exact missing information.
2. Explain how the read-only inspection can resolve that missing information.
3. Declare: "Troubleshooting is required because `<function>` is missing `<information>`. This inspection is read-only, is only for developing the registrar module, does not modify Clientexec core, server configuration, the database, or any out-of-module file, and is not harmful or state-changing."
4. Wait for the kit user to acknowledge the declaration.
5. Reject the command if it can write, delete, move, copy, rename, patch, format, generate, redirect to a file, install, update, migrate, change permissions, clear caches, restart services, mutate a database or API, invoke an unreviewed script, or cause any uncertain side effect.
6. Append the declaration, command purpose, and sanitized result to the development changelog without recording sensitive values or raw output.

Kit-user acknowledgement confirms the troubleshooting purpose; it does not make a mutating or uncertain command safe. Permission for an approved staging or sandbox phase test does not authorize a mutating troubleshooting command. User permission cannot authorize a core or out-of-module change within a registrar-module task. Do not suggest a separate core-change task as a workaround for module development.

Never bypass these blockers:

- Complete setup and configuration before an API capability.
- Use availability as the first end-to-end authentication check when no safer dedicated endpoint exists.
- Verify authentication before a mutating operation.
- Require a passed availability phase before registration.
- Resolve required TLD attributes and eligibility before registration or transfer.
- Verify domain identity and ownership before a domain mutation.
- Verify zone ownership, current records, supported types, and reconciliation semantics before DNS mutation.
- Verify contact groups, field mappings, and preservation semantics before contact mutation.
- Verify hostname rules and replacement semantics before nameserver mutation.
- Verify renewable states, periods, cost implications, and idempotency before renewal.
- Verify EPP input and transfer-status meanings before transfer.
- Verify authorization and secure handling before EPP display or delivery.
- Pass required implementation and tests before enabling an action, feature flag, or metadata capability.

Use actual dependencies:

```text
Setup and configured authentication
├── Read-only availability → Registration
├── Verified authentication + existing sandbox domain → General information
│   ├── Nameservers
│   ├── DNS
│   ├── Contacts
│   ├── Auto-renew and lock
│   └── Renewal
├── Verified authentication + eligible transfer domain → Transfer
├── Domain import
└── Price import
```

Do not require registration for domain-management phases when an eligible existing sandbox domain is available. Require nameserver completion before DNS only when registrar-hosted DNS or zone activation depends on those nameservers.

## Testing and reporting

The agent owns:

- Syntax and supported-version checks
- Static security and code-quality checks
- Parameter and response mapping
- Sanitized fixture tests
- Mocked initialization, transport, malformed-response, ambiguity, pagination, and failure tests
- Write-boundary and release-content checks

The kit user owns:

- Clientexec staging behavior
- Registrar sandbox integration
- Administrator/customer UI behavior
- Approved mutation confirmation
- Registrar-side state comparison
- Rollback confirmation

Do not ask the kit user to manufacture malformed responses, cURL failures, ambiguous internal matches, broken pagination, or missing price components.

Use these overall results:

- `Passed`: every mandatory test passed.
- `Failed`: at least one mandatory test failed.
- `Skipped`: at least one mandatory staging or sandbox test was explicitly skipped.

Optional unavailable coverage may be recorded without changing a passed mandatory result. Keep combined capabilities' results separate.

For each stable test ID, append every result and use the latest appended result as effective. Never convert `Pending`, `Failed`, `Skipped`, `Unavailable`, or no response into a pass. A kit-user-owned test requires the kit user's explicit sanitized response; agent inference, implementation success, or silence is not evidence.

When the kit user skips a mandatory test:

1. Warn that the capability remains unverified.
2. Record the skip and reason.
3. Disable its action, feature flag, and metadata capability.
4. Do not call it complete, tested, or production-ready.
5. Continue only with independent phases or safe scaffolding.
6. Never bypass a hard blocker.

Use this sanitized report:

```text
Phase:
Test date:
Module build/phase identifier:
Development changelog reference:
Evidence gate result: Ready / Blocked
Clientexec version:
PHP version:
Environment: Staging / Sandbox / Live
Registrar sandbox available: Yes / No
Mutation permission granted: Yes / No / Not required
Original state recorded: Yes / No / Not required

Tests:
1. Test ID:
   Owner: Agent / Kit user
   Test name — Passed / Failed / Skipped / Pending
   Sanitized result:

Unexpected behavior:
Sanitized error:
Surface: Admin / Customer / Background
Rollback completed: Yes / No / Not required
Mandatory test skipped: Yes / No

Phase result: Passed / Failed / Skipped
```

Never request unredacted screenshots, logs, domains, contacts, credentials, headers, EPP codes, or raw request/response bodies.

## Enablement and changelog

Keep actions and features disabled while incomplete.

Use `output/DEVELOPMENT-CHANGELOG.md` as append-only durable memory from the beginning of development:

1. Append the Phase 0 entry before the first module-code change.
2. Read the entire file before every resumed session and phase.
3. Append phase start, scope, active functions, evidence, assumptions, decisions, blockers, and next action.
4. Append every troubleshooting declaration, read-only command purpose, and sanitized result.
5. Append every module change with paths relative to `output/`.
6. Append local validation and kit-user results, including skipped coverage and rollback status.
7. Append the current `Mandatory test IDs`, `Kit-user test IDs`, each `Test result [ID]`, sanitized kit-user response where owned by the kit user, final confirmation, and production-readiness decision.
8. Append corrections; never silently rewrite or delete earlier history.

After local validation:

1. Append an `Awaiting kit-user test` entry to `output/DEVELOPMENT-CHANGELOG.md`.
2. Enable the capability only in staging when needed for the approved test.
3. Give the kit user the relevant phase tests.
4. Pause for the result.
5. Append the result, skipped coverage, rollback status, and enablement decision as a new entry.
6. Retain approved enablement only after mandatory tests pass.
7. Disable a failed or skipped capability.
8. Recheck dependencies before opening the next phase.

Never store credentials, headers, contact data, production domains, EPP codes, or raw request and response content in the changelog.

## Phase sequence

### Phase 0 — Evidence and scope

Append the initial development changelog entry, then inventory capabilities, confirm target versions, sandbox, TLD scope, contact empty-field policy, nameserver activation requirements, administrator/customer actions, and the final acceptance checklist. Record the approved scope, module write root, exact changelog exception, sources, assumptions, decisions, blockers, `Mandatory test IDs`, validation state, open items, and next action. Share the plan and limitation summary.

Exit only when the Phase 0 changelog entry is complete, every capability is classified, all conditional nameserver facts are resolved or blocking, the final checklist is tailored, and the kit user approves the scope.

### Phase 1 — Setup page, authentication configuration, and skeleton

Implement:

- `getVariables()`
- Plugin identity
- Credential and sandbox configuration
- Private whitelisted production and sandbox endpoints; no editable endpoint field
- Conditional default nameserver setup fields and proven enforcement strategy when activation requires them
- Small API client
- Safe logging and error mapping
- All eleven required abstract methods as fail-closed implementations
- Verified optional callbacks in scope as disabled fail-closed stubs

Agent-local tests:

- PHP syntax and supported-version compatibility
- Required method coverage
- Missing configuration
- Hidden endpoint rendering and fixed production/sandbox routing
- Conditional nameserver-field rendering, native required behavior when proven, and module-local missing-value failure otherwise
- cURL initialization/configuration cleanup
- Credential and log redaction
- Safe registrar-neutral error behavior (`ERROR-SAFETY-001`)
- Disabled unfinished actions/features

Kit-user tests:

- Setup rendering (`SETUP-RENDER-001`)
- Save/reload behavior (`CONFIG-SAVE-001`)
- Encrypted field protection
- Sandbox selection
- API endpoint/base URL absent from every editable setup surface (`ENDPOINT-HIDDEN-001`)
- Required default nameserver save/reload and missing-value behavior when applicable
- Safe missing or invalid configuration
- No unexpected actions

Exit when configuration works in staging, endpoint routing is non-editable, and conditional nameserver setup behavior passes. Treat authentication as configured; verify it end to end in Phase 2.

### Phase 2 — Domain availability

Implement `checkDomain($params)` and optional name-suggestion behavior through that method.

Prerequisite: Phase 1 passed.

Agent-local tests:

- Available, registered, invalid, unsupported, empty, malformed, timeout, authentication, and rate-limit mapping
- Repeated requests
- Name-suggestion normalization

Kit-user tests:

- Known available and registered sandbox domains
- Invalid and unsupported input
- Repeated search
- Approved name suggestions
- Safe invalid-authentication test only when lockout is impossible

Exit when authenticated availability and mandatory result mapping pass. A failed or skipped mandatory availability test blocks registration.

### Phase 3 — Registration

Implement `registerDomain($params)` and `doRegister($params)`.

Prerequisites: Phase 2 passed; selected TLD requirements resolved; nameserver submission and activation requirements resolved; target Clientexec registration parameter shape verified; explicit mutation permission.

Agent-local tests:

- Registration, contact, nameserver, period, and extended-attribute mapping
- Required nameserver precedence, minimum/maximum count, syntax, defaults, pre-transport failure, and asynchronous activation status handling
- Unavailable, invalid, unsupported, missing, empty, malformed, pending, ambiguous, duplicate, and registrar-error behavior
- No placeholder success

Kit-user tests:

- One approved disposable sandbox registration
- When nameservers are required, setup-field behavior (`NS-SETUP-001`), missing-value no-transport behavior (`NS-MISSING-001`), and documented active-state confirmation (`NS-ACTIVE-001`)
- Exactly one registrar order/domain
- Returned identifier
- Safe invalid input
- Rollback or cleanup status

Exit when local negative paths and the approved sandbox registration pass.

### Phase 4 — General domain information

Implement `getGeneralInfo($params)`.

Prerequisites: verified authentication and an eligible sandbox domain. Permit an existing domain when Phase 3 is unsupported or unavailable.

Agent-local tests:

- Identity, expiration, state, auto-renew, timezone, date, unknown, empty, malformed, and invalid-date mapping

Kit-user tests:

- Registrar versus Clientexec identity, expiration, status, and auto-renew
- Pending/expired states when available
- Stable repeated synchronization

### Phase 5 — Nameserver management

Implement `getNameServers($params)` and `setNameServers($params)`.

Prerequisites: verified domain identity and replacement semantics.

Agent-local tests:

- Mapping, blank removal, ordering, hostname validation, repeated saves, pre-mutation validation, and registrar errors

Kit-user tests:

- Retrieve, update, repeat-save, reject invalid input, and restore original nameservers

### Phase 6 — DNS management

Implement `getDNS($params)` and `setDNS($params)`.

Prerequisites: verified zone ownership, registrar-hosted DNS, current-record retrieval, record types, synchronization semantics, and mutation permission.

Agent-local tests:

- Type and field validation
- Complete-list validation before mutation
- ID ownership
- Unknown temporary-ID reconciliation with zero, one, and multiple normalized matches
- Duplicate and omission planning
- Empty, malformed, partial, ambiguous, and registrar-error behavior
- No mutation from an invalid list

Kit-user sequence:

1. Retrieve current records.
2. Create one disposable record.
3. Save again without refreshing.
4. Confirm no duplicate or deletion.
5. Update the same record.
6. Confirm update rather than duplicate creation.
7. Delete the same record.
8. Confirm removal.

Also test every advertised record type and safely invalid input.

### Phase 7 — Contact management

Implement `getContactInformation($params)` and `setContactInformation($params)`.

Prerequisites: verified domain identity, supported contact groups/fields, preservation semantics, mutation permission, and kit-user empty-field policy.

Agent-local tests:

- Supported groups and mappings
- Required, optional, unsupported, whitespace-only, and zero-like values
- Empty-field policy
- Partial-update preservation
- Empty, malformed, and incomplete responses

Kit-user tests:

- Supported groups
- Required/optional visibility
- One partial update
- Preservation of unrelated fields/groups
- Repeated save
- Rollback

Never request actual contact values.

### Phase 8 — Auto-renew and registrar lock

Implement:

- `setAutorenew($params)`
- `getRegistrarLock($params)`
- `setRegistrarLock($params)`
- `doSetRegistrarLock($params)`

Prerequisites: Phase 4 passed for the test domain; update semantics and permission verified.

Test auto-renew and lock separately even when implementing them together. Cover boolean normalization, repeated state, prohibited states, errors, toggles, registrar confirmation, and rollback.

### Phase 9 — Renewal

Implement `renewDomain($params)` and `doRenew($params)`.

Prerequisites: Phase 4 passed; renewable state, periods, costs, idempotency, disposable domain, and permission verified.

Agent-local tests:

- Period/state validation
- Costs
- Empty, malformed, pending, failed, duplicate, retry, and idempotency behavior
- Expiration confirmation

Kit-user tests:

- One approved renewal
- Exactly one registrar order
- Expiration change
- Safe invalid period
- Duplicate test only with safe idempotency and explicit permission

### Phase 10 — Transfer

Implement:

- `initiateTransfer($params)`
- `doDomainTransferWithPopup($params)`
- `getTransferStatus($params)`

Prerequisites: verified authentication, eligible disposable domain, EPP contract, status meanings, and permission. Do not require Phase 3 for an independent transfer domain.

Agent-local tests:

- Input mapping
- Pending, failed, completed, unknown, empty, and malformed statuses
- Invalid, duplicate, and unsupported behavior
- Completion never inferred

Kit-user tests:

- One approved sandbox transfer
- One order
- Pending/status mapping
- Safe invalid EPP attempt only without lockout risk
- Completion only after registrar confirmation

Never include an EPP code in a report.

### Phase 11 — Transfer key, direct EPP display, and privacy

Implement only supported and approved capabilities:

- `sendTransferKey($params)`
- `doSendTransferKey($params)`
- `getEPPCode($params)`
- `disablePrivateRegistration($params)`

Verify authorization, secure handling, privacy semantics, and permission. Treat delivery, direct display, and privacy as separate test results; split them when nontrivial.

Test administrator/customer authorization, EPP redaction, secure delivery/display separation, privacy confirmation, and supported rollback.

### Phase 12 — Domain import

Implement `fetchDomains($params)` and enable import metadata only after tests pass.

Prerequisites: verified authentication and pagination/import contract. Do not require operational domain phases.

Agent-local tests:

- Pagination, deduplication, splitting, dates, counts, empty account, malformed/failed/partial pages, and large sanitized fixtures

Kit-user tests:

- Count, normalized sample, repeated import, no duplicates, pagination, and empty account

Never request the real domain list.

### Phase 13 — TLD and price import

Implement `getTLDsAndPrices($params)` and enable import metadata only after tests pass.

Prerequisites: verified authentication and pricing/currency/unit/pagination contracts.

Agent-local tests:

- Operation, currency, unit, TLD, pagination, missing components, malformed/partial pages, and no accidental zero pricing

Kit-user tests:

- Count, sanitized sample, registration/renewal/transfer prices, currency, units, repeated import, and unsupported-TLD handling

### Phase 14 — Actions and metadata

Reconcile:

- `Actions`
- `Registered Actions`
- `Registered Actions For Customer`
- `$features`
- `resource/plugin.ini`

Require a recorded implementation and mandatory test result for every enabled capability. Map every action to a working authorized `do{Action}()` handler. Keep failed, skipped, unsupported, blocked, and unfinished capabilities disabled.

Verify administrator/customer visibility, direct authorization, and metadata consistency in Clientexec staging.

### Phase 15 — Final validation and packaging

Require:

- Complete capability classification and phase results
- No enabled unverified capability
- Structural and syntax validation
- Security and code-quality review
- Registrar-neutral client errors
- No credentials or personal data
- Module-directory-only implementation writes with the exact append-only changelog exception
- No core, server, database, other-plugin, or out-of-module change or recommendation
- Only declared, acknowledged, demonstrably read-only troubleshooting commands
- Complete sanitized changelog maintained from Phase 0
- Final checklist created during Phase 0 with stable mandatory test IDs
- Latest result for every applicable mandatory ID is `Passed`
- Every kit-user-owned test has an explicit sanitized kit-user response
- Kit-user final checklist confirmation is recorded
- Nameserver activation and hidden endpoint checklist items passed when applicable
- Read-only generated-module production validator passed
- Archive built only from `output/plugins/registrars/<registrar>/`
- Archive inspection proving development-only artifacts are absent
- Clean staging installation and enabled-capability smoke tests
- Final clean-install and enabled-scope kit-user smoke result (`RELEASE-SMOKE-001`)

## Final reconciliation

Track:

| Capability | Registrar support | Clientexec support | Scope decision | Phase | Implementation | Test result | Enablement |
|---|---|---|---|---|---|---|---|
| Example | Yes/No/Unknown | Yes/No/Unknown | Implement/Unsupported/Out of scope/Blocked/Deferred | Phase or N/A | Not started/Complete | Not run/Passed/Failed/Skipped | Disabled/Enabled |

Reconcile:

```text
Registrar capability inventory
↕
Approved implementation plan
↕
Implemented capability list
↕
Tested and enabled capability list
```

Use:

- `Production-ready for approved scope`: every applicable mandatory ID has a latest `Passed` result, every required kit-user response and final confirmation is recorded, and no unverified capability is enabled.
- `Development complete, not production-ready`: implementation is complete but any mandatory result or kit-user response is pending, skipped, unavailable, unanswered, or unconfirmed. Keep the affected capability disabled.
- `Not ready`: a mandatory test failed, a blocker remains unresolved, required setup or authentication failed, or an unverified capability is enabled.

An unavailable sandbox never waives a mandatory integration result. Use a live environment only with separate explicit authorization and a documented recovery plan; otherwise retain `Development complete, not production-ready`.

Package only the registrar plugin directory. Exclude the development changelog, tests, temporary files, credentials, and every other development-only artifact.
