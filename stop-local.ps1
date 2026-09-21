param()

$ErrorActionPreference = "Stop"
$pidFile = Join-Path $PSScriptRoot ".local-dev.pids.json"
if (-not (Test-Path -LiteralPath $pidFile)) { Write-Host "Nie znaleziono serwerów uruchomionych przez start-local.ps1."; exit 0 }

$pids = Get-Content -LiteralPath $pidFile -Raw | ConvertFrom-Json
foreach ($id in @($pids.vite, $pids.php, $pids.worker)) {
  if ($null -ne $id) { Stop-Process -Id ([int]$id) -Force -ErrorAction SilentlyContinue }
}
Remove-Item -LiteralPath $pidFile -Force -ErrorAction SilentlyContinue
Write-Host "Zatrzymano lokalne serwery."
