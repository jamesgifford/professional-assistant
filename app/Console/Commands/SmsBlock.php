<?php

namespace App\Console\Commands;

use App\Models\SmsBlocklist;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sms:block {phone} {--reason=manual}')]
#[Description('Add a phone number to the SMS blocklist')]
class SmsBlock extends Command
{
    public function handle(): int
    {
        $phone = $this->argument('phone');
        $reason = $this->option('reason');

        $entry = SmsBlocklist::firstOrCreate(
            ['phone_number' => $phone],
            ['reason' => $reason],
        );

        if ($entry->wasRecentlyCreated) {
            $this->info("Blocked {$phone} (reason: {$reason})");
        } else {
            $this->warn("{$phone} is already blocked (reason: {$entry->reason})");
        }

        return self::SUCCESS;
    }
}
