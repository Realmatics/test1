import { spawnSync } from "node:child_process";
import fs from "node:fs";
import net from "node:net";
import os from "node:os";
import path from "node:path";
import { randomUUID } from "node:crypto";

function readMultiline(socket, timeoutMs = 20000) {
  return new Promise((resolve, reject) => {
    let buf = "";
    const timer = setTimeout(() => {
      cleanup();
      reject(new Error(`FTP control timeout after ${timeoutMs}ms`));
    }, timeoutMs);

    const onData = (chunk) => {
      buf += chunk.toString("utf8");
      if (!buf.endsWith("\r\n")) return;
      const lines = buf.split("\r\n").filter(Boolean);
      if (!lines.length) return;
      const first = lines[0];
      const code = first.slice(0, 3);
      if (first[3] === "-") {
        if (lines.some((l) => l.startsWith(`${code} `) || l === code)) {
          cleanup();
          resolve(buf);
        }
      } else {
        cleanup();
        resolve(buf);
      }
    };
    const onErr = (err) => {
      cleanup();
      reject(err);
    };
    const cleanup = () => {
      clearTimeout(timer);
      socket.off("data", onData);
      socket.off("error", onErr);
    };
    socket.on("data", onData);
    socket.on("error", onErr);
  });
}

function connectTcp(host, port, timeoutMs = 20000) {
  return new Promise((resolve, reject) => {
    const socket = net.connect({ host, port }, () => resolve(socket));
    socket.setTimeout(timeoutMs);
    socket.once("error", reject);
    socket.once("timeout", () => reject(new Error(`TCP timeout to ${host}:${port}`)));
  });
}

export class FtpControl {
  constructor(creds) {
    this.creds = creds;
    this.socket = null;
  }

  async connect() {
    this.socket = await connectTcp(this.creds.host, this.creds.port);
    await readMultiline(this.socket);
    this.socket.write(`USER ${this.creds.user}\r\n`);
    await readMultiline(this.socket);
    this.socket.write(`PASS ${this.creds.password}\r\n`);
    const login = await readMultiline(this.socket);
    if (!login.startsWith("230")) {
      throw new Error(`FTP login failed: ${login.trim()}`);
    }
    return login.trim();
  }

  async send(cmd, timeoutMs = 20000) {
    this.socket.write(`${cmd}\r\n`);
    return readMultiline(this.socket, timeoutMs);
  }

  async close() {
    if (!this.socket) return;
    try {
      this.socket.write("QUIT\r\n");
      await readMultiline(this.socket, 5000);
    } catch {
      // ignore
    }
    try {
      this.socket.destroy();
    } catch {
      // ignore
    }
    this.socket = null;
  }
}

function parseStatListing(statText) {
  const entries = [];
  for (const line of statText.split(/\r?\n/)) {
    const trimmed = line.trim();
    // Unix LIST-style lines inside STAT
    if (!/^[dl\-bcsp][rwxSsTt\-]{9}/.test(trimmed)) continue;
    const parts = trimmed.split(/\s+/);
    if (parts.length < 9) continue;
    const name = parts.slice(8).join(" ");
    if (!name || name === "." || name === "..") continue;
    const isDir = trimmed.startsWith("d");
    const size = Number(parts[4]) || 0;
    const modifiedDate = `${parts[5]} ${parts[6]} ${parts[7]}`;
    entries.push({
      name,
      type: isDir ? "directory" : "file",
      size,
      modifiedDate,
    });
  }
  return entries;
}

function writeNetrc(creds) {
  const file = path.join(os.tmpdir(), `ftp-netrc-${randomUUID()}`);
  fs.writeFileSync(
    file,
    `machine ${creds.host} login ${creds.user} password ${creds.password}\n`,
    { mode: 0o600 },
  );
  return file;
}

function runCurl(args, timeoutMs = 120000) {
  return spawnSync("curl", args, {
    encoding: "utf8",
    timeout: timeoutMs,
    maxBuffer: 64 * 1024 * 1024,
    stdio: ["ignore", "pipe", "pipe"],
  });
}

