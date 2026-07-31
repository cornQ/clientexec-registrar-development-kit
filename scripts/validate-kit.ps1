[CmdletBinding()]
param(
    [string]$Root,

    [switch]$RequirePhp
)

$ErrorActionPreference = 'Stop'
$scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($Root)) {
    $Root = Split-Path -Parent $scriptDirectory
}
$rootPath = [System.IO.Path]::GetFullPath($Root)
$errors = [System.Collections.Generic.List[string]]::new()
$expectedManifestKeys = @(
    'registrar',
    'target',
    'sources',
    'requested_capabilities',
    'open_questions'
)
$expectedCapabilities = @(
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
    'extended_attributes'
)

function Add-ValidationError {
    param([string]$Message)
    $script:errors.Add($Message)
}

$requiredPaths = @(
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
    'assets/registrar-template/plugins/registrars/example/resource/plugin.ini'
)

foreach ($relativePath in $requiredPaths) {
    $fullPath = Join-Path $rootPath $relativePath
    if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) {
        Add-ValidationError "Missing required file: $relativePath"
    }
}

foreach ($relativePath in @(
    'scripts/audit-registrar-methods.ps1',
    'scripts/package-release.ps1',
    'scripts/validate-kit.ps1'
)) {
    $fullPath = Join-Path $rootPath $relativePath
    if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) {
        continue
    }

    $tokens = $null
    $parseErrors = $null
    [System.Management.Automation.Language.Parser]::ParseFile(
        $fullPath,
        [ref]$tokens,
        [ref]$parseErrors
    ) | Out-Null
    foreach ($parseError in $parseErrors) {
        Add-ValidationError "PowerShell syntax error in ${relativePath}: $($parseError.Message)"
    }
}

$requiredDirectories = @(
    'assets/registrar-workspace-template/inputs/api-docs',
    'assets/registrar-workspace-template/inputs/specs',
    'assets/registrar-workspace-template/inputs/postman',
    'assets/registrar-workspace-template/inputs/examples/requests',
    'assets/registrar-workspace-template/inputs/examples/responses',
    'assets/registrar-workspace-template/inputs/notes',
    'assets/registrar-workspace-template/output'
)

foreach ($relativePath in $requiredDirectories) {
    $fullPath = Join-Path $rootPath $relativePath
    if (-not (Test-Path -LiteralPath $fullPath -PathType Container)) {
        Add-ValidationError "Missing required directory: $relativePath"
    }
}

$skillPath = Join-Path $rootPath 'SKILL.md'
if (Test-Path -LiteralPath $skillPath -PathType Leaf) {
    $skill = Get-Content -Raw -LiteralPath $skillPath
    if (($skill -split "\r?\n").Count -gt 500) {
        Add-ValidationError 'SKILL.md should remain under 500 lines.'
    }

    $frontmatterMatch = [regex]::Match(
        $skill,
        '\A---\r?\n(?<body>.*?)\r?\n---\r?\n',
        [System.Text.RegularExpressions.RegexOptions]::Singleline
    )

    if (-not $frontmatterMatch.Success) {
        Add-ValidationError 'SKILL.md has invalid or missing YAML frontmatter.'
    } else {
        $frontmatter = $frontmatterMatch.Groups['body'].Value
        $keys = [regex]::Matches($frontmatter, '(?m)^(?<key>[A-Za-z0-9_-]+):') |
            ForEach-Object { $_.Groups['key'].Value }

        $unexpected = @($keys | Where-Object { $_ -notin @('name', 'description') })
        if ($unexpected.Count -gt 0) {
            Add-ValidationError "SKILL.md frontmatter contains unsupported keys: $($unexpected -join ', ')"
        }

        $nameMatch = [regex]::Match($frontmatter, '(?m)^name:\s*(?<name>[a-z0-9-]+)\s*$')
        if (-not $nameMatch.Success) {
            Add-ValidationError 'SKILL.md name must contain only lowercase letters, digits, and hyphens.'
        } elseif ($nameMatch.Groups['name'].Value.Length -gt 63) {
            Add-ValidationError 'SKILL.md name must be shorter than 64 characters.'
        }

        if (-not [regex]::IsMatch($frontmatter, '(?m)^description:\s*\S')) {
            Add-ValidationError 'SKILL.md description is missing or empty.'
        }
    }

    foreach ($requiredSafetyText in @(
        'workspace/<registrar>/output/plugins/registrars/<registrar>/',
        'output/DEVELOPMENT-CHANGELOG.md',
        'Never recommend, provide, or apply a Clientexec core',
        '## Gate troubleshooting commands',
        'Wait for the kit user to acknowledge the declaration',
        'Every command shown to, requested from, or run for the kit user during module development must be read-only',
        '## Gate production readiness',
        'Production-ready for approved scope',
        'Keep every API endpoint, base URL, and equivalent routing value non-editable',
        'nameservers are required for registration or activation'
    )) {
        if (-not $skill.Contains($requiredSafetyText)) {
            Add-ValidationError "SKILL.md is missing required module safety guidance: $requiredSafetyText"
        }
    }
}

