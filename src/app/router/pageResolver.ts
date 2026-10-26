import type { DefineComponent } from 'vue';

const pageModules = import.meta.glob<{ default: DefineComponent }>('../../features/**/pages/*.vue');

export async function resolvePage(name: string): Promise<DefineComponent> {
  const [feature, page] = name.split('/');
  if (!feature || !page || name.split('/').length !== 2) {
    throw new Error(`Nama halaman Inertia "${name}" harus berbentuk Feature/Page.`);
  }

  const path = `../../features/${feature}/pages/${page}.vue`;
  const loadPage = pageModules[path];

  if (!loadPage) {
    throw new Error(`Halaman Inertia "${name}" belum terdaftar di src/features.`);
  }

  return (await loadPage()).default;
}
