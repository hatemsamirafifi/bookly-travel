'use client';

import { useTranslations } from 'next-intl';
import { useTourWizardStore } from '@/lib/stores/tourWizard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2 } from 'lucide-react';

interface PricingTierFormProps {
  disabled?: boolean;
}

export function PricingTierForm({ disabled = false }: PricingTierFormProps) {
  const t = useTranslations('partner.tours.pricingForm');
  const { formData, updatePricingTier, addPricingTier, removePricingTier } = useTourWizardStore();
  const { pricing_tiers } = formData;

  const hasNoTiers = pricing_tiers.length === 0;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h3 className="text-lg font-semibold text-[#0A2540]">{t('heading')}</h3>
          <p className="text-sm text-gray-600">{t('subtitle')}</p>
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={addPricingTier}
          disabled={disabled || pricing_tiers.length >= 10}
          className="gap-1"
        >
          <Plus className="w-4 h-4" />
          {t('addTier')}
        </Button>
      </div>

      {hasNoTiers && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-900" role="alert">
          {t('emptyBanner')}
        </div>
      )}

      <div className="space-y-3">
        {pricing_tiers.map((tier, index) => (
          <div
            key={tier.id}
            className="bg-white rounded-lg border border-gray-200 p-4 space-y-3"
          >
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-[#0A2540]">
                {t('tierTitle', { number: index + 1 })}
              </span>
              <button
                type="button"
                onClick={() => removePricingTier(tier.id)}
                disabled={disabled || pricing_tiers.length <= 1}
                className="p-1 rounded text-gray-600 hover:text-red-700 hover:bg-red-50 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                aria-label={t('removeTier', { number: index + 1 })}
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label htmlFor={`tier-name-${tier.id}`}>{t('nameLabel')}</Label>
                <Input
                  id={`tier-name-${tier.id}`}
                  value={tier.name}
                  onChange={(e) => updatePricingTier(tier.id, { name: e.target.value })}
                  placeholder={t('namePlaceholder')}
                  disabled={disabled}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor={`tier-price-${tier.id}`}>{t('priceLabel')}</Label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-600 text-sm" aria-hidden="true">€</span>
                  <Input
                    id={`tier-price-${tier.id}`}
                    type="number"
                    min="0"
                    step="0.01"
                    value={tier.price}
                    onChange={(e) => updatePricingTier(tier.id, { price: e.target.value })}
                    placeholder="0.00"
                    disabled={disabled}
                    className="pl-7"
                  />
                </div>
              </div>
              <div className="space-y-1">
                <Label htmlFor={`tier-min-${tier.id}`}>{t('minLabel')}</Label>
                <Input
                  id={`tier-min-${tier.id}`}
                  type="number"
                  min="1"
                  value={tier.min_participants}
                  onChange={(e) =>
                    updatePricingTier(tier.id, { min_participants: Math.max(1, parseInt(e.target.value) || 1) })
                  }
                  disabled={disabled}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor={`tier-max-${tier.id}`}>{t('maxLabel')}</Label>
                <Input
                  id={`tier-max-${tier.id}`}
                  type="number"
                  min="1"
                  value={tier.max_participants}
                  onChange={(e) =>
                    updatePricingTier(tier.id, { max_participants: Math.max(1, parseInt(e.target.value) || 1) })
                  }
                  placeholder={t('maxPlaceholder')}
                  disabled={disabled}
                />
              </div>
            </div>

            {/* Validation: price must be a positive number */}
            {tier.price !== '' && tier.price !== '0' && parseFloat(tier.price) <= 0 && (
              <p className="text-xs text-red-700" role="alert">{t('pricePositiveError')}</p>
            )}
            {/* Validation: max >= min */}
            {tier.max_participants > 0 && tier.min_participants > tier.max_participants && (
              <p className="text-xs text-red-700" role="alert">{t('maxGteMinError')}</p>
            )}
          </div>
        ))}
      </div>

      {pricing_tiers.length > 0 && (
        <p className="text-xs text-gray-600">
          {t('summaryCount', { count: pricing_tiers.length })}
        </p>
      )}
    </div>
  );
}