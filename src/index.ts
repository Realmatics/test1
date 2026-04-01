#!/usr/bin/env node
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import { FtpClient, FtpConfig } from "./ftp-client.js";

const ftpConfig: FtpConfig = {
  host: process.env.FTP_HOST || "localhost",
  port: parseInt(process.env.FTP_PORT || "21", 10),
  user: process.env.FTP_USER || "anonymous",
  password: process.env.FTP_PASSWORD || "",
  secure: process.env.FTP_SECURE?.toLowerCase() === "true",
};

const ftpClient = new FtpClient(ftpConfig);

const server = new McpServer({
  name: "mcp-server-ftp-webspace",
  version: "1.0.0",
});

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`;
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

server.tool(
  "list-directory",
  "Inhalt eines Verzeichnisses auf dem FTP-Server auflisten",
  { remotePath: z.string().describe("Pfad auf dem Server") },
  async ({ remotePath }) => {
    try {
      const listing = await ftpClient.listDirectory(remotePath);
      const formatted = listing
        .map((item) => {
          const tag = item.type === "directory" ? "[DIR]" : "[FILE]";
          const size =
            item.type === "file" ? ` (${formatSize(item.size)})` : "";
          return `${tag} ${item.name}${size} - ${item.modifiedDate}`;
        })
        .join("\n");
      const dirs = listing.filter((i) => i.type === "directory").length;
      const files = listing.filter((i) => i.type === "file").length;
      const summary = `Gesamt: ${listing.length} Einträge (${dirs} Verzeichnisse, ${files} Dateien)`;
      return {
        content: [
          {
            type: "text",
            text: `Verzeichnis: ${remotePath}\n\n${formatted}\n\n${summary}`,
          },
        ],
      };
    } catch (error) {
      return {
        isError: true,
        content: [
          {
            type: "text",
            text: error instanceof Error ? error.message : String(error),
          },
        ],
      };
    }
  }
);

server.tool(
  "download-file",
  "Datei vom FTP-Server herunterladen (Text oder Base64 bei Binärdateien)",
  { remotePath: z.string().describe("Dateipfad auf dem Server") },
  async ({ remotePath }) => {
    try {
      const { content } = await ftpClient.downloadFile(remotePath);
      return {
        content: [
          {
            type: "text",
            text: `${remotePath}:\n\n${content}`,
          },
        ],
      };
    } catch (error) {
      return {
        isError: true,
        content: [
          {
            type: "text",
            text: error instanceof Error ? error.message : String(error),
          },
        ],
      };
    }
  }
);

server.tool(
  "upload-file",
  "Datei auf den FTP-Server hochladen (Textinhalt)",
  {
    remotePath: z.string().describe("Zielpfad auf dem Server"),
    content: z.string().describe("Dateiinhalt (UTF-8)"),
  },
  async ({ remotePath, content }) => {
    try {
      await ftpClient.uploadFile(remotePath, content);
      return {
        content: [
          {
            type: "text",
            text: `Hochgeladen: ${remotePath}`,
          },
        ],
      };
    } catch (error) {
      return {
        isError: true,
        content: [
          {
            type: "text",
            text: error instanceof Error ? error.message : String(error),
          },
        ],
      };
    }
  }
);

server.tool(
  "create-directory",
  "Verzeichnis auf dem FTP-Server anlegen",
  { remotePath: z.string().describe("Pfad des neuen Verzeichnisses") },
  async ({ remotePath }) => {
    try {
      await ftpClient.createDirectory(remotePath);
      return {
        content: [
          {
            type: "text",
            text: `Verzeichnis angelegt: ${remotePath}`,
          },
        ],
      };
    } catch (error) {
      return {
        isError: true,
        content: [
          {
            type: "text",
            text: error instanceof Error ? error.message : String(error),
          },
        ],
      };
    }
  }
);

server.tool(
  "delete-file",
  "Datei auf dem FTP-Server löschen",
  { remotePath: z.string().describe("Pfad der Datei") },
  async ({ remotePath }) => {
    try {
      await ftpClient.deleteFile(remotePath);
      return {
        content: [
          {
            type: "text",
            text: `Datei gelöscht: ${remotePath}`,
          },
        ],
      };
    } catch (error) {
      return {
        isError: true,
        content: [
          {
            type: "text",
            text: error instanceof Error ? error.message : String(error),
          },
        ],
      };
    }
  }
);

server.tool(
  "delete-directory",
  "Verzeichnis auf dem FTP-Server löschen",
  { remotePath: z.string().describe("Pfad des Verzeichnisses") },
  async ({ remotePath }) => {
    try {
      await ftpClient.deleteDirectory(remotePath);
      return {
        content: [
          {
            type: "text",
            text: `Verzeichnis gelöscht: ${remotePath}`,
          },
        ],
      };
    } catch (error) {
      return {
        isError: true,
        content: [
          {
            type: "text",
            text: error instanceof Error ? error.message : String(error),
          },
        ],
      };
    }
  }
);

async function main(): Promise<void> {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("mcp-server-ftp-webspace (stdio)");
}

main().catch((error) => {
  console.error("Fatal:", error);
  process.exit(1);
});
