# Registrar Method Contracts

## Contents

- Structural methods
- Action wrappers
- Capability callbacks
- Capability comparison
- Implementation quality
- Parameter handling
- Return and error rules

## Structural methods

The eleven methods in this section are runtime-verified abstract methods. Exact registrar API fields vary.

### `checkDomain($params)`

Read `sld`, `tld`, and optionally `namesuggest`. Return:

```php
[
    'result' => [
        [
            'tld' => 'com',
            'domain' => 'example',
            'status' => 0,
        ],
    ],
]
```

Observed status codes from the official sample:

| Code | Meaning |
|---:|---|
| 0 | Available |
| 1 | Already registered |
| 2 | Unsupported or unrecognized extension |
| 3 | Invalid domain |
| 5 | Registrar connection problem |

Only return `0` after an affirmative registrar response.

### `registerDomain($params)`

Accept parameters built by `buildRegisterParams()`, including `sld`, `tld`, `NumYears`, contact fields, nameservers, package add-ons, and possibly `ExtendedAttributes`. Return the registrar order or domain identifier expected by the action wrapper. Throw on ambiguity or failure.

Before mapping nameservers, verify the target Clientexec version's registration shape rather than assuming `NS1`, `ns1`, or a nested `hostname` value. When registrar or TLD evidence requires nameservers to submit registration or reach active status:

1. Record the minimum and maximum count, syntax, glue requirements, registrar defaults, activation timing, and status-confirmation contract.
2. Prefer the complete valid order/package set supplied by Clientexec.
3. When that set is absent, read the verified `Default NS1` through the required minimum from module configuration.
4. Reject missing, partial, invalid, duplicate, or ambiguous required values before creating a registrar request.
5. Treat an accepted order as pending until the documented status check proves the required active state when activation is asynchronous.

Keep registration disabled when the nameserver requirement, fallback behavior, or active-state confirmation cannot be verified.

### Contact methods

`getContactInformation($params)` returns contact groups whose fields use `[label, value]` pairs:

```php
[
    'Registrant' => [
        'FirstName' => [$this->user->lang('First Name'), 'Ada'],
        'LastName' => [$this->user->lang('Last Name'), 'Lovelace'],
        'EmailAddress' => [$this->user->lang('E-mail'), 'ada@example.test'],
    ],
]
```

`setContactInformation($params)` maps the submitted contact fields to the registrar. Return a translated success message only after confirmed success.

Before implementing contact display, let the user choose one policy:

- Keep optional empty fields visible on editable forms. Use this as the recommended default because the user can add a value later.
- Omit optional empty fields from all supported contact surfaces, with the stated limitation that omitted editable fields cannot be populated through Clientexec.
- Omit optional empty fields only on a surface verified to be read-only.

Record the decision in the implementation plan and `output/DEVELOPMENT-CHANGELOG.md`. Apply it consistently to every contact group unless the registrar contract requires a documented exception.

For every policy:

- Never omit a required field.
- Omit fields the registrar does not support rather than presenting a nonfunctional input.
- Treat only `null`, an empty string, and a whitespace-only string as empty. Preserve valid values such as `"0"`, `0`, and `false`.
- Treat an unexpectedly incomplete or malformed registrar response as a failure rather than hiding evidence of the problem.
- Do not interpret an omitted or unsubmitted field as a request to delete registrar data. Preserve its current value unless the user explicitly submitted a supported clear operation.

### Nameserver methods

`getNameServers($params)` returns:

```php
[
    'hasDefault' => false,
    'usesDefault' => false,
    0 => 'ns1.example.test',
    1 => 'ns2.example.test',
]
```

`setNameServers($params)` reads nameservers from `$params['ns']`. Remove blank values, preserve order where the registrar requires it, and confirm the registrar accepted the update.

### `getGeneralInfo($params)`

Return at least the domain, expiration value, and auto-renew state:

```php
[
    'domain' => 'example.com',
    'expiration' => '2030-01-31',
    'autorenew' => 1,
]
```

Observed optional keys include `id`, `is_registered`, `is_expired`, `registrationstatus`, and `purchasestatus`. Normalize dates only after confirming the registrar's timezone and format.

### `setAutorenew($params)`

Enable or disable registrar-side auto-renew using the submitted state. This method is abstract even if the registrar does not support auto-renew changes. Throw when unsupported.

