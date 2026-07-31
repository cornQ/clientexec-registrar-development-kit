# Security and Testing

## Fail-closed development

- Keep PHP feature flags and INI features disabled until working.
- Throw `CE_Exception` from every unfinished method.
- Treat missing, empty, malformed, or unrecognized API responses as failures.
- Never use a default “available” status.
- Never return a success message before checking the registrar result.
- Never automatically retry a non-idempotent registration, transfer, renewal, or update unless the registrar supplies a safe idempotency mechanism.

## Secrets and personal data

Do not log:

- API keys, secrets, passwords, or authorization headers
- EPP/auth codes
- Full request or response bodies
- Full registrant/admin/technical contact records
- Identity, tax, citizenship, passport, or company-registration values
- Database connection strings

Redact sensitive values before diagnostic logging. Use Clientexec encryptable settings for credentials. Do not commit `.env` files, caches, archives, vendor credentials, or copied production configuration.

### Credential exposure response

If a value appears to be a real credential in user input, a file, command output, log, screenshot, fixture, changelog, manifest, generated artifact, or conversation:

1. Treat it as compromised even if the exposed copy can be deleted.
2. Stop using it and do not make an API request with it.
3. Do not quote, reproduce, log, preserve, or include the value in a tool command or response. Identify only its general type when warning the user.
4. Immediately warn the user to revoke or rotate it before development continues. Recommend invalidating related sessions, grants, certificates, or domain auth codes when applicable.
5. After rotation, inspect the workspace, Git history, remote repository, CI logs, build artifacts, and registrar audit logs for further exposure using methods that do not print the secret.
6. Remove exposed copies only within the active write boundary. Obtain explicit approval before destructive history rewrites, remote artifact deletion, or changes outside that boundary.

Deletion, redaction, or an uncommitted state does not restore trust in an exposed credential; rotation or revocation is required. Do not treat obvious placeholders such as `REDACTED`, documentation-reserved examples, or clearly fake test values as incidents. When authenticity is uncertain, do not use the value and recommend the safer rotation path without reproducing it.

## Message audiences

Treat every exception or returned message as client-visible unless the target Clientexec version proves that the dispatch path is admin-only.

| Audience | Registrar identity | Diagnostic detail |
|---|---|---|
| Client or shared surface | Never include the registrar name, aliases, API hostname, endpoint, or product name | Use a localized generic message; include only a non-sensitive reference ID when needed |
| Verified admin-only surface | Registrar name may be visible | Keep details sanitized; do not include raw requests, responses, headers, credentials, or contact data |
| Diagnostic log | Registrar name and internal error code may be recorded | Redact sensitive values and use the same reference ID as the client-safe exception when correlation is needed |

## API client rules

- Verify TLS certificates.
- Set explicit connection and request timeouts.
- Validate HTTP status and registrar-level result codes.
- Route requests only to verified private production or sandbox endpoint constants. Never trust an editable, saved, request-supplied, redirected, or otherwise arbitrary base URL.
- Bound retries and use backoff only for safe operations.
- Encode query, JSON, XML, and EPP fields with the correct context-aware mechanism.
- Avoid building SQL, URLs, XML, or shell commands through raw concatenation.
- Pin third-party dependencies and retain their license notices.

## Filesystem write safety

Before every filesystem mutation:

1. Identify the active task mode and its allowed write root.
2. Resolve the allowed root and target to absolute filesystem paths.
3. Confirm the target is the allowed root itself or a descendant using path-component-aware comparison, not a string-prefix check.
4. Resolve and reject any symlink, junction, traversal, or other indirection that escapes the allowed root.
5. Stop without writing when the target is ambiguous, cannot be resolved safely, or falls outside the active boundary.

During registrar module implementation, the only recursive write root is `workspace/<registrar>/output/plugins/registrars/<registrar>/`. The only write outside that directory is appending to the exact file `workspace/<registrar>/output/DEVELOPMENT-CHANGELOG.md`. Keep Clientexec core, server configuration, databases, other plugins, the rest of the workspace, and the development kit read-only even when changing them appears helpful.

Never recommend, provide, or apply a core or out-of-module change as a workaround. Classify it as dangerous, keep the affected capability disabled, and either implement a verified module-local solution or record the missing Clientexec module contract as a blocker.

Before running a validator or test suite, determine every location it may write. Run it only when it is demonstrably read-only. Do not run formatters, package managers, generators, cache-building tools, or any other command that writes during module development. Report a skipped validator and its reason rather than weakening the boundary.

## Read-only troubleshooting commands

Use troubleshooting only to obtain an identified piece of information missing from a specific registrar-module function. Before showing or running a command:

