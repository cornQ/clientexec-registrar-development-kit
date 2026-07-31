#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Print command usage.
 */
function printUsage(): void
{
    echo 'Usage: php -n scripts/validate-registrar-output.php --module <directory> --changelog <file> [--allow-incomplete]', PHP_EOL;
}

/**
 * Return the latest append-only changelog field value.
 */
function latestField(string $changelog, string $label): ?string
{
    preg_match_all(
        '/^-\s*' . preg_quote($label, '/') . ':\s*(.*?)\s*$/mi',
        $changelog,
        $matches
    );

    if ($matches[1] === []) {
        return null;
    }

    $values = $matches[1];

    return trim((string) end($values));
}

/**
 * Add a validation error.
 *
 * @param array<int, string> $errors
 */
function addError(array &$errors, string $message): void
{
    $errors[] = $message;
}

$modulePath = null;
$changelogPath = null;
$allowIncomplete = false;
$arguments = array_slice($argv, 1);

for ($index = 0, $count = count($arguments); $index < $count; $index++) {
    $argument = $arguments[$index];

    if ($argument === '--help' || $argument === '-h') {
        printUsage();
        exit(0);
    }

    if ($argument === '--allow-incomplete') {
        $allowIncomplete = true;
        continue;
    }

    if ($argument === '--module' || $argument === '--changelog') {
        if (!isset($arguments[$index + 1])) {
            fwrite(STDERR, "Missing value for {$argument}." . PHP_EOL);
            exit(2);
        }

        $value = $arguments[++$index];
        if ($argument === '--module') {
            $modulePath = $value;
        } else {
            $changelogPath = $value;
        }
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}" . PHP_EOL);
    printUsage();
    exit(2);
}

if ($modulePath === null || $changelogPath === null) {
    printUsage();
    exit(2);
}

$resolvedModulePath = realpath($modulePath);
$resolvedChangelogPath = realpath($changelogPath);
$errors = [];

if ($resolvedModulePath === false || !is_dir($resolvedModulePath)) {
    addError($errors, 'Module directory does not exist or cannot be resolved.');
}
if ($resolvedChangelogPath === false || !is_file($resolvedChangelogPath)) {
    addError($errors, 'Development changelog does not exist or cannot be resolved.');
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}" . PHP_EOL);
    }
    exit(1);
}

$pluginFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $resolvedModulePath,
        FilesystemIterator::SKIP_DOTS
    )
);
foreach ($iterator as $item) {
    if (
        $item instanceof SplFileInfo
        && $item->isFile()
        && preg_match('/^Plugin[A-Za-z0-9]+\.php$/', $item->getFilename()) === 1
    ) {
        $pluginFiles[] = $item->getPathname();
    }
}

if (count($pluginFiles) !== 1) {
    addError($errors, 'The module directory must contain exactly one Plugin{Name}.php file.');
} else {
    $source = file_get_contents($pluginFiles[0]);
    if ($source === false) {
        addError($errors, 'Unable to read the registrar plugin file.');
        $source = '';
    }

    if (
        preg_match(
            '/(?:private|protected)\s+const\s+[A-Z0-9_]*(?:API_URL|BASE_URL|ENDPOINT)[A-Z0-9_]*\s*=\s*[\'\"][^\'\"]+[\'\"]/i',
            $source
        ) !== 1
    ) {
        addError($errors, 'The module must define at least one non-empty verified private/protected endpoint constant.');
    }

    if (
        preg_match(
            '/(?:API_URL|BASE_URL|ENDPOINT)[A-Z0-9_]*\s*=\s*[\'\"]http:\/\//i',
            $source
        ) === 1
    ) {
        addError($errors, 'Endpoint constants must not use insecure HTTP.');
    }

    preg_match_all(
        '/(?:lang\(\s*)?([\'\"])([^\'\"\r\n]*(?:api\s*url|base\s*url|endpoint)[^\'\"\r\n]*)\1\s*\)?\s*=>\s*\[(.*?)\]\s*,/is',
        $source,
        $endpointFields,
        PREG_SET_ORDER
    );
    foreach ($endpointFields as $endpointField) {
        if (
            preg_match(
                '/[\'\"]type[\'\"]\s*=>\s*[\'\"]hidden[\'\"]/i',
                $endpointField[3]
            ) !== 1
        ) {
            addError(
                $errors,
                "Endpoint setup field must be hidden: {$endpointField[2]}"
            );
        }
    }

    if (
        preg_match(
            '/\$params\s*\[\s*[\'\"][^\'\"]*(?:api\s*url|base\s*url|endpoint)[^\'\"]*[\'\"]\s*\]/i',
            $source
        ) === 1
        || preg_match(
            '/settings\s*->\s*get\s*\(\s*[\'\"][^\'\"]*(?:api\s*url|base\s*url|endpoint)[^\'\"]*[\'\"]/i',
            $source
        ) === 1
    ) {
        addError($errors, 'Transport must not consume an arbitrary saved or request-supplied endpoint.');
    }
}

