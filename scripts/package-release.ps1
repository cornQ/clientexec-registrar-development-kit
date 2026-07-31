[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9A-Za-z][0-9A-Za-z._-]*$')]
    [string]$Version,

    [string]$Root,

    [string]$OutputDirectory,

    [ValidatePattern('^[0-9A-Za-z][0-9A-Za-z._-]*$')]
    [string]$PackageName = 'clientexec-registrar-development-kit',

    [switch]$RequirePhp
)

$ErrorActionPreference = 'Stop'
$scriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$isWindowsPlatform = [System.Environment]::OSVersion.Platform -eq [System.PlatformID]::Win32NT
$pathComparison = if ($isWindowsPlatform) {
    [System.StringComparison]::OrdinalIgnoreCase
} else {
    [System.StringComparison]::Ordinal
}

function Get-NormalizedFullPath {
    param([Parameter(Mandatory = $true)][string]$Path)

    return [System.IO.Path]::GetFullPath($Path).TrimEnd(
        [System.IO.Path]::DirectorySeparatorChar,
        [System.IO.Path]::AltDirectorySeparatorChar
    )
}

function Test-PathWithin {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Parent
    )

    $normalizedPath = Get-NormalizedFullPath $Path
    $normalizedParent = Get-NormalizedFullPath $Parent
    if ($normalizedPath.Equals($normalizedParent, $script:pathComparison)) {
        return $true
    }

    $parentPrefix = $normalizedParent + [System.IO.Path]::DirectorySeparatorChar
    return $normalizedPath.StartsWith($parentPrefix, $script:pathComparison)
}

function Assert-PathWithin {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Parent,
        [Parameter(Mandatory = $true)][string]$Label
    )

    if (-not (Test-PathWithin -Path $Path -Parent $Parent)) {
        throw "$Label resolves outside the permitted directory: $Path"
    }
}

function Get-RepositoryRelativePath {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$RepositoryRoot
    )

    Assert-PathWithin -Path $Path -Parent $RepositoryRoot -Label 'Release source'
    $rootPrefix = (Get-NormalizedFullPath $RepositoryRoot) +
        [System.IO.Path]::DirectorySeparatorChar
    $relativePath = (Get-NormalizedFullPath $Path).Substring($rootPrefix.Length)

    return $relativePath.Replace(
        [System.IO.Path]::DirectorySeparatorChar,
        '/'
    )
}

function Assert-OrdinaryItem {
    param(
        [Parameter(Mandatory = $true)][System.IO.FileSystemInfo]$Item,
        [Parameter(Mandatory = $true)][string]$RelativePath
    )

    if (
        ($Item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) -ne 0
    ) {
        throw "Release source cannot be a symbolic link or reparse point: $RelativePath"
    }
}

function Assert-SafeReleaseFile {
    param(
        [Parameter(Mandatory = $true)][System.IO.FileInfo]$File,
        [Parameter(Mandatory = $true)][string]$RelativePath
    )

    $forbiddenDirectories = @(
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
        'workspace'
    )

    foreach ($segment in ($RelativePath -split '/')) {
        if ($segment -in $forbiddenDirectories) {
            throw "Release source uses a private or generated directory: $RelativePath"
        }
    }

    $unsafeNamePattern = '(?i)(^\.env(?:\.|$)|~$|\.(?:bak|cache|gz|log|rar|tar|tgz|tmp|7z|zip)$|(?:^|[._-])(?:backup|credentials?|old|private|secret)(?:[._-]|$))'
    if ($File.Name -match $unsafeNamePattern) {
        throw "Release source has an unsafe filename: $RelativePath"
    }

    $textExtensions = @(
        '.gitignore',
        '.html',
        '.ini',
        '.json',
        '.md',
        '.php',
        '.ps1',
        '.txt',
        '.yaml',
        '.yml'
    )
    $extension = $File.Extension.ToLowerInvariant()
    if ($File.Name -notin @('.gitignore', 'LICENSE', 'SKILL.md') -and
        $extension -notin $textExtensions) {
        return
    }

    $content = Get-Content -Raw -LiteralPath $File.FullName
    $strongCredentialPatterns = @(
        '-----BEGIN (?:EC |OPENSSH |PGP |RSA )?PRIVATE KEY-----',
        '\bAKIA[0-9A-Z]{16}\b',
        '(?i)\bauthorization\s*[:=]\s*bearer\s+[A-Za-z0-9._~+/=-]{12,}'
    )
    foreach ($pattern in $strongCredentialPatterns) {
        if ([regex]::IsMatch($content, $pattern)) {
            throw "Release source appears to contain a credential: $RelativePath"
        }
    }

    $assignmentPattern = '(?im)^\s*(?:api[_ -]?key|client[_ -]?secret|password|access[_ -]?token|refresh[_ -]?token|cookie)\s*[:=]\s*(?<value>\S+)\s*$'
    foreach ($match in [regex]::Matches($content, $assignmentPattern)) {
        $value = $match.Groups['value'].Value.Trim(
            [char[]]@('"', "'", ',', ';')
        )
        $placeholderPattern = '^(?:<[^>]+>|\$\{[^}]+\}|REDACTED|CHANGEME|EXAMPLE|PLACEHOLDER|NONE|NULL|FALSE|TRUE)$'
        if (-not [regex]::IsMatch($value, $placeholderPattern, 'IgnoreCase')) {
            throw "Release source appears to contain an assigned credential: $RelativePath"
        }
    }
}