1. Identify the function, missing information, and why the inspection is needed.
2. Tell the kit user: "Troubleshooting is required because `<function>` is missing `<information>`. This inspection is read-only, is only for developing the registrar module, does not modify Clientexec core, server configuration, the database, or any out-of-module file, and is not harmful or state-changing."
3. Wait for the kit user to acknowledge the declaration.
4. Inspect the complete command, arguments, targets, expansions, redirections, pipelines, invoked scripts, and expected side effects.
5. Provide or run it only when every component is demonstrably read-only and the output can be sanitized.

Treat a command or recommendation as dangerous when it can:

- Create, edit, delete, move, copy, rename, patch, format, or generate a file
- Redirect or pipe output into a file or a state-changing program
- Install or update a package, dependency, plugin, or system component
- Run a migration or mutate a database
- Clear or rebuild a cache
- Change ownership, permissions, services, processes, tasks, or server configuration
- Make a mutating registrar, Clientexec, HTTP, or other API request
- Invoke an unreviewed script or any operation whose side effects are uncertain

Do not provide a dangerous command even as an optional suggestion. Do not convert troubleshooting into a Clientexec core investigation intended to produce a core patch. When read-only evidence remains insufficient, stop and record the function as blocked.

### Defensive cURL initialization

For every normal and cURL-multi request:

- Verify `curl_init()` succeeds before using the returned handle.
- Verify `curl_multi_init()` succeeds before using it when building a multi request.
- Verify every `curl_setopt_array()` call succeeds.
- Verify every individual handle is successfully added to the multi handle.
- Close every successfully initialized individual and multi handle before throwing from any initialization, configuration, add-handle, execution, or response-validation failure.
- Throw a safe `CE_Exception` that does not expose credentials, headers, contact information, or response bodies.

## Testing layers

### Static checks

- Validate PHP syntax using every PHP version supported by the target Clientexec release.
- Confirm filename, directory, and class naming.
- Confirm all eleven abstract methods exist.
- Search for placeholder success, raw logging, hard-coded credentials, and enabled unimplemented features.
- Search registrar callbacks for printed output, PHP warnings, returned UI markup, raw registrar messages passed to exceptions, legacy `CE_Error` returns, and generic `Exception` throws.
- Check for duplicated helpers, dead or unreachable code, unresolved `TODO` or placeholder markers, temporary debugging, commented-out implementations, unused dependencies, and unrelated formatting changes.
- Confirm every added abstraction has repeated concrete use or a clear validation, transport, normalization, or security purpose.
- Validate syntax against every supported target PHP version and review the final diff for compatibility and unnecessary changes.
- Confirm every endpoint or base-URL setup entry is hidden, no endpoint-like field is editable, and transport cannot consume an arbitrary configured URL.
- When nameservers are required for registration or activation, confirm the minimum setup fields, enforcement strategy, pre-transport validation, and active-state confirmation are present.

### Mapping tests

Use sanitized fixtures to test:

- Domain and TLD normalization
- Contact-field mapping
- User-selected empty-contact-field policy across registrant, admin, technical, and billing groups
- Required fields always visible, unsupported fields omitted, and valid zero-like values preserved
- Omitted contact fields preserved during repeated saves and unrelated contact updates
- Nameserver ordering and blank removal
- Boolean and date normalization
- Registrar error mapping
- Extended-attribute codes
- DNS record types
- Pricing currency and units

### Negative tests

Cover:

- Invalid and unsupported domains
- Authentication and authorization failure
- Rate limiting
- Timeout and connection failure
- Malformed JSON/XML
- Empty success bodies
- Duplicate/idempotent requests
- Unknown registrar statuses
- Missing required TLD attributes
- Normal cURL and cURL-multi operation
- Mocked `curl_init()`, `curl_multi_init()`, `curl_setopt_array()`, and multi add-handle failures, including handle-cleanup assertions
- Safe `CE_Exception` mapping for validation, registrar, parsing, and transport failures
- `EXCEPTION_CODE_CONNECTION_ISSUE` only for verified connection failures
- No printed output, PHP warning, UI markup, or sensitive value in any failure response
- Localized success messages only after confirmed registrar success
- No registrar name, brand alias, API hostname, endpoint, product name, or raw registrar text in client-facing or shared-path errors
- Registrar identity visible only in sanitized logs and verified admin-only paths
- Matching non-sensitive reference IDs in client-safe exceptions and diagnostic logs when correlation is implemented
- Every implemented feature has verified registrar and Clientexec support in the capability matrix
- Unsupported registrar features have no callback, action, flag, UI, background job, storage field, or API code
- Target Clientexec and PHP compatibility for every enabled capability
- Read-only audit behavior and task-specific source-intake permission
- Mandatory troubleshooting declaration and kit-user acknowledgement before every diagnostic command
- Rejection of commands that write, redirect to files, install, update, migrate, clear caches, restart services, mutate databases or APIs, or have uncertain side effects
- Rejection of every Clientexec core, server configuration, database, other-plugin, and out-of-module edit or recommendation
- Path containment for create, edit, delete, move, rename, copy, formatting, generation, and cleanup operations
- Rejection of traversal, symlink, junction, ambiguous, and similarly prefixed path escapes
- Detection of API keys, bearer tokens, passwords, private keys, client certificates, Postman secrets, cookies, and domain auth codes in every development input and output
- Immediate non-reproducing rotation warning and cessation of API work after a likely real credential is detected
- No exposed credential in an exception, log, changelog, manifest, fixture, command, tool output, or response
- Placeholder recognition without weakening handling of uncertain values

