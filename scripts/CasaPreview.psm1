Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:ManagedKeys = @('APP_DEBUG', 'APP_URL', 'TRUSTED_HOSTS', 'SESSION_SECURE_COOKIE')

function Get-CasaPreviewEnvironment {
    param([string] $ProjectPath)

    $path = Join-Path $ProjectPath '.env'
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) {
        throw 'Missing .env. Run scripts\casa-docker.ps1 setup first.'
    }

    $text = [System.IO.File]::ReadAllText($path)
    $newline = if ($text.Contains("`r`n")) { "`r`n" } else { "`n" }
    $lines = [System.Collections.Generic.List[string]]::new()
    foreach ($line in [regex]::Split($text, '\r\n|\n|\r')) { $lines.Add($line) }
    return @{ Path = $path; Lines = $lines; Newline = $newline }
}

function Find-CasaPreviewSetting {
    param($Environment, [string] $Key)

    $pattern = '^\s*(?:export\s+)?' + [regex]::Escape($Key) + '\s*=\s*(?<value>.*)$'
    $found = @()
    for ($index = 0; $index -lt $Environment.Lines.Count; $index++) {
        $match = [regex]::Match($Environment.Lines[$index], $pattern)
        if ($match.Success) {
            $found += @{ Index = $index; Line = $Environment.Lines[$index]; Value = $match.Groups['value'].Value }
        }
    }
    if ($found.Count -gt 1) { throw "Duplicate $Key entries in .env; resolve them before starting the preview." }
    if ($found.Count -eq 1) { return $found[0] }
    return $null
}

function Get-CasaPreviewValue {
    param($Setting)

    if ($null -eq $Setting) { return '' }
    $value = $Setting.Value.Trim()
    if ($value -match '^(["''])(.*)\1\s*(?:#.*)?$') { return $Matches[2] }
    return ($value -replace '\s+#.*$', '').Trim()
}

function Save-CasaPreviewEnvironment {
    param($Environment)

    [System.IO.File]::WriteAllText($Environment.Path, ($Environment.Lines -join $Environment.Newline), [System.Text.UTF8Encoding]::new($false))
}

function Write-CasaPreviewState {
    param([string] $StatePath, $State)

    $temporaryPath = "$StatePath.tmp"
    [System.IO.File]::WriteAllText($temporaryPath, ($State | ConvertTo-Json -Depth 8), [System.Text.UTF8Encoding]::new($false))
    Move-Item -LiteralPath $temporaryPath -Destination $StatePath -Force
}

function Read-CasaPreviewState {
    param([string] $StatePath)

    if (-not (Test-Path -LiteralPath $StatePath -PathType Leaf)) { return $null }
    try {
        $state = [System.IO.File]::ReadAllText($StatePath) | ConvertFrom-Json
        if ($state.Version -ne 1 -or $null -eq $state.OriginalSettings -or $null -eq $state.AppliedSettings) { throw 'Invalid state' }
        if ($state.Url -and $state.Url -notmatch '^https://[a-z0-9-]+\.trycloudflare\.com$') { throw 'Invalid URL' }
        foreach ($key in $state.AppliedSettings.PSObject.Properties.Name) {
            if ($key -notin $script:ManagedKeys -or $null -eq $state.OriginalSettings.PSObject.Properties[$key]) { throw 'Invalid settings' }
        }
        return $state
    } catch {
        throw 'Preview state is invalid. Preserve .casa-preview\state.json for recovery; no process or environment settings were changed.'
    }
}