$changelog = file_get_contents($resolvedChangelogPath);
if ($changelog === false) {
    addError($errors, 'Unable to read the development changelog.');
    $changelog = '';
}

$endpointVisibility = latestField($changelog, 'Endpoint visibility');
if (strcasecmp((string) $endpointVisibility, 'Hidden and non-editable') !== 0) {
    addError($errors, 'The latest Endpoint visibility field must be Hidden and non-editable.');
}

$productionEndpointEvidence = latestField($changelog, 'Production endpoint evidence');
if (
    $productionEndpointEvidence === null
    || $productionEndpointEvidence === ''
    || preg_match('/replace|unknown|pending/i', $productionEndpointEvidence) === 1
) {
    addError($errors, 'Verified production endpoint evidence is missing from the changelog.');
}

$nameserverRequirement = latestField($changelog, 'Nameserver activation requirement');
if (
    $nameserverRequirement === null
    || !in_array(
        strtolower($nameserverRequirement),
        ['required', 'optional', 'not applicable'],
        true
    )
) {
    addError($errors, 'Nameserver activation requirement must have a resolved changelog value.');
}

$mandatoryIdsValue = latestField($changelog, 'Mandatory test IDs');
$mandatoryIds = [];
if ($mandatoryIdsValue !== null) {
    foreach (explode(',', $mandatoryIdsValue) as $mandatoryId) {
        $mandatoryId = trim($mandatoryId);
        if ($mandatoryId !== '') {
            $mandatoryIds[] = $mandatoryId;
        }
    }
}

$baselineIds = [
    'SETUP-RENDER-001',
    'CONFIG-SAVE-001',
    'ENDPOINT-HIDDEN-001',
    'ERROR-SAFETY-001',
    'RELEASE-SMOKE-001',
];
foreach ($baselineIds as $baselineId) {
    if (!in_array($baselineId, $mandatoryIds, true)) {
        addError($errors, "Mandatory test list is missing baseline ID: {$baselineId}");
    }
}

$kitUserIdsValue = latestField($changelog, 'Kit-user test IDs');
$kitUserIds = [];
if ($kitUserIdsValue !== null) {
    foreach (explode(',', $kitUserIdsValue) as $kitUserId) {
        $kitUserId = trim($kitUserId);
        if ($kitUserId !== '') {
            $kitUserIds[] = $kitUserId;
        }
    }
}

foreach (
    ['SETUP-RENDER-001', 'CONFIG-SAVE-001', 'ENDPOINT-HIDDEN-001', 'RELEASE-SMOKE-001']
    as $baselineKitUserId
) {
    if (!in_array($baselineKitUserId, $kitUserIds, true)) {
        addError($errors, "Kit-user test list is missing baseline ID: {$baselineKitUserId}");
    }
}
foreach ($kitUserIds as $kitUserId) {
    if (!in_array($kitUserId, $mandatoryIds, true)) {
        addError($errors, "Kit-user test ID is not present in Mandatory test IDs: {$kitUserId}");
    }
}

