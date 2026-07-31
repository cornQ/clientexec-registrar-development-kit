# Security Policy

## Supported versions

Security fixes are provided for the current default branch and the latest published release.

| Version | Supported |
|---|---|
| Current default branch | Yes |
| Latest published release | Yes |
| Older releases | No |

Before the first tagged release, only the current default branch is supported.

## Reporting a vulnerability

Do not disclose a suspected vulnerability in a public issue, discussion, pull request, Gist, or registrar workspace.

Use GitHub private vulnerability reporting:

1. Open this repository's **Security** page.
2. Select **Advisories**.
3. Select **Report a vulnerability**.
4. Submit the report without live credentials, customer data, or production-derived secrets.

Repository administrators should enable private vulnerability reporting before publishing this project. If **Report a vulnerability** is unavailable, open a public issue asking the maintainers for a private security contact, but include no vulnerability details.

Include the following where possible:

- Affected version, tag, or commit.
- A concise description of the impact and attack prerequisites.
- Sanitized reproduction steps or proof of concept.
- Affected files and platforms.
- Suggested remediation or mitigations.
- Whether the issue is already public or under active exploitation.

Use placeholders for registrar credentials, EPP codes, contacts, domains, account identifiers, API responses, and Clientexec installation paths. Revoke any real secret accidentally included in a report.

## Security scope

Examples of in-scope reports include:

- Release packaging that can escape path containment or include excluded private files.
- Credential exposure through templates, scripts, logs, fixtures, or generated output.
- Unsafe defaults that can report an unfinished registrar operation as successful.
- Command, path, archive, YAML, PHP, or Markdown handling that enables code execution or data disclosure.
- GitHub Actions behavior that exposes secrets or grants unnecessary write access.
- Agent instructions that can cause unauthorized live registrar operations.

The following are generally outside this project's security scope:

- Vulnerabilities in Clientexec, a registrar API, an AI agent, or another upstream dependency that are not caused by this kit.
- Registrar account compromise unrelated to generated code.
- Unsupported Clientexec or PHP versions.
- Documentation errors without a security impact.

Report upstream vulnerabilities to the affected vendor. You may still notify this project privately when an upstream issue requires a defensive change here.

## Disclosure and response

Maintainers will review reports, request additional sanitized information when needed, and coordinate remediation and disclosure through the private advisory. Do not publish details until maintainers have released a fix or agreed to a disclosure date.

This community project does not currently operate a bug-bounty program and cannot guarantee payment or a fixed response time. Good-faith reports will be handled respectfully, and reporter credit will be offered when appropriate.

