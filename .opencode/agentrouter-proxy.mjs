import http from "node:http";
import https from "node:https";
import fs from "node:fs";
import zlib from "node:zlib";

const PORT = 8765;
const UPSTREAM = "agentrouter.org";
const LOG = "C:\\Users\\HaTeM\\AppData\\Local\\Temp\\ar-test\\proxy.log";
const log = (s) => fs.appendFileSync(LOG, s + "\n");

const server = http.createServer((req, res) => {
  const chunks = [];
  req.on("data", (c) => chunks.push(c));
  req.on("end", () => {
    const body = Buffer.concat(chunks);
    const upHeaders = { ...req.headers };
    delete upHeaders.host;
    delete upHeaders["content-length"];
    const up = https.request({
      host: UPSTREAM, path: req.url, method: req.method,
      headers: { ...upHeaders, "content-length": body.length },
    }, (upRes) => {
      res.writeHead(upRes.statusCode, upRes.headers);
      const rchunks = [];
      upRes.on("data", (c) => { rchunks.push(c); res.write(c); });
      upRes.on("end", () => {
        res.end();
        const raw = Buffer.concat(rchunks);
        let text;
        try {
          if (upRes.headers["content-encoding"] === "gzip") text = zlib.gunzipSync(raw).toString("utf8");
          else if (upRes.headers["content-encoding"] === "br") text = zlib.brotliDecompressSync(raw).toString("utf8");
          else text = raw.toString("utf8");
        } catch (e) { text = "[decompress fail]"; }
        log("UP status=" + upRes.statusCode + " enc=" + upRes.headers["content-encoding"] + " len=" + raw.length + " isHTML=" + text.startsWith("<") + " hasWORKING=" + text.includes("WORKING-OK"));
      });
    });
    up.on("error", (e) => { log("UP ERR " + e.message); res.writeHead(502); res.end("proxy error"); });
    up.end(body);
  });
});
server.listen(PORT, "127.0.0.1", () => log("proxy ready on " + PORT));
