param([string]$Device)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
& (Join-Path $PSScriptRoot 'start-local-api.ps1') -Device $Device
Push-Location $projectRoot
try {
    # Windows Java socket paths cannot use the long sandbox TEMP directory.
    $javaTemp = Join-Path $projectRoot 'build/java-tmp'
    New-Item -ItemType Directory -Force -Path $javaTemp | Out-Null
    $env:TEMP = $javaTemp
    $env:TMP = $javaTemp
    $flutterArgs = @('run', '--dart-define-from-file=config/local-api.json')
    if ($Device) { $flutterArgs += @('-d', $Device) }
    & flutter @flutterArgs
} finally { Pop-Location }
