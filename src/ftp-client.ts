import { Client, FileInfo, FileType } from "basic-ftp";
import * as fs from "fs";
import * as path from "path";
import * as os from "os";

export interface FtpConfig {
  host: string;
  port: number;
  user: string;
  password: string;
  secure: boolean;
}

export interface ListingEntry {
  name: string;
  type: "file" | "directory" | "other";
  size: number;
  modifiedDate: string;
}

function mapFileType(t: FileType): ListingEntry["type"] {
  if (t === FileType.File) return "file";
  if (t === FileType.Directory) return "directory";
  return "other";
}

export class FtpClient {
  private readonly client = new Client();
  private readonly config: FtpConfig;
  private readonly tempDir: string;

  constructor(config: FtpConfig) {
    this.config = config;
    this.tempDir = path.join(os.tmpdir(), "mcp-ftp-temp");
    if (!fs.existsSync(this.tempDir)) {
      fs.mkdirSync(this.tempDir, { recursive: true });
    }
    this.client.ftp.verbose = false;
  }

  async connect(): Promise<void> {
    try {
      await this.client.access({
        host: this.config.host,
        port: this.config.port,
        user: this.config.user,
        password: this.config.password,
        secure: this.config.secure,
      });
    } catch (error) {
      throw new Error(
        `FTP-Verbindung fehlgeschlagen: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }

  disconnect(): void {
    this.client.close();
  }

  async listDirectory(remotePath: string): Promise<ListingEntry[]> {
    try {
      await this.connect();
      const list: FileInfo[] = await this.client.list(remotePath);
      this.disconnect();
      return list.map((item) => ({
        name: item.name,
        type: mapFileType(item.type),
        size: item.size,
        modifiedDate: item.modifiedAt ? item.modifiedAt.toISOString() : "",
      }));
    } catch (error) {
      this.disconnect();
      throw new Error(
        `Verzeichnisliste fehlgeschlagen: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }

  async downloadFile(remotePath: string): Promise<{ content: string }> {
    try {
      await this.connect();
      const tempFilePath = path.join(
        this.tempDir,
        `download-${Date.now()}-${path.basename(remotePath)}`
      );
      await this.client.downloadTo(tempFilePath, remotePath);
      const buf = fs.readFileSync(tempFilePath);
      fs.unlinkSync(tempFilePath);
      this.disconnect();
      const utf8 = buf.toString("utf8");
      const isBinary = /[\x00-\x08\x0e-\x1f]/.test(utf8.slice(0, Math.min(utf8.length, 8192)));
      const content = isBinary ? buf.toString("base64") : utf8;
      return { content: isBinary ? `[base64]\n${content}` : content };
    } catch (error) {
      this.disconnect();
      throw new Error(
        `Download fehlgeschlagen: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }

  async uploadFile(remotePath: string, content: string): Promise<void> {
    try {
      await this.connect();
      const tempFilePath = path.join(
        this.tempDir,
        `upload-${Date.now()}-${path.basename(remotePath)}`
      );
      fs.writeFileSync(tempFilePath, content, "utf8");
      await this.client.uploadFrom(tempFilePath, remotePath);
      fs.unlinkSync(tempFilePath);
      this.disconnect();
    } catch (error) {
      this.disconnect();
      throw new Error(
        `Upload fehlgeschlagen: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }

  async createDirectory(remotePath: string): Promise<void> {
    try {
      await this.connect();
      await this.client.ensureDir(remotePath);
      this.disconnect();
    } catch (error) {
      this.disconnect();
      throw new Error(
        `Verzeichnis anlegen fehlgeschlagen: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }

  async deleteFile(remotePath: string): Promise<void> {
    try {
      await this.connect();
      await this.client.remove(remotePath);
      this.disconnect();
    } catch (error) {
      this.disconnect();
      throw new Error(
        `Datei löschen fehlgeschlagen: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }

  async deleteDirectory(remotePath: string): Promise<void> {
    try {
      await this.connect();
      await this.client.removeDir(remotePath);
      this.disconnect();
    } catch (error) {
      this.disconnect();
      throw new Error(
        `Verzeichnis löschen fehlgeschlagen: ${error instanceof Error ? error.message : String(error)}`
      );
    }
  }
}