### Registrar-lock methods

`getRegistrarLock($params)` returns the current lock state as a boolean-compatible value.

`setRegistrarLock($params)` reads the requested `lock` value and confirms the update. Use `buildLockParams()` in an action wrapper.

### `sendTransferKey($params)`

Ask the registrar to deliver the authorization key through its supported secure channel. Do not log or expose the key.

## Action wrappers

Clientexec action configuration maps an action name to `do{Action}()`. Common observed wrappers are:

| Action | Handler | Lower-level operation |
|---|---|---|
| `Register` | `doRegister($params)` | `registerDomain(buildRegisterParams(...))` |
| `Renew` | `doRenew($params)` | `renewDomain(buildRenewParams(...))` |
| `DomainTransferWithPopup` | `doDomainTransferWithPopup($params)` | transfer using `buildTransferParams(...)` |
| `SetRegistrarLock` | `doSetRegistrarLock($params)` | `setRegistrarLock(buildLockParams(...))` |
| `SendTransferKey` | `doSendTransferKey($params)` | `sendTransferKey(...)` |
| `Cancel` | Base behavior is available through `doCancel()` | Verify target-version behavior before overriding |

Enable an action only after both its handler and registrar operation work.

## Capability callbacks

These are supported conventions, not abstract `RegistrarPlugin` methods:

| Callback | Enablement/evidence | Expected result |
|---|---|---|
| `renewDomain($params)` | `Renew` action and `renew = 1` metadata | Registrar order identifier |
| `initiateTransfer($params)` | Transfer action and `transfer = 1` | Transfer identifier |
| `getTransferStatus($params)` | Gateway-dispatched | Status; set `Transfer Status` to `Completed` when confirmed |
| `getDNS($params)` | Gateway-dispatched | `records`, `types`, and `default` |
| `setDNS($params)` | Gateway-dispatched | Confirmed update message |
| `getEPPCode($params)` | Concrete base method may be overridden | Authorization code for authorized display |
| `disablePrivateRegistration($params)` | Gateway-dispatched | Confirmed privacy-disable result |
| `getTLDsAndPrices($params)` | `$features['importPrices']` | TLD pricing map |
| `fetchDomains($params)` | `$features['importDomains']` | `[$domainsList, $metaData]` |

A DNS record commonly contains `id`, `hostname`, `address`, and `type`. Advertise only record types the registrar API actually accepts.

### Full-list DNS synchronization

Clientexec may assign a temporary ID to a newly added DNS row. The temporary ID may be numeric, and Clientexec may submit it again on repeated saves until the DNS page refreshes. Therefore, do not assume that every numeric submitted ID is a registrar record ID.

Implement full-list `setDNS()` synchronization as follows:

1. Fetch the registrar's current DNS records before planning changes.
2. Normalize each submitted and current record's type, hostname, and content according to the registrar's verified comparison rules. Treat `address` as the record content when that is the Clientexec field in use.
3. Match a submitted ID directly only when it identifies a record in the registrar's current record set.
4. For every submitted ID that is absent from the current record set, including an unknown numeric ID, compare the normalized type, hostname, and content with current records:
   - Retain the existing record when exactly one record matches.
   - Treat the submitted row as new when no record matches.
   - Reject the request without mutations when multiple records match because reconciliation is ambiguous.
5. Validate and reconcile the complete submitted list before creating, updating, or deleting any registrar record. Include duplicate detection, supported-type checks, field validation, ownership checks for recognized IDs, and ambiguity checks in this pre-mutation pass.
6. Build the omission/deletion set only after reconciliation. Mark both directly identified and exactly reconciled current records as retained so a reconciled record is never deleted as omitted.
7. Apply the validated mutation plan and confirm each registrar result before returning success.

Keep reconciliation scoped to the domain or DNS zone being synchronized. Do not use a content match to adopt a record from another zone, and do not guess between duplicate current records.

Pricing imports commonly use:

```php
$tlds[$tld]['pricing'] = [
    'register' => $registerPrice,
    'transfer' => $transferPrice,
    'renew' => $renewPrice,
];
```

Domain imports commonly return rows with `id`, `sld`, `tld`, and `exp`, plus metadata such as `total`.

## Capability comparison

Complete this comparison before planning implementation:

| Registrar feature | Clientexec support | Evidence | Decision |
|---|---|---|---|
| Documented registrar operation | Verified callback, action, feature flag, and compatible contract, or unsupported | Registrar source and Clientexec source or target-runtime evidence | Implement or ignore |

Implement a feature only when the registrar documents it and the target Clientexec version provides a verified integration point and compatible contract. A registrar API endpoint alone is not evidence that Clientexec supports the feature.

For each unsupported registrar feature:

- Ignore the endpoint and do not add code for it.
- Do not invent a callback, action, feature flag, custom UI, background job, or storage field.
- Keep related advertised capabilities disabled.
- Report it to the user as an unsupported Clientexec limitation, with the evidence used for that decision.

Keep the selected implementation proportional to the verified matrix. Prefer explicit mapping over generic dispatch, and introduce shared helpers only when repeated transport, validation, normalization, or security behavior justifies them.

## Implementation quality

- Follow the existing module's formatting, naming, and organization when modifying it. For a new module, follow the verified Clientexec sample and repository conventions.
- Keep Clientexec callbacks focused on contract handling and explicit parameter mapping. Extract transport, validation, normalization, or safe error mapping only when reuse or security makes the helper clearer than inline code.
- Give methods and variables specific names that reflect registrar operations and Clientexec data. Avoid generic layers that obscure the mapping.
- Remove duplicated logic when a small focused helper improves clarity, but do not introduce a framework, service container, factory hierarchy, generic dispatcher, or speculative extension point without a concrete requirement.
- Preserve working structure and avoid unrelated renaming, file reorganization, formatting, or refactoring.
- Do not leave dead code, commented-out implementations, placeholder success, temporary debugging, unused dependencies, unreachable branches, or unresolved `TODO` markers in completed scope.
- Use syntax and dependencies compatible with the verified target Clientexec and PHP versions.

## Parameter handling

- Use `buildRegisterParams()`, `buildRenewParams()`, `buildTransferParams()`, and `buildLockParams()` rather than reconstructing Clientexec state casually.
- Split a stored domain with `DomainNameGateway::splitDomain()` when public-suffix handling matters.
- Treat `ExtendedAttributes` as untrusted input and validate every field.
- Do not assume every plugin callback receives `userPackageId`; verify the specific action path.
- Normalize booleans, dates, phone numbers, and country codes at the registrar boundary.

## Return and error rules

- Detect and classify registrar, transport, parsing, and validation failures inside the registrar module.
- Throw a sanitized `CE_Exception` for user/admin-visible failures and let Clientexec's dispatch layer render the message. Do not print output, emit PHP warnings, return UI markup, or attempt to render a notification inside the module.
- Keep every message that may reach a client registrar-neutral. Do not include the registrar's name, brand aliases, API hostname, endpoint, product name, or raw registrar error text. Prefer a localized description such as “The domain provider could not complete this request.”
- Show the registrar name in an admin-only surface only after verifying that the target Clientexec path cannot also expose the same message to a client. When the audience boundary is unknown or shared, use the registrar-neutral message.
- Use `EXCEPTION_CODE_CONNECTION_ISSUE` only for verified transport or registrar-connectivity failures so Clientexec can apply its connection-failure behavior.
- Return a localized success message only when the callback contract expects one and the registrar has confirmed success.
- Treat `CE_Error` returns and generic `Exception` throws found in older registrar modules as legacy observations, not the preferred contract for new work.
- Preserve a registrar error code internally when it helps support, but redact sensitive data.
- Do not pass raw registrar messages directly to `CE_Exception`. Map them to a safe message that cannot expose credentials, authorization headers, request or response bodies, contact information, or other private values.
- Keep diagnostic logging separate from client-facing messaging. Sanitized logs may identify the registrar and retain an internal error code, but a log entry neither reports a failure to Clientexec nor replaces an exception.
- Generate a non-sensitive correlation or reference ID when support must connect a registrar-neutral client error with sanitized diagnostic context. Include the same ID in the exception message and log entry; never derive it from credentials, contact data, a domain auth code, or raw request/response content.
- Treat empty, malformed, timed-out, or unrecognized responses as failures.
- Return success only after the registrar confirms the operation.
- Never use placeholder identifiers, transfer states, expiration dates, or availability results.