$developmentChangelogPath = Join-Path $rootPath 'assets/registrar-workspace-template/output/DEVELOPMENT-CHANGELOG.md'
if (Test-Path -LiteralPath $developmentChangelogPath -PathType Leaf) {
    $developmentChangelog = Get-Content -Raw -LiteralPath $developmentChangelogPath
    foreach ($requiredChangelogText in @(
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
        'Next action:'
    )) {
        if (-not $developmentChangelog.Contains($requiredChangelogText)) {
            Add-ValidationError "Development changelog template is missing required memory field: $requiredChangelogText"
        }
    }
}

$requirementsTemplatePath = Join-Path $rootPath 'assets/registrar-workspace-template/inputs/notes/requirements.md'
if (Test-Path -LiteralPath $requirementsTemplatePath -PathType Leaf) {
    $requirementsTemplate = Get-Content -Raw -LiteralPath $requirementsTemplatePath
    foreach ($requiredRequirementsText in @(
        '## Registration and activation nameservers',
        'Nameservers required to reach active status:',
        'Required setup nameserver fields:',
        'Setup-page native required-field support:',
        '## Endpoint visibility',
        'Editable endpoint permitted: no',
        '## Final production acceptance',
        'Mandatory test IDs:',
        'Kit-user test IDs:'
    )) {
        if (-not $requirementsTemplate.Contains($requiredRequirementsText)) {
            Add-ValidationError "Requirements template is missing required planning field: $requiredRequirementsText"
        }
    }
}

$outputValidatorPath = Join-Path $rootPath 'scripts/validate-registrar-output.php'
if (Test-Path -LiteralPath $outputValidatorPath -PathType Leaf) {
    $outputValidator = Get-Content -Raw -LiteralPath $outputValidatorPath
    foreach ($requiredOutputValidatorText in @(
        '--allow-incomplete',
        'ENDPOINT-HIDDEN-001',
        'NS-ACTIVE-001',
        'Kit-user final checklist confirmation',
        'Kit-user test IDs',
        'Production-ready for approved scope'
    )) {
        if (-not $outputValidator.Contains($requiredOutputValidatorText)) {
            Add-ValidationError "Generated-module validator is missing required gate: $requiredOutputValidatorText"
        }
    }

    if ([regex]::IsMatch($outputValidator, '(?i)\b(?:file_put_contents|unlink|rename|copy|mkdir|rmdir)\s*\(')) {
        Add-ValidationError 'Generated-module validator must remain read-only.'
    }
}

