param()

$ErrorActionPreference = "Stop"
$projectRoot = $PSScriptRoot
$pidFile = Join-Path $projectRoot ".local-dev.pids.json"
$phpPath = "D:\xampp\php\php.exe"

if (-not (Test-Path -LiteralPath $phpPath)) { throw "Nie znaleziono PHP: $phpPath" }
if (Test-Path -LiteralPath $pidFile) { & (Join-Path $projectRoot "stop-local.ps1") }

$vite = $null
$php = $null
$worker = $null
try {
  $vite = Start-Process -FilePath "cmd.exe" -ArgumentList "/c", "npm run dev -- --host 127.0.0.1 --port 8443" -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru
  $php = Start-Process -FilePath $phpPath -ArgumentList "-S", "127.0.0.1:8081", "-t", ".", (Join-Path $projectRoot "api/local-router.php") -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru
  $worker = Start-Process -FilePath $phpPath -ArgumentList (Join-Path $projectRoot "api/project-worker.php") -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru
  @{ vite = $vite.Id; php = $php.Id; worker = $worker.Id } | ConvertTo-Json | Set-Content -LiteralPath $pidFile -Encoding utf8

  Write-Host ""
  Write-Host "Projekt działa lokalnie:" -ForegroundColor Green
  Write-Host "  Strona:  http://localhost:8443"
  Write-Host "  AI:      http://localhost:8443/#discovery"
  Write-Host "  Admin:   http://localhost:8443/api/admin.php"
  Write-Host ""
  Write-Host "Naciśnij Ctrl+C, aby zatrzymać oba serwery." -ForegroundColor Yellow
  while ($true) { Start-Sleep -Seconds 1 }
}
finally {
  foreach ($process in @($vite, $php, $worker)) {
    if ($null -ne $process -and -not $process.HasExited) { Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue }
  }
  Remove-Item -LiteralPath $pidFile -Force -ErrorAction SilentlyContinue
}
