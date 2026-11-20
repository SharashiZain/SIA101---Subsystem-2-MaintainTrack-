$ErrorActionPreference = 'Stop'

# First-admin bootstrap for local Supabase staging only. This deliberately
# refuses remote API hosts and refuses to run after an active Admin exists.
$runtimePath = 'D:\xampp\htdocs\SIA103_Central-Server\.local-staging\runtime.json'
$php = 'D:\xampp\php\php.exe'

if (-not (Test-Path -LiteralPath $runtimePath)) {
    throw "Local staging runtime was not found at $runtimePath. Start local staging first."
}
if (-not (Test-Path -LiteralPath $php)) {
    throw "PHP CLI was not found at $php."
}

$runtime = Get-Content -LiteralPath $runtimePath -Raw | ConvertFrom-Json
$apiUrl = [string]$runtime.supabase.maintain.api_url
$apiKey = [string]$runtime.supabase.maintain.secret_key
$apiUri = [Uri]$apiUrl
if ($apiUri.Scheme -ne 'http' -or $apiUri.Host -notin @('127.0.0.1', 'localhost', '::1') -or $apiUri.Port -ne 57421) {
    throw 'Refusing to bootstrap: the MaintainTrack API is not the expected local staging endpoint.'
}
if (-not $apiKey) { throw 'Local MaintainTrack service key was not found in the staging runtime.' }

$headers = @{ apikey = $apiKey; Authorization = "Bearer $apiKey" }
$adminUrl = "$apiUrl/rest/v1/users?select=user_id&role=eq.admin&status=eq.Active&limit=1"
$existingAdmins = Invoke-RestMethod -Method Get -Uri $adminUrl -Headers $headers
if ($null -ne $existingAdmins -and @($existingAdmins).Count -gt 0) {
    throw 'An active Admin already exists in local MaintainTrack. Sign in with that account; bootstrap is disabled.'
}

$fullName = (Read-Host 'Admin full name').Trim()
$email = (Read-Host 'Admin email').Trim().ToLowerInvariant()
$username = (Read-Host 'Admin username (3-50 lowercase letters, numbers, dots, underscores, or hyphens)').Trim().ToLowerInvariant()
if (-not $fullName -or $fullName.Length -gt 100) { throw 'Enter a full name of 1 to 100 characters.' }
if ($email -notmatch '^[^\s@]+@[^\s@]+\.[^\s@]+$' -or $email.Length -gt 150) { throw 'Enter a valid email address.' }
if ($username -notmatch '^[a-z0-9._-]{3,50}$') { throw 'The username format is invalid.' }

$passwordSecure = Read-Host 'Admin password (at least 12 characters)' -AsSecureString
$passwordAgainSecure = Read-Host 'Confirm admin password' -AsSecureString
$passwordPtr = [IntPtr]::Zero
$passwordAgainPtr = [IntPtr]::Zero
$password = $null
$passwordAgain = $null
$authUserId = $null
try {
    $passwordPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordSecure)
    $passwordAgainPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordAgainSecure)
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPtr)
    $passwordAgain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordAgainPtr)
    if ($password.Length -lt 12) { throw 'Use a password with at least 12 characters.' }
    if ($password -cne $passwordAgain) { throw 'The passwords do not match.' }

    $env:MAINTAINTRACK_BOOTSTRAP_PASSWORD = $password
    try {
        $hashOutput = & $php -r 'echo password_hash(getenv("MAINTAINTRACK_BOOTSTRAP_PASSWORD"), PASSWORD_DEFAULT);'
        $hashExitCode = $LASTEXITCODE
        $passwordHash = [string]($hashOutput -join '').Trim()
        if ($passwordHash -notmatch '^\$2[ayb]\$\d{2}\$[./A-Za-z0-9]{53}$') {
            throw "PHP did not return a valid password hash (exit code $hashExitCode)."
        }
    } finally {
        Remove-Item Env:\MAINTAINTRACK_BOOTSTRAP_PASSWORD -ErrorAction SilentlyContinue
    }

    $authBody = @{
        email = $email
        password = $password
        email_confirm = $true
        user_metadata = @{ full_name = $fullName; username = $username }
    } | ConvertTo-Json -Depth 4 -Compress
    $authResult = Invoke-RestMethod -Method Post -Uri "$apiUrl/auth/v1/admin/users" -Headers $headers -ContentType 'application/json' -Body $authBody
    $authUserId = [string]$authResult.id
    if (-not $authUserId) { throw 'Supabase Auth did not return a user ID.' }

    $profileBody = @{
        display_id = 'MT-' + (Get-Date -Format yyyy) + '-' + [Guid]::NewGuid().ToString('N').Substring(0, 6).ToUpperInvariant()
        full_name = $fullName
        username = $username
        email = $email
        password_hash = [string]$passwordHash
        role = 'admin'
        status = 'Active'
        auth_user_id = $authUserId
        updated_at = [DateTime]::UtcNow.ToString('o')
    } | ConvertTo-Json -Depth 4 -Compress
    $profileHeaders = @{ apikey = $apiKey; Authorization = "Bearer $apiKey"; Prefer = 'return=representation' }
    $null = Invoke-RestMethod -Method Post -Uri "$apiUrl/rest/v1/users?select=user_id" -Headers $profileHeaders -ContentType 'application/json' -Body $profileBody

    Write-Host 'Local MaintainTrack Admin account created. Sign in at http://127.0.0.1:8083/.' -ForegroundColor Green
    Write-Host "Username: $username"
    Write-Host 'The password was not saved to a file.'
} catch {
    if ($authUserId) {
        try { $null = Invoke-RestMethod -Method Delete -Uri "$apiUrl/auth/v1/admin/users/$authUserId" -Headers $headers } catch {}
    }
    throw "Admin bootstrap failed; any partially created Auth user was rolled back. $($_.Exception.Message)"
} finally {
    if ($passwordPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPtr) }
    if ($passwordAgainPtr -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordAgainPtr) }
    $password = $null
    $passwordAgain = $null
    $passwordHash = $null
    $authBody = $null
    $profileBody = $null
}
