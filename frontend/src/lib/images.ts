export const DEFAULT_BLUR_DATA_URL =
  'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAzMiAyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZGVmcz48bGluZWFyR3JhZGllbnQgaWQ9ImciIHgxPSIwIiB4Mj0iMSIgeTE9IjAiIHkyPSIxIj48c3RvcCBzdG9wLWNvbG9yPSIjRjdGOUZCIi8+PHN0b3Agb2Zmc2V0PSIuNSIgc3RvcC1jb2xvcj0iI0UwRTdFRiIvPjxzdG9wIG9mZnNldD0iMSIgc3RvcC1jb2xvcj0iI0ZCRjNGMyIvPjwvbGluZWFyR3JhZGllbnQ+PC9kZWZzPjxyZWN0IHdpZHRoPSIzMiIgaGVpZ2h0PSIyMCIgZmlsbD0idXJsKCNnKSIvPjwvc3ZnPg==';

export function getImagePlaceholderProps(image?: unknown) {
  const source = image && typeof image === 'object'
    ? image as { blur_data_url?: string; cover_image_blur?: string }
    : undefined;
  return {
    placeholder: 'blur' as const,
    blurDataURL: source?.blur_data_url || source?.cover_image_blur || DEFAULT_BLUR_DATA_URL,
  };
}
