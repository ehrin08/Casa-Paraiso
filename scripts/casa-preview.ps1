[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('start', 'status', 'stop')]
    [string] $Action = 'status'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'CasaPreview.psm1') -Force
Invoke-CasaPreview -Action $Action -ProjectPath (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
