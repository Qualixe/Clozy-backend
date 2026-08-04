<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:fix-legacy-media-urls')]
#[Description('Command description')]
class FixLegacyMediaUrls extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
