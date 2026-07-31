# Contributing

Thank you for helping improve the Clientexec Registrar Development Kit. Contributions should keep the kit evidence-based, safe by default, portable across supported agents, and suitable for public redistribution.

## Before opening an issue

- Search existing issues and documentation first.
- Select the provided issue form that best matches the report.
- Use a public issue for bugs, documentation corrections, and feature proposals.
- Do not use a public issue for a suspected vulnerability. Follow [SECURITY.md](SECURITY.md).
- Remove credentials, tokens, cookies, customer data, EPP codes, production domains, account identifiers, and sensitive diagnostics from every attachment and example.

## Acceptable contributions

Useful contributions include:

- Corrections supported by current official Clientexec sources.
- Runtime findings with the Clientexec and PHP versions identified.
- Improvements to fail-closed templates, validation, packaging, or agent instructions.
- Sanitized registrar integration patterns that are clearly classified as observed rather than universal framework contracts.
- Accessibility, clarity, portability, and typo fixes.

Do not submit proprietary Clientexec core files, unlicensed registrar modules, copied documentation, generated caches, vendor directories, or private evidence. Link to upstream material when redistribution rights are unclear.

## Contribution workflow

1. Create a focused branch from the current default branch.
2. Make the smallest change that completely addresses the issue.
3. Preserve the evidence order and distinguish documented contracts from runtime observations and registrar-specific patterns.
4. Update validators and the release allowlist when adding a required distributable file.
5. Run the validation commands below.
6. Open a pull request, complete its template, and explain the source, compatibility impact, security impact, and validation performed.

Do not combine unrelated changes in one pull request.

## Development requirements

- Keep `SKILL.md` concise, imperative, and below 500 lines.
- Preserve PHP 7.4 syntax compatibility in the reusable validator and registrar template unless the documented minimum changes.
- Keep unfinished or unsupported registrar operations fail-closed.
- Keep feature flags disabled until their behavior is implemented and tested.
- Keep PowerShell scripts compatible with Windows PowerShell 5.1 and PowerShell 7 where practical.
- Do not add a dependency when the same result can be implemented safely with the existing runtime.
- Do not weaken credential scanning, path containment, release allowlists, or read-only CI permissions without a documented security reason.

## Source provenance and licensing

For a framework or registrar behavior claim, include:

- Source URL or sanitized local evidence path.
- Source authority: official documentation, official sample, installed runtime, maintained module, or registrar-specific observation.
- Clientexec, PHP, and registrar API version when known.
- Retrieval or observation date.
- Redistribution status for any included material.

By submitting a contribution, you confirm that you have the right to provide it and agree that it may be distributed under this repository's [MIT License](LICENSE). Third-party material remains under its own license.

## AI-assisted contributions

AI assistance is welcome, but the contributor remains responsible for every submitted line. Review generated changes, verify cited behavior against the source, remove fabricated claims, and never provide an agent with live credentials or private customer data.

State in the pull request when AI materially generated or transformed the contribution. Do not list an AI system as the copyright owner or attest that it performed validation that you did not run.

## Validation

Run the cross-platform validator with PHP 7.4 or newer:

```bash
php -n scripts/validate-kit.php
```

On Windows, also run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/validate-kit.ps1 -RequirePhp
```

For changes affecting distributable files or packaging, build a test release:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File ./scripts/package-release.ps1 -Version contribution-test -RequirePhp
```

Remove the local test release afterward. Do not commit `dist/`.

All GitHub Actions `Validate Kit` jobs must pass before merge. If a required environment or registrar sandbox is unavailable, state exactly what was not tested.

## Pull request checklist

- [ ] The change has one clear purpose.
- [ ] Claims include adequate provenance.
- [ ] No credentials, personal data, proprietary core files, or unlicensed third-party code are included.
- [ ] Required documentation, validators, and release allowlists are updated.
- [ ] Local validation passes, or unavailable checks are disclosed.
- [ ] New capabilities remain disabled until fully implemented and tested.
- [ ] Security-sensitive changes explain their threat model and failure behavior.
