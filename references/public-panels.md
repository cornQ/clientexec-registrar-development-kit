# Registrar Public Panels

Use registrar `publicPanels` for customer-facing pages in the domain-management left menu. Treat this as a version-observed Clientexec extension point and verify it against the target installation before enabling it.

## Declare panels

Map each public callback key to its menu title:

```php
public $features = [
    'nameSuggest' => false,
    'importDomains' => false,
    'importPrices' => false,
    'publicPanels' => [
        'serviceConnect' => 'Service Connect',
    ],
];
```

Use a unique method-safe key. Implement a public method with the same name.

## Implement the callback contract

In the verified Clientexec 7 public-panel flow, the controller:

1. Loads `id` as a `UserPackage` object.
2. Requires the package to be active.
3. Confirms that the package uses the plugin.
4. Confirms that the callback method exists.
5. Calls the method with the `UserPackage` object and the view.
6. Assigns the method's return value to the view `output`.

Accept the package as an object, never as an array, and return rendered HTML as a string:

```php
public function serviceConnect($userPackage, $view = null)
{
    if (!is_object($userPackage) || !is_callable([$userPackage, 'getCustomField'])) {
        throw new CE_Exception($this->user->lang('The request could not be completed.'));
    }

    $domain = (string) $userPackage->getCustomField('Domain Name');

    ob_start();
    include __DIR__ . '/service-connect.phtml';
    return (string) ob_get_clean();
}
```

Do not return an array. The public view expects string output and may pass it to string-only escaping or decoding functions.

## Handle forms through the native route

Prefer a normal Clientexec POST/render cycle. Preserve the current relative public-panel URL and ensure it includes `action=productsnapinview` when the target controller requires an action parameter. Include Clientexec's session hash:

```php
<form method="post" action="<?= $escapedRelativePanelUrl ?>">
    <input type="hidden" name="sessionHash" value="<?= $escapedSessionHash ?>">
    <input type="hidden" name="module_action" value="save">
</form>
```

Obtain the hash from the verified target-version helper, commonly `CE_Lib::getSessionHash()`. Do not invent custom CSRF validation when the controller already owns it; verify that a missing or invalid hash is rejected in staging.

If AJAX is required by verified UI behavior, inspect the actual response envelope first. Never assume the response is JSON, never treat an unknown response as success, and parse returned markup with scripts disabled. A native POST is safer when the controller renders the callback's returned HTML.

## Enforce panel safety

- Derive the managed domain from the supplied `UserPackage`; never accept it from a hidden input.
- Require scalar POST actions and values before casting.
- Normalize and validate every submitted DNS or connection value before transport.
- Escape every value, message, URL, and attribute for its output context.
- Keep form actions relative and reject control characters, protocol-relative URLs, and external URLs.
- Include no credentials, authorization headers, provider bodies, DNS values, or customer data in logs.
- Return safe validation messages. Keep authentication, configuration, transport, and malformed-response details generic on customer pages.
- Verify mutation success with an authoritative read when the registrar API supports it; never turn an unknown or stale response into success.
- Handle browser form restoration when an editable field must always display an authoritative provider value after reload.

## Separate customer and admin surfaces

Registrar `publicPanels` add customer-side domain-management entries. Do not assume they add an admin/staff package tab.

When an admin/staff surface is required, verify the target Clientexec snap-in or hook contract. Keep registrar API and validation logic in the registrar class and make the snap-in a small admin UI adapter. Treat creation or modification of `plugins/snapin/...` as a separate explicitly authorized write scope; never broaden registrar-module write permission implicitly.

## Validate before enabling

Test each panel in Clientexec staging:

1. The menu appears only for an active package using the intended registrar.
2. Direct access to another customer's package is rejected by Clientexec.
3. The callback receives a `UserPackage` object and returns a string.
4. GET displays the current authoritative provider value.
5. Valid POST updates the provider and displays confirmed state.
6. Same-value, invalid, unauthorized, timeout, malformed, and provider-error paths do not display false success.
7. Missing or invalid `sessionHash` is rejected.
8. Refresh does not repeat a mutation or display a browser-restored value as provider state.
9. Customer output contains no internal reference, secret, stack trace, raw provider body, or executable response script.
10. Admin/staff availability is tested separately when a snap-in is included.
