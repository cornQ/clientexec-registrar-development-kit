# Registrar Requirements

## Registrar

- Display name:
- Plugin identifier:
- Official website:
- API base URL:
- API version:

## Target environment

- Clientexec version:
- PHP versions:
- Sandbox account available:
- ionCube/PHP constraints:

## Requested capabilities

- [ ] Availability
- [ ] Registration
- [ ] Renewal
- [ ] Transfer
- [ ] Nameservers
- [ ] Contact information
- [ ] DNS records
- [ ] Registrar lock
- [ ] EPP/auth code
- [ ] Privacy protection
- [ ] Domain import
- [ ] Pricing import
- [ ] Name suggestions
- [ ] TLD-specific extended attributes

## Authentication

Describe the authentication mechanism without including credentials.

## Constraints and decisions

- Supported TLDs:
- Required nameserver count:
- Rate limits:
- Idempotency support:
- Required contact formats:
- Required extension-specific fields:
- Unsupported operations:

## Registration and activation nameservers

- Nameservers required to submit registration: unknown
- Nameservers required to reach active status: unknown
- Minimum nameserver count: unknown
- Maximum nameserver count: unknown
- Required setup nameserver fields: unknown
- Registrar default nameservers: unknown
- TLD-specific differences: unknown
- Hostname and glue requirements: unknown
- Target Clientexec registration parameter shape: unknown
- Setup-page native required-field support: unknown
- Planned enforcement: blocked until verified
- Activation timing: unknown
- Active-state confirmation method: unknown
- Activation timeout: unknown

## Endpoint visibility

- Production endpoint evidence:
- Sandbox endpoint evidence:
- Routing implementation: private whitelisted constants
- Editable endpoint permitted: no

## Final production acceptance

- Mandatory test IDs: SETUP-RENDER-001, CONFIG-SAVE-001, ENDPOINT-HIDDEN-001, ERROR-SAFETY-001, RELEASE-SMOKE-001, plus capability and conditional IDs
- Kit-user test IDs: SETUP-RENDER-001, CONFIG-SAVE-001, ENDPOINT-HIDDEN-001, RELEASE-SMOKE-001, plus kit-user-owned capability and conditional IDs
- Kit-user final checklist confirmation: pending
- Production readiness: Development complete, not production-ready

## Open questions

- 
