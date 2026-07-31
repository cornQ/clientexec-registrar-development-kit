#!/usr/bin/env php
<?php

declare(strict_types=1);

const MINIMUM_PHP_VERSION_ID = 70400;

/**
 * Print command usage.
 */
function printUsage(): void
{
    echo 'Usage: php -n scripts/validate-kit.php [--root <path>] [--skip-php-lint]', PHP_EOL;
}

/**
 * Join a root path and a repository-relative path.
 */
function repositoryPath(string $root, string $relativePath): string
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);

    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalized;
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

/**
 * Add a validation warning.
 *
 * @param array<int, string> $warnings
 */
function addWarning(array &$warnings, string $message): void
{
    $warnings[] = $message;
}

/**
 * Read a file after its existence has already been checked.
 */
function readTextFile(string $path): string
{
    $contents = file_get_contents($path);

    return $contents === false ? '' : $contents;
}

/**
 * Compare two string arrays without considering order.
 *
 * @param array<int, string> $left
 * @param array<int, string> $right
 */
function sameStringSet(array $left, array $right): bool
{
    sort($left);
    sort($right);

    return $left === $right;
}

/**
 * Run PHP syntax lint without loading php.ini or optional extensions.
 *
 * @return array{available: bool, exit_code: int, output: string}
 */
