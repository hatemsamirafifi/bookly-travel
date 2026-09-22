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

        $parameters = [
            'email' => $email,
            'name' => $name,
            'metadata' => array_merge([
                'origin' => 'bookly-travel',
            ], $metadata),
        ];
        $customer = $this->client()->customers->create($parameters, [
            'idempotency_key' => $this->idempotencyKey(
                'customer',
                json_encode($parameters, JSON_THROW_ON_ERROR),
            ),
        ]);

        return $customer->id;
    }

    /**
     * Create, populate, and finalize a Stripe hosted invoice for a booking.
     */
    public function createInvoiceForBooking(Booking $booking, array $options = []): array
    {
        $booking->loadMissing(['tour', 'traveler', 'guestIdentity']);

        if ($booking->stripe_invoice_id) {
            $invoice = $this->client()->invoices->retrieve($booking->stripe_invoice_id, []);

            if ($invoice->status === 'draft') {
                $invoice = $this->client()->invoices->finalizeInvoice(
                    $invoice->id,
                    [],
                    ['idempotency_key' => $this->bookingKey($booking, 'finalize')],
                );
            }

            $this->persistInvoice($booking, $invoice);

            return $this->invoiceResult($invoice);
        }

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
        $this->client()->invoiceItems->create(
            [
                'customer' => $customerId,
                'amount' => $booking->total_price,
                'currency' => strtolower($booking->currency),
                'description' => "Booking {$booking->reference} — {$tourTitle} ({$booking->participant_count} participants)",
                'metadata' => [
                    'booking_reference' => $booking->reference,
                    'tour_id' => (string) $booking->tour_id,
                ],
            ],
            ['idempotency_key' => $this->bookingKey($booking, 'invoice-item')],
        );

        // Create the invoice
        $invoice = $this->client()->invoices->create(
            [
                'customer' => $customerId,
                'collection_method' => 'send_invoice',
                'days_until_due' => $options['days_until_due'] ?? 7,
                'auto_advance' => true,
                'metadata' => [
                    'booking_reference' => $booking->reference,
                    'booking_id' => (string) $booking->id,
                    'origin' => 'bookly-travel',
                ],
            ],
            ['idempotency_key' => $this->bookingKey($booking, 'invoice')],
        );

        // Persist the remote identifier before finalization. If finalization
        // fails, a retry retrieves this draft instead of creating another one.
        $this->persistInvoice($booking, $invoice);

        // Finalize invoice to get hosted invoice URL and PDF
        $finalized = $this->client()->invoices->finalizeInvoice(
            $invoice->id,
            [],
            ['idempotency_key' => $this->bookingKey($booking, 'finalize')],
        );
        $this->persistInvoice($booking, $finalized);

        Log::info('Created and finalized Stripe invoice for booking', [
            'booking_reference' => $booking->reference,
            'invoice_id' => $finalized->id,
            'hosted_invoice_url' => $finalized->hosted_invoice_url,
        ]);

        return $this->invoiceResult($finalized);
    }

    /**
     * Void an open or uncollectible invoice.
     */
    public function voidInvoice(string $invoiceId): void
    {
        $this->client()->invoices->voidInvoice(
            $invoiceId,
            [],
            ['idempotency_key' => $this->idempotencyKey('void-invoice', $invoiceId)],
        );
    }

    private function bookingKey(Booking $booking, string $operation): string
    {
        $identity = $booking->idempotency_key ?: $booking->reference ?: (string) $booking->getKey();

        return $this->idempotencyKey($operation, $identity);
    }

    private function idempotencyKey(string $operation, string $identity): string
    {
        return "bookly-{$operation}-" . hash('sha256', $identity);
    }

    private function persistInvoice(Booking $booking, object $invoice): void
    {
        $booking->update([
            'stripe_invoice_id' => $invoice->id,
            'stripe_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'stripe_invoice_pdf' => $invoice['invoice_pdf'] ?? null,
            'stripe_invoice_status' => $invoice->status,
        ]);
    }

    /** @return array{invoice_id: string, hosted_invoice_url: ?string, invoice_pdf: ?string, status: string, amount_due: int, currency: string} */
    private function invoiceResult(object $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
            'status' => $invoice->status,
            'amount_due' => $invoice->amount_due,
            'currency' => $invoice->currency,
        ];
    }
}
