import enMessages from '../../messages/en.json';
import esMessages from '../../messages/es.json';
import itMessages from '../../messages/it.json';

describe('partner dashboard translation contract', () => {
  it.each([
    ['en', enMessages, 'Partner Dashboard'],
    ['es', esMessages, 'Panel de socio'],
    ['it', itMessages, 'Cruscotto partner'],
  ] as const)('provides the %s heading and every account-state label', (_locale, messages, title) => {
    expect(messages.partner.dashboard.title).toBe(title);
    for (const key of [
      'accountStatus', 'active', 'pendingReview', 'rejected',
      'pendingDescription', 'viewOnboarding', 'rejectionDescription',
      'resubmit', 'welcome', 'pendingFeatures', 'verified', 'verifiedDescription',
    ] as const) {
      expect(messages.partner.dashboard.status[key].trim().length).toBeGreaterThan(0);
    }
  });
});
