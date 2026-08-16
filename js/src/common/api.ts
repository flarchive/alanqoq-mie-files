import type { FileCategory, FileListResponse, FileRecord } from './types';

export interface ApiClient { request<T>(method: string, url: string, body?: BodyInit): Promise<T>; }
export function createFileApi(client: ApiClient) {
  return {
    list: () => client.request<FileListResponse>('GET', '/api/mie/files'),
    upload: (file: File, categoryId: string) => { const body = new FormData(); body.append('file', file); body.append('categoryId', categoryId); return client.request<{ data: FileRecord }>('POST', '/api/mie/files', body); },
    remove: (id: string) => client.request<void>('DELETE', `/api/mie/files/${encodeURIComponent(id)}`),
    categories: () => client.request<{ data: FileCategory[] }>('GET', '/api/mie/categories'),
    template: (id: string) => client.request<{ data: { markup: string; url: string } }>('POST', `/api/mie/files/${encodeURIComponent(id)}/template`),
    proxyUrl: (id: string) => `/api/mie/files/${encodeURIComponent(id)}/proxy`,
  };
}