$requiredSafetyGuidance = @{
    'references/phase-workflow.md' = @(
        'Before providing or running a troubleshooting command:',
        'Do not suggest a separate core-change task as a workaround for module development.',
        'Use `output/DEVELOPMENT-CHANGELOG.md` as append-only durable memory from the beginning of development:'
    )
    'references/security-and-testing.md' = @(
        '## Read-only troubleshooting commands',
        'Do not provide a dangerous command even as an optional suggestion.',
        'the only recursive write root is `workspace/<registrar>/output/plugins/registrars/<registrar>/`'
    )
    'references/source-intake.md' = @(
        'Append a completed Phase 0 entry immediately after workspace initialization and before the first module-code change;',
        'At the start of every resumed session and every phase, read the entire changelog',
        'Treat the changelog as append-only memory.'
    )
    'references/features-and-actions.md' = @(
        '### Endpoint fields',
        'Never expose an API endpoint, base URL, hostname, or equivalent routing value',
        '### Conditional default nameservers'
    )
    'references/method-contracts.md' = @(
        "verify the target Clientexec version's registration shape",
        'Treat an accepted order as pending until the documented status check proves the required active state'
    )
}

foreach ($relativePath in $requiredSafetyGuidance.Keys) {
    $guidancePath = Join-Path $rootPath $relativePath
    if (-not (Test-Path -LiteralPath $guidancePath -PathType Leaf)) {
        continue
    }

    $guidance = Get-Content -Raw -LiteralPath $guidancePath
    foreach ($requiredText in $requiredSafetyGuidance[$relativePath]) {
        if (-not $guidance.Contains($requiredText)) {
            Add-ValidationError "${relativePath} is missing required safety guidance: $requiredText"
        }
    }
}

$gitignorePath = Join-Path $rootPath '.gitignore'
if (Test-Path -LiteralPath $gitignorePath -PathType Leaf) {
    $gitignore = Get-Content -Raw -LiteralPath $gitignorePath
    foreach ($requiredPattern in @(
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
        '*.zip'
    )) {
        $pattern = "(?m)^$([regex]::Escape($requiredPattern))\s*$"
        if (-not [regex]::IsMatch($gitignore, $pattern)) {
            Add-ValidationError ".gitignore is missing required exclusion: $requiredPattern"
        }
    }

    if ([regex]::IsMatch($gitignore, '(?m)^composer\.lock\s*$')) {
        Add-ValidationError '.gitignore must not hide composer.lock from review.'
    }

    if ([regex]::IsMatch($gitignore, '(?m)^!\.env(?:\.|$)')) {
        Add-ValidationError '.gitignore must not re-include environment files.'
    }
}

$contributingPath = Join-Path $rootPath 'CONTRIBUTING.md'
if (Test-Path -LiteralPath $contributingPath -PathType Leaf) {
    $contributing = Get-Content -Raw -LiteralPath $contributingPath
    foreach ($requiredText in @(
        'SECURITY.md',
        'php -n scripts/validate-kit.php',
        'package-release.ps1',
        'Source provenance and licensing',
        'AI-assisted contributions'
    )) {
        if (-not $contributing.Contains($requiredText)) {
            Add-ValidationError "CONTRIBUTING.md is missing required guidance: $requiredText"
        }
    }
}

$securityPath = Join-Path $rootPath 'SECURITY.md'
if (Test-Path -LiteralPath $securityPath -PathType Leaf) {
    $security = Get-Content -Raw -LiteralPath $securityPath
    foreach ($requiredText in @(
        'Supported versions',
        'Reporting a vulnerability',
        'Report a vulnerability',
        'Do not disclose a suspected vulnerability in a public issue',
        'Security scope'
    )) {
        if (-not $security.Contains($requiredText)) {
            Add-ValidationError "SECURITY.md is missing required guidance: $requiredText"
        }
    }

    if (
        [regex]::IsMatch(
            $security,
            '(?i)(security@example\.com|your[- ]email|email@example\.com)'
        )
    ) {
        Add-ValidationError 'SECURITY.md must not contain a placeholder security contact.'
    }
}

