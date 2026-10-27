### 04 engine recovery wait loop (up to 300s, ~15s interval; bounded per-iteration watchdog 15s)
ts_start_utc=2026-10-19T20:03:07.0382937Z
iter=1 ts_utc=2026-10-19T20:03:09.2100609Z cmd=docker version --format {{.Server.Version}} status={"error":"","args":"version --format {{.Server.Version}}","elapsed_ms":1217,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[29.8.2] stderr=[<no-output-file>]
ENGINE_API_READY ts_utc=2026-10-19T20:03:09.2112332Z server_version=29.8.2
post_ready_status: docker desktop status status={"error":"","args":"desktop status","elapsed_ms":1682,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
desktop-status-stdout=[Name                Value
Status              running
SessionID           d7c47394-d2be-4005-bb75-592a7ceacb40]
ts_done_utc=2026-10-19T20:03:10.9461426Z
