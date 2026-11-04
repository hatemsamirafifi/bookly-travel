import type { Metadata } from 'next';
import { Plus_Jakarta_Sans } from 'next/font/google';
import '../globals.css';

export const metadata: Metadata = {
  title: 'Bookly - Voucher Verification',
  description: 'Verify your Bookly tour booking voucher.',
  robots: {
    index: false,
    follow: false,
  },
};

const plusJakartaSans = Plus_Jakarta_Sans({
  variable: '--font-plus-jakarta-sans',
  subsets: ['latin'],
  display: 'swap',
});

export default function VoucherLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className={`${plusJakartaSans.variable} h-full antialiased`}>
      <body className="min-h-full flex flex-col font-sans bg-surface-alt">
        {children}
      </body>
    </html>
  );
}

