---
name: clientexec-registrar-development-kit
description: Build, audit, extend, and test Clientexec domain registrar modules from registrar API documentation. Use when creating a new Clientexec registrar plugin, implementing registration or domain-management capabilities, adding customer-facing registrar public panels, reviewing an existing Plugin{Name}.php module, adding TLD-specific extended attributes, or validating plugin metadata, actions, safety, and method contracts.
---

# Build Clientexec Registrar Modules

Build registrar modules conservatively from verified Clientexec behavior and the target registrar's API documentation.

## Follow the evidence order

Resolve conflicts in this order:

1. Current official Clientexec registrar development guide.
2. Current official `clientexec/sample-registrar`.
3. Runtime reflection and schema evidence from the target Clientexec installation.
4. Maintained registrar modules from Clientexec.
5. Other production modules and registrar-specific helpers.

Treat a method found only in one registrar as an observed pattern, not a framework contract. Never copy credentials, cached data, vendor code, or proprietary module code into the output.

## Gather requirements

Before executing any build or implementation request:

1. Identify the registrar from the request or ask for its name.
2. Check whether `workspace/<registrar>/inputs/` already contains adequate registrar API sources.
3. If the sources are missing or insufficient, ask for official API documentation, a documentation URL, or sanitized source files before implementation.
4. Thoroughly read the current official registrar documentation and every relevant supplied source before planning code. Derive the plugin ID, authentication method, available registrar features, Clientexec method mapping, supported TLDs, API endpoints, sandbox availability, and technical requirements.
5. Ask the user only for requirements, business decisions, target-environment details, or missing evidence that cannot be verified from the sources. Never ask the user to repeat technical information already documented.
6. Record contradictions and unknowns. Stop before implementation when guessing would change API behavior, enable an unsupported capability, or cause a live operation.

Do not require the user to know Clientexec method names, construct a plugin ID, identify the API authentication scheme, or enumerate every registrar capability. If the target Clientexec or PHP version is unknown, report the compatibility assumption and ask only when it materially affects the implementation.

When contact management is in scope, ask the user whether optional empty fields should remain visible, be hidden everywhere, or be hidden only on verified read-only surfaces. Recommend keeping them visible on editable forms, and record the user's decision in the implementation plan and development changelog. Never hide required fields.

When registration is in scope, verify from registrar and TLD evidence whether nameservers are required to submit registration, activate the domain, or leave a pending state. Record the minimum and maximum count, hostname and glue rules, registrar defaults, Clientexec parameter shape, TLD differences, activation timing, status-confirmation method, and timeout. Treat every unknown that can affect activation as blocking.

## Verify TLD requirements

Before implementing registration or transfer for any TLD:

1. Identify the initial TLD scope from the request and verified registrar sources.
2. Check the registrar documentation for TLD-specific eligibility, residency, registrant type, identity, trademark, consent, local-presence, contact, or other additional fields.
3. Preserve the registrar's exact field names and option codes separately from human-readable labels.
4. If the required TLD scope or additional-field rules are missing or ambiguous, ask the user for the intended TLDs and the registrar's official TLD requirements.
5. Keep registration or transfer disabled for every TLD whose required fields cannot be verified.

Do not ask the user to design technical fields that are already defined in the registrar documentation.

## Enforce the task write boundary

Classify the request before writing:

- For an audit, review, diagnosis, or report, keep the entire repository read-only.
- For registrar module implementation, allow writes only under `workspace/<registrar>/output/plugins/registrars/<registrar>/` and to the exact append-only file `workspace/<registrar>/output/DEVELOPMENT-CHANGELOG.md`. Treat every other path as read-only, including Clientexec core, server configuration, databases, other plugins, the skill, references, assets, agents, scripts, repository configuration, root documentation, supplied inputs, source manifests, templates, and other registrar workspaces.
- For source intake or workspace initialization, write `workspace/<registrar>/source-manifest.yaml`, its adjacent schema, and `workspace/<registrar>/inputs/` only when the user explicitly requested that work. This permission does not carry into module implementation.
- For skill maintenance, change skill-owned files only when the user explicitly requested a separate skill update. Never infer permission to improve the skill from a registrar development discovery; report the suggestion for a separate task.

