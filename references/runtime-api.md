# Runtime API and Evidence Classification

## Contents

- Evidence hierarchy
- Verified class hierarchy
- Required abstract methods
- Concrete inherited methods
- Gateway and import evidence
- Classification rules

## Evidence hierarchy

Use the current official guide and official sample as the public baseline. Use reflection against the target Clientexec installation to resolve structural questions. Use real registrar modules to discover conventions, then verify each convention before presenting it as general guidance.

The runtime inventory below was captured from an installed Clientexec system using PHP 8.4.23 and ionCube Loader 15.5.0. The Clientexec application version was not captured. Treat the inventory as strong evidence for that installation, not a promise that every past or future release is identical.

## Verified class hierarchy

```text
RegistrarPlugin
└── NE_Plugin
    └── NE_Model
```

The following eleven methods were reflected as public and abstract on `RegistrarPlugin`:

```text
checkDomain($params)
registerDomain($params)
getContactInformation($params)
setContactInformation($params)
getNameServers($params)
setNameServers($params)
getGeneralInfo($params)
setAutorenew($params)
getRegistrarLock($params)
setRegistrarLock($params)
sendTransferKey($params)
```

A concrete plugin class must implement all eleven. If the registrar cannot perform an operation, implement the method and throw a clear `CE_Exception`; do not return simulated success.

## Concrete `RegistrarPlugin` methods

The same runtime exposed these concrete methods:

```text
getEPPCode($params)
hasPrivacyProtection($contactInfo)
getUsernamePassword($obj, $decrypt)
buildRenewParams($userPackage, $params)
buildLockParams($userPackage, $params)
buildTransferParams($userPackage, $params)
show_publicviews($user_package, $action)
buildRegisterParams($userPackage, $params)
doAction($userPackage, $actionToPerform, $params)
supportsAction($action)
getAvailableActions($userPackage, $status)
doCancel($params)
supports($feature)
```

These are available base behaviors or helpers. Do not override them without a concrete need and version-specific verification.

## Inherited methods

Runtime reflection exposed these relevant `NE_Plugin` methods:

```text
__construct()
setDatabase()
setUser()
getDescription()
setDescription()
trackUsage()
setTemplate()
setInternalName()
getInternalName()
getVariables()
getVariable()
registerevents()
```

It also exposed `lang()`, `log()`, `debug()`, and `escape()` from `NE_Model`.

`getVariables()` is therefore concrete rather than part of the PHP abstract contract. Registrar modules should still override it to declare their operational configuration and action lists.

## `DomainNameGateway` evidence

Runtime reflection found gateway methods including:

```text
splitDomain($domain)
getNameServersViaPlugin($userPackage)
getDNSViaPlugin($userPackage)
getContactInfoViaPlugin($userPackage)
getTransferStatus($userPackage)
getRegistrarLockViaPlugin($userPackage)
getEPPCodeViaPlugin($userPackage)
getGeneralInfoViaPlugin($userPackage)
callPlugin($obj, $function, $params)
disablePrivateRegistration($obj)
getDomainUsernameAndPassword($userPackage)
```

This confirms that Clientexec dispatches capabilities such as DNS and transfer status even though those callbacks are not abstract methods on `RegistrarPlugin`.

Do not implement the `*ViaPlugin()` gateway methods in a registrar. They are internal callers. Implement the corresponding registrar callback documented in `method-contracts.md`.

## Domain import evidence

The installed `ICanImportDomains` interface declares only:

```php
public function fetchDomains($params);
```

The official sample and examined registrar modules expose `fetchDomains()` with the `importDomains` feature flag but do not declare `implements ICanImportDomains`. Treat the method and flag as the observed integration mechanism. Do not require the interface declaration unless the target Clientexec version explicitly requires it.

## Classification rules

Classify a discovered method before using it:

| Classification | Meaning |
|---|---|
| Structural | Abstract base method required for the class to load |
| Operational | Expected configuration such as `getVariables()` |
| Capability callback | Used only when a feature/action is enabled |
| Helper | Safe inherited utility intended for plugin code |
| Internal | Clientexec dispatcher or implementation detail |
| Observed | Production pattern needing further verification |
| Registrar-specific | Belongs only to one registrar or API wrapper |

Method names extracted from production files are not sufficient proof of a Clientexec extension point. Internal API helpers such as `makeRequest()`, `apiRequest()`, `splitName()`, or currency conversion methods remain registrar-specific.

## Reproduce the inventory

Copy `scripts/inspect-clientexec-runtime.php` to a trusted environment and run it with a PHP binary that loads the ionCube version required by the installed Clientexec:

```bash
/path/to/php inspect-clientexec-runtime.php /absolute/path/to/clientexec
```

The script reads class metadata and writes JSON to standard output. It does not instantiate plugins, access credentials, call registrar APIs, or modify Clientexec.
