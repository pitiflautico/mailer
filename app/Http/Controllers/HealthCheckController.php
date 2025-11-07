<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class HealthCheckController extends Controller
{
    /**
     * Show the health check dashboard
     */
    public function index()
    {
        $services = $this->checkAllServices();
        return view('health-check', compact('services'));
    }

    /**
     * API endpoint for health check
     */
    public function api()
    {
        $services = $this->checkAllServices();

        $allHealthy = collect($services)->every(function ($service) {
            return $service['status'] === 'healthy';
        });

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Check all services
     */
    private function checkAllServices()
    {
        return [
            'application' => $this->checkApplication(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'mail' => $this->checkMailServices(),
        ];
    }

    /**
     * Check application
     */
    private function checkApplication()
    {
        return [
            'name' => 'Application',
            'status' => 'healthy',
            'message' => 'Laravel application is running',
            'details' => [
                'environment' => config('app.env'),
                'debug' => config('app.debug') ? 'enabled' : 'disabled',
                'timezone' => config('app.timezone'),
                'url' => config('app.url'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ];
    }

    /**
     * Check database connection
     */
    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();

            // Get stats
            $tables = DB::select('SHOW TABLES');
            $domains = DB::table('domains')->count();
            $mailboxes = DB::table('mailboxes')->count();
            $sendLogs = DB::table('send_logs')->count();

            return [
                'name' => 'Database',
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'details' => [
                    'driver' => config('database.default'),
                    'host' => config('database.connections.mysql.host'),
                    'database' => config('database.connections.mysql.database'),
                    'tables' => count($tables),
                    'domains' => $domains,
                    'mailboxes' => $mailboxes,
                    'emails_sent' => $sendLogs,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Database',
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check Redis connection
     */
    private function checkRedis()
    {
        try {
            Redis::ping();

            $info = Redis::info();

            return [
                'name' => 'Redis',
                'status' => 'healthy',
                'message' => 'Redis connection successful',
                'details' => [
                    'host' => config('database.redis.default.host'),
                    'port' => config('database.redis.default.port'),
                    'version' => $info['redis_version'] ?? 'unknown',
                    'uptime_days' => isset($info['uptime_in_seconds']) ? round($info['uptime_in_seconds'] / 86400, 1) : 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 'unknown',
                    'used_memory' => isset($info['used_memory_human']) ? $info['used_memory_human'] : 'unknown',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Redis',
                'status' => 'unhealthy',
                'message' => 'Redis connection failed',
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check cache
     */
    private function checkCache()
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_' . uniqid();

            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            $working = $retrieved === $testValue;

            return [
                'name' => 'Cache',
                'status' => $working ? 'healthy' : 'unhealthy',
                'message' => $working ? 'Cache read/write successful' : 'Cache read/write failed',
                'details' => [
                    'driver' => config('cache.default'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Cache',
                'status' => 'unhealthy',
                'message' => 'Cache check failed',
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check queue system
     */
    private function checkQueue()
    {
        try {
            // Check if queue connection works
            $connection = config('queue.default');

            // Get queue size (works with Redis)
            $size = 0;
            if ($connection === 'redis') {
                try {
                    $size = Redis::llen('queues:default');
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            // Check failed jobs
            $failedJobs = DB::table('failed_jobs')->count();

            return [
                'name' => 'Queue System',
                'status' => 'healthy',
                'message' => 'Queue system operational',
                'details' => [
                    'connection' => $connection,
                    'pending_jobs' => $size,
                    'failed_jobs' => $failedJobs,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Queue System',
                'status' => 'unhealthy',
                'message' => 'Queue system check failed',
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check storage
     */
    private function checkStorage()
    {
        try {
            $storagePath = storage_path();
            $logPath = storage_path('logs');
            $cachePath = storage_path('framework/cache');

            // Check if writable
            $writable = is_writable($storagePath);

            // Get disk space
            $diskFree = disk_free_space($storagePath);
            $diskTotal = disk_total_space($storagePath);
            $diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 2);

            return [
                'name' => 'Storage',
                'status' => $writable ? 'healthy' : 'unhealthy',
                'message' => $writable ? 'Storage is writable' : 'Storage is not writable',
                'details' => [
                    'storage_path' => $storagePath,
                    'writable' => $writable,
                    'disk_free' => $this->formatBytes($diskFree),
                    'disk_total' => $this->formatBytes($diskTotal),
                    'disk_used_percent' => $diskUsedPercent . '%',
                ],
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Storage',
                'status' => 'unhealthy',
                'message' => 'Storage check failed',
                'details' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check mail services (external check via shell)
     */
    private function checkMailServices()
    {
        $services = [];

        // Check Postfix
        $postfix = $this->checkSystemService('postfix');
        $services['postfix'] = $postfix;

        // Check Dovecot
        $dovecot = $this->checkSystemService('dovecot');
        $services['dovecot'] = $dovecot;

        // Check OpenDKIM
        $opendkim = $this->checkSystemService('opendkim');
        $services['opendkim'] = $opendkim;

        // Check OpenDMARC
        $opendmarc = $this->checkSystemService('opendmarc');
        $services['opendmarc'] = $opendmarc;

        $allHealthy = collect($services)->every(fn($s) => $s === 'active');

        return [
            'name' => 'Mail Services',
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'message' => $allHealthy ? 'All mail services running' : 'Some mail services not running',
            'details' => $services,
        ];
    }

    /**
     * Check if system service is running
     */
    private function checkSystemService($service)
    {
        try {
            $output = shell_exec("systemctl is-active $service 2>&1");
            return trim($output) === 'active' ? 'active' : 'inactive';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
