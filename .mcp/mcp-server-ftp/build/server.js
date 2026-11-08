#!/usr/bin/env node
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import { resolveFtpCredentials } from "./credentials.js";
import * as ftp from "./ftp.js";

const creds = resolveFtpCredentials();

const server = new McpServer({
  name: "ftp-webspace",
  version: "1.0.0",
});

let queue = Promise.resolve();
function serialized(handler) {
  return (...args) => {
    const run = () => handler(...args);
    const result = queue.then(run, run);
    queue = result.catch(() => {});
    return result;
  };
}

function errorResult(prefix, error) {
  return {
    isError: true,
    content: [{ type: "text", text: `${prefix}: ${error instanceof Error ? error.message : String(error)}` }],
  };
}

server.registerTool(
  "ftp-check",
  {
    title: "FTP Access Check",
    description: "Verify FTP login and report connection details (no secrets).",
    inputSchema: {},
    annotations: { readOnlyHint: true, openWorldHint: false },
  },
  serialized(async () => {
    try {
      const info = await ftp.checkAccess(creds);
      return {
        content: [
          {
            type: "text",
            text: `FTP OK\nhost=${info.host}\nport=${info.port}\nuser=${info.user}\npret=${info.pretSupported}\ncredentials=${info.credentialSource}\n${info.login}\n${info.pwd}`,
          },
        ],
        structuredContent: info,
      };
    } catch (error) {
      return errorResult("FTP check failed", error);
    }
  }),
);

server.registerTool(
  "list-directory",
  {
    title: "List Directory",
    description: "List FTP directory contents (uses STAT over the control channel).",
    inputSchema: {
      remotePath: z.string().default("/").describe("Remote directory path"),
    },
    annotations: { readOnlyHint: true, openWorldHint: false },
  },
  serialized(async ({ remotePath }) => {
    try {
      const listing = await ftp.listDirectory(creds, remotePath || "/");
      const formatted = listing
        .map((item) => `${item.type === "directory" ? "[DIR]" : "[FILE]"} ${item.name} (${item.size}) ${item.modifiedDate}`)
        .join("\n");
      return {
        content: [{ type: "text", text: `Directory listing for: ${remotePath || "/"}\n\n${formatted || "(empty)"}` }],
        structuredContent: { path: remotePath || "/", entries: listing },
      };
    } catch (error) {
      return errorResult("Error listing directory", error);
    }
  }),
);

server.registerTool(
  "download-file",
  {
    title: "Download File",
    description: "Download a remote FTP file (curl --ftp-pret, with retries).",
    inputSchema: {
      remotePath: z.string().describe("Remote file path"),
    },
    annotations: { readOnlyHint: true, openWorldHint: false },
  },
  serialized(async ({ remotePath }) => {
    try {
      const { content, encoding, bytes } = await ftp.downloadFile(creds, remotePath);
      const header =
        encoding === "base64"
          ? `File content of ${remotePath} (binary, base64-encoded, ${bytes} bytes):`
          : `File content of ${remotePath} (${bytes} bytes):`;
      return {
        content: [{ type: "text", text: `${header}\n\n${content}` }],
        structuredContent: { remotePath, content, encoding, bytes },
      };
    } catch (error) {
      return errorResult("Error downloading file", error);
    }
  }),
);

server.registerTool(
  "upload-file",
  {
    title: "Upload File",
    description: "Upload content to a remote FTP path (curl --ftp-pret, with retries).",
    inputSchema: {
      remotePath: z.string().describe("Destination path on the FTP server"),
      content: z.string().describe("File content"),
      encoding: z.enum(["utf8", "base64"]).optional().describe("Content encoding (default utf8)"),
    },
    annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
  },
  serialized(async ({ remotePath, content, encoding }) => {
    try {
      await ftp.uploadFile(creds, remotePath, content, encoding ?? "utf8");
      return { content: [{ type: "text", text: `Uploaded to ${remotePath}` }] };
    } catch (error) {
      return errorResult("Error uploading file", error);
    }
  }),
);

server.registerTool(
  "delete-file",
  {
    title: "Delete File",
    description: "Delete a remote FTP file",
    inputSchema: { remotePath: z.string().describe("Remote file path") },
    annotations: { readOnlyHint: false, destructiveHint: true, openWorldHint: false },
  },
  serialized(async ({ remotePath }) => {
    try {
      await ftp.deleteFile(creds, remotePath);
      return { content: [{ type: "text", text: `Deleted ${remotePath}` }] };
    } catch (error) {
      return errorResult("Error deleting file", error);
    }
  }),
);

server.registerTool(
  "create-directory",
  {
    title: "Create Directory",
    description: "Create a remote FTP directory",
    inputSchema: { remotePath: z.string().describe("Remote directory path") },
    annotations: { readOnlyHint: false, destructiveHint: true, openWorldHint: false },
  },
  serialized(async ({ remotePath }) => {
    try {
      await ftp.createDirectory(creds, remotePath);
      return { content: [{ type: "text", text: `Created directory ${remotePath}` }] };
    } catch (error) {
      return errorResult("Error creating directory", error);
    }
  }),
);

server.registerTool(
  "delete-directory",
  {
    title: "Delete Directory",
    description: "Remove an empty remote FTP directory",
    inputSchema: { remotePath: z.string().describe("Remote directory path") },
    annotations: { readOnlyHint: false, destructiveHint: true, openWorldHint: false },
  },
  serialized(async ({ remotePath }) => {
    try {
      await ftp.deleteDirectory(creds, remotePath);
      return { content: [{ type: "text", text: `Deleted directory ${remotePath}` }] };
    } catch (error) {
      return errorResult("Error deleting directory", error);
    }
  }),
);

server.registerTool(
  "rename",
  {
    title: "Rename / Move",
    description: "Rename or move a remote FTP path",
    inputSchema: {
      fromPath: z.string().describe("Source path"),
      toPath: z.string().describe("Destination path"),
    },
    annotations: { readOnlyHint: false, destructiveHint: true, openWorldHint: false },
  },
  serialized(async ({ fromPath, toPath }) => {
    try {
      await ftp.renamePath(creds, fromPath, toPath);
      return { content: [{ type: "text", text: `Renamed ${fromPath} → ${toPath}` }] };
    } catch (error) {
      return errorResult("Error renaming", error);
    }
  }),
);

const transport = new StdioServerTransport();
await server.connect(transport);