if ([string]::IsNullOrWhiteSpace($Root)) {
    $Root = Split-Path -Parent $scriptDirectory
} elseif (-not [System.IO.Path]::IsPathRooted($Root)) {
    $Root = Join-Path (Get-Location).Path $Root
}
$rootPath = Get-NormalizedFullPath $Root
if (-not (Test-Path -LiteralPath $rootPath -PathType Container)) {
    throw "Repository root does not exist: $rootPath"
}

if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $rootPath 'dist'
} elseif (-not [System.IO.Path]::IsPathRooted($OutputDirectory)) {
    $OutputDirectory = Join-Path $rootPath $OutputDirectory
}
$outputPath = Get-NormalizedFullPath $OutputDirectory
Assert-PathWithin -Path $outputPath -Parent $rootPath -Label 'Output directory'
if ($outputPath.Equals($rootPath, $pathComparison)) {
    throw 'Output directory must be a child of the repository root.'
}

$archiveBaseName = "$PackageName-$Version"
$archivePath = Join-Path $outputPath "$archiveBaseName.zip"
$checksumPath = "$archivePath.sha256"
foreach ($targetPath in @($archivePath, $checksumPath)) {
    if (Test-Path -LiteralPath $targetPath) {
        throw "Refusing to overwrite an existing release artifact: $targetPath"
    }
}

$validationScript = Join-Path $rootPath 'scripts/validate-kit.ps1'
if (-not (Test-Path -LiteralPath $validationScript -PathType Leaf)) {
    throw "Validation script is missing: $validationScript"
}
$powershellCommand = Get-Command 'pwsh' -ErrorAction SilentlyContinue
if ($null -eq $powershellCommand) {
    $powershellCommand = Get-Command 'powershell' -ErrorAction SilentlyContinue
}
if ($null -eq $powershellCommand) {
    throw 'PowerShell is required to run the release validation gate.'
}

$validationArguments = @(
    '-NoProfile',
    '-ExecutionPolicy',
    'Bypass',
    '-File',
    $validationScript,
    '-Root',
    $rootPath
)
if ($RequirePhp) {
    $validationArguments += '-RequirePhp'
}

Write-Host 'Running release validation gate...'
& $powershellCommand.Source @validationArguments
if ($LASTEXITCODE -ne 0) {
    throw 'Release packaging stopped because kit validation failed.'
}

$allowlistedFiles = @(
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
    'scripts/package-release.ps1',
    'scripts/validate-registrar-output.php',
    'scripts/validate-kit.php',
    'scripts/validate-kit.ps1',
    'scripts/audit-registrar-methods.ps1',
    'scripts/inspect-clientexec-runtime.php'
)
$allowlistedDirectories = @(
    'assets/registrar-template',
    'assets/registrar-workspace-template',
    'references'
)

$releaseFiles = [System.Collections.Generic.List[System.IO.FileInfo]]::new()
foreach ($relativePath in $allowlistedFiles) {
    $sourcePath = Join-Path $rootPath $relativePath
    if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
        throw "Allowlisted release file is missing: $relativePath"
    }

    $item = Get-Item -LiteralPath $sourcePath -Force
    Assert-OrdinaryItem -Item $item -RelativePath $relativePath
    $releaseFiles.Add($item)
}

foreach ($relativeDirectory in $allowlistedDirectories) {
    $sourceDirectory = Join-Path $rootPath $relativeDirectory
    if (-not (Test-Path -LiteralPath $sourceDirectory -PathType Container)) {
        throw "Allowlisted release directory is missing: $relativeDirectory"
    }

    $directoryItem = Get-Item -LiteralPath $sourceDirectory -Force
    Assert-OrdinaryItem -Item $directoryItem -RelativePath $relativeDirectory
    foreach ($item in Get-ChildItem -LiteralPath $sourceDirectory -Recurse -Force) {
        $relativePath = Get-RepositoryRelativePath `
            -Path $item.FullName `
            -RepositoryRoot $rootPath
        Assert-OrdinaryItem -Item $item -RelativePath $relativePath
        if (-not $item.PSIsContainer) {
            $releaseFiles.Add($item)
        }
    }
}

$releaseEntries = @{}
foreach ($file in $releaseFiles) {
    $relativePath = Get-RepositoryRelativePath `
        -Path $file.FullName `
        -RepositoryRoot $rootPath
    if ($releaseEntries.ContainsKey($relativePath)) {
        throw "Duplicate release source path: $relativePath"
    }

    Assert-SafeReleaseFile -File $file -RelativePath $relativePath
    $releaseEntries[$relativePath] = $file
}