function lintPhpFile(string $path): array
{
    if (!function_exists('proc_open')) {
        return [
            'available' => false,
            'exit_code' => -1,
            'output' => 'proc_open() is unavailable.',
        ];
    }

    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $pipes = [];
    $process = @proc_open(
        [PHP_BINARY, '-n', '-l', $path],
        $descriptorSpec,
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );

    if (!is_resource($process)) {
        return [
            'available' => false,
            'exit_code' => -1,
            'output' => 'Unable to start the PHP lint subprocess.',
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'available' => true,
        'exit_code' => $exitCode,
        'output' => trim((string) $stdout . PHP_EOL . (string) $stderr),
    ];
}

/**
 * Find distributable Markdown files while excluding private or generated trees.
 *
 * @return array<int, string>
 */
function findMarkdownFiles(string $root): array
{
    $excludedDirectories = [
        '.git',
        '.idea',
        '.vscode',
        'build',
        'clientexec-core-files',
        'coverage',
        'dist',
        'real-sample',
        'sample-registrar',
        'vendor',
        'workspace',
    ];

    $directoryIterator = new RecursiveDirectoryIterator(
        $root,
        FilesystemIterator::SKIP_DOTS
    );
    $filterIterator = new RecursiveCallbackFilterIterator(
        $directoryIterator,
        static function (SplFileInfo $current) use ($excludedDirectories): bool {
            if (!$current->isDir()) {
                return true;
            }

            return !in_array($current->getFilename(), $excludedDirectories, true);
        }
    );
    $iterator = new RecursiveIteratorIterator($filterIterator);
    $files = [];

    foreach ($iterator as $file) {
        if (
            $file instanceof SplFileInfo
            && $file->isFile()
            && strtolower($file->getExtension()) === 'md'
        ) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

$root = dirname(__DIR__);
$skipPhpLint = false;
$arguments = array_slice($argv, 1);

for ($index = 0, $count = count($arguments); $index < $count; $index++) {
    $argument = $arguments[$index];

    if ($argument === '--help' || $argument === '-h') {
        printUsage();
        exit(0);
    }

    if ($argument === '--skip-php-lint') {
        $skipPhpLint = true;
        continue;
    }

    if ($argument === '--root') {
        if (!isset($arguments[$index + 1])) {
            fwrite(STDERR, "Missing value for --root." . PHP_EOL);
            printUsage();
            exit(2);
        }

        $root = $arguments[++$index];
        continue;
    }

    if (strpos($argument, '--root=') === 0) {
        $root = substr($argument, strlen('--root='));
        continue;
    }

    fwrite(STDERR, "Unknown option: {$argument}" . PHP_EOL);
    printUsage();
    exit(2);
}

if (PHP_VERSION_ID < MINIMUM_PHP_VERSION_ID) {
    fwrite(
        STDERR,
        sprintf(
            "PHP 7.4 or newer is required; current version is %s%s",
            PHP_VERSION,
            PHP_EOL
        )
    );
    exit(2);
}

$resolvedRoot = realpath($root);
if ($resolvedRoot === false || !is_dir($resolvedRoot)) {
    fwrite(STDERR, "Repository root does not exist: {$root}" . PHP_EOL);
    exit(2);
}
$root = $resolvedRoot;

$errors = [];
$warnings = [];
$expectedManifestKeys = [
    'registrar',
    'target',
    'sources',
    'requested_capabilities',
    'open_questions',
];
$expectedCapabilities = [
    'availability',
    'registration',
    'renewal',
    'transfer',
    'nameservers',
    'contacts',
    'dns',
    'registrar_lock',
    'epp',
    'privacy',
    'domain_import',
    'pricing_import',
    'name_suggestions',
    'extended_attributes',
];

$requiredFiles = [
    '.gitignore',
    '.github/workflows/validate.yml',
    '.github/ISSUE_TEMPLATE/bug_report.yml',
    '.github/ISSUE_TEMPLATE/config.yml',
    '.github/ISSUE_TEMPLATE/feature_request.yml',
    '.github/pull_request_template.md',
    'CONTRIBUTING.md',
    'README.md',
    'LICENSE',
    'SECURITY.md',
    'SKILL.md',
    'agents/openai.yaml',
    'references/official-sources.md',
    'references/source-intake.md',
    'references/runtime-api.md',
    'references/method-contracts.md',
    'references/features-and-actions.md',
    'references/extra-attributes.md',
    'references/security-and-testing.md',
    'scripts/audit-registrar-methods.ps1',
    'scripts/package-release.ps1',
    'scripts/validate-registrar-output.php',
    'scripts/validate-kit.php',
    'scripts/validate-kit.ps1',
    'scripts/inspect-clientexec-runtime.php',
    'assets/registrar-workspace-template/source-manifest.schema.json',
    'assets/registrar-workspace-template/source-manifest.yaml',
    'assets/registrar-workspace-template/inputs/notes/requirements.md',
    'assets/registrar-workspace-template/output/DEVELOPMENT-CHANGELOG.md',
    'assets/registrar-template/plugins/registrars/example/PluginExample.php',
    'assets/registrar-template/plugins/registrars/example/resource/plugin.ini',
];

foreach ($requiredFiles as $relativePath) {
    if (!is_file(repositoryPath($root, $relativePath))) {
        addError($errors, "Missing required file: {$relativePath}");
    }
}

$contributingPath = repositoryPath($root, 'CONTRIBUTING.md');
if (is_file($contributingPath)) {
    $contributing = readTextFile($contributingPath);

    foreach (
        [
            'SECURITY.md',
            'php -n scripts/validate-kit.php',
            'package-release.ps1',
            'Source provenance and licensing',
            'AI-assisted contributions',
        ] as $requiredText
    ) {
        if (strpos($contributing, $requiredText) === false) {
            addError(
                $errors,
                "CONTRIBUTING.md is missing required guidance: {$requiredText}"
            );
        }
    }
}

$securityPath = repositoryPath($root, 'SECURITY.md');
if (is_file($securityPath)) {
    $security = readTextFile($securityPath);

    foreach (
        [
            'Supported versions',
            'Reporting a vulnerability',
            'Report a vulnerability',
            'Do not disclose a suspected vulnerability in a public issue',
            'Security scope',
        ] as $requiredText
    ) {
        if (strpos($security, $requiredText) === false) {
            addError(
                $errors,
                "SECURITY.md is missing required guidance: {$requiredText}"
            );
        }
    }

    if (
        preg_match(
            '/security@example\.com|your[- ]email|email@example\.com/i',
            $security
        ) === 1
    ) {
        addError(
            $errors,
            'SECURITY.md must not contain a placeholder security contact.'
        );
    }
}

$workflowPath = repositoryPath($root, '.github/workflows/validate.yml');
if (is_file($workflowPath)) {
    $workflow = readTextFile($workflowPath);
    $requiredWorkflowPatterns = [
        'push trigger' => '/^  push:\s*$/m',
        'pull request trigger' => '/^  pull_request:\s*$/m',
        'manual trigger' => '/^  workflow_dispatch:\s*$/m',
        'read-only contents permission' => '/^  contents:\s*read\s*$/m',
        'PHP 7.4 matrix entry' => "/^\s+- '7\.4'\s*$/m",
        'PHP 8.5 matrix entry' => "/^\s+- '8\.5'\s*$/m",
        'PHP validator' => '/php -n scripts\/validate-kit\.php/',
        'PowerShell validator' => '/validate-kit\.ps1 -RequirePhp/',
        'release packaging smoke test' => '/package-release\.ps1/',
    ];

    foreach ($requiredWorkflowPatterns as $description => $pattern) {
        if (preg_match($pattern, $workflow) !== 1) {
            addError(
                $errors,
                "GitHub Actions workflow is missing {$description}."
            );
        }
    }

    if (preg_match('/^\s*pull_request_target:\s*$/m', $workflow) === 1) {
        addError(
            $errors,
            'GitHub Actions workflow must not use pull_request_target.'
        );
    }

    if (
        preg_match(
            '/^\s+(?:actions|checks|contents|deployments|id-token|issues|packages|pages|pull-requests|security-events|statuses):\s*write\s*$/im',
            $workflow
        ) === 1
    ) {
        addError(
            $errors,
            'GitHub Actions validation workflow must not request write permissions.'
        );
    }
}

$issueFormPaths = [
    '.github/ISSUE_TEMPLATE/bug_report.yml',
    '.github/ISSUE_TEMPLATE/feature_request.yml',
];
foreach ($issueFormPaths as $relativePath) {
    $fullPath = repositoryPath($root, $relativePath);
    if (!is_file($fullPath)) {
        continue;
    }

    $issueForm = readTextFile($fullPath);
    $requiredPatterns = [
        '/^name:\s*\S/m',
        '/^description:\s*\S/m',
        '/^body:\s*$/m',
        '/^\s+validations:\s*$/m',
        '/^\s+required:\s*true\s*$/m',
        '/SECURITY\.md/',
        '/credentials/i',
    ];

    foreach ($requiredPatterns as $pattern) {
        if (preg_match($pattern, $issueForm) !== 1) {
            addError(
                $errors,
                "GitHub issue form is missing a required safety or schema element: {$relativePath}"
            );
            break;
        }
    }

    preg_match_all(
        '/^\s+id:\s*([A-Za-z0-9_-]+)\s*$/m',
        $issueForm,
        $idMatches
    );
    if (count($idMatches[1]) !== count(array_unique($idMatches[1]))) {
        addError(
            $errors,
            "GitHub issue form contains duplicate field IDs: {$relativePath}"
        );
    }
}

$issueConfigPath = repositoryPath(
    $root,
    '.github/ISSUE_TEMPLATE/config.yml'
);
if (is_file($issueConfigPath)) {
    $issueConfig = readTextFile($issueConfigPath);
    if (
        preg_match(
            '/^blank_issues_enabled:\s*false\s*$/m',
            $issueConfig
        ) !== 1
    ) {
        addError(
            $errors,
            'GitHub issue chooser must disable blank contributor issues.'
        );
    }
}

$pullRequestTemplatePath = repositoryPath(
    $root,
    '.github/pull_request_template.md'
);
if (is_file($pullRequestTemplatePath)) {
    $pullRequestTemplate = readTextFile($pullRequestTemplatePath);

    foreach (
        [
            'Evidence and provenance',
            'Compatibility and security impact',
            'Validation performed',
            'AI assistance',
            'CONTRIBUTING.md',
            'SECURITY.md',
        ] as $requiredText
    ) {
        if (strpos($pullRequestTemplate, $requiredText) === false) {
            addError(
                $errors,
                "Pull request template is missing required guidance: {$requiredText}"
            );
        }
    }
}

$requiredDirectories = [
    'assets/registrar-workspace-template/inputs/api-docs',
    'assets/registrar-workspace-template/inputs/specs',
    'assets/registrar-workspace-template/inputs/postman',
    'assets/registrar-workspace-template/inputs/examples/requests',
    'assets/registrar-workspace-template/inputs/examples/responses',
    'assets/registrar-workspace-template/inputs/notes',
    'assets/registrar-workspace-template/output',
];

foreach ($requiredDirectories as $relativePath) {
    if (!is_dir(repositoryPath($root, $relativePath))) {
        addError($errors, "Missing required directory: {$relativePath}");
    }
}

$skillPath = repositoryPath($root, 'SKILL.md');
if (is_file($skillPath)) {
    $skill = readTextFile($skillPath);
    $skillLines = preg_split('/\R/', $skill);

    if (is_array($skillLines) && count($skillLines) > 500) {
        addError($errors, 'SKILL.md should remain under 500 lines.');
    }

    if (!preg_match('/\A---\R(?P<body>.*?)\R---\R/s', $skill, $frontmatterMatch)) {
        addError($errors, 'SKILL.md has invalid or missing YAML frontmatter.');
    } else {
        $frontmatter = $frontmatterMatch['body'];
        preg_match_all('/^([A-Za-z0-9_-]+):/m', $frontmatter, $keyMatches);
        $unexpectedKeys = array_values(
            array_diff($keyMatches[1], ['name', 'description'])
        );

        if ($unexpectedKeys !== []) {
            addError(
                $errors,
                'SKILL.md frontmatter contains unsupported keys: '
                . implode(', ', $unexpectedKeys)
            );
        }

        if (!preg_match('/^name:\s*([a-z0-9-]+)\s*$/m', $frontmatter, $nameMatch)) {
            addError(
                $errors,
                'SKILL.md name must contain only lowercase letters, digits, and hyphens.'
            );
        } elseif (strlen($nameMatch[1]) > 63) {
            addError($errors, 'SKILL.md name must be shorter than 64 characters.');
        }

        if (!preg_match('/^description:\s*\S/m', $frontmatter)) {
            addError($errors, 'SKILL.md description is missing or empty.');
        }
    }

    foreach (
        [
            'workspace/<registrar>/output/plugins/registrars/<registrar>/',
            'output/DEVELOPMENT-CHANGELOG.md',
            'Never recommend, provide, or apply a Clientexec core',
            '## Gate troubleshooting commands',
            'Wait for the kit user to acknowledge the declaration',
            'Every command shown to, requested from, or run for the kit user during module development must be read-only',
            '## Gate production readiness',
            'Production-ready for approved scope',
            'Keep every API endpoint, base URL, and equivalent routing value non-editable',
            'nameservers are required for registration or activation',
        ] as $requiredSafetyText
    ) {
        if (strpos($skill, $requiredSafetyText) === false) {
            addError(
                $errors,
                "SKILL.md is missing required module safety guidance: {$requiredSafetyText}"
            );
        }
    }
}

$developmentChangelogPath = repositoryPath(
    $root,
    'assets/registrar-workspace-template/output/DEVELOPMENT-CHANGELOG.md'
);
if (is_file($developmentChangelogPath)) {
    $developmentChangelog = readTextFile($developmentChangelogPath);

    foreach (
        [
            '# Development Changelog',
            'append-only durable memory',
            'before changing module code',
            'before every resumed session and phase',
            '## Phase 0',
            'Module write root:',
            'Only non-module write:',
            'Troubleshooting declaration:',
            'Read-only command purpose and sanitized result:',
            'Nameserver activation requirement:',
            'Required setup nameserver fields:',
            'Endpoint visibility:',
            'Mandatory test IDs:',
            'Kit-user test IDs:',
            'Test result [TEST-ID]:',
            'Test owner:',
            'Sanitized kit-user response:',
            'Kit-user final checklist confirmation:',
            'Production readiness:',
            'Next action:',
        ] as $requiredChangelogText
    ) {
        if (strpos($developmentChangelog, $requiredChangelogText) === false) {
            addError(
                $errors,
                "Development changelog template is missing required memory field: {$requiredChangelogText}"
            );
        }
    }
}

$requirementsTemplatePath = repositoryPath(
    $root,
    'assets/registrar-workspace-template/inputs/notes/requirements.md'
);
if (is_file($requirementsTemplatePath)) {
    $requirementsTemplate = readTextFile($requirementsTemplatePath);
    foreach (
        [
            '## Registration and activation nameservers',
            'Nameservers required to reach active status:',
            'Required setup nameserver fields:',
            'Setup-page native required-field support:',
            '## Endpoint visibility',
            'Editable endpoint permitted: no',
            '## Final production acceptance',
            'Mandatory test IDs:',
            'Kit-user test IDs:',
        ] as $requiredRequirementsText
    ) {
        if (strpos($requirementsTemplate, $requiredRequirementsText) === false) {
            addError(
                $errors,
                "Requirements template is missing required planning field: {$requiredRequirementsText}"
            );
        }
    }
}

$outputValidatorPath = repositoryPath($root, 'scripts/validate-registrar-output.php');
if (is_file($outputValidatorPath)) {
    $outputValidator = readTextFile($outputValidatorPath);
    foreach (
        [
            '--allow-incomplete',
            'ENDPOINT-HIDDEN-001',
            'NS-ACTIVE-001',
            'Kit-user final checklist confirmation',
            'Kit-user test IDs',
            'Production-ready for approved scope',
        ] as $requiredOutputValidatorText
    ) {
        if (strpos($outputValidator, $requiredOutputValidatorText) === false) {
            addError(
                $errors,
                "Generated-module validator is missing required gate: {$requiredOutputValidatorText}"
            );
        }
    }

    if (
        preg_match(
            '/\b(?:file_put_contents|unlink|rename|copy|mkdir|rmdir)\s*\(/i',
            $outputValidator
        ) === 1
    ) {
        addError($errors, 'Generated-module validator must remain read-only.');
    }
}

$requiredSafetyGuidance = [
    'references/phase-workflow.md' => [
        'Before providing or running a troubleshooting command:',
        'Do not suggest a separate core-change task as a workaround for module development.',
        'Use `output/DEVELOPMENT-CHANGELOG.md` as append-only durable memory from the beginning of development:',
    ],
    'references/security-and-testing.md' => [
        '## Read-only troubleshooting commands',
        'Do not provide a dangerous command even as an optional suggestion.',
        'the only recursive write root is `workspace/<registrar>/output/plugins/registrars/<registrar>/`',
    ],
    'references/source-intake.md' => [
        'Append a completed Phase 0 entry immediately after workspace initialization and before the first module-code change;',
        'At the start of every resumed session and every phase, read the entire changelog',
        'Treat the changelog as append-only memory.',
    ],
    'references/features-and-actions.md' => [
        '### Endpoint fields',
        'Never expose an API endpoint, base URL, hostname, or equivalent routing value',
        '### Conditional default nameservers',
    ],
    'references/method-contracts.md' => [
        'verify the target Clientexec version\'s registration shape',
        'Treat an accepted order as pending until the documented status check proves the required active state',
    ],
];

foreach ($requiredSafetyGuidance as $relativePath => $requiredTexts) {
    $guidancePath = repositoryPath($root, $relativePath);
    if (!is_file($guidancePath)) {
        continue;
    }

    $guidance = readTextFile($guidancePath);
    foreach ($requiredTexts as $requiredText) {
        if (strpos($guidance, $requiredText) === false) {
            addError(
                $errors,
                "{$relativePath} is missing required safety guidance: {$requiredText}"
            );
        }
    }
}

$gitignorePath = repositoryPath($root, '.gitignore');
if (is_file($gitignorePath)) {
    $gitignore = readTextFile($gitignorePath);

    foreach (
        [
            '/clientexec-core-files/',
            '/real-sample/',
            '/sample-registrar/',
            '/workspace/',
            'dist/',
            'vendor/',
            'node_modules/',
            '.env.*',
            '*.pem',
            '*.sql',
            '*.zip',
        ] as $requiredPattern
    ) {
        if (
            preg_match(
                '/^' . preg_quote($requiredPattern, '/') . '\s*$/m',
                $gitignore
            ) !== 1
        ) {
            addError(
                $errors,
                ".gitignore is missing required exclusion: {$requiredPattern}"
            );
        }
    }

    if (preg_match('/^composer\.lock\s*$/m', $gitignore) === 1) {
        addError(
            $errors,
            '.gitignore must not hide composer.lock from review.'
        );
    }

    if (preg_match('/^!\.env(?:\.|$)/m', $gitignore) === 1) {
        addError(
            $errors,
            '.gitignore must not re-include environment files.'
        );
    }
}

$sourceSchemaPath = repositoryPath(
    $root,
    'assets/registrar-workspace-template/source-manifest.schema.json'
);
if (is_file($sourceSchemaPath)) {
    $sourceSchema = json_decode(readTextFile($sourceSchemaPath), true);

    if (!is_array($sourceSchema) || json_last_error() !== JSON_ERROR_NONE) {
        addError(
            $errors,
            'Workspace source manifest schema is invalid JSON: '
            . json_last_error_msg()
        );
    } else {
        if (
            ($sourceSchema['$schema'] ?? null)
            !== 'https://json-schema.org/draft/2020-12/schema'
        ) {
            addError(
                $errors,
                'Workspace source manifest schema must use JSON Schema draft 2020-12.'
            );
        }

        if (
            ($sourceSchema['type'] ?? null) !== 'object'
            || ($sourceSchema['additionalProperties'] ?? null) !== false
        ) {
            addError(
                $errors,
                'Workspace source manifest schema must be a closed object.'
            );
        }

        $schemaRequired = $sourceSchema['required'] ?? null;
        if (
            !is_array($schemaRequired)
            || !sameStringSet(array_values($schemaRequired), $expectedManifestKeys)
        ) {
            addError(
                $errors,
                'Workspace source manifest schema has an unexpected top-level required set.'
            );
        }

        $capabilitySchema = $sourceSchema['properties']['requested_capabilities'] ?? null;
        if (
            !is_array($capabilitySchema)
            || ($capabilitySchema['type'] ?? null) !== 'object'
            || ($capabilitySchema['additionalProperties'] ?? null) !== false
        ) {
            addError(
                $errors,
                'Workspace capability schema must be a closed object.'
            );
        } else {
            $schemaCapabilityRequired = $capabilitySchema['required'] ?? null;
            if (
                !is_array($schemaCapabilityRequired)
                || !sameStringSet(
                    array_values($schemaCapabilityRequired),
                    $expectedCapabilities
                )
            ) {
                addError(
                    $errors,
                    'Workspace capability schema has an unexpected required set.'
                );
            }

            $capabilityProperties = $capabilitySchema['properties'] ?? null;
            if (
                !is_array($capabilityProperties)
                || !sameStringSet(
                    array_keys($capabilityProperties),
                    $expectedCapabilities
                )
            ) {
                addError(
                    $errors,
                    'Workspace capability schema properties do not match the kit contract.'
                );
            } else {
                foreach ($expectedCapabilities as $capability) {
                    if (($capabilityProperties[$capability]['type'] ?? null) !== 'boolean') {
                        addError(
                            $errors,
                            "Workspace capability schema must define {$capability} as boolean."
                        );
                    }
                }
            }
        }
    }
}

$sourceManifestPath = repositoryPath(
    $root,
    'assets/registrar-workspace-template/source-manifest.yaml'
);
if (is_file($sourceManifestPath)) {
    $sourceManifest = readTextFile($sourceManifestPath);

    if (
        strpos(
            $sourceManifest,
            '# yaml-language-server: $schema=source-manifest.schema.json'
        ) !== 0
    ) {
        addError(
            $errors,
            'Workspace source manifest must reference the adjacent JSON Schema.'
        );
    }

    foreach ($expectedManifestKeys as $manifestKey) {
        if (!preg_match('/^' . preg_quote($manifestKey, '/') . ':\s*/m', $sourceManifest)) {
            addError($errors, "Workspace source manifest is missing key: {$manifestKey}");
        }
    }

    preg_match_all('/^([A-Za-z0-9_-]+):/m', $sourceManifest, $topLevelMatches);
    if (!sameStringSet(array_values($topLevelMatches[1]), $expectedManifestKeys)) {
        addError(
            $errors,
            'Workspace source manifest has unexpected or duplicate top-level keys.'
        );
    }

    if (!preg_match('/^\s{2}id:\s*[a-z0-9]+\s*$/m', $sourceManifest)) {
        addError($errors, 'Workspace source manifest registrar.id is invalid.');
    }

    if (!preg_match('/^\s{2}display_name:\s*\S.*$/m', $sourceManifest)) {
        addError($errors, 'Workspace source manifest registrar.display_name is missing.');
    }

    foreach (['clientexec_version', 'php_versions', 'sandbox_available'] as $targetKey) {
        if (
            !preg_match(
                '/^\s{2}' . preg_quote($targetKey, '/') . ':\s*\S.*$/m',
                $sourceManifest
            )
        ) {
            addError(
                $errors,
                "Workspace source manifest target.{$targetKey} is missing."
            );
        }
    }

    if (!preg_match('/^sources:\s*\[\]\s*$/m', $sourceManifest)) {
        addError(
            $errors,
            'Workspace source manifest template sources must default to an empty array.'
        );
    }

    foreach ($expectedCapabilities as $capability) {
        if (
            !preg_match(
                '/^\s{2}' . preg_quote($capability, '/') . ':\s*false\s*$/m',
                $sourceManifest
            )
        ) {
            addError(
                $errors,
                "Workspace source manifest capability must default to false: {$capability}"
            );
        }
    }

    if (!preg_match('/^open_questions:\s*\[\]\s*$/m', $sourceManifest)) {
        addError(
            $errors,
            'Workspace source manifest open_questions must default to an empty array.'
        );
    }

    if (
        preg_match(
            '/^\s*(api[_-]?key|secret|password|token|cookie):\s*\S+/im',
            $sourceManifest
        )
    ) {
        addError(
            $errors,
            'Workspace source manifest appears to contain a credential field with a value.'
        );
    }
}

$openaiPath = repositoryPath($root, 'agents/openai.yaml');
if (is_file($openaiPath)) {
    $openai = readTextFile($openaiPath);

    foreach (['display_name', 'short_description', 'default_prompt'] as $field) {
        if (
            !preg_match(
                '/^\s+' . preg_quote($field, '/') . ':\s+"[^"]+"\s*$/m',
                $openai
            )
        ) {
            addError($errors, "agents/openai.yaml must contain a quoted {$field}.");
        }
    }

    if (strpos($openai, '$clientexec-registrar-development-kit') === false) {
        addError(
            $errors,
            'agents/openai.yaml default_prompt must mention $clientexec-registrar-development-kit.'
        );
    }
}

$pluginPath = repositoryPath(
    $root,
    'assets/registrar-template/plugins/registrars/example/PluginExample.php'
);
if (is_file($pluginPath)) {
    $plugin = readTextFile($pluginPath);
    $requiredMethods = [
        'checkDomain',
        'registerDomain',
        'getContactInformation',
        'setContactInformation',
        'getNameServers',
        'setNameServers',
        'getGeneralInfo',
        'setAutorenew',
        'getRegistrarLock',
        'setRegistrarLock',
        'sendTransferKey',
    ];

    foreach ($requiredMethods as $method) {
        if (
            !preg_match(
                '/public\s+function\s+' . preg_quote($method, '/') . '\s*\(/',
                $plugin
            )
        ) {
            addError(
                $errors,
                "Template is missing abstract method implementation: {$method}"
            );
        }
    }

    foreach (['nameSuggest', 'importDomains', 'importPrices'] as $feature) {
        if (
            !preg_match(
                "/'" . preg_quote($feature, '/') . "'\s*=>\s*false/",
                $plugin
            )
        ) {
            addError($errors, "Template feature must default to false: {$feature}");
        }
    }

    if (
        preg_match(
            '/(api[_ -]?key|secret|password)\s*=\s*[\'"][^\'"]+[\'"]/i',
            $plugin
        )
    ) {
        addError($errors, 'Template appears to contain a hard-coded credential.');
    }

    if (
        preg_match('/private\s+const\s+PRODUCTION_API_URL\s*=/', $plugin) !== 1
        || preg_match('/private\s+const\s+SANDBOX_API_URL\s*=/', $plugin) !== 1
    ) {
        addError($errors, 'Template must define private production and sandbox endpoint constants.');
    }

    if (
        preg_match(
            '/[\'\"]API URL[\'\"]\s*=>\s*\[.*?[\'\"]type[\'\"]\s*=>\s*[\'\"]hidden[\'\"]/s',
            $plugin
        ) !== 1
    ) {
        addError($errors, 'Template API URL field must be hidden.');
    }
}

$iniPath = repositoryPath(
    $root,
    'assets/registrar-template/plugins/registrars/example/resource/plugin.ini'
);
if (is_file($iniPath)) {
    $ini = readTextFile($iniPath);

    foreach (
        ['register', 'transfer', 'renew', 'domainsync', 'pricingsync', 'registrarlock']
        as $feature
    ) {
        if (
            !preg_match(
                '/^' . preg_quote($feature, '/') . '\s*=\s*0\s*$/m',
                $ini
            )
        ) {
            addError($errors, "plugin.ini feature must default to 0: {$feature}");
        }
    }
}

foreach (findMarkdownFiles($root) as $markdownPath) {
    $markdown = readTextFile($markdownPath);
    preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $markdown, $linkMatches);

    foreach ($linkMatches[1] as $rawTarget) {
        $target = trim($rawTarget);

        if (
            preg_match('/^(https?:\/\/|mailto:|#|<|\$)/', $target)
        ) {
            continue;
        }

        $pathPart = explode('#', $target, 2)[0];
        if (trim($pathPart) === '') {
            continue;
        }

        $resolvedLink = repositoryPath(dirname($markdownPath), $pathPart);
        if (!file_exists($resolvedLink)) {
            $relativeMarkdown = ltrim(substr($markdownPath, strlen($root)), '/\\');
            addError(
                $errors,
                "Broken Markdown link in {$relativeMarkdown}: {$target}"
            );
        }
    }
}

if (!$skipPhpLint) {
    $phpFiles = [
        'scripts/validate-kit.php',
        'scripts/validate-registrar-output.php',
        'scripts/inspect-clientexec-runtime.php',
        'assets/registrar-template/plugins/registrars/example/PluginExample.php',
    ];
    $lintAvailable = true;

    foreach ($phpFiles as $relativePath) {
        $phpPath = repositoryPath($root, $relativePath);
        if (!is_file($phpPath)) {
            continue;
        }

        $lintResult = lintPhpFile($phpPath);
        if (!$lintResult['available']) {
            $lintAvailable = false;
            addWarning(
                $warnings,
                'PHP subprocess lint was skipped: ' . $lintResult['output']
            );
            break;
        }

        if ($lintResult['exit_code'] !== 0) {
            addError(
                $errors,
                "PHP syntax check failed for {$relativePath}: {$lintResult['output']}"
            );
        }
    }

    if (!$lintAvailable) {
        addWarning(
            $warnings,
            'Run php -n -l manually for each PHP file before release.'
        );
    }
}

foreach ($warnings as $warning) {
    fwrite(STDERR, "WARNING: {$warning}" . PHP_EOL);
}

if ($errors !== []) {
    fwrite(
        STDERR,
        sprintf(
            "Validation failed with %d issue(s):%s",
            count($errors),
            PHP_EOL
        )
    );

    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}" . PHP_EOL);
    }

    exit(1);
}

echo 'Clientexec registrar kit validation passed.', PHP_EOL;
exit(0);
