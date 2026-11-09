param([string]$Python = 'py')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$venvPython = Join-Path $projectRoot 'backend/.venv/Scripts/python.exe'
$config = Join-Path $projectRoot 'backend/.env.mysql'

Push-Location $projectRoot
try {
    if (!(Test-Path -LiteralPath $venvPython)) {
        # Override -Python with a Python 3 executable if the Windows launcher is unavailable.
        $pythonArgs = if ((Split-Path -Leaf $Python) -in @('py', 'py.exe')) { @('-3') } else { @() }
        & $Python @pythonArgs -m venv backend/.venv
        if ($LASTEXITCODE -ne 0) { throw 'Could not create the backend environment. Install Python 3 and retry.' }
    }
    & $venvPython -m pip install -r backend/requirements-mysql.txt
    if ($LASTEXITCODE -ne 0) { throw 'Backend dependency installation failed.' }
    if (!(Test-Path -LiteralPath $config)) {
        Copy-Item -LiteralPath (Join-Path $projectRoot 'backend/.env.mysql.example') -Destination $config
        Write-Host 'Created backend/.env.mysql. Enter your local MySQL credentials before starting the API.'
    } else {
        Write-Host 'Keeping your existing backend/.env.mysql.'
    }
    & flutter pub get
    if ($LASTEXITCODE -ne 0) { throw 'Flutter dependency installation failed.' }
    Write-Host 'Dependencies ready. Follow GROUPMATE_SETUP.md to import the database, configure email, and launch.'
} finally { Pop-Location }
