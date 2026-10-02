[CmdletBinding()]
param([switch]$CheckOnly)

$ErrorActionPreference = 'Stop'
$target = 'F:\pagefile.sys'
$disk = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='F:'"
if (-not $disk -or $disk.DriveType -ne 3 -or $disk.FreeSpace -lt 24GB) {
    throw 'F: must be a fixed local disk with at least 24GB free. No settings changed.'
}
$system = Get-CimInstance Win32_ComputerSystem
if ($system.AutomaticManagedPagefile) {
    throw 'Automatic pagefile management is enabled. This helper will not change that global setting.'
}
$before = @(Get-CimInstance Win32_PageFileSetting)
$existing = @($before | Where-Object { $_.Name -ieq $target })
if ($existing.Count -gt 1) { throw 'Ambiguous F: pagefile configuration. No settings changed.' }
if ($existing.Count -eq 1 -and ($existing[0].InitialSize -ne 8192 -or $existing[0].MaximumSize -ne 16384)) {
    throw 'An F: pagefile already has different settings. This helper will not overwrite it.'
}
$untouchedBefore = @($before | Where-Object { $_.Name -ine $target } | Sort-Object Name |
    Select-Object Name,InitialSize,MaximumSize) | ConvertTo-Json -Compress
if ($CheckOnly) {
    [pscustomobject]@{ Target=$target; InitialMB=8192; MaximumMB=16384; ExistingMatching=($existing.Count -eq 1); RebootAutomatically=$false }
    return
}
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Administrator privileges are required. No settings changed.'
}
if ($existing.Count -eq 0) {
    New-CimInstance -ClassName Win32_PageFileSetting -Property @{
        Name=$target; InitialSize=[uint32]8192; MaximumSize=[uint32]16384
    } | Out-Null
}
$after = @(Get-CimInstance Win32_PageFileSetting)
$untouchedAfter = @($after | Where-Object { $_.Name -ine $target } | Sort-Object Name |
    Select-Object Name,InitialSize,MaximumSize) | ConvertTo-Json -Compress
if ($untouchedBefore -ne $untouchedAfter) { throw 'Unexpected change to another pagefile. Inspect Windows settings before continuing.' }
$configured = @($after | Where-Object { $_.Name -ieq $target })
if ($configured.Count -ne 1 -or $configured[0].InitialSize -ne 8192 -or $configured[0].MaximumSize -ne 16384) {
    throw 'F: pagefile configuration could not be verified.'
}
$configured | Select-Object Name,InitialSize,MaximumSize
# Configuration can be pending until a user-controlled reboot. Never reboot here.