function remoteUrl(creds, remotePath) {
  const clean = remotePath.startsWith("/") ? remotePath : `/${remotePath}`;
  return `ftp://${creds.host}${clean}`;
}

export async function checkAccess(creds) {
  const ftp = new FtpControl(creds);
  const login = await ftp.connect();
  const pwd = await ftp.send("PWD");
  const feat = await ftp.send("FEAT");
  await ftp.close();
  return {
    ok: true,
    login,
    pwd: pwd.trim(),
    pretSupported: /PRET/i.test(feat),
    host: creds.host,
    port: creds.port,
    user: creds.user,
    credentialSource: creds.source,
  };
}

export async function listDirectory(creds, remotePath = "/") {
  const ftp = new FtpControl(creds);
  await ftp.connect();
  const target = !remotePath || remotePath === "." ? "/" : remotePath;
  const stat = await ftp.send(`STAT ${target}`);
  if (!stat.startsWith("213") && !stat.startsWith("211")) {
    // fallback: try curl NLST
    await ftp.close();
    return listViaCurl(creds, target);
  }
  await ftp.close();
  return parseStatListing(stat);
}

async function listViaCurl(creds, remotePath) {
  const netrc = writeNetrc(creds);
  try {
    const url = remoteUrl(creds, remotePath.endsWith("/") ? remotePath : `${remotePath}/`);
    const result = runCurl([
      "-sS",
      "--ftp-pret",
      "--disable-epsv",
      "--connect-timeout",
      "15",
      "--max-time",
      "45",
      "--netrc-file",
      netrc,
      url,
      "-l",
    ]);
    if (result.status !== 0) {
      throw new Error(result.stderr || `curl list failed (${result.status})`);
    }
    return (result.stdout || "")
      .split(/\r?\n/)
      .map((n) => n.trim())
      .filter((n) => n && n !== "." && n !== "..")
      .map((name) => ({ name, type: "other", size: 0, modifiedDate: "" }));
  } finally {
    fs.rmSync(netrc, { force: true });
  }
}

async function remoteSize(creds, remotePath) {
  const ftp = new FtpControl(creds);
  await ftp.connect();
  try {
    const resp = await ftp.send(`SIZE ${remotePath}`);
    if (!resp.startsWith("213")) return null;
    const n = Number(resp.trim().split(/\s+/)[1]);
    return Number.isFinite(n) ? n : null;
  } finally {
    await ftp.close();
  }
}

export async function downloadFile(creds, remotePath, { retries = 3 } = {}) {
  const netrc = writeNetrc(creds);
  const out = path.join(os.tmpdir(), `ftp-dl-${randomUUID()}`);
  let lastErr = null;
  try {
    const expected = await remoteSize(creds, remotePath);
    for (let attempt = 1; attempt <= retries; attempt++) {
      fs.rmSync(out, { force: true });
      const result = runCurl([
        "-sS",
        "--ftp-pret",
        "--ignore-content-length",
        "--connect-timeout",
        "15",
        "--max-time",
        "35",
        "--netrc-file",
        netrc,
        remoteUrl(creds, remotePath),
        "-o",
        out,
      ]);
      if (result.status === 0 && fs.existsSync(out)) {
        const buf = fs.readFileSync(out);
        if (expected != null && expected > 0 && buf.length !== expected) {
          lastErr = new Error(
            `download size mismatch (got ${buf.length}, expected ${expected}), attempt ${attempt}/${retries}`,
          );
          continue;
        }
        const asText = buf.toString("utf8");
        const isUtf8 = Buffer.from(asText, "utf8").equals(buf);
        return isUtf8
          ? { content: asText, encoding: "utf8", bytes: buf.length }
          : { content: buf.toString("base64"), encoding: "base64", bytes: buf.length };
      }
      lastErr = new Error(
        (result.stderr || `curl download failed (${result.status})`).trim() +
          ` (attempt ${attempt}/${retries})`,
      );
    }
    throw lastErr;
  } finally {
    fs.rmSync(netrc, { force: true });
    fs.rmSync(out, { force: true });
  }
}