Before every create, edit, delete, move, rename, copy, formatting, generation, or cleanup operation, resolve the target and allowed root to absolute paths and verify with a filesystem-aware containment check that the target is the allowed root or its descendant. Reject traversal, symlink, junction, ambiguous, and similarly prefixed path escapes. If a required change falls outside the active boundary, make no workaround there; keep the capability disabled when applicable and report the limitation to the user.

Never recommend, provide, or apply a Clientexec core, server configuration, database, or out-of-module code change as a registrar-module solution. Classify such a proposal as dangerous because it can break unrelated Clientexec behavior. Find a verified module-local solution or mark the capability blocked or unsupported and request official module-contract evidence or Clientexec support clarification.

## Gate troubleshooting commands

Before showing or running any troubleshooting command during module development:

1. Name the module function being implemented or verified.
2. Name the exact missing information that blocks that function.
3. Explain why the proposed inspection can obtain that information.
4. Give this declaration, completed with concrete details: "Troubleshooting is required because `<function>` is missing `<information>`. This inspection is read-only, is only for developing the registrar module, does not modify Clientexec core, server configuration, the database, or any out-of-module file, and is not harmful or state-changing."
5. Wait for the kit user to acknowledge the declaration before providing or running the command.
6. Recheck the command, every argument, target, expansion, redirection, invoked script, and expected side effect. Proceed only when all of them are demonstrably read-only.

Every command shown to, requested from, or run for the kit user during module development must be read-only. Treat a command as dangerous and do not provide or run it when it can write, delete, move, copy, rename, patch, format in place, generate, install, update, migrate, change permissions, clear caches, restart services, mutate a database or API, redirect output to a file, or invoke a script with uncertain side effects. Do not disguise a state-changing command as troubleshooting. If safe read-only inspection cannot resolve the missing information, keep the function blocked.

Read [references/security-and-testing.md](references/security-and-testing.md) before proposing or running any command.

## Create the registrar workspace

Perform this section only during explicitly authorized source intake or workspace initialization. Do not create or update intake files as a side effect of module implementation.

1. Copy `assets/registrar-workspace-template/` to `workspace/<registrar>/`.
2. Replace `<registrar>` with the lowercase alphanumeric plugin identifier.
3. Update the registrar identity and target environment in `source-manifest.yaml`. Keep `source-manifest.schema.json` beside it and resolve every schema error.
4. Complete `inputs/notes/requirements.md` with the requested capabilities and known constraints.
5. Put supplied files under `inputs/` according to their type.
6. Record URLs, Gists, repositories, attachments, Postman collections, and other evidence in `source-manifest.yaml`.
7. Remove credentials, cookies, customer data, production domains, and private environment values before preserving any source.
8. Record contradictions and open questions instead of silently resolving uncertain behavior.
9. Append the initial Phase 0 entry to `output/DEVELOPMENT-CHANGELOG.md` before the first module-code change.
10. Keep generated code under `output/plugins/registrars/<registrar>/`; never modify a supplied source in place without explicit instruction.

Read [references/source-intake.md](references/source-intake.md) before copying, downloading, sanitizing, or classifying registrar material.

## Plan phased implementation

Before changing plugin code:

1. Read `output/DEVELOPMENT-CHANGELOG.md` as the durable memory for all prior scope, decisions, troubleshooting, changes, validation, blockers, and next steps.
2. Append the initial Phase 0 entry before editing module code.
3. Inventory every registrar capability and compare it with verified Clientexec support.
4. Classify every capability as implement, unsupported by Clientexec, unsupported by the registrar, outside scope, blocked, or deferred.
5. Share the complete scope-based phase plan and limitation summary with the kit user.
6. Include the final production acceptance checklist in the plan. Give every applicable mandatory test a stable ID, owner, environment, preconditions, steps, expected result, and `Pending` status.
7. Obtain plan approval.
8. Run the Phase Evidence Gate before every included phase.
9. Implement and locally validate only that phase.
10. Append the pre-test changelog entry, pause for the kit user's sanitized staging or sandbox result, and append the result under the same test ID.
11. Keep failed, skipped, blocked, unsupported, and unfinished capabilities disabled.
12. Reconcile inventory, implementation, tests, enablement, and the latest result for every mandatory test before release.

