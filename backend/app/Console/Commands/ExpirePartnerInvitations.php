<?php

namespace App\Console\Commands;

use App\Domains\Partner\Models\PartnerInvitation;
use Illuminate\Console\Command;

class ExpirePartnerInvitations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'partner-invitations:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired pending partner invitations as expired';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = PartnerInvitation::query()
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} partner invitation(s).");

        return Command::SUCCESS;
    }
}
