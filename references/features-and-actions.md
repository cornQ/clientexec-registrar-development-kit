# Features, Actions, and Plugin Metadata

## `$features`

The official guide identifies three optional runtime flags:

```php
public $features = [
    'nameSuggest' => false,
    'importDomains' => false,
    'importPrices' => false,
];
```

Enable them only when:

| Flag | Required behavior |
|---|---|
| `nameSuggest` | `checkDomain()` correctly handles the supplied suggestion/TLD collection |
| `importDomains` | `fetchDomains()` is implemented and pagination is handled |
| `importPrices` | `getTLDsAndPrices()` returns complete, normalized pricing |

## `getVariables()`

Override the inherited `getVariables()` method to define administrator configuration. Common field types from the official guide include:

- `hidden`
- `text`
- `password`
- `textarea`
- `yesno`
- `label`

Mark credentials as `encryptable => true`. Do not provide realistic-looking default secrets.

### Endpoint fields

Never expose an API endpoint, base URL, hostname, or equivalent routing value as `text`, `textarea`, or any other editable setup field. Keep verified production and sandbox URLs in private module constants and select between them internally. When the target Clientexec contract requires an endpoint entry in `getVariables()`, use `type => 'hidden'`, populate it only from a verified constant, and never use a saved or request-supplied endpoint as the transport destination. A visible `yesno` sandbox selector may choose between whitelisted constants.

Treat this as mandatory for every new module. During setup-page testing, confirm that administrators cannot view, edit, or submit an arbitrary endpoint.

### Conditional default nameservers

When registrar or TLD evidence requires nameservers for registration or activation, add `Default NS1` through the greatest documented minimum across the approved TLD scope as visible setup fields. Record each TLD's minimum and maximum separately; do not create unnecessary required fields beyond the greatest applicable minimum.

Verify the target Clientexec version before using a native `required` property. If staging proves native enforcement, apply it to every minimum field. Otherwise keep the fields visible and validate their configured values inside the module before any registration or activation request. Do not claim that configuration save itself is blocked unless staging proves it.

Prefer verified order or package nameservers when supplied by Clientexec. Verify whether the target version provides `NS1`, `ns1`, nested `hostname` values, or another shape. Fall back to the configured defaults only when the order values are absent. Normalize and validate the complete required set before transport; never hard-code undocumented registrar nameservers.

The operational hidden fields are normally:

```php
'Plugin Name' => [
    'type' => 'hidden',
    'description' => 'Used by CE to show plugin',
    'value' => 'Example',
],
'Description' => [
    'type' => 'hidden',
    'description' => 'Description shown to administrators',
    'value' => 'Example registrar integration',
],
```

The current official sample and all maintained modules examined during this audit use `Plugin Name`. Older or reconstructed guidance may show `Name`; prefer the current official sample and verify against the target Clientexec version.

## Actions

The official guide defines three action lists:

- `Actions`: administrator actions before registration.
- `Registered Actions`: administrator actions after registration.
- `Registered Actions For Customer`: customer-accessible actions after registration.

Observed format:

```text
Renew (Renew Domain),SendTransferKey (Send Auth Info),Cancel
```

The token before the optional label maps to `do{Token}()`. For example, `Renew` maps to `doRenew()`.

Start with empty action values. Add actions only after implementation and authorization checks are complete. Customer actions require special care because they may expose EPP or account-sensitive operations.

## `resource/plugin.ini`

Maintained registrar modules use:

```ini
[meta]
createdby = Example Author
name = Example Registrar
type = registrar
included = 0

[ext_urls]
official_url = https://registrar.example/
forum_url = https://github.com/example/example-clientexec/issues
creator_url = https://example.test/
brief = Integrate Clientexec with Example Registrar

[features]
register = 0
transfer = 0
renew = 0
domainsync = 0
pricingsync = 0
registrarlock = 0
```

Observed metadata mapping:

| INI field | Module capability |
|---|---|
| `register` | Registration |
| `transfer` | Transfer initiation |
| `renew` | Renewal |
| `domainsync` | Existing-domain import |
| `pricingsync` | TLD/pricing import |
| `registrarlock` | Registrar-lock management |

The INI feature section and PHP `$features` overlap only for import capabilities. Keep both consistent with the implementation.

Use `included = 0` for a third-party template unless there is verified Clientexec packaging guidance requiring another value.

## Capability review

Before changing a flag from `0` or `false`, verify:

1. The registrar API supports the operation.
2. Authentication and permissions are documented.
3. The callback and action wrapper exist where required.
4. Success and error responses are mapped.
5. Sandbox tests cover the operation.
6. The UI does not expose an action to an unauthorized user.