function Set-CasaPreviewSettings {
    param([string] $ProjectPath, [string] $StatePath, $State, [System.Collections.IDictionary] $Settings)

    $environment = Get-CasaPreviewEnvironment $ProjectPath
    foreach ($key in $Settings.Keys) {
        if ($key -notin $script:ManagedKeys) { throw 'Unsupported preview setting.' }
        $setting = Find-CasaPreviewSetting $environment $key
        $rawValue = [string] $Settings[$key]
        $State.AppliedSettings | Add-Member -NotePropertyName $key -NotePropertyValue $rawValue -Force
        if ($null -eq $setting) {
            $position = $environment.Lines.Count
            if ($position -gt 0 -and $environment.Lines[$position - 1] -eq '') { $position-- }
            $environment.Lines.Insert($position, "$key=$rawValue")
        } else {
            $environment.Lines[$setting.Index] = "$key=$rawValue"
        }
    }
    # Journal intended changes before writing them, so an interrupted start can be recovered.
    Write-CasaPreviewState $StatePath $State
    Save-CasaPreviewEnvironment $environment
}

function Restore-CasaPreviewSettings {
    param([string] $ProjectPath, $State)

    $environment = Get-CasaPreviewEnvironment $ProjectPath
    $conflicts = @()
    foreach ($property in $State.AppliedSettings.PSObject.Properties) {
        $key = $property.Name
        $original = $State.OriginalSettings.$key
        $current = Find-CasaPreviewSetting $environment $key
        if (($null -eq $current -and -not $original.Present) -or
            ($null -ne $current -and $original.Present -and $current.Line -ceq $original.Line)) { continue }
        if ($null -eq $current -or $current.Value -cne [string] $property.Value) {
            $conflicts += $key
            continue
        }
        if ($original.Present) { $environment.Lines[$current.Index] = $original.Line }
        else { $environment.Lines.RemoveAt($current.Index) }
    }
    Save-CasaPreviewEnvironment $environment
    return $conflicts
}

function Invoke-CasaPreviewDocker {
    param([string] $ProjectPath, [ValidateSet('start', 'clear-config')] [string] $Action)

    $wrapper = Join-Path $ProjectPath 'scripts\casa-docker.ps1'
    if ($Action -eq 'start') { & $wrapper -Action start | Out-Null }
    else { & $wrapper -Action compose -ComposeArguments @('exec', '-T', 'app', 'php', 'artisan', 'config:clear') | Out-Null }
}

function Test-CasaPreviewHttp {
    param([string] $Url)

    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5 -MaximumRedirection 5
        return $response.StatusCode -eq 200
    } catch { return $false }
}

function Get-CasaPreviewOwnedProcess {
    param($State)

    if ($null -eq $State.Process) { return $null }
    $process = Get-Process -Id $State.Process.Id -ErrorAction SilentlyContinue
    if ($null -eq $process) { return $null }
    try {
        if ($process.Path -ine $State.Process.ExecutablePath -or
            $process.StartTime.ToUniversalTime().ToString('o') -cne $State.Process.StartedAtUtc) { return $null }
    } catch { return $null }
    return $process
}

function Start-CasaPreviewTunnel {
    param([string] $ExecutablePath, [string] $Origin, [string] $Directory)

    $process = Start-Process -FilePath $ExecutablePath -ArgumentList @('tunnel', '--url', $Origin) -WorkingDirectory $Directory -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $Directory 'cloudflared.out.log') -RedirectStandardError (Join-Path $Directory 'cloudflared.err.log')
    try {
        return [pscustomobject] @{ Id = $process.Id; ExecutablePath = $ExecutablePath; StartedAtUtc = $process.StartTime.ToUniversalTime().ToString('o') }
    } catch {
        if (-not $process.HasExited) { $process.Kill() }
        throw 'Cloudflare exited before starting. See the preview logs.'
    }
}

function Read-CasaPreviewLog {
    param([string] $Path)

    # Start-Process keeps the redirected log open for writing until the tunnel exits.
    $stream = [System.IO.File]::Open($Path, 'Open', 'Read', 'ReadWrite')
    $reader = [System.IO.StreamReader]::new($stream)
    try { return $reader.ReadToEnd() }
    finally { $reader.Dispose() }
}

