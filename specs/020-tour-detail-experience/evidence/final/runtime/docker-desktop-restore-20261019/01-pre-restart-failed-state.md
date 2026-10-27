### 01 pre-restart failed-state baseline
captured_at_utc=2026-10-19T20:00:50.9429076Z
watchdog_note = on timeout: taskkill /T /F /PID targets ONLY the spawned probe PID (bounded watchdog per task brief); no user apps touched

#### probe version
ts_utc     = 2026-10-19T20:00:51.3082839Z
cmd        = docker version
status     = {"error":"","args":"version","elapsed_ms":204,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
Client:
 Version:           29.8.2
 API version:       1.56
 Go version:        go1.26.8
 Git commit:        7fc2dff
 Built:             Wed Sep 30 19:35:42 2026
 OS/Arch:           windows/amd64
 Context:           desktop-linux

Server: Docker Desktop 4.95.0 (242831)
 Engine:
  Version:          29.8.2
  API version:      1.56 (minimum version 1.40)
  Go version:       go1.26.8
  Git commit:       8af9fe3
  Built:            Wed Sep 30 19:32:25 2026
  OS/Arch:          linux/amd64
  Experimental:     false
 containerd:
  Version:          v2.3.6
  GitCommit:        ee2735368117d2eb259779949d5e75cdafec9761
 runc:
  Version:          1.5.1
  GitCommit:        v1.5.1-0-g8f2685a4
 docker-init:
  Version:          0.19.0
  GitCommit:        de40ad0
stderr >>>
<no-output-file>

#### probe inspect-state
ts_utc     = 2026-10-19T20:00:51.5297404Z
cmd        = docker inspect bookly-frontend --format {{json .State}}
status     = {"error":"","args":"inspect bookly-frontend --format \"{{json .State}}\"","elapsed_ms":185,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
{"Status":"running","Running":true,"Paused":false,"Restarting":false,"OOMKilled":false,"Dead":false,"Pid":888,"ExitCode":0,"Error":"","StartedAt":"2026-10-19T19:25:17.5997743Z","FinishedAt":"2026-10-19T19:25:15.1966774Z"}
stderr >>>
<no-output-file>

#### probe inspect-id
ts_utc     = 2026-10-19T20:00:51.7392691Z
cmd        = docker inspect bookly-frontend --format {{.Id}}
status     = {"error":"","args":"inspect bookly-frontend --format {{.Id}}","elapsed_ms":179,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
02576f5389daf73a62d3ca5193d434ca12b6a8e13a43b4454496439caaa1e692
stderr >>>
<no-output-file>

#### probe ps-frontend
ts_utc     = 2026-10-19T20:00:51.9675707Z
cmd        = docker ps -a --filter name=bookly-frontend --format {{.ID}} | {{.Image}} | {{.Status}} | {{.Names}}
status     = {"error":"","args":"ps -a --filter name=bookly-frontend --format \"{{.ID}} | {{.Image}} | {{.Status}} | {{.Names}}\"","elapsed_ms":201,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
02576f5389da | booklytravel-nextjs:spec020-preload-be653d72 | Up 35 minutes | bookly-frontend
stderr >>>
<no-output-file>

#### probe inspect-full
ts_utc     = 2026-10-19T20:00:52.1867108Z
cmd        = docker inspect bookly-frontend
status     = {"error":"","args":"inspect bookly-frontend","elapsed_ms":184,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
[
    {
        "Id": "02576f5389daf73a62d3ca5193d434ca12b6a8e13a43b4454496439caaa1e692",
        "Created": "2026-10-18T21:42:42.851635027Z",
        "Path": "docker-entrypoint.sh",
        "Args": [
            "sh",
            "-c",
            "rm -rf /app/.next/* \u0026\u0026 npm run build \u0026\u0026 npm start"
        ],
        "State": {
            "Status": "running",
            "Running": true,
            "Paused": false,
            "Restarting": false,
            "OOMKilled": false,
            "Dead": false,
            "Pid": 888,
            "ExitCode": 0,
            "Error": "",
            "StartedAt": "2026-10-19T19:25:17.5997743Z",
            "FinishedAt": "2026-10-19T19:25:15.1966774Z"
        },
        "Image": "sha256:834f6e3234360ff29d62b042e1070011f4a80e28d8c199ab0a49ec2b86c7d7b9",
        "ResolvConfPath": "/var/lib/docker/containers/02576f5389daf73a62d3ca5193d434ca12b6a8e13a43b4454496439caaa1e692/resolv.conf",
        "HostnamePath": "/var/lib/docker/containers/02576f5389daf73a62d3ca5193d434ca12b6a8e13a43b4454496439caaa1e692/hostname",
        "HostsPath": "/var/lib/docker/containers/02576f5389daf73a62d3ca5193d434ca12b6a8e13a43b4454496439caaa1e692/hosts",
        "LogPath": "",
        "Name": "/bookly-frontend",
        "RestartCount": 0,
        "Driver": "overlayfs",
        "Platform": "linux",
        "MountLabel": "",
        "ProcessLabel": "",
        "AppArmorProfile": "",
        "ExecIDs": null,
        "HostConfig": {
            "Binds": [
                "F:\\Travel Website\\bookly travel\\frontend\\.env.local:/app/.env.local:ro"
            ],
            "ContainerIDFile": "",
            "LogConfig": {
                "Type": "local",
                "Config": {}
            },
            "NetworkMode": "booklytravel_bookly-network",
            "PortBindings": {
                "3000/tcp": [
                    {
                        "HostIp": "",
                        "HostPort": "3001"
                    }
                ]
            },
            "RestartPolicy": {
                "Name": "unless-stopped",
                "MaximumRetryCount": 0
            },
            "AutoRemove": false,
            "VolumeDriver": "",
            "VolumesFrom": null,
            "ConsoleSize": [
                0,
                0
            ],
            "CapAdd": null,
            "CapDrop": null,
            "CgroupnsMode": "private",
            "Dns": [],
            "DnsOptions": [],
            "DnsSearch": [],
            "ExtraHosts": [],
            "GroupAdd": null,
            "IpcMode": "private",
            "Cgroup": "",
            "Links": null,
            "OomScoreAdj": 0,
            "PidMode": "",
            "Privileged": false,
            "PublishAllPorts": false,
            "ReadonlyRootfs": false,
            "SecurityOpt": null,
            "UTSMode": "",
            "UsernsMode": "",
            "ShmSize": 67108864,
            "Runtime": "runc",
            "Isolation": "",
            "CpuShares": 0,
            "Memory": 0,
            "NanoCpus": 0,
            "CgroupParent": "",
            "BlkioWeight": 0,
            "BlkioWeightDevice": null,
            "BlkioDeviceReadBps": null,
            "BlkioDeviceWriteBps": null,
            "BlkioDeviceReadIOps": null,
            "BlkioDeviceWriteIOps": null,
            "CpuPeriod": 0,
            "CpuQuota": 0,
            "CpuRealtimePeriod": 0,
            "CpuRealtimeRuntime": 0,
            "CpusetCpus": "",
            "CpusetMems": "",
            "Devices": null,
            "DeviceCgroupRules": null,
            "DeviceRequests": null,
            "MemoryReservation": 0,
            "MemorySwap": 0,
            "MemorySwappiness": null,
            "OomKillDisable": null,
            "PidsLimit": null,
            "Ulimits": null,
            "CpuCount": 0,
            "CpuPercent": 0,
            "IOMaximumIOps": 0,
            "IOMaximumBandwidth": 0,
            "Mounts": [
                {
                    "Type": "volume",
                    "Source": "aa225930c887b33175e23086d1e0d33b2b2ff1b4d030a5d1ac6fd7abba1167cc",
                    "Target": "/app/.next"
                },
                {
                    "Type": "volume",
                    "Source": "700d0170b9a2f062afb79e7867a5f3aa0269f96dc0800c0796f4383fa726783f",
                    "Target": "/app/node_modules"
                }
            ],
            "MaskedPaths": [
                "/proc/acpi",
                "/proc/asound",
                "/proc/interrupts",
                "/proc/kcore",
                "/proc/keys",
                "/proc/latency_stats",
                "/proc/sched_debug",
                "/proc/scsi",
                "/proc/timer_list",
                "/proc/timer_stats",
                "/sys/devices/virtual/powercap",
                "/sys/firmware"
            ],
            "ReadonlyPaths": [
                "/proc/bus",
                "/proc/fs",
                "/proc/irq",
                "/proc/sys",
                "/proc/sysrq-trigger"
            ]
        },
        "Storage": {
            "RootFS": {
                "Snapshot": {
                    "Name": "overlayfs"
                }
            }
        },
        "Mounts": [
            {
                "Type": "bind",
                "Source": "F:\\Travel Website\\bookly travel\\frontend\\.env.local",
                "Destination": "/app/.env.local",
                "Mode": "ro",
                "RW": false,
                "Propagation": "rprivate"
            },
            {
                "Type": "volume",
                "Name": "aa225930c887b33175e23086d1e0d33b2b2ff1b4d030a5d1ac6fd7abba1167cc",
                "Source": "/var/lib/docker/volumes/aa225930c887b33175e23086d1e0d33b2b2ff1b4d030a5d1ac6fd7abba1167cc/_data",
                "Destination": "/app/.next",
                "Driver": "local",
                "Mode": "z",
                "RW": true,
                "Propagation": ""
            },
            {
                "Type": "volume",
                "Name": "700d0170b9a2f062afb79e7867a5f3aa0269f96dc0800c0796f4383fa726783f",
                "Source": "/var/lib/docker/volumes/700d0170b9a2f062afb79e7867a5f3aa0269f96dc0800c0796f4383fa726783f/_data",
                "Destination": "/app/node_modules",
                "Driver": "local",
                "Mode": "z",
                "RW": true,
                "Propagation": ""
            }
        ],
        "Config": {
            "Hostname": "02576f5389da",
            "Domainname": "",
            "User": "",
            "AttachStdin": false,
            "AttachStdout": true,
            "AttachStderr": true,
            "ExposedPorts": {
                "3000/tcp": {}
            },
            "Tty": false,
            "OpenStdin": false,
            "StdinOnce": false,
            "Env": [
                "NEXT_PUBLIC_API_URL=",
                "DOCKER_ENV=true",
                "ALLOW_PRIVATE_IMAGE_UPSTREAM=true",
                "API_INTERNAL_URL=http://nginx",
                "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
                "NODE_VERSION=20.20.2",
                "YARN_VERSION=1.22.22",
                "WATCHPACK_POLLING=true"
            ],
            "Cmd": [
                "sh",
                "-c",
                "rm -rf /app/.next/* \u0026\u0026 npm run build \u0026\u0026 npm start"
            ],
            "Image": "booklytravel-nextjs:spec020-preload-be653d72",
            "Volumes": null,
            "WorkingDir": "/app",
            "Entrypoint": [
                "docker-entrypoint.sh"
            ],
            "Labels": {
                "com.docker.compose.config-hash": "4364d11eb4ff3e51be42a7326a4ad43db513bb16794e203acec74d6e574e031c",
                "com.docker.compose.container-number": "1",
                "com.docker.compose.depends_on": "",
                "com.docker.compose.image": "sha256:130b263a2d57303199a2b106b08a13d4362b64b8a8cdca7f6ef3fb9b712b97ba",
                "com.docker.compose.oneoff": "False",
                "com.docker.compose.project": "booklytravel",
                "com.docker.compose.project.config_files": "F:\\Travel Website\\bookly travel\\docker-compose.yml,C:\\Users\\HaTeM\\.codex\\tmp\\bookly-spec020\\lighthouse-repair-20261018\\qa-preload\\compose.preload-override.yml",
                "com.docker.compose.project.working_dir": "F:\\Travel Website\\bookly travel",
                "com.docker.compose.replace": "bookly-frontend",
                "com.docker.compose.service": "nextjs",
                "com.docker.compose.version": "5.6.0"
            }
        },
        "NetworkSettings": {
            "SandboxID": "0e1dbbc616b9f1535c5481fda833731f1492586bf3699a6f9d1ca97428f39f5c",
            "SandboxKey": "/var/run/docker/netns/0e1dbbc616b9",
            "Ports": {
                "3000/tcp": [
                    {
                        "HostIp": "0.0.0.0",
                        "HostPort": "3001"
                    },
                    {
                        "HostIp": "::",
                        "HostPort": "3001"
                    }
                ]
            },
            "Networks": {
                "booklytravel_bookly-network": {
                    "IPAMConfig": null,
                    "Links": null,
                    "Aliases": [
                        "bookly-frontend",
                        "nextjs"
                    ],
                    "DriverOpts": null,
                    "GwPriority": 0,
                    "NetworkID": "baa054b3cbb8332932341b2a562dc38a7ad48a9913b238b7a8db59c4bb22be12",
                    "EndpointID": "65f8b6e82c10d5bd024361e8cdbe2aeaf2cd4f36de70e53911320c1a3b3e937a",
                    "Gateway": "172.21.0.1",
                    "IPAddress": "172.21.0.8",
                    "MacAddress": "8e:34:96:61:d9:7d",
                    "IPPrefixLen": 16,
                    "IPv6Gateway": "",
                    "GlobalIPv6Address": "",
                    "GlobalIPv6PrefixLen": 0,
                    "DNSNames": [
                        "bookly-frontend",
                        "nextjs",
                        "02576f5389da"
                    ]
                }
            }
        },
        "ImageManifestDescriptor": {
            "mediaType": "application/vnd.oci.image.manifest.v1+json",
            "digest": "sha256:130b263a2d57303199a2b106b08a13d4362b64b8a8cdca7f6ef3fb9b712b97ba",
            "size": 3727,
            "platform": {
                "architecture": "amd64",
                "os": "linux"
            }
        }
    }
]
stderr >>>
<no-output-file>

#### probe image-inspect
ts_utc     = 2026-10-19T20:00:52.4406595Z
cmd        = docker image inspect booklytravel-nextjs:spec020-preload-be653d72 --format {{.Id}}
status     = {"error":"","args":"image inspect booklytravel-nextjs:spec020-preload-be653d72 --format {{.Id}}","elapsed_ms":226,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
sha256:834f6e3234360ff29d62b042e1070011f4a80e28d8c199ab0a49ec2b86c7d7b9
stderr >>>
<no-output-file>

#### probe logs-tail
ts_utc     = 2026-10-19T20:00:52.6518307Z
cmd        = docker logs bookly-frontend --tail 30
status     = {"error":"","args":"logs bookly-frontend --tail 30","elapsed_ms":184,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
Γö£ ╞Æ /[locale]/partner/reviews
Γö£ ╞Æ /[locale]/partner/tours
Γö£ ╞Æ /[locale]/partner/tours/[id]/availability
Γö£ ╞Æ /[locale]/partner/tours/[id]/edit
Γö£ ╞Æ /[locale]/partner/tours/[id]/pricing
Γö£ ╞Æ /[locale]/partner/tours/create
Γö£ ╞Æ /[locale]/privacy
Γö£ ╞Æ /[locale]/profile
Γö£ ╞Æ /[locale]/search
Γö£ ╞Æ /[locale]/terms
Γö£ ╞Æ /[locale]/tours/[slug]
Γö£ ╞Æ /[locale]/wishlist
Γö£ ╞Æ /api/auth/session
Γö£ Γùï /robots.txt
Γöö ╞Æ /v/[reference]


╞Æ Proxy (Middleware)

Γùï  (Static)   prerendered as static content
╞Æ  (Dynamic)  server-rendered on demand


> frontend@0.1.0 start
> next start

Γû▓ Next.js 16.2.3
- Local:         http://localhost:3000
- Network:       http://172.21.0.8:3000
Γ£ô Ready in 873ms
stderr >>>
<no-output-file>

captured_done_utc=2026-10-19T20:00:52.6528383Z
