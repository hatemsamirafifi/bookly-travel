import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';
import ProfileSettings from '@/components/profile/ProfileSettings';
import TravelerPageShell from '@/components/traveler/TravelerPageShell';

interface Props {
  params: Promise<{ locale: string }>;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'traveler.pages.profile' });
  return {
    title: t('metaTitle'),
    description: t('metaDescription'),
  };
}

export default async function ProfilePage({ params }: Props) {
  const { locale } = await params;
  const t = await getTranslations({ locale, namespace: 'traveler.pages.profile' });

  return (
    <TravelerPageShell title={t('title')} subtitle={t('subtitle')}>
      <ProfileSettings />
    </TravelerPageShell>
  );
}
