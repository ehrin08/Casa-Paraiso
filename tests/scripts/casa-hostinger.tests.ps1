Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$packageWrapper = Join-Path (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path 'scripts/casa-hostinger.ps1'
$global:CasaHostingerTestCalls = [System.Collections.Generic.List[string]]::new()
$global:CasaHostingerFailAudit = $false

function global:docker {
    param([Parameter(ValueFromRemainingArguments = $true)] [string[]] $DockerArguments)
    $global:LASTEXITCODE = 0
    if ($DockerArguments -contains 'info') { return 'docker-desktop|Docker Desktop' }
    $serviceIndex = [Array]::IndexOf($DockerArguments, 'app')
    if ($serviceIndex -lt 0) { throw 'Packaging must use the existing app service.' }
    $command = $DockerArguments[($serviceIndex + 1)..($DockerArguments.Count - 1)] -join ' '
    $global:CasaHostingerTestCalls.Add($command)
    if ($global:CasaHostingerFailAudit -and $command -eq 'npm audit --audit-level=low') {
        $global:LASTEXITCODE = 1
        return 'Simulated security audit rejection'
    }
    return 'Mock packaging check passed'
}

try {
    & $packageWrapper package | Out-Null
    if ($global:CasaHostingerTestCalls.Count -ne 6) { throw 'Unexpected packaging command count.' }
    if ($global:CasaHostingerTestCalls[0] -notlike 'composer validate *') { throw 'Manifest validation must run first.' }
    if ($global:CasaHostingerTestCalls[1] -notlike 'composer audit --locked*') { throw 'The locked PHP dependency tree must be audited.' }
    if ($global:CasaHostingerTestCalls[2] -ne 'npm ci') { throw 'Frontend installation must use the lockfile.' }
    if ($global:CasaHostingerTestCalls[3] -ne 'npm audit --audit-level=low') { throw 'All npm vulnerability levels must be checked.' }
    if ($global:CasaHostingerTestCalls[4] -ne 'npm run build') { throw 'Assets must be compiled before packaging.' }
    if ($global:CasaHostingerTestCalls[5] -ne 'php deploy/build-hostinger.php') { throw 'Production dependencies must use isolated staging.' }

    $global:CasaHostingerTestCalls.Clear()
    $global:CasaHostingerFailAudit = $true
    $rejected = $false
    try { & $packageWrapper package | Out-Null } catch { $rejected = $_.Exception.Message -like '*Docker Compose failed*' }
    if (-not $rejected) { throw 'A failed audit must stop packaging.' }
    if ($global:CasaHostingerTestCalls -contains 'npm run build' -or $global:CasaHostingerTestCalls -contains 'php deploy/build-hostinger.php') {
        throw 'A failed audit continued into artifact creation.'
    }
    $global:LASTEXITCODE = 0
    Write-Output 'Hostinger packaging order and audit failure checks passed.'
} finally {
    Remove-Item Function:/docker -ErrorAction SilentlyContinue
    Remove-Variable CasaHostingerTestCalls, CasaHostingerFailAudit -Scope Global -ErrorAction SilentlyContinue
}
