### 05 bookly-frontend identity + entrypoint readiness (post ONE docker desktop restart)
ts_start_utc=2026-10-19T20:04:36.3573831Z
NOTE: container Cmd is "rm -rf /app/.next/* && npm run build && npm start" -> on its own restart the GENERATED Next output rebuilds automatically; BUILD_ID may legitimately change; no manual rebuild performed.

#### ps-all
ts_utc =2026-10-19T20:04:36.7881971Z
cmd    = docker ps -a --format {{.Names}} | {{.Image}} | {{.Status}} | {{.RestartPolicy?none}}
status = {"error":"","args":"ps -a --format \"{{.Names}} | {{.Image}} | {{.Status}} | {{.RestartPolicy?none}}\"","elapsed_ms":202,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":1,"watchdog":"completed"}
stdout >>>
<no-output-file>
stderr >>>
failed to parse template: template: :1: bad character U+003F '?'

#### ps-frontend
ts_utc =2026-10-19T20:04:37.1179518Z
cmd    = docker ps -a --filter name=bookly-frontend --format {{.ID}} | {{.Image}} | {{.Status}}
status = {"error":"","args":"ps -a --filter name=bookly-frontend --format \"{{.ID}} | {{.Image}} | {{.Status}}\"","elapsed_ms":245,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
02576f5389da | booklytravel-nextjs:spec020-preload-be653d72 | Up 2 minutes
stderr >>>
<no-output-file>

#### inspect-short
ts_utc =2026-10-19T20:04:37.5056045Z
cmd    = docker inspect bookly-frontend --format id={{.Id}} image-tag={{.Config.Image}} image-id={{.Image}} status={{.State.Status}} started={{.State.StartedAt}} restartcount={{.RestartCount}} restartpolicy={{.HostConfig.RestartPolicy.Name}} entrypoint={{.Config.Entrypoint}} cmd={{.Config.Cmd}} nets={{.NetworkSettings.Networks}} ports={{.NetworkSettings.Ports}}
status = {"error":"","args":"inspect bookly-frontend --format \"id={{.Id}} image-tag={{.Config.Image}} image-id={{.Image}} status={{.State.Status}} started={{.State.StartedAt}} restartcount={{.RestartCount}} restartpolicy={{.HostConfig.RestartPolicy.Name}} entrypoint={{.Config.Entrypoint}} cmd={{.Config.Cmd}} nets={{.NetworkSettings.Networks}} ports={{.NetworkSettings.Ports}}\"","elapsed_ms":243,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
id=02576f5389daf73a62d3ca5193d434ca12b6a8e13a43b4454496439caaa1e692 image-tag=booklytravel-nextjs:spec020-preload-be653d72 image-id=sha256:834f6e3234360ff29d62b042e1070011f4a80e28d8c199ab0a49ec2b86c7d7b9 status=running started=2026-10-19T20:02:27.652130105Z restartcount=0 restartpolicy=unless-stopped entrypoint=[docker-entrypoint.sh] cmd=[sh -c rm -rf /app/.next/* && npm run build && npm start] nets=map[booklytravel_bookly-network:map[Aliases:[bookly-frontend nextjs] DNSNames:[bookly-frontend nextjs 02576f5389da] DriverOpts:<nil> EndpointID:373d0feabaf24122b3d2578f9d5bcba515b820d3ee33a57de16499884e9fd8b4 Gateway:172.21.0.1 GlobalIPv6Address: GlobalIPv6PrefixLen:0 GwPriority:0 IPAMConfig:<nil> IPAddress:172.21.0.6 IPPrefixLen:16 IPv6Gateway: Links:<nil> MacAddress:46:fd:91:48:54:b0 NetworkID:baa054b3cbb8332932341b2a562dc38a7ad48a9913b238b7a8db59c4bb22be12]] ports=map[3000/tcp:[map[HostIp:0.0.0.0 HostPort:3001] map[HostIp::: HostPort:3001]]]
stderr >>>
<no-output-file>

#### image-id
ts_utc =2026-10-19T20:04:37.8883095Z
cmd    = docker image inspect booklytravel-nextjs:spec020-preload-be653d72 --format {{.Id}}
status = {"error":"","args":"image inspect booklytravel-nextjs:spec020-preload-be653d72 --format {{.Id}}","elapsed_ms":347,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
sha256:834f6e3234360ff29d62b042e1070011f4a80e28d8c199ab0a49ec2b86c7d7b9
stderr >>>
<no-output-file>

#### image-tags/name-of-container-image-id-sha
ts_utc =2026-10-19T20:04:38.2133078Z
cmd    = docker image inspect sha256:834f6e3234360ff29d62b042e1070011f4a80e28d8c199ab0a49ec2b86c7d7b9 --format {{json .RepoTags}}
status = {"error":"","args":"image inspect sha256:834f6e3234360ff29d62b042e1070011f4a80e28d8c199ab0a49ec2b86c7d7b9 --format \"{{json .RepoTags}}\"","elapsed_ms":283,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
["booklytravel-nextjs:spec020-preload-be653d72"]
stderr >>>
<no-output-file>
iter=1 ts_utc=2026-10-19T20:04:38.7887126Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":481,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=1 ts_utc=2026-10-19T20:04:38.9676239Z step=http-3001-origin http_code=[000]
iter=2 ts_utc=2026-10-19T20:04:54.3351335Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":322,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=2 ts_utc=2026-10-19T20:04:54.6188020Z step=http-3001-origin http_code=[307]
iter=3 ts_utc=2026-10-19T20:05:09.9750236Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":320,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=3 ts_utc=2026-10-19T20:05:10.0605588Z step=http-3001-origin http_code=[307]
iter=4 ts_utc=2026-10-19T20:05:25.5100277Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":369,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=4 ts_utc=2026-10-19T20:05:25.6081451Z step=http-3001-origin http_code=[307]
iter=5 ts_utc=2026-10-19T20:05:40.9821564Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":313,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=5 ts_utc=2026-10-19T20:05:41.0951870Z step=http-3001-origin http_code=[307]
iter=6 ts_utc=2026-10-19T20:05:56.3760028Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":246,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=6 ts_utc=2026-10-19T20:05:56.4412569Z step=http-3001-origin http_code=[307]
iter=7 ts_utc=2026-10-19T20:06:11.7899128Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":311,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=7 ts_utc=2026-10-19T20:06:11.8840363Z step=http-3001-origin http_code=[307]
iter=8 ts_utc=2026-10-19T20:06:27.2035280Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":279,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=8 ts_utc=2026-10-19T20:06:27.3047028Z step=http-3001-origin http_code=[307]
iter=9 ts_utc=2026-10-19T20:06:42.5582538Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":235,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=9 ts_utc=2026-10-19T20:06:42.6316247Z step=http-3001-origin http_code=[307]
iter=10 ts_utc=2026-10-19T20:06:57.9599810Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":276,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=10 ts_utc=2026-10-19T20:06:58.0369961Z step=http-3001-origin http_code=[307]
iter=11 ts_utc=2026-10-19T20:07:13.3463000Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":271,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=11 ts_utc=2026-10-19T20:07:13.4410068Z step=http-3001-origin http_code=[307]
iter=12 ts_utc=2026-10-19T20:07:28.7521813Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":266,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=12 ts_utc=2026-10-19T20:07:28.8225086Z step=http-3001-origin http_code=[307]
iter=13 ts_utc=2026-10-19T20:07:44.1338631Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":268,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=13 ts_utc=2026-10-19T20:07:44.1963632Z step=http-3001-origin http_code=[307]
iter=14 ts_utc=2026-10-19T20:07:59.4644710Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":241,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=14 ts_utc=2026-10-19T20:07:59.5297260Z step=http-3001-origin http_code=[307]
iter=15 ts_utc=2026-10-19T20:08:14.9551148Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":328,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=15 ts_utc=2026-10-19T20:08:15.1051424Z step=http-3001-origin http_code=[307]
iter=16 ts_utc=2026-10-19T20:08:30.3872252Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":242,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=16 ts_utc=2026-10-19T20:08:30.4488767Z step=http-3001-origin http_code=[307]
iter=17 ts_utc=2026-10-19T20:08:45.8202198Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":330,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=17 ts_utc=2026-10-19T20:08:45.9051740Z step=http-3001-origin http_code=[307]
iter=18 ts_utc=2026-10-19T20:09:01.1953693Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":271,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=18 ts_utc=2026-10-19T20:09:01.2914640Z step=http-3001-origin http_code=[307]
iter=19 ts_utc=2026-10-19T20:09:16.7866669Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":360,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=19 ts_utc=2026-10-19T20:09:16.8814592Z step=http-3001-origin http_code=[307]
iter=20 ts_utc=2026-10-19T20:09:32.2559518Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":329,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=20 ts_utc=2026-10-19T20:09:32.3459335Z step=http-3001-origin http_code=[307]
iter=21 ts_utc=2026-10-19T20:09:47.7264977Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":323,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=21 ts_utc=2026-10-19T20:09:47.8468612Z step=http-3001-origin http_code=[307]
iter=22 ts_utc=2026-10-19T20:10:03.1936494Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":302,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=22 ts_utc=2026-10-19T20:10:03.2741150Z step=http-3001-origin http_code=[307]
iter=23 ts_utc=2026-10-19T20:10:18.5780522Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":263,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=23 ts_utc=2026-10-19T20:10:18.8525868Z step=http-3001-origin http_code=[307]
iter=24 ts_utc=2026-10-19T20:10:34.1487034Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":254,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=24 ts_utc=2026-10-19T20:10:34.2368253Z step=http-3001-origin http_code=[307]
iter=25 ts_utc=2026-10-19T20:10:49.5217509Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":244,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=25 ts_utc=2026-10-19T20:10:49.5809764Z step=http-3001-origin http_code=[307]
iter=26 ts_utc=2026-10-19T20:11:04.8879931Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":273,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=26 ts_utc=2026-10-19T20:11:05.0004594Z step=http-3001-origin http_code=[307]
iter=27 ts_utc=2026-10-19T20:11:20.2764056Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":246,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=27 ts_utc=2026-10-19T20:11:20.3462188Z step=http-3001-origin http_code=[307]
iter=28 ts_utc=2026-10-19T20:11:35.7278564Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":326,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=28 ts_utc=2026-10-19T20:11:35.8117206Z step=http-3001-origin http_code=[307]
iter=29 ts_utc=2026-10-19T20:11:51.2068137Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":349,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=29 ts_utc=2026-10-19T20:11:51.2892082Z step=http-3001-origin http_code=[307]
iter=30 ts_utc=2026-10-19T20:12:06.6418633Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":315,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=30 ts_utc=2026-10-19T20:12:06.7172748Z step=http-3001-origin http_code=[307]
iter=31 ts_utc=2026-10-19T20:12:22.0725075Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":312,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=31 ts_utc=2026-10-19T20:12:22.1672734Z step=http-3001-origin http_code=[307]
iter=32 ts_utc=2026-10-19T20:12:37.4934545Z step=exec-buildid status={"error":"","args":"exec bookly-frontend sh -c \"tu -f; echo === ; node -v\"","elapsed_ms":297,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"} stdout=[===
v20.20.2]
iter=32 ts_utc=2026-10-19T20:12:37.5774103Z step=http-3001-origin http_code=[307]
ENTRYPOINT_NOT_READY_WITHIN_480S ts_utc=2026-10-19T20:12:39.5809208Z iterations=32

#### logs-tail-on-timeout
ts_utc =2026-10-19T20:12:39.8145053Z
cmd    = docker logs bookly-frontend --tail 120 --timestamps
status = {"error":"","args":"logs bookly-frontend --tail 120 --timestamps","elapsed_ms":200,"exe":"C:\\Program Files\\Docker\\Docker\\resources\\bin\\docker.exe","exit":0,"watchdog":"completed"}
stdout >>>
2026-10-19T19:27:32.473853083Z Γö£ ╞Æ /[locale]/partner/analytics
2026-10-19T19:27:32.473855584Z Γö£ ╞Æ /[locale]/partner/bookings
2026-10-19T19:27:32.473858085Z Γö£ ╞Æ /[locale]/partner/bookings/[reference]
2026-10-19T19:27:32.473860685Z Γö£ ╞Æ /[locale]/partner/onboarding
2026-10-19T19:27:32.473863186Z Γö£ ╞Æ /[locale]/partner/profile
2026-10-19T19:27:32.473866087Z Γö£ ╞Æ /[locale]/partner/reviews
2026-10-19T19:27:32.473868788Z Γö£ ╞Æ /[locale]/partner/tours
2026-10-19T19:27:32.473871289Z Γö£ ╞Æ /[locale]/partner/tours/[id]/availability
2026-10-19T19:27:32.473873790Z Γö£ ╞Æ /[locale]/partner/tours/[id]/edit
2026-10-19T19:27:32.473876391Z Γö£ ╞Æ /[locale]/partner/tours/[id]/pricing
2026-10-19T19:27:32.473878992Z Γö£ ╞Æ /[locale]/partner/tours/create
2026-10-19T19:27:32.473881393Z Γö£ ╞Æ /[locale]/privacy
2026-10-19T19:27:32.473883994Z Γö£ ╞Æ /[locale]/profile
2026-10-19T19:27:32.473886594Z Γö£ ╞Æ /[locale]/search
2026-10-19T19:27:32.473889095Z Γö£ ╞Æ /[locale]/terms
2026-10-19T19:27:32.473891696Z Γö£ ╞Æ /[locale]/tours/[slug]
2026-10-19T19:27:32.473894297Z Γö£ ╞Æ /[locale]/wishlist
2026-10-19T19:27:32.473896798Z Γö£ ╞Æ /api/auth/session
2026-10-19T19:27:32.473899399Z Γö£ Γùï /robots.txt
2026-10-19T19:27:32.473902000Z Γöö ╞Æ /v/[reference]
2026-10-19T19:27:32.473904501Z 
2026-10-19T19:27:32.473907002Z 
2026-10-19T19:27:32.473909603Z ╞Æ Proxy (Middleware)
2026-10-19T19:27:32.474230414Z 
2026-10-19T19:27:32.474435886Z Γùï  (Static)   prerendered as static content
2026-10-19T19:27:32.474444989Z ╞Æ  (Dynamic)  server-rendered on demand
2026-10-19T19:27:32.474447690Z 
2026-10-19T19:27:34.118026247Z 
2026-10-19T19:27:34.118094963Z > frontend@0.1.0 start
2026-10-19T19:27:34.118102164Z > next start
2026-10-19T19:27:34.118105365Z 
2026-10-19T19:27:35.546000926Z Γû▓ Next.js 16.2.3
2026-10-19T19:27:35.546588558Z - Local:         http://localhost:3000
2026-10-19T19:27:35.546603261Z - Network:       http://172.21.0.8:3000
2026-10-19T19:27:35.557330968Z Γ£ô Ready in 873ms
2026-10-19T20:02:31.446052608Z 
2026-10-19T20:02:31.446905714Z > frontend@0.1.0 build
2026-10-19T20:02:31.446919216Z > next build
2026-10-19T20:02:31.447034730Z 
2026-10-19T20:02:38.804891055Z Attention: Next.js now collects completely anonymous telemetry regarding usage.
2026-10-19T20:02:38.810589038Z This information is used to shape Next.js' roadmap and prioritize features.
2026-10-19T20:02:38.810657345Z You can learn more, including how to opt-out if you'd not like to participate in this anonymous program, by visiting the following URL:
2026-10-19T20:02:38.810668846Z https://nextjs.org/telemetry
2026-10-19T20:02:38.810675047Z 
2026-10-19T20:02:39.079574976Z Γû▓ Next.js 16.2.3 (Turbopack)
2026-10-19T20:02:39.079626181Z - Environments: .env.local
2026-10-19T20:02:39.079635182Z 
2026-10-19T20:02:39.853358091Z   Creating an optimized production build ...
2026-10-19T20:03:32.404023806Z Γ£ô Compiled successfully in 51s
2026-10-19T20:03:32.502637404Z   Running TypeScript ...
2026-10-19T20:04:26.060054554Z   Finished TypeScript in 53s ...
2026-10-19T20:04:26.148175610Z   Collecting page data using 7 workers ...
2026-10-19T20:04:28.808115277Z   Generating static pages using 7 workers (0/89) ...
2026-10-19T20:04:29.537797137Z   Generating static pages using 7 workers (22/89) 
2026-10-19T20:04:29.837112077Z   Generating static pages using 7 workers (44/89) 
2026-10-19T20:04:30.076040643Z   Generating static pages using 7 workers (66/89) 
2026-10-19T20:04:33.225251430Z Γ£ô Generating static pages using 7 workers (89/89) in 4.4s
2026-10-19T20:04:33.256260202Z   Finalizing page optimization ...
2026-10-19T20:04:33.380284979Z 
2026-10-19T20:04:33.400920989Z Route (app)
2026-10-19T20:04:33.400989859Z Γöî Γùï /_not-found
2026-10-19T20:04:33.400997167Z Γö£ ╞Æ /[locale]
2026-10-19T20:04:33.401001872Z Γö£ ╞Æ /[locale]/[...notFound]
2026-10-19T20:04:33.401006877Z Γö£ ╞Æ /[locale]/auth/forgot-password
2026-10-19T20:04:33.401011682Z Γö£ ╞Æ /[locale]/auth/login
2026-10-19T20:04:33.401016186Z Γö£ ╞Æ /[locale]/auth/partner-register
2026-10-19T20:04:33.401020591Z Γö£ ╞Æ /[locale]/auth/register
2026-10-19T20:04:33.401024995Z Γö£ ╞Æ /[locale]/auth/reset-password
2026-10-19T20:04:33.401029400Z Γö£ ╞Æ /[locale]/auth/verify-email
2026-10-19T20:04:33.401070141Z Γö£ ╞Æ /[locale]/blog
2026-10-19T20:04:33.401079250Z Γö£ ╞Æ /[locale]/blog/[slug]
2026-10-19T20:04:33.401083955Z Γö£ ╞Æ /[locale]/blog/[slug]/preview
2026-10-19T20:04:33.401088560Z Γö£ ╞Æ /[locale]/blog/category/[slug]
2026-10-19T20:04:33.401093064Z Γö£ ╞Æ /[locale]/booking
2026-10-19T20:04:33.401097569Z Γö£ ╞Æ /[locale]/booking/confirmation
2026-10-19T20:04:33.401102074Z Γö£ ╞Æ /[locale]/categories
2026-10-19T20:04:33.401106578Z Γö£ ╞Æ /[locale]/categories/[slug]
2026-10-19T20:04:33.401111083Z Γö£ ╞Æ /[locale]/destinations
2026-10-19T20:04:33.401115587Z Γö£ ╞Æ /[locale]/destinations/[slug]
2026-10-19T20:04:33.401120192Z Γö£ ╞Æ /[locale]/my-bookings
2026-10-19T20:04:33.401125498Z Γö£ ╞Æ /[locale]/my-bookings/[reference]
2026-10-19T20:04:33.401135708Z Γö£ ╞Æ /[locale]/my-reviews
2026-10-19T20:04:33.401141914Z Γö£ ╞Æ /[locale]/partner
2026-10-19T20:04:33.401147019Z Γö£ ╞Æ /[locale]/partner-invite/[token]
2026-10-19T20:04:33.401152125Z Γö£ ╞Æ /[locale]/partner-register
2026-10-19T20:04:33.401157130Z Γö£ ╞Æ /[locale]/partner/analytics
2026-10-19T20:04:33.401161835Z Γö£ ╞Æ /[locale]/partner/bookings
2026-10-19T20:04:33.401166039Z Γö£ ╞Æ /[locale]/partner/bookings/[reference]
2026-10-19T20:04:33.401170744Z Γö£ ╞Æ /[locale]/partner/onboarding
2026-10-19T20:04:33.401175348Z Γö£ ╞Æ /[locale]/partner/profile
2026-10-19T20:04:33.401180253Z Γö£ ╞Æ /[locale]/partner/reviews
2026-10-19T20:04:33.401184858Z Γö£ ╞Æ /[locale]/partner/tours
2026-10-19T20:04:33.401189262Z Γö£ ╞Æ /[locale]/partner/tours/[id]/availability
2026-10-19T20:04:33.401194067Z Γö£ ╞Æ /[locale]/partner/tours/[id]/edit
2026-10-19T20:04:33.401202276Z Γö£ ╞Æ /[locale]/partner/tours/[id]/pricing
2026-10-19T20:04:33.401209583Z Γö£ ╞Æ /[locale]/partner/tours/create
2026-10-19T20:04:33.401215089Z Γö£ ╞Æ /[locale]/privacy
2026-10-19T20:04:33.401220094Z Γö£ ╞Æ /[locale]/profile
2026-10-19T20:04:33.401224799Z Γö£ ╞Æ /[locale]/search
2026-10-19T20:04:33.401229303Z Γö£ ╞Æ /[locale]/terms
2026-10-19T20:04:33.401234208Z Γö£ ╞Æ /[locale]/tours/[slug]
2026-10-19T20:04:33.401239013Z Γö£ ╞Æ /[locale]/wishlist
2026-10-19T20:04:33.401243618Z Γö£ ╞Æ /api/auth/session
2026-10-19T20:04:33.401248222Z Γö£ Γùï /robots.txt
2026-10-19T20:04:33.401252827Z Γöö ╞Æ /v/[reference]
2026-10-19T20:04:33.401257532Z 
2026-10-19T20:04:33.401262237Z 
2026-10-19T20:04:33.401267242Z ╞Æ Proxy (Middleware)
2026-10-19T20:04:33.401272047Z 
2026-10-19T20:04:33.401411188Z Γùï  (Static)   prerendered as static content
2026-10-19T20:04:33.401433711Z ╞Æ  (Dynamic)  server-rendered on demand
2026-10-19T20:04:33.401440718Z 
2026-10-19T20:04:39.016574492Z 
2026-10-19T20:04:39.016659796Z > frontend@0.1.0 start
2026-10-19T20:04:39.016665824Z > next start
2026-10-19T20:04:39.016670728Z 
2026-10-19T20:04:39.610952631Z Γû▓ Next.js 16.2.3
2026-10-19T20:04:39.611428172Z - Local:         http://localhost:3000
2026-10-19T20:04:39.611447874Z - Network:       http://172.21.0.6:3000
2026-10-19T20:04:39.612293047Z Γ£ô Ready in 309ms
stderr >>>
<no-output-file>

ts_done_utc=2026-10-19T20:12:39.8167895Z
