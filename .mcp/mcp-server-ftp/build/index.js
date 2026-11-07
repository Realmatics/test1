#!/usr/bin/env node
import { spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const sdkEntry = join(root, "node_modules", "@modelcontextprotocol", "sdk", "package.json");

if (!existsSync(sdkEntry)) {
  const install = spawnSync("npm", ["install", "--omit=dev", "--no-fund", "--no-audit"], {
    cwd: root,
    encoding: "utf8",
    stdio: ["ignore", "pipe", "pipe"],
  });
  if (install.status !== 0) {
    console.error(install.stderr || install.stdout || "npm install failed");
    process.exit(install.status || 1);
  }
}

await import(pathToFileURL(join(root, "build", "server.js")).href);
