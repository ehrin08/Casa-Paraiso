[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('package')]
    [string] $Action = 'package'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$wrapper = Join-Path $PSScriptRoot 'casa-docker.ps1'

# Run in the existing Docker toolchain. Production vendor is staged separately.
& $wrapper compose -ComposeArguments @('exec', '-T', 'app', 'composer', 'validate', '--no-check-publish', '--no-ansi')
& $wrapper compose -ComposeArguments @('exec', '-T', 'app', 'composer', 'audit', '--locked', '--no-ansi')
& $wrapper compose -ComposeArguments @('exec', '-T', 'app', 'npm', 'ci')
& $wrapper compose -ComposeArguments @('exec', '-T', 'app', 'npm', 'audit', '--audit-level=low')
& $wrapper compose -ComposeArguments @('exec', '-T', 'app', 'npm', 'run', 'build')
& $wrapper compose -ComposeArguments @('exec', '-T', 'app', 'php', 'deploy/build-hostinger.php')
