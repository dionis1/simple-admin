<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CheckRedis extends Command
{
    protected $signature = 'app:check-redis';

    protected $description = 'Verify a Redis cache round trip without clearing existing data';

    public function handle(): int
    {
        $key = 'health:'.Str::uuid();
        $cache = Cache::store('redis');

        try {
            $cache->put($key, 'ok', 10);

            if ($cache->get($key) !== 'ok') {
                $this->error('Redis did not return the expected value.');

                return self::FAILURE;
            }

            $this->info('Redis cache round trip passed.');

            return self::SUCCESS;
        } finally {
            $cache->forget($key);
        }
    }
}
