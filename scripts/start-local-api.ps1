param([string]$Device)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$python = Join-Path $projectRoot 'backend/.venv/Scripts/python.exe'
$runner = Join-Path $projectRoot 'backend/run_api.py'
if (!(Test-Path -LiteralPath $python)) { throw 'Install backend dependencies first. See backend/README.md.' }
$checkOutput = & $python $runner --check
if ($LASTEXITCODE -ne 0) { throw 'The shared MySQL connection check failed. Update backend/.env.mysql.' }
$expected = $checkOutput | ConvertFrom-Json
$health = $null
try { $health = Invoke-RestMethod 'http://127.0.0.1:8000/api/health' -TimeoutSec 3 } catch {}
if ($null -ne $health -and $health.application -ne 'lumear_mobile') { throw 'Port 8000 is occupied by another service.' }
if ($null -ne $health -and ($health.database_tag -ne $expected.database_tag -or $health.code_tag -ne $expected.code_tag)) {
    throw 'Another Lumear API version is using port 8000. Stop that API before restarting this launcher.'
}
if ($null -eq $health) {
    Start-Process -FilePath $python -ArgumentList @($runner, '--port', '8000') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot 'backend/api.stdout.log') -RedirectStandardError (Join-Path $projectRoot 'backend/api.stderr.log') | Out-Null
    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        try { $health = Invoke-RestMethod 'http://127.0.0.1:8000/api/health' -TimeoutSec 2; break } catch { Start-Sleep -Milliseconds 300 }
    }
    if ($null -eq $health) { throw 'API startup failed. Check backend/api.stderr.log.' }
}
$adb = Join-Path $env:LOCALAPPDATA 'Android/Sdk/platform-tools/adb.exe'
if (Test-Path -LiteralPath $adb) {
    $adbArgs = if ($Device -and $Device -ne 'windows') { @('-s', $Device) } else { @() }
    & $adb @adbArgs reverse tcp:8000 tcp:8000
    if ($LASTEXITCODE -ne 0) { Write-Warning 'USB forwarding was unavailable. Connect an Android device or use desktop.' }
}
New-Item -ItemType Directory -Force -Path (Join-Path $projectRoot 'build/java-tmp') | Out-Null
Write-Host 'Lumear local API is ready.'
