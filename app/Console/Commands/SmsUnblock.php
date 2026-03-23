<?php

namespace App\Console\Commands;

use App\Models\SmsBlocklist;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sms:unblock {phone}')]
#[Description('Remove a phone number from the SMS blocklist')]
class SmsUnblock extends Command
{
    public function handle(): int
    {
        $phone = $this->argument('phone');

        $deleted = SmsBlocklist::where('phone_number', $phone)->delete();

        if ($deleted) {
            $this->info("Unblocked {$phone}");
        } else {
            $this->warn("{$phone} was not on the blocklist");
        }

        return self::SUCCESS;
    }
}
