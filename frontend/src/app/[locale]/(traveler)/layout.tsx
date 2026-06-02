import { AuthGuard } from '@/components/auth/AuthGuard';

export default function TravelerLayout({ children }: { children: React.ReactNode }) {
  return <AuthGuard>{children}</AuthGuard>;
}
