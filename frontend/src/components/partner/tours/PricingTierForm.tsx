'use client';

import { useTourWizardStore } from '@/lib/stores/tourWizard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2 } from 'lucide-react';

interface PricingTierFormProps {
  disabled?: boolean;
}

export function PricingTierForm({ disabled = false }: PricingTierFormProps) {
  const { formData, updatePricingTier, addPricingTier, removePricingTier } = useTourWizardStore();
  const { pricing_tiers } = formData;

  const hasNoTiers = pricing_tiers.length === 0;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold text-[#0A2540]">Pricing Tiers</h3>
          <p className="text-sm text-gray-500">
            Add pricing options for your tour (e.g., Adult, Child, Senior, Group).
          </p>
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
          Add Tier
        </Button>
      </div>

      {hasNoTiers && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
          At least one pricing tier is required. Click &ldquo;Add Tier&rdquo; to create one.
        </div>
      )}

      <div className="space-y-3">
        {pricing_tiers.map((tier, index) => (
          <div
            key={tier.id}
            className="bg-white rounded-lg border border-gray-200 p-4 space-y-3"
          >
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-[#0A2540]">Tier {index + 1}</span>
              <button
                type="button"
                onClick={() => removePricingTier(tier.id)}
                disabled={disabled || pricing_tiers.length <= 1}
                className="p-1 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                aria-label={`Remove pricing tier ${index + 1}`}
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-1">
                <Label htmlFor={`tier-name-${tier.id}`}>Name</Label>
                <Input
                  id={`tier-name-${tier.id}`}
                  value={tier.name}
                  onChange={(e) => updatePricingTier(tier.id, { name: e.target.value })}
                  placeholder="e.g., Adult, Child, Group"
                  disabled={disabled}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor={`tier-price-${tier.id}`}>Price per person</Label>
                <div className="relative">
                  <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
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
                <Label htmlFor={`tier-min-${tier.id}`}>Min participants</Label>
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
                <Label htmlFor={`tier-max-${tier.id}`}>Max participants</Label>
                <Input
                  id={`tier-max-${tier.id}`}
                  type="number"
                  min="1"
                  value={tier.max_participants}
                  onChange={(e) =>
                    updatePricingTier(tier.id, { max_participants: Math.max(1, parseInt(e.target.value) || 1) })
                  }
                  placeholder="Leave empty for unlimited"
                  disabled={disabled}
                />
              </div>
            </div>

            {/* Validation: price must be a positive number */}
            {tier.price !== '' && tier.price !== '0' && parseFloat(tier.price) <= 0 && (
              <p className="text-xs text-red-500">Price must be a positive number.</p>
            )}
            {/* Validation: max >= min */}
            {tier.max_participants > 0 && tier.min_participants > tier.max_participants && (
              <p className="text-xs text-red-500">Max participants must be greater than or equal to min.</p>
            )}
          </div>
        ))}
      </div>

      {pricing_tiers.length > 0 && (
        <p className="text-xs text-gray-400">
          {pricing_tiers.length} pricing tier{pricing_tiers.length !== 1 ? 's' : ''} defined.
        </p>
      )}
    </div>
  );
}