param([ValidateSet('browser','a11y','visual','performance')][string]$Mode = 'browser')
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath 'F:/Travel Website/bookly travel'
$scriptsRoot = 'C:/Users/HaTeM/.codex/tmp/bookly-spec020'
if ($Mode -eq 'browser' -or $Mode -eq 'a11y') {
  & node (Join-Path $scriptsRoot 'root-final-browser.cjs') $Mode
} elseif ($Mode -eq 'visual') {
  & node (Join-Path $scriptsRoot 'root-visual-matrix.cjs')
} else {
  & node (Join-Path $scriptsRoot 'root-final-performance.cjs')
}
exit $LASTEXITCODE
