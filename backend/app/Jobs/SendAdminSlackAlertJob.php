<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort admin Slack alert (FR-012).
 *
 * The webhook URL is read from config at run time and NEVER travels with the
 * job: the queue payload only carries the rendered message and a safe context
 * array, so the credential-bearing URL cannot leak into queued-job storage,
 * failed-job payloads, or log entries.
 *
 * A Slack outage must never fail the dispatching caller or the queue worker:
 * every failure path (non-2xx response or throwable) is swallowed after a
 * WARNING whose context has been scrubbed of the webhook URL. Because the job
 * can never throw, it never retries in practice; the tries/backoff values are
 * a defensive ceiling only.
 */
class SendAdminSlackAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 15;

    public function __construct(
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    public function handle(): void
    {
        $webhookUrl = config('services.slack.admin_webhook_url');

        if (! $webhookUrl) {
            return;
        }

        try {
            $response = Http::timeout(5)->post($webhookUrl, ['text' => $this->message]);

            if (! $response->successful()) {
                Log::warning('Slack admin alert failed', array_merge($this->context, [
                    'response_status' => $response->status(),
                    'slack_error' => 'HTTP ' . $response->status(),
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning('Slack admin alert failed', array_merge($this->context, [
                'exception_class' => $e::class,
                'slack_error' => self::redactWebhookUrls($e->getMessage(), (string) $webhookUrl),
            ]));
        }
    }

    /**
     * Strip Slack webhook URLs (the configured one and any hooks.slack.com
     * URL) from an arbitrary string so exception messages can be logged
     * without leaking the credential-bearing URL.
     */
    public static function redactWebhookUrls(string $message, ?string $webhookUrl = null): string
    {
        if ($webhookUrl !== null && $webhookUrl !== '') {
            $message = str_replace($webhookUrl, '[REDACTED_WEBHOOK_URL]', $message);
        }

        $redacted = preg_replace('/https?:\/\/hooks\.slack\.com\/\S+/i', '[REDACTED_WEBHOOK_URL]', $message);

        return $redacted ?? $message;
    }
}
