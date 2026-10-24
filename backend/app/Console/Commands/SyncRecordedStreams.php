<?php

namespace App\Console\Commands;

use App\Services\LiveStream\RecordedStreamScheduler;
use Illuminate\Console\Command;

class SyncRecordedStreams extends Command
{
    protected $signature = 'streams:sync-recorded';

    protected $description = 'Start due radio/video channels and end the ones whose duration has elapsed';

    public function handle(RecordedStreamScheduler $scheduler): int
    {
        $result = $scheduler->sync();
        $this->info("started {$result['started']}, ended {$result['ended']}");

        return self::SUCCESS;
    }
}
