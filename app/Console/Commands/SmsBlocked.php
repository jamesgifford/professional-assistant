<?php

namespace App\Console\Commands;

use App\Models\SmsBlocklist;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sms:blocked')]
#[Description('List all blocked SMS phone numbers')]
class SmsBlocked extends Command
{
    public function handle(): int
    {
        $entries = SmsBlocklist::orderBy('created_at', 'desc')->get();

        if ($entries->isEmpty()) {
            $this->info('No blocked phone numbers.');

            return self::SUCCESS;
        }

        $this->table(
            ['Phone Number', 'Reason', 'Blocked At'],
            $entries->map(fn (SmsBlocklist $entry) => [
                $entry->phone_number,
                $entry->reason,
                $entry->created_at->format('Y-m-d H:i:s'),
            ]),
        );

        return self::SUCCESS;
    }
}
