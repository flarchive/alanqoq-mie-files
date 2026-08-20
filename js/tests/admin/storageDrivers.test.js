import { describe, expect, it } from 'vitest';
import { STORAGE_DRIVERS, storageDriver } from '../../src/admin/storageDrivers';

describe('storage driver definitions', () => {
  it('makes DogeCloud and Aliyun OSS selectable with their required fields', () => {
    const dogeCloud = storageDriver('dogecloud');
    const aliyunOss = storageDriver('aliyun_oss');

    expect(STORAGE_DRIVERS.map((driver) => driver.id)).toEqual(['dogecloud', 'aliyun_oss']);
    expect(dogeCloud.fields.map((field) => field.name)).toContain('pathPrefix');
    expect(dogeCloud.fields.map((field) => field.name)).not.toContain('region');
    expect(aliyunOss.fields.map((field) => field.name)).toEqual([
      'accessKey', 'secretKey', 'bucket', 'endpoint', 'region', 'pathPrefix', 'publicBaseUrl',
    ]);
  });
});