Never leave a capability unclassified, start a blocked phase, bypass a hard dependency, or describe an unverified capability as complete. Permit the kit user to skip a mandatory staging or sandbox test only after warning that the capability will remain unverified and disabled. Combine adjacent small phases only with kit-user approval and keep their evidence, tests, results, and changelog entries separate.

Keep the implementation simple, readable, and consistent with the existing module and target Clientexec conventions. Prefer direct parameter mapping and a small registrar API client. Keep callbacks focused, use clear names, and add a helper only for repeated transport, validation, normalization, or security behavior. Do not add duplicated logic, dead or commented-out implementation, unrelated refactoring, speculative extensibility, frameworks, dependencies, unrelated features, or abstractions that the approved Clientexec scope does not require.

Read [references/phase-workflow.md](references/phase-workflow.md) before preparing the plan, opening a phase, requesting a test, enabling a capability, or declaring the module ready.

## Build the setup page first

Complete the registrar-specific setup surface before implementing or updating any feature function:

1. Set the final plugin directory, filename, class name, `Plugin Name`, description, and `resource/plugin.ini` identity.
2. Build `getVariables()` from the verified authentication and environment requirements. Include every configuration value needed to instantiate the API client and test later features.
3. Mark credentials and other secrets as `encryptable => true`, use empty defaults, and never embed live credentials.
4. Keep every API endpoint, base URL, and equivalent routing value non-editable. Prefer private verified production and sandbox constants. If Clientexec requires a configuration entry, make it `hidden`, select only a whitelisted constant, and never trust a saved arbitrary URL. A visible sandbox selector may choose between verified constants.
5. When registrar or TLD evidence says nameservers are required for registration or activation, add visible `Default NS1` through the greatest documented minimum across the approved TLD scope to the setup page. Verify whether the target Clientexec version enforces a native required property. Use it when proven; otherwise validate the configured defaults inside the module before any affected API request. Never edit Clientexec core to enforce the fields.
6. Prefer order/package nameservers after verifying their target-version parameter shape. When they are absent, use the verified setup defaults. Reject an incomplete or invalid required set before transport.
7. Keep all action lists empty and all PHP and INI feature flags disabled.
8. Load the module in a Clientexec staging installation and confirm that the setup page renders, configuration saves and reloads correctly, encrypted fields remain usable, endpoint values are not visible or editable, conditional nameserver fields behave as planned, and missing configuration fails safely.

Do not implement or update a registrar operation until the setup page and configuration path are complete enough to test that operation.

## Create the module

From this point through registrar implementation and module validation, write only inside `workspace/<registrar>/output/plugins/registrars/<registrar>/` and append only to `workspace/<registrar>/output/DEVELOPMENT-CHANGELOG.md`.

