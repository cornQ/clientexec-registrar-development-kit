# Registrar Source Intake

## Purpose

Keep registrar-specific API material separate from the reusable Clientexec skill. Create one ignored workspace per registrar:

```text
workspace/
└── example/
    ├── source-manifest.yaml
    ├── source-manifest.schema.json
    ├── inputs/
    │   ├── api-docs/
    │   ├── specs/
    │   ├── postman/
    │   ├── examples/
    │   │   ├── requests/
    │   │   └── responses/
    │   └── notes/
    │       └── requirements.md
    └── output/
        ├── DEVELOPMENT-CHANGELOG.md
        └── plugins/registrars/example/
```

Create it from `assets/registrar-workspace-template/`. Replace `example` with the lowercase alphanumeric plugin identifier, then update the copied `source-manifest.yaml` and `inputs/notes/requirements.md`. Keep `source-manifest.schema.json` beside the manifest so compatible editors can report missing fields, invalid values, and unsupported properties.

## Route each source

| Source | Location |
|---|---|
| PDF, HTML export, Markdown documentation, screenshots | `inputs/api-docs/` |
| OpenAPI, Swagger, JSON Schema, WSDL, EPP/XML schema | `inputs/specs/` |
| Sanitized Postman collection or environment example | `inputs/postman/` |
| Sanitized request/response fixtures | `inputs/examples/requests/` and `inputs/examples/responses/` |
| Requirements, support answers, decisions, unknowns | `inputs/notes/` |
| Public documentation, Gist, repository, article, or SDK URL | `source-manifest.yaml` |
| Durable scope, decision, troubleshooting, validation, blocker, and next-step memory | `output/DEVELOPMENT-CHANGELOG.md` |
| Generated registrar module | `output/plugins/registrars/<registrar>/` |

Do not place registrar-specific inputs in the skill's `references/` directory.

## Maintain the source manifest

The first line of `source-manifest.yaml` points YAML language servers to the adjacent JSON Schema. Resolve schema errors before implementation. Do not remove required fields; use `unknown`, `[]`, or `false` only where the schema permits them.

Record every source with:

- Stable identifier
- Source type
- URL or workspace-relative path
- Authority such as official, registrar support, community, or user-provided
- Retrieval date
- License or redistribution status when known
- Short scope and limitations

For a public URL, record the URL even when a local snapshot exists. Keep a local snapshot only when it improves reproducibility and its storage is permitted.

Use this registrar-source priority:

1. Current official registrar API documentation or protocol specification.
2. Current official OpenAPI/WSDL/schema or maintained SDK.
3. Official Postman collection and registrar support response.
4. Sanitized successful sandbox evidence.
5. Registrar-maintained examples.
6. Community Gists, articles, and forum posts.

Treat lower-priority sources as leads to verify. Record contradictions rather than silently choosing one.

Before comparing capabilities or planning code, read all relevant official registrar documentation, specifications, maintained SDK material, support clarifications, and supplied sanitized evidence. Do not plan from an endpoint list, a single example, or a production registrar module when more authoritative relevant sources are available. Record which source establishes each available registrar feature and any version, environment, account-tier, or TLD restriction.

## Sanitize before storage

Remove or replace:

- API keys, secrets, passwords, bearer tokens, signatures, and cookies
- Postman `currentValue` secrets and private environments
- EPP/auth codes
- Customer or registrant personal data
- Production domains, account IDs, IP allowlists, and internal hostnames
- Live database configuration

Use obvious placeholders such as `REDACTED`, `example.test`, and documentation-reserved IP addresses.

If intake reveals a likely real credential, stop using the source, do not reproduce the value, and immediately warn the user to revoke or rotate it. Treat the credential as compromised even when it was uncommitted or the exposed file can be removed. Resume processing only with a sanitized copy and a replacement credential when testing requires one.

Do not copy an entire private or copyrighted source into a public repository. Retain a URL and notes when redistribution permission is unclear.

