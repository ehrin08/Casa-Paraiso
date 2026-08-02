[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('setup', 'start', 'stop', 'status', 'compose')]
    [string] $Action = 'status',
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $ComposeArguments
)

Import-Module (Join-Path $PSScriptRoot 'CasaDocker.psm1') -Force

switch ($Action) {
    'setup' {
        $projectPath = Get-CasaProjectPath
        $environmentPath = Join-Path $projectPath '.env'
        if (-not (Test-Path $environmentPath)) {
            Copy-Item (Join-Path $projectPath '.env.example') $environmentPath
        }

        Invoke-CasaCompose -EnsureEngine -Arguments @('up', '--build', '-d') | Write-Output

        Invoke-CasaCompose -EnsureEngine -Arguments @('exec', '-T', 'app', 'composer', 'install', '--no-interaction', '--prefer-dist') | Write-Output
        Invoke-CasaCompose -EnsureEngine -Arguments @('exec', '-T', 'app', 'npm', 'ci') | Write-Output

        if ((Select-String -Path $environmentPath -Pattern '^APP_KEY=$' -Quiet)) {
            Invoke-CasaCompose -EnsureEngine -Arguments @('exec', '-T', 'app', 'php', 'artisan', 'key:generate', '--force') | Write-Output
        }

        Invoke-CasaCompose -EnsureEngine -Arguments @('exec', '-T', 'app', 'php', 'artisan', 'migrate', '--force') | Write-Output
        Invoke-CasaCompose -EnsureEngine -Arguments @('exec', '-T', 'app', 'npm', 'run', 'build') | Write-Output
    }
    'start' {
        Invoke-CasaCompose -EnsureEngine -Arguments @('up', '-d') | Write-Output
    }
    'stop' {
        Invoke-CasaCompose -Arguments @('stop') | Write-Output
    }
    'status' {
        Assert-CasaDockerEngine
        Write-Output 'Docker engine: Docker Desktop (desktop-linux)'
        Write-Output "Compose project: $(Get-CasaComposeProjectName)"
        Invoke-CasaCompose -Arguments @('ps', '--all') | Write-Output
    }
    'compose' {
        if (-not $ComposeArguments) {
            throw 'Usage: .\scripts\casa-docker.ps1 compose <docker compose arguments>'
        }

        Invoke-CasaCompose -EnsureEngine -Arguments $ComposeArguments | Write-Output
    }
}