1. Copy `assets/registrar-template/plugins/registrars/example/` to `workspace/<registrar>/output/plugins/registrars/<registrar>/`.
2. Rename `PluginExample.php` using `Plugin{Name}.php` with only the first registrar-name character capitalized.
3. Rename `PluginExample` inside the file to exactly match the filename.
4. Update `resource/plugin.ini`.
5. Complete and validate the registrar setup page before changing any feature function.
6. Create the complete callable skeleton before implementing the first operation. Include all eleven abstract `RegistrarPlugin` methods immediately.
7. Inventory every convention-based callback and action handler that Clientexec can dispatch for the verified capability scope. Add each verified callback to the skeleton before feature work begins.
8. Make every unfinished structural method, verified callback, and handler throw a clear `CE_Exception` stating that the operation is not implemented for this registrar module. Never leave a required method absent and never return placeholder success.
9. Do not add speculative callback names found only in unrelated production modules. Classify each callback with `references/runtime-api.md` and `references/method-contracts.md`.
10. Keep every feature flag and action disabled until its corresponding implementation and negative paths are complete.
11. Replace fail-closed stubs one operation at a time. Do not remove the remaining stubs while working on another function.
12. Map Clientexec parameters to registrar API fields explicitly. Preserve registrar option codes separately from human labels.
13. Translate registrar failures into safe `CE_Exception` messages.
14. Validate syntax, metadata, setup-page behavior, method coverage, and negative paths after every operation.

Read [references/runtime-api.md](references/runtime-api.md) before deciding whether a method is required, optional, inherited, or internal.

Read [references/official-sources.md](references/official-sources.md) when verifying the public baseline or checking whether upstream guidance changed.

Read [references/method-contracts.md](references/method-contracts.md) while implementing inputs, outputs, action wrappers, DNS, transfer, pricing, or imports.

Read [references/features-and-actions.md](references/features-and-actions.md) while editing `$features`, `getVariables()`, registered actions, or `plugin.ini`.

Read [references/public-panels.md](references/public-panels.md) when adding a customer-facing domain-management menu item or form through registrar `$features['publicPanels']`, or when deciding whether an admin/staff surface requires a separate snap-in.

Read [references/extra-attributes.md](references/extra-attributes.md) when a TLD requires eligibility, consent, identity, or other extension-specific fields.

Read [references/security-and-testing.md](references/security-and-testing.md) before logging, storing credentials, calling live APIs, or declaring the module complete.

## Gate production readiness

Maintain the plan-time final acceptance checklist throughout development. Record ordered `Mandatory test IDs` and `Kit-user test IDs`, then append every agent and kit-user result to `output/DEVELOPMENT-CHANGELOG.md` under its stable test ID; preserve history and treat the latest appended result as effective. A kit-user-owned pass must include the kit user's sanitized response. Never infer a pass from silence, partial feedback, implementation completion, or an earlier result.

Use `Production-ready for approved scope` only when every applicable mandatory test has a latest result of `Passed`, every required kit-user response is recorded, the kit user explicitly confirms the final checklist, and no unverified capability is enabled. Use `Development complete, not production-ready` when code is complete but any mandatory result or confirmation is pending, skipped, unavailable, or unanswered. Use `Not ready` for a failed mandatory test, unresolved blocker, or enabled unverified capability. An unavailable sandbox does not waive a required integration test; keep the module not production-ready unless an explicitly authorized safe environment supplies the missing evidence.

Run the read-only generated-module validator in production-readiness mode before release. A validator pass supplements but never replaces the kit user's staging or sandbox results.

## Implement the structural contract

Include these eleven runtime-verified abstract methods in the initial scaffold, before implementing the first feature:

- `checkDomain($params)`
- `registerDomain($params)`
- `getContactInformation($params)`
- `setContactInformation($params)`
- `getNameServers($params)`
- `setNameServers($params)`
- `getGeneralInfo($params)`
- `setAutorenew($params)`
- `getRegistrarLock($params)`
- `setRegistrarLock($params)`
- `sendTransferKey($params)`

Also override `getVariables()` for operational configuration. It is inherited and concrete, so it is not part of the eleven-method PHP abstract contract.

Do not describe `renewDomain()`, DNS methods, transfer methods, pricing import, or domain import as abstract base methods. They are capability-specific conventions dispatched elsewhere in Clientexec.

For every capability included in the verified module scope, add its documented callback to the initial scaffold and make it fail closed until implemented. A stub prevents missing-method failures; it does not prove support and must not enable the related feature flag, INI flag, or action.

## Fail closed

