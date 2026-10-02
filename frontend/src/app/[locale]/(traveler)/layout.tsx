import { AuthGuard } from '@/components/auth/AuthGuard';
import Header from '@/components/layout/Header';
import Footer from '@/components/layout/Footer';

export default async function TravelerLayout({ children, params }: { children: React.ReactNode; params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  return (
    <AuthGuard>
      <div className="flex min-h-screen flex-col">
        <Header locale={locale} />
        <div id="main-content" tabIndex={-1} className="flex-1">{children}</div>
        <Footer locale={locale} />
      </div>
    </AuthGuard>
  );
}
