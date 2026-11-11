// Shared by the header and content that sticks immediately below it.
export function useSiteHeaderHeight() {
  return useState<number>('site-header-height', () => 0)
}