function Wait-CasaPreviewReady {
    param([string] $ProjectPath, [string] $StatePath, [string] $Directory, $State, [int] $TimeoutSeconds = 90)

    $timer = [System.Diagnostics.Stopwatch]::StartNew()
    while ($timer.Elapsed.TotalSeconds -lt $TimeoutSeconds) {
        if ($null -eq (Get-CasaPreviewOwnedProcess $State)) { throw 'Cloudflare stopped before the preview was ready. See the preview logs.' }
        if (-not $State.Url) {
            foreach ($name in @('cloudflared.out.log', 'cloudflared.err.log')) {
                $path = Join-Path $Directory $name
                if (-not (Test-Path -LiteralPath $path)) { continue }
                $match = [regex]::Match((Read-CasaPreviewLog $path), 'https://[a-z0-9-]+\.trycloudflare\.com')
                if (-not $match.Success) { continue }
                $State.Url = $match.Value
                $hostname = ([uri] $State.Url).Host
                $environment = Get-CasaPreviewEnvironment $ProjectPath
                $hosts = Get-CasaPreviewValue (Find-CasaPreviewSetting $environment 'TRUSTED_HOSTS')
                $additionalHosts = @('^localhost$', '^127\.0\.0\.1$', ('^' + [regex]::Escape($hostname) + '$')) -join ','
                if ($hosts) { $additionalHosts = "$hosts,$additionalHosts" }
                Set-CasaPreviewSettings $ProjectPath $StatePath $State @{ APP_URL = $State.Url; TRUSTED_HOSTS = $additionalHosts }
                Invoke-CasaPreviewDocker $ProjectPath 'clear-config'
                break
            }
        }
        if ($State.Url -and (Test-CasaPreviewHttp $State.Url)) { return }
        Start-Sleep -Milliseconds 500
    }
    throw 'Cloudflare preview was not ready within 90 seconds. Check your internet connection and the preview logs.'
}

function Stop-CasaPreviewSession {
    param([string] $ProjectPath, [string] $StatePath, $State)

    $process = Get-CasaPreviewOwnedProcess $State
    if ($null -ne $process) {
        Stop-Process -InputObject $process -Force -ErrorAction Stop
        $process.WaitForExit(5000) | Out-Null
    }
    $State.Process = $null
    Write-CasaPreviewState $StatePath $State
    $conflicts = @(Restore-CasaPreviewSettings $ProjectPath $State)
    try { Invoke-CasaPreviewDocker $ProjectPath 'clear-config' }
    catch { throw 'The tunnel is stopped and local settings were restored. Start Docker and run casa-preview.ps1 stop again to clear Laravel configuration.' }
    if ($conflicts.Count -gt 0) {
        throw "The tunnel is stopped. Settings edited since starting were preserved: $($conflicts -join ', '). Restore those keys using the original values in .casa-preview\state.json, then run stop again."
    }
    Remove-Item -LiteralPath $StatePath
}

