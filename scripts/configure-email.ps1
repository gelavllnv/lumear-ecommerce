param()
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $projectRoot 'backend/.env.mysql'
if (!(Test-Path -LiteralPath $configPath)) { throw 'The existing local API configuration is missing.' }
$senderEmail = (Read-Host 'Lumear sender Gmail address').Trim()
if ($senderEmail -notmatch '^[^\s@]+@gmail\.com$') { throw 'Enter the Gmail account used to send Lumear email.' }
$secureSmtpPassword = Read-Host 'Gmail App Password (hidden; not your normal Gmail password)' -AsSecureString
$smtpPasswordPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureSmtpPassword)
try {
    $smtpAppPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($smtpPasswordPtr) -replace '\s',''
    if ($smtpAppPassword.Length -ne 16) { throw 'A Gmail App Password contains 16 characters.' }
    $smtpValues = @{
        SMTP_HOST = 'smtp.gmail.com'
        SMTP_PORT = '587'
        SMTP_USERNAME = $senderEmail
        SMTP_PASSWORD = $smtpAppPassword
        SMTP_FROM = $senderEmail
    }
    $configLines = [System.Collections.Generic.List[string]]::new()
    foreach ($configLine in [IO.File]::ReadAllLines($configPath)) {
        if ($configLine -match '^\s*(SMTP_HOST|SMTP_PORT|SMTP_USERNAME|SMTP_PASSWORD|SMTP_FROM)\s*=') { continue }
        $configLines.Add($configLine)
    }
    foreach ($smtpKey in @('SMTP_HOST','SMTP_PORT','SMTP_USERNAME','SMTP_PASSWORD','SMTP_FROM')) {
        $configLines.Add($smtpKey + '=' + $smtpValues[$smtpKey])
    }
    [IO.File]::WriteAllLines($configPath, $configLines, [Text.UTF8Encoding]::new($false))
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($smtpPasswordPtr)
    $smtpAppPassword = $null
    $smtpValues = $null
}
Write-Host 'Saved only to the ignored backend configuration. Checking the sender connection...'
& (Join-Path $projectRoot 'backend/.venv/Scripts/python.exe') (Join-Path $projectRoot 'backend/check_email.py')
if ($LASTEXITCODE -ne 0) { throw 'Sender check failed. Review the setup and run this script again.' }
Write-Host 'Sender connection verified. Starting/reloading the local API...'
& (Join-Path $PSScriptRoot 'start-local-api.ps1') -Restart
Write-Host 'Email OTP is configured and the local API has been reloaded.'
