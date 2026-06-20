import type { Metadata } from 'next';
import { getTranslations } from 'next-intl/server';

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations('partner');
  return {
    title: t('tours.edit.metaTitle', { defaultValue: 'Edit Tour | Bookly Partner' }),
    description: t('tours.edit.metaDescription', { defaultValue: 'Edit an existing tour listing.' }),
  };
}

export default async function PartnerTourEditPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold text-[#0A2540]">Edit Tour</h1>
      <div className="rounded-xl border border-gray-200 bg-white p-8 text-center">
        <p className="text-gray-500">Tour editor will appear here.</p>
        <p className="mt-2 text-xs text-gray-400">Tour ID: {id}</p>
      </div>
    </div>
  );
}
