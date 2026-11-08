#!/usr/bin/env node
/**
 * Direct FTP CLI (no MCP). Uses Cloud Agent FTP_* secrets.
 *
 * Usage:
 *   node scripts/ftp.mjs check
 *   node scripts/ftp.mjs list [/remote/path]
 *   node scripts/ftp.mjs download <remote> [local]
 *   node scripts/ftp.mjs upload <local> <remote>
 *   node scripts/ftp.mjs delete <remote>
 *   node scripts/ftp.mjs mkdir <remote>
 *   node scripts/ftp.mjs rmdir <remote>
 *   node scripts/ftp.mjs rename <from> <to>
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const credPath = path.join(root, ".mcp/mcp-server-ftp/build/credentials.js");
const ftpPath = path.join(root, ".mcp/mcp-server-ftp/build/ftp.js");

const { resolveFtpCredentials } = await import(pathToFileURL(credPath).href);
const ftp = await import(pathToFileURL(ftpPath).href);

function usage(code = 1) {
  console.error(`Usage:
  node scripts/ftp.mjs check
  node scripts/ftp.mjs list [/remote/path]
  node scripts/ftp.mjs download <remote> [local]
  node scripts/ftp.mjs upload <local> <remote>
  node scripts/ftp.mjs delete <remote>
  node scripts/ftp.mjs mkdir <remote>
  node scripts/ftp.mjs rmdir <remote>
  node scripts/ftp.mjs rename <from> <to>`);
  process.exit(code);
}

const [cmd, ...args] = process.argv.slice(2);
if (!cmd) usage(1);

const creds = resolveFtpCredentials();

try {
  switch (cmd) {
    case "check": {
      const info = await ftp.checkAccess(creds);
      console.log(JSON.stringify(info, null, 2));
      break;
    }
    case "list": {
      const remotePath = args[0] || "/";
      const listing = await ftp.listDirectory(creds, remotePath);
      for (const item of listing) {
        const tag = item.type === "directory" ? "DIR " : "FILE";
        console.log(`${tag}\t${item.size}\t${item.modifiedDate}\t${item.name}`);
      }
      break;
    }
    case "download": {
      const remote = args[0];
      if (!remote) usage(1);
      const local = args[1] || path.basename(remote);
      const { content, encoding, bytes } = await ftp.downloadFile(creds, remote);
      fs.writeFileSync(local, Buffer.from(content, encoding));
      console.log(`Downloaded ${remote} → ${local} (${bytes} bytes)`);
      break;
    }
    case "upload": {
      const local = args[0];
      const remote = args[1];
      if (!local || !remote) usage(1);
      const buf = fs.readFileSync(local);
      await ftp.uploadFile(creds, remote, buf.toString("base64"), "base64");
      console.log(`Uploaded ${local} → ${remote} (${buf.length} bytes)`);
      break;
    }
    case "delete": {
      const remote = args[0];
      if (!remote) usage(1);
      await ftp.deleteFile(creds, remote);
      console.log(`Deleted ${remote}`);
      break;
    }
    case "mkdir": {
      const remote = args[0];
      if (!remote) usage(1);
      await ftp.createDirectory(creds, remote);
      console.log(`Created ${remote}`);
      break;
    }
    case "rmdir": {
      const remote = args[0];
      if (!remote) usage(1);
      await ftp.deleteDirectory(creds, remote);
      console.log(`Removed ${remote}`);
      break;
    }
    case "rename": {
      const from = args[0];
      const to = args[1];
      if (!from || !to) usage(1);
      await ftp.renamePath(creds, from, to);
      console.log(`Renamed ${from} → ${to}`);
      break;
    }
    default:
      usage(1);
  }
} catch (err) {
  console.error(err instanceof Error ? err.message : String(err));
  process.exit(2);
}
