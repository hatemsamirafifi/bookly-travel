<?php

namespace Tests\Unit\Booking;

use App\Domains\Booking\Actions\CreateBookingAction;
use App\Domains\Booking\Controllers\Public\BookingController;
use App\Domains\Booking\DTOs\CreateBookingDTO;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class BookingIdempotencyHeaderTest extends TestCase
{
    #[DataProvider('acceptedKeys')]
    public function test_uuid_shaped_key_is_forwarded_unchanged(string $key): void
    {
        $dispatched = new RuntimeException('Booking action reached.');
        $action = $this->createMock(CreateBookingAction::class);
        $action->expects($this->once())
            ->method('execute')
            ->with($this->callback(fn (CreateBookingDTO $booking): bool => $booking->idempotencyKey === $key))
            ->willThrowException($dispatched);

        $this->expectExceptionObject($dispatched);
        (new BookingController)->store($this->request($key), $action);
    }

    public static function acceptedKeys(): array
    {
        return [
            'version 4' => ['550e8400-e29b-41d4-a716-446655440000'],
            'uppercase version 1' => ['123E4567-E89B-12D3-A456-426614174000'],
        ];
    }

    #[DataProvider('rejectedKeys')]
    public function test_missing_or_malformed_key_is_rejected_before_dispatch(?string $key): void
    {
        $action = $this->createMock(CreateBookingAction::class);
        $action->expects($this->never())->method('execute');

        try {
            (new BookingController)->store($this->request($key), $action);
            $this->fail('Expected an invalid booking key to be rejected.');
        } catch (UnprocessableEntityHttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public static function rejectedKeys(): array
    {
        return ['missing' => [null], 'malformed' => ['not-a-uuid']];
    }

    private function request(?string $key): Request
    {
        $request = new class extends Request
        {
            public function validate(array $rules): array
            {
                return [
                    'tour_slug' => 'example-tour',
                    'tour_date' => '2026-12-01',
                    'participant_count' => 1,
                    'locale' => 'en',
                ];
            }
        };

        if ($key !== null) {
            $request->headers->set('Idempotency-Key', $key);
        }
        $request->setUserResolver(fn () => (object) ['id' => 1]);

        return $request;
    }
}
