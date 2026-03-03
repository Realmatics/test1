#!/usr/bin/env node
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import SftpClient from "ssh2-sftp-client";

const config = {
  host: process.env.SFTP_HOST || "localhost",
  port: parseInt(process.env.SFTP_PORT || "22"),
  username: process.env.SFTP_USER || "anonymous",
  password: process.env.SFTP_PASSWORD || "",
};

async function withSftp(fn) {
  const sftp = new SftpClient();
  try {
    await sftp.connect(config);
    return await fn(sftp);
  } finally {
    await sftp.end();
  }
}

const server = new McpServer({
  name: "mcp-server-sftp",
  version: "1.0.0",
});

server.tool(
  "list-directory",
  "List contents of a remote SFTP directory",
  { remotePath: z.string().describe("Path of the directory on the SFTP server") },
  async ({ remotePath }) => {
    try {
      const listing = await withSftp((sftp) => sftp.list(remotePath));
      const formatted = listing
        .map((item) => {
          const type = item.type === "d" ? "[DIR]" : "[FILE]";
          const size = item.type !== "d" ? `(${formatSize(item.size)})` : "";
          const mod = item.modifyTime ? new Date(item.modifyTime).toISOString() : "";
          return `${type} ${item.name} ${size} - ${mod}`;
        })
        .join("\n");
      const dirs = listing.filter((i) => i.type === "d").length;
      const files = listing.filter((i) => i.type !== "d").length;
      return {
        content: [
          {
            type: "text",
            text: `Directory listing for: ${remotePath}\n\n${formatted}\n\nTotal: ${listing.length} items (${dirs} directories, ${files} files)`,
          },
        ],
      };
    } catch (error) {
      return {
        isError: true,
        content: [{ type: "text", text: `Error listing directory: ${error.message}` }],
      };
    }
  }
);

server.tool(
  "download-file",
  "Download a file from the SFTP server (returns text content)",
  { remotePath: z.string().describe("Path of the file on the SFTP server") },
  async ({ remotePath }) => {
    try {
      const content = await withSftp((sftp) => sftp.get(remotePath));
      return {
        content: [{ type: "text", text: `File content of ${remotePath}:\n\n${content.toString()}` }],
      };
    } catch (error) {
      return {
        isError: true,
        content: [{ type: "text", text: `Error downloading file: ${error.message}` }],
      };
    }
  }
);

server.tool(
  "upload-file",
  "Upload a file to the SFTP server",
  {
    remotePath: z.string().describe("Destination path on the SFTP server"),
    content: z.string().describe("Content to upload to the file"),
  },
  async ({ remotePath, content }) => {
    try {
      await withSftp((sftp) => sftp.put(Buffer.from(content), remotePath));
      return {
        content: [{ type: "text", text: `File successfully uploaded to ${remotePath}` }],
      };
    } catch (error) {
      return {
        isError: true,
        content: [{ type: "text", text: `Error uploading file: ${error.message}` }],
      };
    }
  }
);

server.tool(
  "create-directory",
  "Create a new directory on the SFTP server",
  { remotePath: z.string().describe("Path of the directory to create") },
  async ({ remotePath }) => {
    try {
      await withSftp((sftp) => sftp.mkdir(remotePath, true));
      return {
        content: [{ type: "text", text: `Directory successfully created at ${remotePath}` }],
      };
    } catch (error) {
      return {
        isError: true,
        content: [{ type: "text", text: `Error creating directory: ${error.message}` }],
      };
    }
  }
);

server.tool(
  "delete-file",
  "Delete a file from the SFTP server",
  { remotePath: z.string().describe("Path of the file to delete") },
  async ({ remotePath }) => {
    try {
      await withSftp((sftp) => sftp.delete(remotePath));
      return {
        content: [{ type: "text", text: `File successfully deleted from ${remotePath}` }],
      };
    } catch (error) {
      return {
        isError: true,
        content: [{ type: "text", text: `Error deleting file: ${error.message}` }],
      };
    }
  }
);

server.tool(
  "delete-directory",
  "Delete a directory from the SFTP server",
  { remotePath: z.string().describe("Path of the directory to delete") },
  async ({ remotePath }) => {
    try {
      await withSftp((sftp) => sftp.rmdir(remotePath, true));
      return {
        content: [{ type: "text", text: `Directory successfully deleted from ${remotePath}` }],
      };
    } catch (error) {
      return {
        isError: true,
        content: [{ type: "text", text: `Error deleting directory: ${error.message}` }],
      };
    }
  }
);

function formatSize(bytes) {
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + " KB";
  if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + " MB";
  return (bytes / (1024 * 1024 * 1024)).toFixed(2) + " GB";
}

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("SFTP MCP Server running on stdio");
}

main().catch((error) => {
  console.error("Fatal error in main():", error);
  process.exit(1);
});
