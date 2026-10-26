$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms
$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
$os = Get-CimInstance Win32_OperatingSystem
$ps = [System.Windows.Forms.SystemInformation]::PowerStatus
$powerScheme = (powercfg /getactivescheme 2>&1 | Out-String).Trim()
$memTotalKb = [double]$os.TotalVisibleMemorySize
$memFreeKb = [double]$os.FreePhysicalMemory
$snap = [ordered]@{
  recordedAtUtc = (Get-Date).ToUniversalTime().ToString('o')
  purpose = 'Single post-restore environment observation for documentation only. No benchmark ran. One snapshot does not establish causality of any earlier readiness failure and does not exclude Docker/hardware/power contributors. No setting was changed and no application was started or stopped.'
  cpu = [ordered]@{
    loadPercentSingleSample = [int](Get-CimInstance Win32_Processor).LoadPercentage
    currentClockMhzRaw = [int]$cpu.CurrentClockSpeed
    maxClockMhzRaw = [int]$cpu.MaxClockSpeed
    coreCount = [int]$cpu.NumberOfCores
    logicalProcessors = [int]$cpu.NumberOfLogicalProcessors
  }
  memory = [ordered]@{
    totalVisibleKb = $memTotalKb
    freeKb = $memFreeKb
    usedPercentApprox = [math]::Round(100 * ($memTotalKb - $memFreeKb) / $memTotalKb, 1)
  }
  power = [ordered]@{
    source = 'System.Windows.Forms.SystemInformation.PowerStatus'
    powerLineStatusEnum = [string]$ps.PowerLineStatus
    batteryChargeStatusEnum = [string]$ps.BatteryChargeStatus
    batteryLifePercentRaw = $ps.BatteryLifePercent
    batteryLifeSecondsRemainingRaw = $ps.BatteryLifeRemaining
  }
  activePowerSchemeRawText = $powerScheme
}
$dir = 'F:/Travel Website/bookly travel/specs/020-tour-detail-experience/evidence/final/runtime/opencode-engine-resume-20261017'
Set-Content -Path "$dir/environment-snapshot.json" -Value ($snap | ConvertTo-Json -Depth 4) -Encoding UTF8
$snap | ConvertTo-Json -Depth 4
