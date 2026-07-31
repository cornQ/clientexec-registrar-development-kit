# Clientexec Registrar Development Kit

An evidence-based development kit for building and reviewing Clientexec domain registrar modules with AI coding agents.

The kit combines the official Clientexec guide and sample with runtime-discovered contracts and carefully classified production-module patterns. Generated modules start with capabilities disabled and unfinished operations fail safely.

## What This Kit Does

The kit gives an AI coding agent a controlled workflow to:

1. Collect and classify registrar API sources.
2. Create a private workspace for one registrar.
3. Generate a fail-closed Clientexec registrar module from the included template.
4. Validate method coverage, metadata, syntax, security, and unsupported behavior.

Registrar documentation belongs under `workspace/<registrar>/inputs/`. The root `references/` directory contains reusable Clientexec guidance for the skill and should not be used for registrar-specific files.

## Quick Navigation

- [Using Instructions](#using-instructions)
- [How to Start](#3-how-to-start)
- [Agent-specific Setup Instructions](#agent-specific-setup-instructions)
- [Directory Structure](#directory-structure)
- [Registrar Source Workspace](#registrar-source-workspace)
- [Validation](#validation)
- [Continuous Integration](#continuous-integration)
- [Release Packaging](#release-packaging)
- [Contributing and Security](#contributing-and-security)

## Supported AI agents

The kit follows the open `SKILL.md` Agent Skills format and can be used with:

- OpenAI Codex
- Anthropic Claude Code
- Google Gemini CLI
- GitHub Copilot
- Cursor
- Kimi Code CLI
- Xiaomi MiMo Code
- Other coding agents that can read repository files and follow Markdown instructions

Native discovery paths and invocation syntax vary by agent. Agents without native Agent Skills support can still use the kit by reading `SKILL.md` directly.

## Using Instructions

### 1. Get the kit

Clone or download this repository, then open its root directory in your AI coding agent.

```bash
git clone https://github.com/cornQ/clientexec-registrar-development-kit.git
cd clientexec-registrar-development-kit
```

Review `SKILL.md` and all executable scripts before allowing an agent to run commands.

### 2. Provide registrar sources

For a new module, provide:

- The registrar name
- Official API documentation, a documentation URL, or sanitized source files
- A plain-language description of what you want the module to do

You may put registrar files in the copied workspace `inputs/` folders before starting. The agent must first confirm that adequate sources exist there or ask you for them.

The agent should derive the plugin ID, authentication method, supported capabilities, Clientexec methods, endpoints, sandbox availability, and supported TLDs from verified sources. It should ask you only for missing evidence, business choices, or environment details that cannot be determined safely.

Never provide live API secrets, passwords, tokens, cookies, EPP codes, or customer data.

#### Module-development safety boundary

During registrar module implementation, the agent may change only `workspace/<registrar>/output/plugins/registrars/<registrar>/` and append to `workspace/<registrar>/output/DEVELOPMENT-CHANGELOG.md`. It must never edit or recommend editing Clientexec core, server configuration, databases, another plugin, or any other out-of-module file. If the module API cannot support a capability, the agent reports it as blocked or unsupported instead of proposing a core patch.

Before any troubleshooting command, the agent must identify the affected module function and missing information, declare that the inspection is read-only and will not change core or server state, and wait for your acknowledgement. Commands that can write, redirect to a file, install, update, migrate, clear caches, restart services, mutate a database or API, or have uncertain side effects are dangerous and must not be provided or run.

The development changelog is append-only project memory. Append its initial Phase 0 entry before the first module-code change, read it before every resumed session and phase, and append all decisions, troubleshooting, changes, validation, blockers, corrections, and next actions.

Every new module plan includes a final production acceptance checklist with stable test IDs. The latest appended result for every applicable mandatory ID must be `Passed`, every kit-user-owned test needs your sanitized response, and you must explicitly confirm the final checklist. Otherwise the module is `Development complete, not production-ready` or `Not ready`; implementation completion alone is never production evidence.

API endpoints and base URLs are always hidden and non-editable. Modules route only to verified private production or sandbox constants. When registrar or TLD evidence requires nameservers for registration or activation, the plan must record that contract and the setup page must include the verified minimum default nameserver fields. Native required-field behavior is used only when the target Clientexec version proves it; otherwise the module fails safely before transport when required values are unavailable.

### 3. How to Start

Choose one starting scope:

- **Audit only:** Ask the agent to inspect the registrar sources and prepare a plan without changing code.
- **One capability:** Implement and validate one capability before moving to the next. This is recommended for a new or unfamiliar registrar API.
- **Full module:** Describe the complete outcome you want. The agent will determine documented capabilities and ask about any business decisions or missing evidence.

#### How phased development works

Before changing plugin code, the agent will:

1. Verify the user requirements and all supplied documentation or other source material.
2. Create the registrar workspace and record the sources.
3. Append the Phase 0 entry to `output/DEVELOPMENT-CHANGELOG.md` before editing module code.
4. Prepare a dependency-aware, phase-by-phase implementation plan with the tailored final production acceptance checklist.
5. Ask whether to stop for review after each phase or continue automatically through all approved phases.
6. Build and validate the registrar setup page, configuration path, authenticated API client, and complete fail-closed function skeleton before feature implementation.
7. Keep all required methods and verified capability callbacks present from the initial scaffold. Unfinished operations throw a clear not-implemented exception while their feature flags and actions remain disabled.
8. Implement and validate one phase at a time. Automatic execution still uses separate phases and validation gates.

Before moving to a dependent phase, the agent asks for the relevant Clientexec staging or registrar sandbox test result. The user may explicitly skip a test, but skipped work remains unverified, affected features stay disabled, and the module cannot be described as complete or tested.

Failed prerequisite tests block dependent phases. For example, authenticated domain availability must work before registration because it verifies configuration, authentication, transport, response parsing, error handling, and logging redaction. If the availability test is skipped, the agent may continue scaffolding later code but must keep it unverified and disabled.

For incremental development, start with the API client and domain availability through `checkDomain()`. Availability is usually the safest end-to-end request to validate authentication, request signing, transport, response parsing, error handling, and logging redaction.

Continue in this suggested order:

1. Configuration and authenticated API client
2. Domain availability: `checkDomain()`
3. Registration: `registerDomain()`
4. Nameservers: `getNameServers()` and `setNameServers()`
5. Contacts: `getContactInformation()` and `setContactInformation()`
6. General information and autorenew: `getGeneralInfo()` and `setAutorenew()`
7. Registrar lock: `getRegistrarLock()` and `setRegistrarLock()`
8. Transfer key or EPP code: `sendTransferKey()` and registrar-supported retrieval
9. Renewal, transfer, DNS, privacy, imports, pricing, suggestions, and extended attributes as required

Do not enable a capability until its implementation and negative paths have been validated.

#### Beginner prompt

This short prompt is enough to start:

```text
Use the clientexec-registrar-development-kit skill in this repository.

Registrar: <registrar name>
API documentation: <documentation URL, attached files, or "already in inputs">
What I want: <describe the module or feature in plain language>

Before doing anything else, confirm that adequate registrar sources exist in
the workspace inputs. If they do not, ask me for the missing documentation.
Derive technical details from verified sources and ask me only for information
that cannot be determined safely.

Prepare a phase-by-phase plan before changing plugin code. Ask whether I want
to review each phase or continue automatically through the approved phases.
Request the relevant test result before moving to a dependent phase; allow me
to skip while clearly marking the affected work unverified and disabled.

Before implementing registration or transfer, verify whether the requested TLDs
require additional fields. Ask me about the intended TLDs or missing official
requirements only when the supplied documentation does not answer this.

Keep unsupported or unverified features disabled. Do not perform live registrar
operations or ask me to place credentials in the prompt.
```

#### Start with an audit only

Use this when the sources should be reviewed before any implementation:

```text
Use the clientexec-registrar-development-kit skill in this repository.

Registrar: <Registrar Name>
API documentation: <documentation URL, attached files, or "already in inputs">

Confirm that adequate sources exist before proceeding. Audit them, create the
registrar workspace and source manifest, derive the technical requirements,
identify supported capabilities, TLD-specific additional fields,
contradictions, missing information, and security concerns, then propose an
implementation plan.

Do not change or generate plugin code yet.
```

#### Start with one capability

Use this for safer incremental development:

```text
Use the clientexec-registrar-development-kit skill in this repository.

Registrar: <Registrar Name>
API documentation: <documentation URL, attached files, or "already in inputs">
What I want: <describe one feature in plain language>

Verify the sources first. Derive the plugin ID, authentication, Clientexec
methods, supported TLDs, and technical requirements from them. Ask me only for
missing information that cannot be determined safely.

Create or update the registrar workspace, record all sources, and implement only
the requested feature. If it involves registration or transfer, verify required
TLD-specific additional fields before enabling it. Keep every other unfinished
or unverified capability disabled. Add safe error handling and redacted
diagnostics, validate syntax and method coverage, and report assumptions and
tests that still require the sandbox.

Do not perform live registration, transfer, renewal, contact, nameserver, or DNS
operations without my explicit approval.
```

Recommended first request:

```text
What I want: Check whether a domain is available.
```

#### Start with the full feature set

Use this only when the registrar requirements and API documentation are sufficiently complete:

```text
Use the clientexec-registrar-development-kit skill in this repository.

Registrar: <Registrar Name>
API documentation: <documentation URL, attached files, or "already in inputs">
What I want: A complete registrar module based on the verified API features.

Confirm that adequate sources exist first. Create the registrar workspace and
source manifest, derive the plugin identity, authentication, supported
capabilities, Clientexec method mapping, supported TLDs, and environment
requirements, report missing or contradictory information, and then build the
module in reviewable capability phases.

Build and validate the setup page and complete fail-closed function skeleton
before implementing features. Implement all eleven required abstract
RegistrarPlugin methods and every verified callback required by the approved
scope. Enable only completed capabilities, fail closed for unsupported
operations, verify TLD-specific additional fields before enabling registration
or transfer, preserve exact registrar option codes, redact sensitive
diagnostics, and validate each phase.

Do not perform live registrar operations without my explicit approval. Report
all assumptions, disabled capabilities, and sandbox tests still required. Ask
for each prerequisite test result before moving to a dependent phase and record
any user-skipped test as unverified.
```

### 4. Validate in safe environments

Validate the generated module with:

- The included validation and audit scripts
- Every PHP version supported by the target installation
- A Clientexec staging installation
- The registrar's sandbox or test account

Do not test registration, transfer, renewal, contact changes, nameserver changes, or DNS changes against a live account without explicit authorization.

## Agent-specific Setup Instructions

Install the complete repository—not only `SKILL.md`—because the workflow depends on bundled references, scripts, and templates. Replace `<skill-name>` below with `clientexec-registrar-development-kit`.

### OpenAI Codex

- Project scope: `.agents/skills/<skill-name>/`
- Personal scope: `~/.agents/skills/<skill-name>/`
- Invoke explicitly with `$clientexec-registrar-development-kit`, or describe a matching Clientexec registrar task.
- Codex may detect changes automatically; restart it if an updated skill does not appear.

See the [official Codex skills documentation](https://developers.openai.com/codex/skills/).

### Anthropic Claude Code

- Project scope: `.claude/skills/<skill-name>/`
- Personal scope: `~/.claude/skills/<skill-name>/`
- Invoke with `/clientexec-registrar-development-kit`, or ask Claude to build or audit a Clientexec registrar module.

See the [official Claude Code skills documentation](https://code.claude.com/docs/en/skills).

### Google Gemini CLI

- Project scope: `.gemini/skills/<skill-name>/` or `.agents/skills/<skill-name>/`
- Personal scope: `~/.gemini/skills/<skill-name>/` or `~/.agents/skills/<skill-name>/`
- Run `/skills list` to verify discovery and `/skills reload` after changes.
- Gemini can select the skill automatically when the request matches its description.

Gemini CLI also supports `gemini skills install <source>` and `gemini skills link <path>`. See the [official Gemini CLI skills documentation](https://geminicli.com/docs/cli/tutorials/skills-getting-started/).

### GitHub Copilot

- Project scope: `.github/skills/<skill-name>/`, `.agents/skills/<skill-name>/`, or `.claude/skills/<skill-name>/`
- Personal scope: `~/.copilot/skills/<skill-name>/` or `~/.agents/skills/<skill-name>/`
- In Copilot CLI, invoke the skill explicitly or describe a matching registrar task.

Agent Skills work with Copilot coding agent surfaces documented by GitHub. See the [official GitHub Copilot skills documentation](https://docs.github.com/en/copilot/how-tos/copilot-on-github/customize-copilot/customize-cloud-agent/add-skills).

### Cursor

- Project scope: `.cursor/skills/<skill-name>/` or `.agents/skills/<skill-name>/`
- Personal scope: `~/.cursor/skills/<skill-name>/` or `~/.agents/skills/<skill-name>/`
- Use the slash-command menu to invoke the skill, or describe a matching registrar task.

Use a current Cursor release with Agent Skills support. See the [official Cursor skills documentation](https://cursor.com/docs/skills).

### Kimi Code CLI

- Project scope: `.kimi-code/skills/<skill-name>/` or `.agents/skills/<skill-name>/`
- Personal scope: `~/.kimi-code/skills/<skill-name>/` or `~/.agents/skills/<skill-name>/`
- Invoke with `/skill:clientexec-registrar-development-kit`, or allow Kimi to select it automatically.

See the [official Kimi Code CLI skills documentation](https://www.kimi.com/code/docs/en/kimi-code-cli/customization/skills.html).

### Xiaomi MiMo Code

- Project scope: `.mimocode/skills/<skill-name>/`
- Start MiMo Code from the project root.
- Invoke with `/clientexec-registrar-development-kit`, or describe a matching registrar task.

See the [official Xiaomi MiMo Code repository](https://github.com/XiaomiMiMo/MiMo-Code).

### Other AI coding agents

If the agent does not discover `SKILL.md` automatically:

1. Open the cloned kit as the working directory.
2. Tell the agent to read the root `SKILL.md` completely.
3. Tell it to resolve referenced paths relative to this repository.
4. Use the starter prompt above.
5. Require it to run the validation checklist before completion.

Native support depends on the agent version and host application. Reading `SKILL.md` manually remains the portable fallback.

## Directory Structure

This is the actual distributable kit structure. Comments explain the purpose of each path.

```text
clientexec-registrar-development-kit/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug_report.yml                 # Collects reproducible, sanitized bug reports
│   │   ├── feature_request.yml            # Collects scoped, evidence-backed proposals
│   │   └── config.yml                     # Disables blank contributor issues
│   ├── pull_request_template.md           # Standard contribution and validation checklist
│   └── workflows/
│       └── validate.yml                    # Runs validation and packaging checks in GitHub
├── CONTRIBUTING.md                        # Contribution, provenance, and review requirements
├── SECURITY.md                            # Private vulnerability-reporting policy
├── SKILL.md                              # Main AI-agent workflow and safety rules
├── agents/
│   └── openai.yaml                       # Optional Codex/ChatGPT display metadata
├── assets/
│   ├── registrar-template/               # Fail-closed registrar module scaffold
│   │   └── plugins/registrars/example/
│   │       ├── PluginExample.php         # PHP class template with required methods
│   │       ├── index.html                # Prevents directory listing
│   │       └── resource/
│   │           └── plugin.ini            # Clientexec plugin metadata and feature flags
│   └── registrar-workspace-template/     # Copy once for each registrar project
│       ├── source-manifest.yaml           # Records source provenance and requested features
│       ├── source-manifest.schema.json    # Validates manifest fields and value types
│       ├── inputs/
│       │   ├── api-docs/                 # Registrar PDF, HTML, Markdown, or screenshots
│       │   ├── specs/                    # OpenAPI, Swagger, WSDL, JSON, or XML schemas
│       │   ├── postman/                  # Sanitized Postman collections and examples
│       │   ├── examples/
│       │   │   ├── requests/             # Sanitized registrar request fixtures
│       │   │   └── responses/            # Sanitized registrar response fixtures
│       │   └── notes/
│       │       └── requirements.md        # Capabilities, constraints, and open questions
│       └── output/
│           ├── DEVELOPMENT-CHANGELOG.md    # Append-only development memory from Phase 0
│           └── plugins/registrars/         # Generated registrar modules
├── references/                            # Reusable Clientexec guidance for the AI agent
│   ├── official-sources.md                # Official source links and evidence priority
│   ├── source-intake.md                   # Source storage, provenance, and sanitization rules
│   ├── runtime-api.md                     # Runtime-verified RegistrarPlugin API surface
│   ├── method-contracts.md                # Method inputs, outputs, and conventions
│   ├── features-and-actions.md            # Feature flags, actions, and plugin.ini guidance
│   ├── extra-attributes.md                # TLD-specific field and option-code guidance
│   └── security-and-testing.md            # Logging, secret handling, and test checklist
├── scripts/                               # Audit, validation, and release utilities
│   ├── validate-kit.php                   # Cross-platform kit and PHP syntax validator
│   ├── validate-kit.ps1                   # PowerShell validation alternative
│   ├── validate-registrar-output.php      # Read-only generated-module production gate
│   ├── audit-registrar-methods.ps1        # Inventories methods in registrar PHP modules
│   ├── inspect-clientexec-runtime.php     # Reflects an installed Clientexec runtime
│   └── package-release.ps1                # Builds an audited ZIP and SHA-256 checksum
├── workspace/                             # Created locally per registrar; ignored by Git
├── .gitignore                             # Excludes private evidence, workspaces, and secrets
├── LICENSE                                # MIT license for independently authored kit files
└── README.md                              # User setup and usage guide
```

### Folder and File Guide

| Path | Purpose | What the user or agent should do |
|---|---|---|
| `.github/ISSUE_TEMPLATE/` | Provides structured forms for bugs and evidence-backed proposals. | Use the matching form and remove all private data before submission. |
| `.github/pull_request_template.md` | Collects scope, provenance, compatibility, validation, security, and AI-assistance details. | Complete every applicable section and checklist item. |
| `.github/workflows/validate.yml` | Runs the automated GitHub validation matrix and packaging smoke test. | Keep it enabled and require its checks before merging changes. |
| `CONTRIBUTING.md` | Defines contribution scope, provenance, licensing, AI-use, and validation requirements. | Read before opening an issue or pull request. |
| `SECURITY.md` | Defines supported versions and private vulnerability reporting. | Never place vulnerability details or secrets in a public issue. |
| `SKILL.md` | Defines the complete registrar-development workflow. | Read this first. Follow its evidence, safety, implementation, and validation rules. |
| `agents/` | Stores optional host-specific presentation metadata. | Usually leave unchanged. It does not contain registrar API logic. |
| `assets/registrar-template/` | Provides the PHP registrar scaffold. | Copy the `example/` module into the registrar workspace output, then rename its directory, file, and class. |
| `assets/registrar-workspace-template/` | Provides a clean input/output workspace. | Copy it to `workspace/<registrar>/` before collecting sources or generating code. |
| `assets/registrar-workspace-template/source-manifest.schema.json` | Defines required manifest fields, types, allowed capability names, and source metadata. | Keep it beside the copied manifest and resolve schema errors before implementation. |
| `references/` | Holds reusable Clientexec contracts and guidance. | Read the relevant file while implementing. Do not put registrar-specific API documents here. |
| `scripts/` | Provides deterministic audit, validation, and release utilities. | Review scripts before execution, run them from the kit root, and preserve their output as validation evidence. |
| `workspace/` | Holds private registrar sources and generated work. | Create one subdirectory per registrar. Keep it sanitized and do not publish it. |
| `.gitignore` | Prevents private evidence and generated work from being committed. | Keep the evidence and workspace exclusions unless intentionally publishing sanitized material elsewhere. |
| `README.md` | Explains installation, prompts, paths, and validation. | Use this as the human-facing starting point. |
| `LICENSE` | States the license for this independently authored kit. | Check third-party files and dependencies separately before redistribution. |

## Registrar source workspace

Create this directory by copying `assets/registrar-workspace-template/`. Keep registrar-specific material here, outside the reusable skill references:

```text
workspace/<registrar>/
├── source-manifest.yaml                   # Index of every source used
├── source-manifest.schema.json            # Machine-readable manifest validation rules
├── inputs/                                # Supplied registrar material; never generated code
│   ├── api-docs/                          # Human-readable API documentation
│   ├── specs/                             # Machine-readable API/protocol specifications
│   ├── postman/                           # Sanitized Postman files
│   ├── examples/
│   │   ├── requests/                      # Sanitized request examples
│   │   └── responses/                     # Sanitized response examples
│   └── notes/
│       └── requirements.md                # Scope, decisions, constraints, and unknowns
└── output/
    ├── DEVELOPMENT-CHANGELOG.md            # Append-only development memory from Phase 0
    └── plugins/registrars/<registrar>/    # Finished Clientexec registrar module
```

### Where to Store Registrar Information

| Information supplied by the user | Store it in | Instruction |
|---|---|---|
| Public documentation URL, Gist, repository, SDK URL, or support-post URL | `source-manifest.yaml` | Record the URL, authority, retrieval date, scope, and licensing status. |
| PDF, HTML export, Markdown guide, text file, or screenshot | `inputs/api-docs/` | Sanitize private data and record the local path in the manifest. |
| OpenAPI, Swagger, JSON Schema, WSDL, EPP/XML schema | `inputs/specs/` | Preserve the original structure; do not rewrite the supplied specification in place. |
| Postman collection or environment example | `inputs/postman/` | Remove secrets, tokens, cookies, account IDs, and live environment values first. |
| Example API request | `inputs/examples/requests/` | Replace production domains, contact data, and credentials with obvious placeholders. |
| Example API response | `inputs/examples/responses/` | Remove customer data, auth codes, internal identifiers, and sensitive diagnostics. |
| Required features, TLD scope, support response, or implementation decision | `inputs/notes/requirements.md` | Record confirmed facts separately from assumptions and open questions. |
| Development scope, decisions, troubleshooting, changes, validation, blockers, and next actions | `output/DEVELOPMENT-CHANGELOG.md` | Append Phase 0 before code changes, read it before every session and phase, and append without rewriting history. |
| Generated Clientexec registrar module | `output/plugins/registrars/<registrar>/` | Keep generated code separate from all supplied source material. |

Never store credentials, live Postman environments, EPP codes, customer data, or production-derived caches. The entire `workspace/` directory is Git-ignored by default.

The manifest includes a YAML language-server directive that loads the adjacent JSON Schema. Compatible editors can therefore flag missing required fields, unsupported properties, invalid registrar IDs, malformed dates, incorrect PHP-version values, and non-boolean capability flags.

Because the workspace is ignored, copy the finished `output/plugins/registrars/<registrar>/` module into its own registrar-module repository before committing or publishing it.

## Evidence policy

Framework claims are classified by source:

1. Official Clientexec documentation
2. Official sample registrar
3. Installed Clientexec runtime and schema
4. Maintained Clientexec registrar modules
5. Registrar-specific production patterns

The development-only evidence folders are ignored by Git and are not part of the distributable kit:

- `sample-registrar/`
- `clientexec-core-files/`
- `real-sample/`

Do not publish those folders without separately verifying ownership and redistribution rights.

## Requirements

Use a PHP version supported by the target Clientexec installation. Check the current [Clientexec system requirements](https://docs.clientexec.com/en/article/system-requirements-yl750s/) and the [official registrar development guide](https://docs.clientexec.com/en/article/domain-registrar-plugin-development-guide-i9wpwd/).

The template intentionally performs no successful registrar operation until implemented.

## Validation

Cross-platform validation on Windows, Linux, or macOS:

```bash
php -n scripts/validate-kit.php
```

Validate a completed generated module and its latest checklist state before production use:

```bash
php -n scripts/validate-registrar-output.php --module workspace/<registrar>/output/plugins/registrars/<registrar> --changelog workspace/<registrar>/output/DEVELOPMENT-CHANGELOG.md
```

The generated-module validator is read-only. It rejects editable or arbitrary endpoint routing, incomplete conditional nameserver evidence, missing mandatory test results, missing kit-user confirmation, and any non-production-ready final state.

The validator requires PHP 7.4 or newer. It uses the active PHP binary to lint the kit's PHP files with `-n`, so optional `php.ini` extensions do not interfere.

PowerShell alternative:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/validate-kit.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/audit-registrar-methods.ps1 -Path ./assets/registrar-template
```

Use `-RequirePhp` with `validate-kit.ps1` in CI to fail when PHP syntax lint cannot run.

To inventory an installed Clientexec runtime:

```bash
/path/to/php scripts/inspect-clientexec-runtime.php /absolute/path/to/clientexec
```

Also run PHP syntax checks using every PHP version supported by the target Clientexec installation:

```bash
php -l plugins/registrars/example/PluginExample.php
```

## Continuous Integration

The included GitHub Actions workflow runs automatically for pushes and pull requests, and can also be started manually. It:

- Runs the cross-platform validator on PHP 7.4 and every PHP 8.x release through PHP 8.5.
- Runs the PowerShell validator with mandatory PHP lint on Windows.
- Builds and verifies a test release without uploading or publishing it.
- Uses a read-only `GITHUB_TOKEN` and does not receive or require project secrets.

Before merging a change, require all `Validate Kit` jobs to pass in the repository branch-protection settings. The workflow uses [GitHub’s checkout action](https://github.com/actions/checkout) and [Setup PHP](https://github.com/shivammathur/setup-php).

## Release Packaging

Build a public release from the repository root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/package-release.ps1 -Version 1.0.0
```

The packager runs the PowerShell validator first, copies only an explicit allowlist of public kit files, rejects symbolic links and credential-like content, and excludes development evidence and private workspaces. It writes:

- `dist/clientexec-registrar-development-kit-1.0.0.zip`
- `dist/clientexec-registrar-development-kit-1.0.0.zip.sha256`
- `RELEASE-MANIFEST.sha256` inside the ZIP, containing a SHA-256 hash for every packaged source file

Existing release artifacts are never overwritten. Use `-RequirePhp` when packaging in CI to require the PHP syntax checks. The output directory must remain inside the repository root.

## Contributing and Security

Read [CONTRIBUTING.md](CONTRIBUTING.md) before proposing a change. Contributions must identify source provenance, exclude private or unlicensed evidence, preserve fail-closed behavior, and pass the validation workflow.

Report suspected vulnerabilities according to [SECURITY.md](SECURITY.md). Do not include vulnerability details, credentials, customer data, or production registrar information in a public issue.

GitHub presents structured forms for bug reports and feature or evidence proposals, and automatically inserts the pull-request checklist. Blank public issues are disabled for contributors so reports begin with the minimum information needed for review.

## Independence and license

This community project is not affiliated with or endorsed by Clientexec. Clientexec and registrar names belong to their respective owners.

The independently authored kit is available under the MIT License. Third-party API clients and dependencies retain their own licenses.