$workflowPath = Join-Path $rootPath '.github/workflows/validate.yml'
if (Test-Path -LiteralPath $workflowPath -PathType Leaf) {
    $workflow = Get-Content -Raw -LiteralPath $workflowPath
    $requiredWorkflowPatterns = @{
        'push trigger' = '(?m)^\s{2}push:\s*$'
        'pull request trigger' = '(?m)^\s{2}pull_request:\s*$'
        'manual trigger' = '(?m)^\s{2}workflow_dispatch:\s*$'
        'read-only contents permission' = '(?m)^\s{2}contents:\s*read\s*$'
        'PHP 7.4 matrix entry' = "(?m)^\s+- '7\.4'\s*$"
        'PHP 8.5 matrix entry' = "(?m)^\s+- '8\.5'\s*$"
        'PHP validator' = 'php -n scripts/validate-kit\.php'
        'PowerShell validator' = 'validate-kit\.ps1 -RequirePhp'
        'release packaging smoke test' = 'package-release\.ps1'
    }

    foreach ($description in $requiredWorkflowPatterns.Keys) {
        if (
            -not [regex]::IsMatch(
                $workflow,
                $requiredWorkflowPatterns[$description]
            )
        ) {
            Add-ValidationError "GitHub Actions workflow is missing $description."
        }
    }

    if ([regex]::IsMatch($workflow, '(?m)^\s*pull_request_target:\s*$')) {
        Add-ValidationError 'GitHub Actions workflow must not use pull_request_target.'
    }

    if (
        [regex]::IsMatch(
            $workflow,
            '(?im)^\s+(?:actions|checks|contents|deployments|id-token|issues|packages|pages|pull-requests|security-events|statuses):\s*write\s*$'
        )
    ) {
        Add-ValidationError 'GitHub Actions validation workflow must not request write permissions.'
    }
}

$issueFormPaths = @(
    '.github/ISSUE_TEMPLATE/bug_report.yml',
    '.github/ISSUE_TEMPLATE/feature_request.yml'
)
foreach ($relativePath in $issueFormPaths) {
    $fullPath = Join-Path $rootPath $relativePath
    if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) {
        continue
    }

    $issueForm = Get-Content -Raw -LiteralPath $fullPath
    foreach ($pattern in @(
        '(?m)^name:\s*\S',
        '(?m)^description:\s*\S',
        '(?m)^body:\s*$',
        '(?m)^\s+validations:\s*$',
        '(?m)^\s+required:\s*true\s*$',
        'SECURITY\.md',
        '(?i)credentials'
    )) {
        if (-not [regex]::IsMatch($issueForm, $pattern)) {
            Add-ValidationError "GitHub issue form is missing a required safety or schema element: $relativePath"
            break
        }
    }

    $ids = @(
        [regex]::Matches($issueForm, '(?m)^\s+id:\s*(?<id>[A-Za-z0-9_-]+)\s*$') |
            ForEach-Object { $_.Groups['id'].Value }
    )
    if ($ids.Count -ne @($ids | Sort-Object -Unique).Count) {
        Add-ValidationError "GitHub issue form contains duplicate field IDs: $relativePath"
    }
}

$issueConfigPath = Join-Path $rootPath '.github/ISSUE_TEMPLATE/config.yml'
if (Test-Path -LiteralPath $issueConfigPath -PathType Leaf) {
    $issueConfig = Get-Content -Raw -LiteralPath $issueConfigPath
    if (
        -not [regex]::IsMatch(
            $issueConfig,
            '(?m)^blank_issues_enabled:\s*false\s*$'
        )
    ) {
        Add-ValidationError 'GitHub issue chooser must disable blank contributor issues.'
    }
}

$pullRequestTemplatePath = Join-Path $rootPath '.github/pull_request_template.md'
if (Test-Path -LiteralPath $pullRequestTemplatePath -PathType Leaf) {
    $pullRequestTemplate = Get-Content -Raw -LiteralPath $pullRequestTemplatePath
    foreach ($requiredText in @(
        'Evidence and provenance',
        'Compatibility and security impact',
        'Validation performed',
        'AI assistance',
        'CONTRIBUTING.md',
        'SECURITY.md'
    )) {
        if (-not $pullRequestTemplate.Contains($requiredText)) {
            Add-ValidationError "Pull request template is missing required guidance: $requiredText"
        }
    }
}

