'use client';

import type { TourImage } from '@/lib/api/types';
import TourGallery from './TourGallery';

interface ImageGalleryProps {
  images: TourImage[];
  title: string;
  contentLocale?: string;
}

/** Legacy alias for surfaces not yet on the canonical gallery (partner
 *  content preview). Delegates with identical props; the duplicate
 *  modal/state engine is retired, TourGallery owns all behavior. */
export default function ImageGallery(props: ImageGalleryProps) {
  return <TourGallery {...props} />;
}
