import { describe, expect, it, vi } from 'vitest';
import { createFileApi } from '../../src/common/api';

describe('file api', () => {
  it('uses encoded ids and multipart upload', async () => {
    const request = vi.fn().mockResolvedValue({ data: [] });
    const api = createFileApi({ request });
    expect(api.proxyUrl('a/b')).toBe('/api/mie/files/a%2Fb/proxy');
    await api.upload(new File(['x'], 'x.txt'), 'document');
    expect(request).toHaveBeenCalledWith('POST', '/api/mie/files', expect.any(FormData));
  });
});
