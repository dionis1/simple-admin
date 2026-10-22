<?php

namespace App\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Health
{
    /** @return array{database: bool, cache: bool, worker: bool} */
    public function check(): array
    {
        $database = $cache = false;
        try {
            DB::select('SELECT 1');
            $database = true;
        } catch (\Throwable) {
            // Public readiness output never exposes exception text or connection details.
        }
        $key = 'health:'.Str::uuid();
        try {
            Cache::put($key, 'ok', 10);
            $cache = Cache::pull($key) === 'ok';
            $heartbeat = Cache::get('governance:worker-heartbeat');
        } catch (\Throwable) {
            $heartbeat = null;
        }

        return ['database' => $database, 'cache' => $cache, 'worker' => is_numeric($heartbeat) && (int) $heartbeat > time() - 90];
    }
}