- Throw for every unfinished or unsupported operation.
- Never return “available,” “registered,” “renewed,” or “updated” from placeholder code.
- Never enable a feature merely because a stub exists.
- Never log API secrets, passwords, EPP codes, full contact records, authorization headers, or unredacted request/response bodies.
- If a likely real credential appears anywhere in the workflow, treat it as compromised, stop using it without reproducing it, immediately warn the user to revoke or rotate it, and do not continue API work with that credential. Cleanup alone never restores trust.
- Detect failures in the registrar module, throw a sanitized registrar-neutral `CE_Exception`, and let Clientexec render it; never expose the registrar identity on a client or shared path, print errors, emit PHP warnings, return UI markup, or copy legacy `CE_Error` and generic-exception patterns into new work. Permit the registrar name only in sanitized logs or a verified admin-only surface.
- Never run a live registration, transfer, renewal, contact update, nameserver update, or DNS update without explicit authorization.
- Never silently convert an unknown registrar response into success.

## Complete validation

- Confirm the directory, filename, and class naming convention.
- Confirm the registrar setup page renders and its configuration saves and reloads in Clientexec staging.
- Confirm no API endpoint, base URL, or equivalent routing value is visible, editable, or sourced from arbitrary saved configuration.
- Confirm the nameserver activation requirement and target Clientexec registration parameter shape are documented. When nameservers are required, confirm the setup fields exist, required-field support or module-local enforcement is recorded, missing values fail before transport, and valid values reach the verified active state.
- Confirm all eleven abstract methods exist.
- Confirm every callback and handler required by the verified capability scope exists, even when it still fails closed.
- Confirm advertised actions have corresponding working `do{Action}()` handlers.
- Confirm enabled `$features` have working callbacks.
- Confirm `plugin.ini` matches implemented capabilities.
- Confirm every implemented capability appears as supported in the capability matrix and every ignored registrar feature is reported as an unsupported Clientexec limitation.
- Confirm no speculative callback, action, feature flag, dependency, or abstraction was added.
- Confirm the final diff contains no unrelated reformatting, duplicate logic, dead code, temporary debugging, unresolved placeholder, or commented-out implementation.
- Confirm every module path created, modified, deleted, moved, or renamed during implementation is inside the resolved `workspace/<registrar>/output/plugins/registrars/<registrar>/` boundary and the only non-module write is the exact changelog file.
- Confirm `DEVELOPMENT-CHANGELOG.md` began at Phase 0, was read before each resumed session or phase, records every troubleshooting declaration and completed phase, remains append-only, and stays outside the distributable plugin directory.
- Confirm no command provided or run during module development could change Clientexec core, server state, database state, or an out-of-module file.
- Confirm every applicable mandatory final-checklist test ID has a latest `Passed` result, every required kit-user response and final confirmation is recorded, and the production-readiness state matches those results.
- Confirm `source-manifest.yaml` validates against `source-manifest.schema.json`.
- Test unavailable, invalid, unauthorized, throttled, malformed, timeout, and registrar-error responses.
- For full-list `setDNS()`, validate the entire list before mutation and reconcile every ID absent from the registrar's current records by normalized type, hostname, and content; retain one exact match, create on no match, reject multiple matches, and never delete a reconciled record as omitted.
- Test against a Clientexec staging installation and registrar sandbox where available.
- Run `php -n scripts/validate-kit.php` for cross-platform kit-level checks.
- Use `scripts/validate-kit.ps1` as the PowerShell alternative.
- Run `scripts/audit-registrar-methods.ps1` when comparing registrar implementations.
- Run `php -n scripts/validate-registrar-output.php --module workspace/<registrar>/output/plugins/registrars/<registrar> --changelog workspace/<registrar>/output/DEVELOPMENT-CHANGELOG.md` before any production-ready claim.
- Require the GitHub Actions `Validate Kit` workflow to pass before merging or packaging a public release.
- Before publishing this kit, run `scripts/package-release.ps1 -Version <version>` and publish only its generated ZIP and checksum.

Report unverified assumptions, unavailable sandbox coverage, and any capability intentionally left disabled.
