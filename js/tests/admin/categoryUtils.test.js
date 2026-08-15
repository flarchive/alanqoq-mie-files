import { describe, expect, it } from 'vitest';
import { clonePreset, validateCategories } from '../../src/admin/categoryUtils';

describe('category validation', () => {
  it('permits ogg in separate audio and video MIME rules', () => {
    const audio = clonePreset('audio');
    const video = clonePreset('video');
    expect(validateCategories([audio, video])).toEqual({});
  });

  it('marks duplicated ordinary extensions', () => {
    const one = clonePreset('images');
    const two = clonePreset('images');
    two.slug = 'images-two';
    two.permissionName = 'images-two';
    expect(Object.keys(validateCategories([one, two]))).not.toHaveLength(0);
  });
});
