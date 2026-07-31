# TLD-Specific Extended Attributes

## Evidence

Clientexec's domain schema includes a `tld_extra_attributes` table keyed by `tld`. Maintained registrar modules read submitted values from:

```php
$params['ExtendedAttributes']
```

Observed uses include `.us` nexus/purpose codes, `.ca` legal types, `.au` eligibility fields, `.uk` registrant data, and registrar-specific eligibility fields.

The official registrar development page does not fully document this mechanism. Treat it as runtime/schema-supported behavior requiring target-version validation.

## Observed data shape

Historical Clientexec SQL contains serialized arrays shaped like:

```php
[
    'us_nexus' => [
        'ID' => '1',
        'description' => 'Nexus Category',
        'options' => [
            'US Citizen' => [
                'description' => 'A natural person who is a US Citizen.',
                'value' => 'C11',
            ],
        ],
    ],
]
```

The visible label and submitted registrar value are different. Preserve the exact API code in `value`.

Fields with an empty `options` array are observed for free-text input. Options may also contain `requires`, referencing another field ID.

## Storage format warning

Do not assume one database encoding:

- Historical Clientexec upgrade SQL stores raw PHP-serialized arrays.
- A current installed system examined during this kit's audit stored a Base64-encoded serialized array.
- A production Netim helper also writes `base64_encode(serialize(...))`.

Inspect a known row in the target installation and prefer a supported Clientexec import path. If direct database work is unavoidable, back up the row, use the target installation's encoding, parameterize the query, and verify the decoded structure before replacement.

Never expose a web-accessible unauthenticated importer.

## Safe implementation workflow

1. Obtain the registrar's authoritative field definitions.
2. Normalize TLD keys consistently without losing multi-label extensions.
3. Assign stable field IDs.
4. Keep field labels/descriptions separate from API values.
5. Preserve dependencies such as `requires`.
6. Validate submitted values against the allowed code set.
7. Map only recognized `ExtendedAttributes` keys into registrar requests.
8. Reject missing required fields before making an API request.
9. Test registration for each distinct field schema in a registrar sandbox.

## Netim evidence and caution

The examined Netim module includes:

- `additional_fields/additional_fields.json`
- translated field descriptions
- `import_extra_attributes.php`
- runtime handling of `ExtendedAttributes`

This demonstrates a useful extension mechanism but the importer is not suitable as a public template:

- It forces `.eu` during processing.
- It handles only selected input types.
- It writes directly to the database.
- It stores translated option descriptions where registrar codes should be stored.
- It relies on request parameters and emits HTML.

Extract the schema idea, not the script.

For `.eu`, for example, the registrar values are codes such as `RESIDENT`, `CITIZEN`, and country codes such as `AT`; translated labels must not replace those values.

## Validation checklist

- Confirm the decoded database row matches the expected PHP array.
- Confirm every UI option submits the registrar code.
- Confirm free-text fields remain free text.
- Confirm conditional fields appear and are validated correctly.
- Confirm `$params['ExtendedAttributes']` contains the expected keys and values.
- Confirm sensitive identity fields are neither logged nor included in exceptions.
