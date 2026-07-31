[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Path,

    [switch]$Json
)

$ErrorActionPreference = 'Stop'
$auditPath = [System.IO.Path]::GetFullPath($Path)

if (-not (Test-Path -LiteralPath $auditPath -PathType Container)) {
    throw "Directory not found: $auditPath"
}

$pluginFiles = Get-ChildItem -LiteralPath $auditPath -Recurse -File -Filter 'Plugin*.php' |
    Where-Object {
        $_.Name -notmatch '(?i)(~|old|backup)' -and
        $_.FullName -notmatch '[\\/]vendor[\\/]'
    }

if ($pluginFiles.Count -eq 0) {
    throw "No Plugin*.php files found under $auditPath"
}

$rows = foreach ($pluginFile in $pluginFiles) {
    $source = Get-Content -Raw -LiteralPath $pluginFile.FullName
    $matches = [regex]::Matches(
        $source,
        '(?im)^[ \t]*(?:(?<visibility>public|protected|private)[ \t]+)?(?:static[ \t]+)?function[ \t]+&?[ \t]*(?<method>[A-Za-z_][A-Za-z0-9_]*)[ \t]*\('
    )

    foreach ($match in $matches) {
        [pscustomobject]@{
            Method = $match.Groups['method'].Value
            Visibility = if ($match.Groups['visibility'].Success) {
                $match.Groups['visibility'].Value.ToLowerInvariant()
            } else {
                'public'
            }
            File = $pluginFile.FullName.Substring($auditPath.Length).TrimStart('\', '/')
        }
    }
}

$summary = $rows |
    Group-Object -Property Method |
    ForEach-Object {
        [pscustomobject]@{
            Method = $_.Name
            Count = $_.Count
            Files = @($_.Group.File | Sort-Object -Unique)
        }
    } |
    Sort-Object -Property @{ Expression = 'Count'; Descending = $true }, Method

if ($Json) {
    $summary | ConvertTo-Json -Depth 4
    exit 0
}

$summary |
    Select-Object Method, Count, @{ Name = 'Files'; Expression = { $_.Files -join ', ' } } |
    Format-Table -AutoSize