function parsePasv(pasvText) {
  const m = /\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/.exec(pasvText);
  if (!m) throw new Error(`Bad PASV response: ${pasvText.trim()}`);
  return {
    host: `${m[1]}.${m[2]}.${m[3]}.${m[4]}`,
    port: Number(m[5]) * 256 + Number(m[6]),
  };
}

async function uploadOnceRaw(creds, remotePath, buf) {
  const ftp = new FtpControl(creds);
  await ftp.connect();
  let data = null;
  try {
    // Working Pure-FTPd order from this network:
    // PRET STOR -> PASV -> open data socket -> TYPE I -> STOR -> send bytes
    await ftp.send(`PRET STOR ${remotePath}`);
    const pasv = await ftp.send("PASV");
    const target = parsePasv(pasv);
    data = await connectTcp(target.host, target.port, 15000);
    await ftp.send("TYPE I");
    const stor = await ftp.send(`STOR ${remotePath}`, 20000);
    if (!(stor.startsWith("150") || stor.startsWith("125"))) {
      throw new Error(stor.trim());
    }
    await new Promise((resolve, reject) => {
      data.end(buf, (err) => (err ? reject(err) : resolve()));
    });
    await new Promise((resolve, reject) => {
      const t = setTimeout(() => reject(new Error("data socket close timeout")), 20000);
      data.once("close", () => {
        clearTimeout(t);
        resolve();
      });
      data.once("error", reject);
    });
    // 226 / transfer-complete reply arrives after the data connection closes.
    const final = await readMultiline(ftp.socket, 30000);
    if (!(final.startsWith("226") || final.startsWith("250"))) {
      throw new Error(final.trim());
    }
  } finally {
    try {
      data?.destroy();
    } catch {
      // ignore
    }
    try {
      await ftp.close();
    } catch {
      // ignore
    }
  }
  const size = await remoteSize(creds, remotePath);
  if (size !== buf.length) {
    throw new Error(`upload size mismatch got=${size} expected=${buf.length}`);
  }
  return true;
}

export async function uploadFile(creds, remotePath, content, encoding = "utf8", { retries = 6 } = {}) {
  const buf = Buffer.from(content, encoding);
  let lastErr = null;
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      await uploadOnceRaw(creds, remotePath, buf);
      return true;
    } catch (error) {
      lastErr = new Error(
        `${error instanceof Error ? error.message : String(error)} (attempt ${attempt}/${retries})`,
      );
      await new Promise((r) => setTimeout(r, 400));
    }
  }
  throw lastErr;
}

export async function deleteFile(creds, remotePath) {
  const ftp = new FtpControl(creds);
  await ftp.connect();
  const resp = await ftp.send(`DELE ${remotePath}`);
  await ftp.close();
  if (!resp.startsWith("250")) throw new Error(resp.trim());
  return true;
}

export async function createDirectory(creds, remotePath) {
  const ftp = new FtpControl(creds);
  await ftp.connect();
  const resp = await ftp.send(`MKD ${remotePath}`);
  await ftp.close();
  if (!(resp.startsWith("257") || resp.startsWith("250"))) throw new Error(resp.trim());
  return true;
}

export async function deleteDirectory(creds, remotePath) {
  const ftp = new FtpControl(creds);
  await ftp.connect();
  const resp = await ftp.send(`RMD ${remotePath}`);
  await ftp.close();
  if (!resp.startsWith("250")) throw new Error(resp.trim());
  return true;
}

export async function renamePath(creds, fromPath, toPath) {
  const ftp = new FtpControl(creds);
  await ftp.connect();
  const r1 = await ftp.send(`RNFR ${fromPath}`);
  if (!r1.startsWith("350")) throw new Error(r1.trim());
  const r2 = await ftp.send(`RNTO ${toPath}`);
  await ftp.close();
  if (!r2.startsWith("250")) throw new Error(r2.trim());
  return true;
}
