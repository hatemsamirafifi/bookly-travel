<?php

namespace App\Domains\Payment\Services;

use App\Domains\Booking\Models\Booking;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeInvoiceService
{
    private ?StripeClient $client = null;

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(config('services.stripe.secret') ?? '');
    }

    /**
     * Retrieve or create a Stripe customer record for the booking traveler.
     */
    public function getOrCreateCustomer(string $email, string $name, array $metadata = []): string
    {
        $existing = $this->client()->customers->all([
            'email' => $email,
            'limit' => 1,
        ]);

        if (! empty($existing->data)) {
            return $existing->data[0]->id;
        }

        $customer = $this->client()->customers->create([
            'email' => $email,
            'name' => $name,
            'metadata' => array_merge([
                'origin' => 'bookly-travel',
            ], $metadata),
        ]);

        return $customer->id;
    }

    /**
     * Create, populate, and finalize a Stripe hosted invoice for a booking.
     */
    public function createInvoiceForBooking(Booking $booking, array $options = []): array
    {
        $booking->loadMissing(['tour', 'traveler', 'guestIdentity']);

        $email = $booking->traveler?->email ?? $booking->guestIdentity?->email;
        $name = $booking->traveler?->name ?? $booking->guestIdentity?->name ?? 'Valued Traveler';

        if (empty($email)) {
            throw new \InvalidArgumentException('Booking must have an associated traveler or guest identity email for invoicing.');
        }

        $customerId = $this->getOrCreateCustomer($email, $name, [
            'booking_reference' => $booking->reference,
        ]);

        $tourTitle = $booking->tour?->slug ?? 'Tour Booking';

        // Add line item to invoice
        $this->client()->invoiceItems->create([
            'customer' => $customerId,
            'amount' => $booking->total_price,
            'currency' => strtolower($booking->currency),
            'description' => "Booking {$booking->reference} — {$tourTitle} ({$booking->participant_count} participants)",
            'metadata' => [
                'booking_reference' => $booking->reference,
                'tour_id' => (string) $booking->tour_id,
            ],
        ]);

        // Create the invoice
        $invoice = $this->client()->invoices->create([
            'customer' => $customerId,
            'collection_method' => 'send_invoice',
            'days_until_due' => $options['days_until_due'] ?? 7,
            'auto_advance' => true,
            'metadata' => [
                'booking_reference' => $booking->reference,
                'booking_id' => (string) $booking->id,
                'origin' => 'bookly-travel',
            ],
        ]);

        // Finalize invoice to get hosted invoice URL and PDF
        $finalized = $this->client()->invoices->finalizeInvoice($invoice->id);

        Log::info('Created and finalized Stripe invoice for booking', [
            'booking_reference' => $booking->reference,
            'invoice_id' => $finalized->id,
            'hosted_invoice_url' => $finalized->hosted_invoice_url,
        ]);

        return [
            'invoice_id' => $finalized->id,
            'hosted_invoice_url' => $finalized->hosted_invoice_url,
            'invoice_pdf' => $finalized->invoice_pdf,
            'status' => $finalized->status,
            'amount_due' => $finalized->amount_due,
            'currency' => $finalized->currency,
        ];
    }

    /**
     * Void an open or uncollectible invoice.
     */
    public function voidInvoice(string $invoiceId): void
    {
        $this->client()->invoices->voidInvoice($invoiceId);
    }
}
