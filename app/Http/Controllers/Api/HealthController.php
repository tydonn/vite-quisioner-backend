<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function db(Request $request)
    {
        $connection = $request->get('connection', 'quisioner');
        $host = config("database.connections.$connection.host");
        $port = (int) (config("database.connections.$connection.port") ?? 3306);
        $timestamp = now()->toIso8601String();

        $portOk = false;
        $portError = null;
        $portLatencyMs = null;
        try {
            $t0 = microtime(true);
            $socket = @fsockopen($host, $port, $errno, $errstr, 3);
            $portLatencyMs = (int) round((microtime(true) - $t0) * 1000);
            if ($socket) {
                $portOk = true;
                fclose($socket);
            } else {
                $portError = $errstr ?: "errno:$errno";
            }
        } catch (\Throwable $e) {
            $portError = $e->getMessage();
        }

        $dbOk = false;
        $dbError = null;
        $dbLatencyMs = null;
        $serverVersion = null;
        try {
            $t0 = microtime(true);
            DB::connection($connection)->getPdo();
            $dbLatencyMs = (int) round((microtime(true) - $t0) * 1000);
            $serverVersion = DB::connection($connection)->selectOne('select version() as v')->v ?? null;
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        $privateIp = null;
        try {
            $privateIp = gethostbyname(gethostname());
        } catch (\Throwable $e) {
            $privateIp = null;
        }

        $publicIp = null;
        try {
            $publicIp = Http::timeout(3)->get('https://api.ipify.org')->body() ?: null;
        } catch (\Throwable $e) {
            $publicIp = null;
        }

        return response()->json([
            'success' => true,
            'connection' => $connection,
            'host' => $host,
            'port' => $port,
            'port_ok' => $portOk,
            'db_ok' => $dbOk,
            'port_error' => $portOk ? null : $portError,
            'db_error' => $dbOk ? null : $dbError,
            'port_latency_ms' => $portLatencyMs,
            'db_latency_ms' => $dbLatencyMs,
            'server_version' => $serverVersion,
            'server_private_ip' => $privateIp,
            'server_public_ip' => $publicIp,
            'timestamp' => $timestamp,
        ]);
    }

    public function dbSiakad(Request $request)
    {
        $request->merge(['connection' => 'siakad']);
        return $this->db($request);
    }
}
