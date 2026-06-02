import { getPlaiceholder } from 'plaiceholder';

export async function getBlurDataUrl(src: string): Promise<string | undefined> {
  try {
    const buffer = await fetch(src).then(async (res) =>
      Buffer.from(await res.arrayBuffer())
    );
    const { base64 } = await getPlaiceholder(buffer);
    return base64;
  } catch {
    return undefined;
  }
}