$sourceManifestPath = Join-Path $rootPath 'assets/registrar-workspace-template/source-manifest.yaml'
$sourceSchemaPath = Join-Path $rootPath 'assets/registrar-workspace-template/source-manifest.schema.json'

if (Test-Path -LiteralPath $sourceSchemaPath -PathType Leaf) {
    try {
        $sourceSchema = Get-Content -Raw -LiteralPath $sourceSchemaPath |
            ConvertFrom-Json -ErrorAction Stop

        if ($sourceSchema.'$schema' -ne 'https://json-schema.org/draft/2020-12/schema') {
            Add-ValidationError 'Workspace source manifest schema must use JSON Schema draft 2020-12.'
        }

        if ($sourceSchema.type -ne 'object' -or $sourceSchema.additionalProperties -ne $false) {
            Add-ValidationError 'Workspace source manifest schema must be a closed object.'
        }

        $topLevelDifference = @(
            Compare-Object `
                -ReferenceObject ($expectedManifestKeys | Sort-Object) `
                -DifferenceObject (@($sourceSchema.required) | Sort-Object)
        )
        if ($topLevelDifference.Count -gt 0) {
            Add-ValidationError 'Workspace source manifest schema has an unexpected top-level required set.'
        }

        $capabilitySchema = $sourceSchema.properties.requested_capabilities
        if (
            $capabilitySchema.type -ne 'object' -or
            $capabilitySchema.additionalProperties -ne $false
        ) {
            Add-ValidationError 'Workspace capability schema must be a closed object.'
        }

        $capabilityRequiredDifference = @(
            Compare-Object `
                -ReferenceObject ($expectedCapabilities | Sort-Object) `
                -DifferenceObject (@($capabilitySchema.required) | Sort-Object)
        )
        if ($capabilityRequiredDifference.Count -gt 0) {
            Add-ValidationError 'Workspace capability schema has an unexpected required set.'
        }

        $schemaCapabilityProperties = @(
            $capabilitySchema.properties.PSObject.Properties.Name
        )
        $capabilityPropertyDifference = @(
            Compare-Object `
                -ReferenceObject ($expectedCapabilities | Sort-Object) `
                -DifferenceObject ($schemaCapabilityProperties | Sort-Object)
        )
        if ($capabilityPropertyDifference.Count -gt 0) {
            Add-ValidationError 'Workspace capability schema properties do not match the kit contract.'
        }

        foreach ($capability in $expectedCapabilities) {
            if ($capabilitySchema.properties.$capability.type -ne 'boolean') {
                Add-ValidationError "Workspace capability schema must define $capability as boolean."
            }
        }
    } catch {
        Add-ValidationError "Workspace source manifest schema is invalid JSON: $($_.Exception.Message)"
    }
}