function Invoke-CasaPreview {
    [CmdletBinding()]
    param(
        [ValidateSet('start', 'status', 'stop')] [string] $Action = 'status',
        [Parameter(Mandatory)] [string] $ProjectPath
    )

    $directory = Join-Path $ProjectPath '.casa-preview'
    $statePath = Join-Path $directory 'state.json'
    if ($Action -eq 'status') {
        $state = Read-CasaPreviewState $statePath
        if ($null -eq $state) { Write-Output 'Cloudflare preview: stopped.'; return }
        if ($null -eq (Get-CasaPreviewOwnedProcess $state)) {
            Write-Output 'Cloudflare preview: stopped; run scripts\casa-preview.ps1 stop to restore local settings.'
            return
        }
        Write-Output "Cloudflare preview: running. $($state.Url)"
        $health = if ($state.Url -and (Test-CasaPreviewHttp $state.Url)) { 'reachable' } else { 'unreachable' }
        Write-Output "Public site: $health. Origin: $($state.Origin)"
        return
    }
    if ($Action -eq 'stop' -and -not (Test-Path -LiteralPath $statePath)) { Write-Output 'Cloudflare preview: already stopped.'; return }

    New-Item -ItemType Directory -Path $directory -Force | Out-Null
    try { $lock = [System.IO.File]::Open((Join-Path $directory 'session.lock'), 'OpenOrCreate', 'ReadWrite', 'None') }
    catch { throw 'Another preview start/stop command is running. Wait for it to finish, then try again.' }
    try {
        $state = Read-CasaPreviewState $statePath
        if ($Action -eq 'stop') {
            Stop-CasaPreviewSession $ProjectPath $statePath $state
            Write-Output 'Cloudflare preview stopped. Local settings restored; Docker is still running.'
            return
        }
        if ($null -ne $state) {
            if ($null -ne (Get-CasaPreviewOwnedProcess $state) -and $state.Url -and (Test-CasaPreviewHttp $state.Url)) {
                Write-Output "Cloudflare preview is already running: $($state.Url)"
                return
            }
            Stop-CasaPreviewSession $ProjectPath $statePath $state
        }

        $cloudflared = Get-Command cloudflared -CommandType Application -ErrorAction SilentlyContinue
        if ($null -eq $cloudflared) { throw 'Install cloudflared before starting the preview: https://developers.cloudflare.com/tunnel/downloads/' }
        $environment = Get-CasaPreviewEnvironment $ProjectPath
        $portValue = Get-CasaPreviewValue (Find-CasaPreviewSetting $environment 'APP_PORT')
        $port = 18001
        if ($portValue -and (-not [int]::TryParse($portValue, [ref] $port) -or $port -lt 1 -or $port -gt 65535)) { throw 'APP_PORT must be a valid TCP port.' }
        $origin = "http://127.0.0.1:$port"
        if (Test-Path -LiteralPath (Join-Path $ProjectPath 'public\hot')) { throw 'Stop the Vite dev server and run scripts\casa-docker.ps1 compose exec -T app npm run build before previewing.' }
        if (-not (Test-Path -LiteralPath (Join-Path $ProjectPath 'public\build\manifest.json'))) { throw 'Build assets first: scripts\casa-docker.ps1 compose exec -T app npm run build' }
        if (-not (Test-CasaPreviewHttp $origin)) { Invoke-CasaPreviewDocker $ProjectPath 'start' }
        $localTimer = [System.Diagnostics.Stopwatch]::StartNew()
        while (-not (Test-CasaPreviewHttp $origin)) {
            if ($localTimer.Elapsed.TotalSeconds -gt 15) { throw 'The local site is not responding successfully. Check scripts\casa-docker.ps1 status.' }
            Start-Sleep -Milliseconds 500
        }

        $originals = [pscustomobject] @{}
        foreach ($key in $script:ManagedKeys) {
            $setting = Find-CasaPreviewSetting $environment $key
            $original = [pscustomobject] @{ Present = ($null -ne $setting); Line = $(if ($null -ne $setting) { $setting.Line } else { $null }) }
            $originals | Add-Member -NotePropertyName $key -NotePropertyValue $original
        }
        $state = [pscustomobject] @{ Version = 1; Process = $null; Url = $null; Origin = $origin; OriginalSettings = $originals; AppliedSettings = [pscustomobject] @{} }
        try {
            Set-CasaPreviewSettings $ProjectPath $statePath $state @{ APP_DEBUG = 'false'; SESSION_SECURE_COOKIE = 'true' }
            Invoke-CasaPreviewDocker $ProjectPath 'clear-config'
            $state.Process = Start-CasaPreviewTunnel $cloudflared.Source $origin $directory
            Write-CasaPreviewState $statePath $state
            Wait-CasaPreviewReady $ProjectPath $statePath $directory $state
        } catch {
            $failure = $_.Exception.Message
            try { Stop-CasaPreviewSession $ProjectPath $statePath $state }
            catch { throw "Preview startup failed: $failure Recovery needs attention: $($_.Exception.Message)" }
            throw "Preview startup failed: $failure Local settings were restored."
        }
        Write-Output "Casa Paraiso preview: $($state.Url)"
        Write-Output 'Keep this computer, Docker, and cloudflared running. Stop with scripts\casa-preview.ps1 stop.'
    } finally { $lock.Dispose() }
}

Export-ModuleMember -Function Invoke-CasaPreview
