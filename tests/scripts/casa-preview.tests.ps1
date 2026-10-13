Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$module = Import-Module (Join-Path $projectPath 'scripts\CasaPreview.psm1') -Force -PassThru
$fixtureRoot = Join-Path $projectPath ('.casa-preview\tests-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $fixtureRoot -Force | Out-Null

function Assert-Preview {
    param([bool] $Condition, [string] $Message)
    if (-not $Condition) { throw $Message }
}

function New-PreviewFixture {
    param([string] $Name, [switch] $MissingSettings)
    $path = Join-Path $fixtureRoot $Name
    New-Item -ItemType Directory -Path (Join-Path $path 'public\build') -Force | Out-Null
    [System.IO.File]::WriteAllText((Join-Path $path 'public\build\manifest.json'), '{}')
    $lines = @('# fixture', 'APP_ENV=local', ' APP_DEBUG = true', 'APP_URL=http://localhost:18001', 'APP_PORT=18001', 'DB_PASSWORD=fixture-only', 'TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12')
    if (-not $MissingSettings) { $lines += @('TRUSTED_HOSTS=^existing\.example$', 'SESSION_SECURE_COOKIE=false') }
    [System.IO.File]::WriteAllText((Join-Path $path '.env'), (($lines -join "`r`n") + "`r`n"), [System.Text.UTF8Encoding]::new($false))
    & $module {
        $script:FakeProcess = $null
        $script:Alive = $false
        $script:Starts = 0
        $script:Stops = 0
        $script:DockerStarts = 0
        $script:ClearFailures = 0
        $script:Timeout = $false
        $script:ExitEarly = $false
    }
    return $path
}

& $module {
    $script:ReadyImplementation = ${function:Wait-CasaPreviewReady}
    function script:Get-Command {
        param($Name, $CommandType, $ErrorAction)
        return [pscustomobject] @{ Source = 'C:\fixture\cloudflared.exe' }
    }
    function script:Test-CasaPreviewHttp { param($Url) return $true }
    function script:Invoke-CasaPreviewDocker {
        param($ProjectPath, $Action)
        if ($Action -eq 'start') { $script:DockerStarts++ }
        if ($Action -eq 'clear-config' -and $script:ClearFailures -gt 0) {
            $script:ClearFailures--
            throw 'Simulated configuration failure.'
        }
    }
    function script:Start-Process {
        param($FilePath, $ArgumentList, $WorkingDirectory, $WindowStyle, [switch] $PassThru, $RedirectStandardOutput, $RedirectStandardError)
        if ($WindowStyle -ne 'Hidden') { throw 'Cloudflare must start hidden.' }
        $script:Starts++
        $script:Alive = -not $script:ExitEarly
        $script:FakeProcess = [pscustomobject] @{ Id = (31000 + $script:Starts); Path = $FilePath; StartTime = [datetime]::UtcNow; HasExited = $script:ExitEarly }
        $script:FakeProcess | Add-Member -MemberType ScriptMethod -Name WaitForExit -Value { param($Timeout) return $true }
        [System.IO.File]::WriteAllText($RedirectStandardOutput, '')
        [System.IO.File]::WriteAllText($RedirectStandardError, "https://preview-$($script:Starts).trycloudflare.com")
        $settings = Get-CasaPreviewEnvironment (Split-Path $WorkingDirectory -Parent)
        if ((Get-CasaPreviewValue (Find-CasaPreviewSetting $settings 'APP_DEBUG')) -ne 'false') { throw 'Debug output must be disabled before exposing the app.' }
        return $script:FakeProcess
    }
    function script:Get-Process {
        param($Id, $ErrorAction)
        if ($script:Alive -and $script:FakeProcess.Id -eq $Id) { return $script:FakeProcess }
        return $null
    }
    function script:Stop-Process {
        param($InputObject, [switch] $Force, $ErrorAction)
        if ($InputObject.Id -ne $script:FakeProcess.Id) { throw 'Attempted to stop an unrelated process.' }
        $script:Stops++
        $script:Alive = $false
    }
    function script:Wait-CasaPreviewReady {
        param($ProjectPath, $StatePath, $Directory, $State)
        $timeoutSeconds = if ($script:Timeout) { 0 } else { 90 }
        & $script:ReadyImplementation $ProjectPath $StatePath $Directory $State -TimeoutSeconds $timeoutSeconds
    }
}

try {
    $logPath = Join-Path $fixtureRoot 'shared.log'
    $writer = [System.IO.File]::Open($logPath, 'Create', 'Write', 'Read')
    try {
        $bytes = [System.Text.Encoding]::UTF8.GetBytes('https://shared-log.trycloudflare.com')
        $writer.Write($bytes, 0, $bytes.Length)
        $writer.Flush()
        $logText = & $module { param($Path) Read-CasaPreviewLog $Path } $logPath
        Assert-Preview ($logText -eq 'https://shared-log.trycloudflare.com') 'The launcher must read a log while its redirected writer is still open.'
    } finally { $writer.Dispose() }
    Write-Output 'PASS: active Windows log sharing'

    $path = New-PreviewFixture 'lifecycle'
    $original = [System.IO.File]::ReadAllText((Join-Path $path '.env'))
    $started = Invoke-CasaPreview start $path
    Assert-Preview (($started -join '') -match 'https://preview-1.trycloudflare.com') 'Start must print the public URL.'
    $previewEnvironment = [System.IO.File]::ReadAllText((Join-Path $path '.env'))
    Assert-Preview ($previewEnvironment -match 'APP_DEBUG=false' -and $previewEnvironment -match 'SESSION_SECURE_COOKIE=true') 'Preview security settings were not applied.'
    Assert-Preview ($previewEnvironment -match '\^existing\\.example\$,' -and $previewEnvironment -match '\^preview-1\\.trycloudflare\\.com\$') 'Existing and generated trusted hosts must both be preserved.'
    $journal = [System.IO.File]::ReadAllText((Join-Path $path '.casa-preview\state.json'))
    Assert-Preview (-not $journal.Contains('fixture-only')) 'The state journal must not copy credentials.'
    $status = Invoke-CasaPreview status $path
    Assert-Preview (($status -join '') -match 'running.*https://preview-1.trycloudflare.com' -and ($status -join '') -match 'reachable') 'Status must report the running URL and health.'
    Invoke-CasaPreview start $path | Out-Null
    Assert-Preview ((& $module { $script:Starts }) -eq 1) 'Repeated start must reuse the existing process.'
    [System.IO.File]::AppendAllText((Join-Path $path '.env'), "MAIL_HOST=unchanged`r`n")
    Invoke-CasaPreview stop $path | Out-Null
    Assert-Preview ([System.IO.File]::ReadAllText((Join-Path $path '.env')) -ceq ($original + "MAIL_HOST=unchanged`r`n")) 'Stop must restore original formatting and preserve unrelated edits.'
    Assert-Preview ((& $module { $script:Stops }) -eq 1 -and (& $module { $script:DockerStarts }) -eq 0) 'Stop must target only its tunnel and leave Docker alone.'
    Invoke-CasaPreview stop $path | Out-Null
    Invoke-CasaPreview start $path | Out-Null
    Assert-Preview ((& $module { $script:Starts }) -eq 2) 'Restart must create a new tunnel.'
    Invoke-CasaPreview stop $path | Out-Null
    Write-Output 'PASS: start/status/stop, duplicate start, restart, and unrelated edits'

    $path = New-PreviewFixture 'missing-settings' -MissingSettings
    $original = [System.IO.File]::ReadAllText((Join-Path $path '.env'))
    Invoke-CasaPreview start $path | Out-Null
    Invoke-CasaPreview stop $path | Out-Null
    Assert-Preview ([System.IO.File]::ReadAllText((Join-Path $path '.env')) -ceq $original) 'Previously absent settings must be removed during restoration.'
    Write-Output 'PASS: restoration of absent settings'

    foreach ($failureMode in @('configuration', 'timeout', 'early-exit')) {
        $path = New-PreviewFixture $failureMode
        $original = [System.IO.File]::ReadAllText((Join-Path $path '.env'))
        & $module {
            param($Mode)
            if ($Mode -eq 'configuration') { $script:ClearFailures = 1 }
            if ($Mode -eq 'timeout') { $script:Timeout = $true }
            if ($Mode -eq 'early-exit') { $script:ExitEarly = $true }
        } $failureMode
        $failed = $false
        try { Invoke-CasaPreview start $path | Out-Null } catch { $failed = $_.Exception.Message -match 'startup failed' }
        Assert-Preview $failed "$failureMode must fail clearly."
        Assert-Preview ([System.IO.File]::ReadAllText((Join-Path $path '.env')) -ceq $original) "$failureMode must restore local settings."
        Assert-Preview (-not (Test-Path -LiteralPath (Join-Path $path '.casa-preview\state.json'))) "$failureMode must remove recovered state."
        Assert-Preview (-not (& $module { $script:Alive })) "$failureMode must not leave a tunnel running."
        Write-Output "PASS: $failureMode cleanup"
    }

    $path = New-PreviewFixture 'stale'
    Invoke-CasaPreview start $path | Out-Null
    & $module { $script:Alive = $false }
    Invoke-CasaPreview start $path | Out-Null
    Assert-Preview ((& $module { $script:Starts }) -eq 2) 'An unexpectedly exited tunnel must be recovered before restart.'
    & $module { $script:FakeProcess.StartTime = $script:FakeProcess.StartTime.AddSeconds(1) }
    Invoke-CasaPreview stop $path | Out-Null
    Assert-Preview ((& $module { $script:Stops }) -eq 0) 'A reused PID with a different start time must never be stopped.'
    Write-Output 'PASS: unexpected exit and PID reuse'

    $path = New-PreviewFixture 'conflict'
    Invoke-CasaPreview start $path | Out-Null
    $edited = [System.IO.File]::ReadAllText((Join-Path $path '.env')).Replace('APP_URL=https://preview-1.trycloudflare.com', 'APP_URL=https://edited.example')
    [System.IO.File]::WriteAllText((Join-Path $path '.env'), $edited)
    $failed = $false
    try { Invoke-CasaPreview stop $path | Out-Null } catch { $failed = $_.Exception.Message -match 'APP_URL' }
    Assert-Preview $failed 'Edits to managed keys must require explicit recovery.'
    Assert-Preview ([System.IO.File]::ReadAllText((Join-Path $path '.env')).Contains('APP_URL=https://edited.example')) 'A user-edited managed setting must not be overwritten.'
    Assert-Preview (-not (& $module { $script:Alive })) 'A settings conflict must still stop the tunnel.'
    $edited = [System.IO.File]::ReadAllText((Join-Path $path '.env')).Replace('APP_URL=https://edited.example', 'APP_URL=http://localhost:18001')
    [System.IO.File]::WriteAllText((Join-Path $path '.env'), $edited)
    Invoke-CasaPreview stop $path | Out-Null
    Write-Output 'PASS: managed-setting conflict and recovery'

    $path = New-PreviewFixture 'invalid-state'
    New-Item -ItemType Directory -Path (Join-Path $path '.casa-preview') -Force | Out-Null
    [System.IO.File]::WriteAllText((Join-Path $path '.casa-preview\state.json'), '{broken')
    $failed = $false
    try { Invoke-CasaPreview stop $path | Out-Null } catch { $failed = $_.Exception.Message -match 'state is invalid' }
    Assert-Preview $failed 'Invalid state must fail without modifying any process.'
    Write-Output 'PASS: invalid state'
} finally {
    Remove-Module $module
    $resolvedFixture = (Resolve-Path -LiteralPath $fixtureRoot).Path
    $expectedRoot = [System.IO.Path]::GetFullPath((Join-Path $projectPath '.casa-preview')) + [System.IO.Path]::DirectorySeparatorChar
    if (-not $resolvedFixture.StartsWith($expectedRoot, [System.StringComparison]::OrdinalIgnoreCase)) { throw 'Refusing to remove fixtures outside the preview directory.' }
    Remove-Item -LiteralPath $resolvedFixture -Recurse -Force
}
