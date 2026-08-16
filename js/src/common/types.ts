export type FileStatus = 'pending' | 'success' | 'failed' | 'delete_failed';

export interface FileTypeRule {
  extension: string;
  mime: string;
}

export interface FileRecord {
  id: string;
  categoryId: string;
  categoryName?: string;
  originalName: string;
  extension: string;
  mimeType: string;
  size: number;
  status: FileStatus;
  downloads: number;
  createdAt?: string;
}

export interface FileCategory {
  id?: string;
  slug: string;
  name: string;
  permissionName: string;
  maxSizeMb: number;
  storageName: string;
  insertTemplate: 'file_download' | 'image_download' | 'image_inline' | 'url_only' | 'markdown_image' | 'bbcode_image';
  rules: FileTypeRule[];
  enabled: boolean;
}

export interface FileListResponse { data: FileRecord[]; }
export interface FileError { status: string; code?: string; detail?: string; }