if (Test-Path -LiteralPath $sourceManifestPath -PathType Leaf) {
    $sourceManifest = Get-Content -Raw -LiteralPath $sourceManifestPath

    if (-not $sourceManifest.StartsWith('# yaml-language-server: $schema=source-manifest.schema.json')) {
        Add-ValidationError 'Workspace source manifest must reference the adjacent JSON Schema.'
    }

    foreach ($manifestKey in $expectedManifestKeys) {
        $pattern = "(?m)^$([regex]::Escape($manifestKey)):\s*"
        if (-not [regex]::IsMatch($sourceManifest, $pattern)) {
            Add-ValidationError "Workspace source manifest is missing key: $manifestKey"
        }
    }

    $topLevelKeys = @(
        [regex]::Matches($sourceManifest, '(?m)^(?<key>[A-Za-z0-9_-]+):') |
            ForEach-Object { $_.Groups['key'].Value }
    )
    $manifestKeyDifference = @(
        Compare-Object `
            -ReferenceObject ($expectedManifestKeys | Sort-Object) `
            -DifferenceObject ($topLevelKeys | Sort-Object)
    )
    if ($manifestKeyDifference.Count -gt 0) {
        Add-ValidationError 'Workspace source manifest has unexpected or duplicate top-level keys.'
    }

    if (-not [regex]::IsMatch($sourceManifest, '(?m)^\s{2}id:\s*[a-z0-9]+\s*$')) {
        Add-ValidationError 'Workspace source manifest registrar.id is invalid.'
    }

    if (-not [regex]::IsMatch($sourceManifest, '(?m)^\s{2}display_name:\s*\S.*$')) {
        Add-ValidationError 'Workspace source manifest registrar.display_name is missing.'
    }

    foreach ($targetKey in @('clientexec_version', 'php_versions', 'sandbox_available')) {
        $pattern = "(?m)^\s{2}$([regex]::Escape($targetKey)):\s*\S.*$"
        if (-not [regex]::IsMatch($sourceManifest, $pattern)) {
            Add-ValidationError "Workspace source manifest target.$targetKey is missing."
        }
    }

    if (-not [regex]::IsMatch($sourceManifest, '(?m)^sources:\s*\[\]\s*$')) {
        Add-ValidationError 'Workspace source manifest template sources must default to an empty array.'
    }

    foreach ($capability in $expectedCapabilities) {
        $pattern = "(?m)^\s{2}$([regex]::Escape($capability)):\s*false\s*$"
        if (-not [regex]::IsMatch($sourceManifest, $pattern)) {
            Add-ValidationError "Workspace source manifest capability must default to false: $capability"
        }
    }

    if (-not [regex]::IsMatch($sourceManifest, '(?m)^open_questions:\s*\[\]\s*$')) {
        Add-ValidationError 'Workspace source manifest open_questions must default to an empty array.'
    }

    if ([regex]::IsMatch($sourceManifest, '(?im)^\s*(api[_-]?key|secret|password|token|cookie):\s*\S+')) {
        Add-ValidationError 'Workspace source manifest appears to contain a credential field with a value.'
    }
}

