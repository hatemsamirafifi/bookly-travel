### 02 docker desktop CLI discovery (bounded reads, no state change)
ts_utc=2026-10-19T20:01:30.8234647Z

#### help
cmd    = docker desktop --help
status = {"error":"","args":"desktop --help","elapsed_ms":421,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
Usage:  docker desktop COMMAND

The Docker Desktop CLI lets you perform key operations such as starting, stopping,
restarting, and updating Docker Desktop directly from the command line.

Management Commands:
  auth         Manage Docker Desktop authentication
  disable      Disable a feature
  enable       Enable a feature
  engine       Commands to list and switch engine modes (Windows only)
  kubernetes   Manage Kubernetes

Commands:
  diagnose     Diagnose Docker Desktop
  logs         Print log entries
  restart      Restart Docker Desktop
  start        Start Docker Desktop
  status       Show the status of the Docker Desktop engines
  stop         Stop Docker Desktop
  update       Manage Docker Desktop updates
  version      Show the Docker Desktop CLI plugin version information

Run 'docker desktop COMMAND --help' for more information on a command.
stderr >>>
<no-output-file>

#### restart-help
cmd    = docker desktop restart --help
status = {"error":"","args":"desktop restart --help","elapsed_ms":242,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
Usage:  docker desktop restart

Restart Docker Desktop

Options:
  -d, --detach            Do not synchronously wait for the requested
                          operation to complete.
      --timeout seconds   Terminate the running command after the
                          specified timeout with a non-zero exit code. A
                          value of zero (the default) or -1 means no timeout.
stderr >>>
<no-output-file>

#### status-help
cmd    = docker desktop status --help
status = {"error":"","args":"desktop status --help","elapsed_ms":220,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
Usage:  docker desktop status

Show the status of the Docker Desktop engines

Options:
      --format string   Format the output. Accepted values are: pretty,
                        json (default "pretty")
stderr >>>
<no-output-file>

#### status
cmd    = docker desktop status
status = {"error":"","args":"desktop status","elapsed_ms":406,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
Name                Value
Status              running
SessionID           a8e0030a-1a01-432d-ad4c-912b66688463
stderr >>>
<no-output-file>

ts_done_utc=2026-10-19T20:01:32.4819857Z