New-Item -ItemType Directory -Path $outputPath -Force | Out-Null
$stagingPath = Join-Path $outputPath (
    '.package-staging-' + [System.Guid]::NewGuid().ToString('N')
)
Assert-PathWithin -Path $stagingPath -Parent $outputPath -Label 'Staging directory'
$temporaryArchivePath = Join-Path $outputPath (
    '.package-archive-' + [System.Guid]::NewGuid().ToString('N') + '.zip'
)
Assert-PathWithin `
    -Path $temporaryArchivePath `
    -Parent $outputPath `
    -Label 'Temporary archive'

try {
    New-Item -ItemType Directory -Path $stagingPath | Out-Null
    foreach ($relativePath in @($releaseEntries.Keys | Sort-Object)) {
        $destinationPath = Join-Path $stagingPath (
            $relativePath.Replace('/', [System.IO.Path]::DirectorySeparatorChar)
        )
        $destinationDirectory = Split-Path -Parent $destinationPath
        New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null
        Copy-Item -LiteralPath $releaseEntries[$relativePath].FullName `
            -Destination $destinationPath
    }

    $manifestLines = @(
        foreach ($relativePath in @($releaseEntries.Keys | Sort-Object)) {
            $stagedFile = Join-Path $stagingPath (
                $relativePath.Replace(
                    '/',
                    [System.IO.Path]::DirectorySeparatorChar
                )
            )
            $hash = (Get-FileHash -LiteralPath $stagedFile -Algorithm SHA256).
                Hash.ToLowerInvariant()
            "$hash  $relativePath"
        }
    )
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    $manifestPath = Join-Path $stagingPath 'RELEASE-MANIFEST.sha256'
    [System.IO.File]::WriteAllLines(
        $manifestPath,
        [string[]]$manifestLines,
        $utf8NoBom
    )

    Add-Type -AssemblyName System.IO.Compression
    $archiveStream = [System.IO.File]::Open(
        $temporaryArchivePath,
        [System.IO.FileMode]::CreateNew,
        [System.IO.FileAccess]::ReadWrite,
        [System.IO.FileShare]::None
    )
    try {
        $archive = New-Object System.IO.Compression.ZipArchive(
            $archiveStream,
            [System.IO.Compression.ZipArchiveMode]::Create,
            $false
        )
        try {
            foreach (
                $stagedFile in Get-ChildItem `
                    -LiteralPath $stagingPath `
                    -Recurse `
                    -Force `
                    -File |
                    Sort-Object FullName
            ) {
                $entryName = Get-RepositoryRelativePath `
                    -Path $stagedFile.FullName `
                    -RepositoryRoot $stagingPath
                $entry = $archive.CreateEntry(
                    $entryName,
                    [System.IO.Compression.CompressionLevel]::Optimal
                )
                $entryStream = $entry.Open()
                $sourceStream = [System.IO.File]::OpenRead($stagedFile.FullName)
                try {
                    $sourceStream.CopyTo($entryStream)
                } finally {
                    $sourceStream.Dispose()
                    $entryStream.Dispose()
                }
            }
        } finally {
            $archive.Dispose()
        }
    } finally {
        $archiveStream.Dispose()
    }

    [System.IO.File]::Move($temporaryArchivePath, $archivePath)

    $archiveHash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).
        Hash.ToLowerInvariant()
    $checksumLine = "$archiveHash  $([System.IO.Path]::GetFileName($archivePath))"
    [System.IO.File]::WriteAllText(
        $checksumPath,
        $checksumLine + [System.Environment]::NewLine,
        $utf8NoBom
    )
} finally {
    if (Test-Path -LiteralPath $stagingPath -PathType Container) {
        $resolvedStagingPath = Get-NormalizedFullPath $stagingPath
        Assert-PathWithin `
            -Path $resolvedStagingPath `
            -Parent $outputPath `
            -Label 'Staging cleanup target'
        if (
            [System.IO.Path]::GetFileName($resolvedStagingPath) -notlike
                '.package-staging-*'
        ) {
            throw "Refusing to remove an unexpected staging path: $resolvedStagingPath"
        }
        Remove-Item -LiteralPath $resolvedStagingPath -Recurse -Force
    }

    if (Test-Path -LiteralPath $temporaryArchivePath -PathType Leaf) {
        $resolvedTemporaryArchive = Get-NormalizedFullPath $temporaryArchivePath
        Assert-PathWithin `
            -Path $resolvedTemporaryArchive `
            -Parent $outputPath `
            -Label 'Temporary archive cleanup target'
        if (
            [System.IO.Path]::GetFileName($resolvedTemporaryArchive) -notlike
                '.package-archive-*.zip'
        ) {
            throw "Refusing to remove an unexpected temporary archive: $resolvedTemporaryArchive"
        }
        Remove-Item -LiteralPath $resolvedTemporaryArchive -Force
    }
}

Write-Host 'Release package created successfully.' -ForegroundColor Green
Write-Host "Archive: $archivePath"
Write-Host "Checksum: $checksumPath"
Write-Host "Files: $($releaseEntries.Count + 1)"
