Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$wrapperPath = Join-Path (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path 'scripts/casa-docker.ps1'
$global:CasaDockerTestCalls = [System.Collections.Generic.List[object]]::new()

function global:docker {
    param([Parameter(ValueFromRemainingArguments = $true)] [string[]] $DockerArguments)
    $global:LASTEXITCODE = 0
    if ($DockerArguments -contains 'info') { return 'docker-desktop|Docker Desktop' }
    $global:CasaDockerTestCalls.Add($DockerArguments)
    return 'Mock Compose completed'
}

try {
    & $wrapperPath compose ps | Out-Null
    $defaultCall = $global:CasaDockerTestCalls[0]
    if ($defaultCall[4] -ne 'casa-paraiso-web') { throw 'The default project changed.' }
    & $wrapperPath -ProjectName casa-paraiso-schedule-test compose -f compose.testing.yaml ps | Out-Null
    $isolatedCall = $global:CasaDockerTestCalls[1]
    if ($isolatedCall[4] -ne 'casa-paraiso-schedule-test') { throw 'The explicit project was ignored.' }
    if (@($isolatedCall | Where-Object { $_ -eq '--project-name' }).Count -ne 1) { throw 'More than one project-name flag was emitted.' }
    $rejected = $false
    try { & $wrapperPath -Action compose -ComposeArguments @('-p', 'unsafe-override', 'ps') | Out-Null } catch { $rejected = $_.Exception.Message -like '*-ProjectName*' }
    if (-not $rejected) { throw 'Raw project overrides must fail before running Compose.' }
    Write-Output 'Docker wrapper project selection checks passed.'
    & $wrapperPath -Action compose -ComposeArguments @('exec', '-T', 'app', 'node', '-p', '1') | Out-Null
    if ($global:CasaDockerTestCalls[2][9] -ne '-p') { throw 'Application flags were incorrectly treated as Compose project flags.' }
    $testingRejected = $false
    try { & $wrapperPath -Action compose -ComposeArguments @('-f', 'compose.testing.yaml', 'up', '-d') | Out-Null } catch { $testingRejected = $_.Exception.Message -like '*explicit non-default*' }
    if (-not $testingRejected) { throw 'The testing configuration must not recreate the main project.' }
    if ((Get-CasaComposeProjectName) -ne 'casa-paraiso-web') { throw 'Project lookup lost its default.' }
    if ((Get-CasaComposeProjectName -ProjectName casa-paraiso-schedule-test) -ne 'casa-paraiso-schedule-test') { throw 'Project lookup ignored its argument.' }
    & $wrapperPath status | Out-Null
} finally {
    Remove-Item Function:/docker -ErrorAction SilentlyContinue
}
