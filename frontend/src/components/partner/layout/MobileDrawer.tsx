'use client';

import { useTranslations } from 'next-intl';
import { PartnerSidebar } from './PartnerSidebar';
import { Drawer } from '@/components/ui/Drawer';

export function MobileDrawer({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  const nav = useTranslations('nav');
  return <Drawer open={isOpen} onClose={onClose} title={nav('mainNavigation')} closeLabel={nav('closeMenu')} side="left" className="bg-primary text-text-inverse"><PartnerSidebar onNavigate={onClose} /></Drawer>;
}