### Integration tests

Use a Clientexec staging installation and registrar sandbox. Test each advertised operation from both administrator and customer surfaces as applicable.

Confirm the setup page does not render or accept an editable API endpoint or base URL. Verify that the sandbox selector, when present, routes only to the documented sandbox constant and that disabling it routes only to the documented production constant.

When registration or activation requires nameservers, confirm the setup page shows every minimum default field. Verify native required behavior only when the target Clientexec version proves it. Otherwise confirm missing defaults fail safely before transport when no valid order nameservers exist. In an explicitly approved sandbox test, confirm a valid required set reaches the documented active state; for asynchronous activation, record the sanitized status sequence and timeout result.

For contact management, verify the user-selected empty-field policy on every editable and read-only surface in the target Clientexec version. Confirm that hidden fields cannot cause unintended deletion, required fields remain available, populated fields remain visible, and malformed registrar responses fail safely.

Confirm that Clientexec displays each sanitized `CE_Exception` through its normal error surface and displays confirmed localized success messages through its normal success surface. The registrar module must not render either surface itself. Verify the behavior against the target Clientexec version because the core dispatch and presentation implementation may not be available for source inspection.

For validation, authentication, authorization, connection, timeout, malformed-response, and registrar-rejection failures:

1. Exercise the customer-facing path and assert case-insensitively that the displayed message contains no registrar name, known alias, API hostname, endpoint, product name, or raw registrar response text.
2. Confirm the sanitized diagnostic log identifies the registrar and internal failure category without exposing secrets or personal data.
3. When a reference ID is used, confirm the client-safe message and log contain the same ID.
4. Exercise the administrator path. Permit the registrar name only when the target Clientexec version verifies that the path is exclusively admin-visible; otherwise require the same registrar-neutral message as the customer path.

For every full-list `setDNS()` implementation, run this sequence without skipping the middle save:

1. Create a DNS record and save it.
2. Save the DNS list again without refreshing the page.
3. Confirm the record remains at the registrar and was not duplicated.
4. Update that same record and confirm the existing registrar record changes.
5. Delete that same record and confirm it is removed.

Use a Clientexec-assigned temporary numeric ID in this sequence when the staging version produces one. Also cover unknown-ID reconciliation with zero, one, and multiple normalized matches, and confirm that ambiguous or otherwise invalid lists cause no registrar API mutations.

Do not use live registration, renewal, transfer, contact, nameserver, DNS, lock, or privacy operations without explicit authorization and a documented rollback/recovery plan.

## Release review

- Disable or remove diagnostic logging.
- Confirm no secrets or production-derived files are present.
- Confirm any detected real credential was treated as compromised and revoked or rotated; cleanup alone is not sufficient.
- Confirm `plugin.ini`, `$features`, and action lists match reality.
- Confirm all errors are safe for user/admin display.
- Confirm no client-facing or shared-path message identifies the registrar.
- Confirm no API endpoint or base URL is visible, editable, or accepted from arbitrary saved configuration.
- Confirm registrar/TLD nameserver activation evidence, setup behavior, missing-value failure, and active-state result are recorded when applicable.
- Confirm implemented and ignored features match the approved capability matrix.
- Confirm unsupported registrar features are documented as Clientexec limitations and remain disabled.
- Confirm no unnecessary framework, dependency, speculative extension point, or unrelated feature is present.
- Confirm the completed scope contains no duplicate logic, dead code, temporary debugging, unresolved placeholder, commented-out implementation, or unrelated refactoring.
- Confirm module implementation produced no mutation outside the resolved registrar module directory except append-only changelog entries.
- Confirm no troubleshooting command changed Clientexec core, server state, database state, or an out-of-module file, and every command followed the declaration gate.
- Confirm `DEVELOPMENT-CHANGELOG.md` began at Phase 0, was read before every resumed session and phase, contains no secrets or personal data, and records every declaration, change, validation result, blocker, correction, and next action.
- Confirm every applicable mandatory stable test ID has a latest `Passed` result, every required kit-user response is recorded, and the kit user explicitly confirmed the final checklist. Otherwise label the module `Development complete, not production-ready` or `Not ready` as applicable.
- Build the registrar release from `output/plugins/registrars/<registrar>/` only, then inspect the archive and confirm it excludes `DEVELOPMENT-CHANGELOG.md` and every other development-only artifact.
- Confirm third-party licensing and attribution.
- Document capabilities that remain intentionally unsupported.
- Test installation from a clean copy of the distributable repository.