$openaiPath = Join-Path $rootPath 'agents/openai.yaml'
if (Test-Path -LiteralPath $openaiPath -PathType Leaf) {
    $openai = Get-Content -Raw -LiteralPath $openaiPath

    foreach ($field in @('display_name', 'short_description', 'default_prompt')) {
        $pattern = "(?m)^\s+$([regex]::Escape($field)):\s+`"[^`"]+`"\s*$"
        if (-not [regex]::IsMatch($openai, $pattern)) {
            Add-ValidationError "agents/openai.yaml must contain a quoted $field."
        }
    }

    if (-not [regex]::IsMatch($openai, '\$clientexec-registrar-development-kit')) {
        Add-ValidationError 'agents/openai.yaml default_prompt must mention $clientexec-registrar-development-kit.'
    }
}

$pluginPath = Join-Path $rootPath 'assets/registrar-template/plugins/registrars/example/PluginExample.php'
if (Test-Path -LiteralPath $pluginPath -PathType Leaf) {
    $plugin = Get-Content -Raw -LiteralPath $pluginPath
    $requiredMethods = @(
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
        'sendTransferKey'
    )

    foreach ($method in $requiredMethods) {
        $pattern = "public\s+function\s+$([regex]::Escape($method))\s*\("
        if (-not [regex]::IsMatch($plugin, $pattern)) {
            Add-ValidationError "Template is missing abstract method implementation: $method"
        }
    }

    foreach ($feature in @('nameSuggest', 'importDomains', 'importPrices')) {
        $pattern = "'$([regex]::Escape($feature))'\s*=>\s*false"
        if (-not [regex]::IsMatch($plugin, $pattern)) {
            Add-ValidationError "Template feature must default to false: $feature"
        }
    }

    if ([regex]::IsMatch($plugin, '(?i)(api[_ -]?key|secret|password)\s*=\s*[''"][^''"]+[''"]')) {
        Add-ValidationError 'Template appears to contain a hard-coded credential.'
    }

    if (
        -not [regex]::IsMatch($plugin, 'private\s+const\s+PRODUCTION_API_URL\s*=') -or
        -not [regex]::IsMatch($plugin, 'private\s+const\s+SANDBOX_API_URL\s*=')
    ) {
        Add-ValidationError 'Template must define private production and sandbox endpoint constants.'
    }

    if (
        -not [regex]::IsMatch(
            $plugin,
            '[''"]API URL[''"]\s*=>\s*\[.*?[''"]type[''"]\s*=>\s*[''"]hidden[''"]',
            [System.Text.RegularExpressions.RegexOptions]::Singleline
        )
    ) {
        Add-ValidationError 'Template API URL field must be hidden.'
    }

    $phpCommand = Get-Command 'php' -ErrorAction SilentlyContinue
    if ($null -ne $phpCommand) {
        $phpFiles = @(
            $pluginPath,
            (Join-Path $rootPath 'scripts/validate-kit.php'),
            (Join-Path $rootPath 'scripts/validate-registrar-output.php'),
            (Join-Path $rootPath 'scripts/inspect-clientexec-runtime.php')
        )

        foreach ($phpFile in $phpFiles) {
            $lintOutput = & $phpCommand.Source -n -l $phpFile 2>&1
            if ($LASTEXITCODE -ne 0) {
                Add-ValidationError "PHP syntax check failed for ${phpFile}: $($lintOutput -join ' ')"
            }
        }
    } elseif ($RequirePhp) {
        Add-ValidationError 'PHP is required for syntax validation but was not found.'
    } else {
        Write-Warning 'PHP was not found; template syntax lint was skipped.'
    }
}

$iniPath = Join-Path $rootPath 'assets/registrar-template/plugins/registrars/example/resource/plugin.ini'
if (Test-Path -LiteralPath $iniPath -PathType Leaf) {
    $ini = Get-Content -Raw -LiteralPath $iniPath
    foreach ($feature in @('register', 'transfer', 'renew', 'domainsync', 'pricingsync', 'registrarlock')) {
        $pattern = "(?m)^$([regex]::Escape($feature))\s*=\s*0\s*$"
        if (-not [regex]::IsMatch($ini, $pattern)) {
            Add-ValidationError "plugin.ini feature must default to 0: $feature"
        }
    }
}

$markdownFiles = Get-ChildItem -LiteralPath $rootPath -Recurse -File -Filter '*.md' |
    Where-Object {
        $_.FullName -notmatch '[\\/](\.git|\.idea|\.vscode|build|clientexec-core-files|coverage|dist|real-sample|sample-registrar|vendor|workspace)[\\/]'
    }

foreach ($markdownFile in $markdownFiles) {
    $markdown = Get-Content -Raw -LiteralPath $markdownFile.FullName
    $linkMatches = [regex]::Matches($markdown, '\[[^\]]+\]\((?<target>[^)]+)\)')

    foreach ($linkMatch in $linkMatches) {
        $target = $linkMatch.Groups['target'].Value.Trim()
        if (
            $target -match '^(https?://|mailto:|#)' -or
            $target -match '^<' -or
            $target -match '^\$'
        ) {
            continue
        }

        $pathPart = ($target -split '#', 2)[0]
        if ([string]::IsNullOrWhiteSpace($pathPart)) {
            continue
        }

        $resolved = Join-Path $markdownFile.DirectoryName $pathPart
        if (-not (Test-Path -LiteralPath $resolved)) {
            $relativeMarkdown = $markdownFile.FullName.Substring($rootPath.Length).TrimStart('\', '/')
            Add-ValidationError "Broken Markdown link in ${relativeMarkdown}: $target"
        }
    }
}

if ($errors.Count -gt 0) {
    Write-Host "Validation failed with $($errors.Count) issue(s):" -ForegroundColor Red
    foreach ($validationError in $errors) {
        Write-Host " - $validationError" -ForegroundColor Red
    }
    exit 1
}

Write-Host 'Clientexec registrar kit validation passed.' -ForegroundColor Green
exit 0