if (strtolower((string) $nameserverRequirement) === 'required') {
    $requiredCount = latestField($changelog, 'Required setup nameserver fields');
    if ($requiredCount === null || preg_match('/\d+/', $requiredCount, $countMatch) !== 1) {
        addError($errors, 'Required setup nameserver field count is missing or invalid.');
        $minimumNameserverCount = 0;
    } else {
        $minimumNameserverCount = (int) $countMatch[0];
    }

    $parameterShape = latestField($changelog, 'Clientexec registration nameserver shape');
    if ($parameterShape === null || preg_match('/unknown|replace|pending/i', $parameterShape) === 1) {
        addError($errors, 'Clientexec registration nameserver shape is unresolved.');
    }

    $enforcement = latestField($changelog, 'Setup required-field enforcement');
    if (!in_array(strtolower((string) $enforcement), ['native', 'module-local'], true)) {
        addError($errors, 'Setup required-field enforcement must be Native or Module-local.');
    }

    $activationConfirmation = latestField($changelog, 'Activation confirmation');
    if (
        $activationConfirmation === null
        || preg_match('/unknown|replace|pending|not applicable/i', $activationConfirmation) === 1
    ) {
        addError($errors, 'Nameserver activation confirmation is unresolved.');
    }

    if (isset($source)) {
        for ($index = 1; $index <= $minimumNameserverCount; $index++) {
            $fieldName = "Default NS{$index}";
            if (stripos($source, $fieldName) === false) {
                addError($errors, "Module setup is missing required field: Default NS{$index}");
                continue;
            }

            if (strtolower((string) $enforcement) === 'native') {
                $fieldPattern = '/(?:lang\(\s*)?([\'\"])'
                    . preg_quote($fieldName, '/')
                    . '\1\s*\)?\s*=>\s*\[(.*?)\]\s*,/is';
                if (
                    preg_match($fieldPattern, $source, $fieldMatch) !== 1
                    || preg_match(
                        '/[\'\"]required[\'\"]\s*=>\s*true/i',
                        $fieldMatch[2]
                    ) !== 1
                ) {
                    addError(
                        $errors,
                        "Native required enforcement is missing for setup field: {$fieldName}"
                    );
                }
            }
        }
    }

    foreach (['NS-SETUP-001', 'NS-MISSING-001', 'NS-ACTIVE-001'] as $nameserverTestId) {
        if (!in_array($nameserverTestId, $mandatoryIds, true)) {
            addError($errors, "Mandatory test list is missing nameserver ID: {$nameserverTestId}");
        }
    }

    foreach (['NS-SETUP-001', 'NS-ACTIVE-001'] as $nameserverKitUserId) {
        if (!in_array($nameserverKitUserId, $kitUserIds, true)) {
            addError($errors, "Kit-user test list is missing nameserver ID: {$nameserverKitUserId}");
        }
    }
}

if (!$allowIncomplete) {
    $latestRecords = [];
    foreach (preg_split('/^###\s+/m', $changelog) as $entry) {
        if (
            preg_match(
                '/^-\s*Test result \[([A-Z0-9-]+)\]:\s*(Passed|Failed|Skipped|Pending|Unavailable)\s*$/mi',
                $entry,
                $testMatch
            ) !== 1
        ) {
            continue;
        }

        preg_match('/^-\s*Test owner:\s*(.*?)\s*$/mi', $entry, $ownerMatch);
        preg_match('/^-\s*Sanitized kit-user response:\s*(.*?)\s*$/mi', $entry, $responseMatch);
        $latestRecords[strtoupper($testMatch[1])] = [
            'status' => ucfirst(strtolower($testMatch[2])),
            'owner' => trim($ownerMatch[1] ?? ''),
            'response' => trim($responseMatch[1] ?? ''),
        ];
    }

    foreach ($mandatoryIds as $mandatoryId) {
        $normalizedId = strtoupper($mandatoryId);
        if (($latestRecords[$normalizedId]['status'] ?? null) !== 'Passed') {
            addError($errors, "Latest mandatory test result is not Passed: {$normalizedId}");
        }
    }

    foreach ($kitUserIds as $kitUserId) {
        $normalizedId = strtoupper($kitUserId);
        $record = $latestRecords[$normalizedId] ?? null;
        if ($record === null || strcasecmp($record['owner'], 'Kit user') !== 0) {
            addError($errors, "Latest kit-user test record has the wrong or missing owner: {$normalizedId}");
            continue;
        }

        if (
            $record['response'] === ''
            || preg_match('/^(?:pending|none|n\/a|not provided|replace)/i', $record['response']) === 1
        ) {
            addError($errors, "Sanitized kit-user response is missing: {$normalizedId}");
        }
    }

    if (strcasecmp((string) latestField($changelog, 'Kit-user final checklist confirmation'), 'Confirmed') !== 0) {
        addError($errors, 'Kit-user final checklist confirmation is not Confirmed.');
    }

    if (strcasecmp((string) latestField($changelog, 'Production readiness'), 'Production-ready for approved scope') !== 0) {
        addError($errors, 'Production readiness is not Production-ready for approved scope.');
    }
}

if ($errors !== []) {
    fwrite(
        STDERR,
        sprintf("Generated registrar validation failed with %d issue(s):%s", count($errors), PHP_EOL)
    );
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}" . PHP_EOL);
    }
    exit(1);
}

echo $allowIncomplete
    ? 'Generated registrar structural validation passed; production checklist completion was not required.' . PHP_EOL
    : 'Generated registrar production validation passed.' . PHP_EOL;
exit(0);
