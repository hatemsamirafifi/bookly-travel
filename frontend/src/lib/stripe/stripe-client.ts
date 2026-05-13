import { loadStripe } from '@stripe/stripe-js';

const stripePromises = new Map<string, ReturnType<typeof loadStripe>>();

export const getStripe = (publishableKey?: string) => {
  const key = publishableKey || process.env.NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY || '';
  if (!stripePromises.has(key)) {
    stripePromises.set(key, loadStripe(key));
  }
  return stripePromises.get(key)!;
};
