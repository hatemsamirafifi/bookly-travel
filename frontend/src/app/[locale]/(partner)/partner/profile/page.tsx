import { ProfileForm } from '@/components/partner/profile/ProfileForm';
import { PayoutForm } from '@/components/partner/profile/PayoutForm';
import { NotificationSettings } from '@/components/partner/profile/NotificationSettings';

export default function ProfilePage() {
  return (
    <div className="max-w-3xl space-y-6">
      <h1 className="text-2xl font-bold text-[#0A2540]">Profile Settings</h1>
      <ProfileForm />
      <PayoutForm />
      <NotificationSettings />
    </div>
  );
}