## Handle uploads and attachments

When the user uploads a file:

1. Read it from the provided attachment location.
2. Classify its type and authority.
3. Check for secrets and personal data.
4. Ask before preserving sensitive or private material beyond the active task.
5. Copy a sanitized version into the appropriate `inputs/` directory when reproducibility is needed.
6. Add it to `source-manifest.yaml`.

When the user supplies only a URL, record it in the manifest. Download or snapshot it only when access, licensing, and task requirements justify doing so.

## Keep outputs separate

Write generated code only under `output/plugins/registrars/<registrar>/`. During module implementation, the exact file `output/DEVELOPMENT-CHANGELOG.md` is the only allowed write outside that module directory. Never edit a Postman collection, OpenAPI document, or supplied reference in place unless the user explicitly requests that change.

Source-intake write permission is task-specific. It permits only the explicitly requested workspace initialization, manifest maintenance, and sanitized input preservation described in this reference. Once module implementation begins, treat `source-manifest.yaml`, its schema, and all of `inputs/` as read-only; write module artifacts only under `output/plugins/registrars/<registrar>/` and append only to the development changelog.

Do not update the skill, shared references, templates, scripts, repository configuration, or another registrar workspace because intake or implementation reveals a useful improvement. Report the improvement for a separate user-authorized skill-maintenance task.

## Maintain the development changelog

Use `output/DEVELOPMENT-CHANGELOG.md` as durable implementation memory. Keep it beside `plugins/`, not inside `output/plugins/registrars/<registrar>/`, so the development record remains separate from the distributable module.

The workspace template contains the changelog. Append a completed Phase 0 entry immediately after workspace initialization and before the first module-code change; do not replace the template text. For an existing workspace without a changelog, create this exact file as the first implementation write and include the Phase 0 entry. At the start of every resumed session and every phase, read the entire changelog before planning, troubleshooting, editing, or validating.

Append concise entries when a phase starts, before and after troubleshooting, after a module change, after local validation, and after kit-user feedback. Record:

- Date and phase
- Status
- Approved scope and active function
- Evidence, assumptions, decisions, and blockers
- Nameserver submission/activation requirement, count, parameter shape, setup enforcement, and active-state confirmation
- Verified production/sandbox endpoint sources and hidden-field decision
- Mandatory test IDs, latest test results, kit-user responses, final confirmation, and production-readiness state
- Implemented behavior
- Changed paths relative to `output/`
- The troubleshooting declaration, read-only command purpose, and sanitized result
- Decisions and unsupported Clientexec limitations
- Completed, skipped, and pending validation
- Open items and next action

Treat the changelog as append-only memory. Do not record credentials, headers, contact information, production domains, auth codes, or raw request and response content. Do not copy source code into the changelog. Preserve prior entries; when a decision changes, append a correction instead of silently rewriting history.

When publishing the generated registrar module, copy `output/plugins/registrars/<registrar>/` into a dedicated registrar-module repository. Do not publish the private workspace. Include only independently authored documentation and licensed dependencies.

## Intake checklist

- [ ] Workspace identifier matches the intended plugin directory.
- [ ] All sources appear in `source-manifest.yaml`.
- [ ] Official sources are distinguished from community material.
- [ ] All relevant official sources were reviewed before capability comparison.
- [ ] Each registrar feature has source and version or scope evidence.
- [ ] Attachments and fixtures are sanitized.
- [ ] No live Postman environment or credential file is present.
- [ ] Licensing or redistribution uncertainty is recorded.
- [ ] Requested capabilities and open questions are documented.
- [ ] Generated output is separate from inputs.
- [ ] Source-intake permission did not carry into module implementation.
- [ ] The completed Phase 0 changelog entry was appended before any module-code change.
- [ ] Every resumed session and phase begins by reading the development changelog.
- [ ] The development changelog is outside the distributable plugin directory.
