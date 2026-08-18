export const STORAGE_DRIVERS = [
  {
    id: 'dogecloud',
    label: 'mie-files.admin.storage_driver_dogecloud',
    icon: 'fas fa-cloud',
    fields: [
      { name: 'accessKey', type: 'text', requiredOnCreate: true },
      { name: 'secretKey', type: 'password', requiredOnCreate: true },
      { name: 'bucket', type: 'text', requiredOnCreate: true },
      { name: 'endpoint', type: 'url', requiredOnCreate: true },
      { name: 'pathPrefix', type: 'text' },
      { name: 'publicBaseUrl', type: 'url' },
    ],
  },
  {
    id: 'aliyun_oss',
    label: 'mie-files.admin.storage_driver_aliyun_oss',
    icon: 'fas fa-cloud',
    fields: [
      { name: 'accessKey', type: 'text', requiredOnCreate: true },
      { name: 'secretKey', type: 'password', requiredOnCreate: true },
      { name: 'bucket', type: 'text', requiredOnCreate: true },
      { name: 'endpoint', type: 'url', requiredOnCreate: true },
      { name: 'region', type: 'text', requiredOnCreate: true },
      { name: 'pathPrefix', type: 'text' },
      { name: 'publicBaseUrl', type: 'url' },
    ],
  },
];

export function storageDriver(id) {
  return STORAGE_DRIVERS.find((driver) => driver.id === id);
}
