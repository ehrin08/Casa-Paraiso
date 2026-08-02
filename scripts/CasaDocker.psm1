Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:DockerContext = 'desktop-linux'
$script:ComposeProjectName = 'casa-paraiso-web'

function Get-CasaProjectPath {
    return (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
}

function Get-CasaComposeProjectName {
    return $script:ComposeProjectName
}

function Assert-CasaDockerEngine {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw 'Docker CLI is unavailable. Install and start Docker Desktop before running Casa Paraiso.'
    }

    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & docker --context $script:DockerContext info --format '{{.Name}}|{{.OperatingSystem}}' 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    $output = @($output | ForEach-Object { $_.ToString() })

    if ($exitCode -ne 0) {
        throw "Docker Desktop is unavailable on context '$script:DockerContext': $($output -join [Environment]::NewLine)"
    }

    $identity = ($output | Select-Object -Last 1).Trim()
    if ($identity -ne 'docker-desktop|Docker Desktop') {
        throw "Refusing to run against an unexpected Docker daemon. Expected Docker Desktop on context '$script:DockerContext', received '$identity'."
    }
}

function Start-CasaDockerEngine {
    Assert-CasaDockerEngine
}

function Invoke-CasaCompose {
    param(
        [Parameter(Mandatory)] [string[]] $Arguments,
        [switch] $EnsureEngine
    )

    Assert-CasaDockerEngine
    $projectPath = Get-CasaProjectPath
    $command = @('--context', $script:DockerContext, 'compose', '--project-name', $script:ComposeProjectName) + $Arguments
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    Push-Location $projectPath
    try {
        $output = & docker @command 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        Pop-Location
        $ErrorActionPreference = $previousErrorAction
    }
    $output = @($output | ForEach-Object { $_.ToString() })

    if ($exitCode -ne 0) {
        throw "Docker Compose failed ($exitCode): $($output -join [Environment]::NewLine)"
    }

    return $output
}

Export-ModuleMember -Function Get-CasaProjectPath, Get-CasaComposeProjectName, Assert-CasaDockerEngine, Start-CasaDockerEngine, Invoke-CasaCompose
