<?php

namespace App\Http\Controllers\Biometric;

use App\Services\Biometric\BiometricAdmsIngestService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AdmsIclockController
{
    public function __construct(
        protected BiometricAdmsIngestService $ingest,
    ) {}

    public function cdata(Request $request): Response
    {
        if (! config('biometric.enabled', true)) {
            return $this->plain('OK');
        }

        $serial = strtoupper(trim((string) $request->query('SN', $request->query('sn', ''))));

        if ($serial === '') {
            Log::warning('biometric.adms.missing_serial', ['path' => $request->path()]);

            return $this->plain('OK');
        }

        $device = $this->ingest->findAllowedDevice($serial);

        if (! $device) {
            Log::warning('biometric.adms.unknown_or_inactive_device', ['serial' => $serial]);

            return $this->plain('OK');
        }

        if ($request->isMethod('get')) {
            return $this->plain($this->ingest->handshakeOptions($device));
        }

        $table = strtoupper(trim((string) $request->query('table', '')));
        $stamp = (string) $request->query('Stamp', $request->query('stamp', ''));
        $body = (string) $request->getContent();

        if ($table === 'ATTLOG' || ($table === '' && trim($body) !== '')) {
            $stats = $this->ingest->ingestAttLog($device, $body, $stamp !== '' ? $stamp : null);

            Log::info('biometric.adms.attlog_received', [
                'serial' => $serial,
                'stats' => $stats,
            ]);

            return $this->plain('OK');
        }

        $device->touchSeen(null, $stamp !== '' ? $stamp : null);

        return $this->plain('OK');
    }

    public function getrequest(Request $request): Response
    {
        $serial = strtoupper(trim((string) $request->query('SN', $request->query('sn', ''))));

        if ($serial !== '' && ($device = $this->ingest->findAllowedDevice($serial))) {
            $device->touchSeen();
        }

        return $this->plain('OK');
    }

    public function devicecmd(Request $request): Response
    {
        return $this->plain('OK');
    }

    public function registry(Request $request): Response
    {
        return $this->cdata($request);
    }

    protected function plain(string $body): Response
    {
        return response($body, 200)
            ->header('Content-Type', 'text/plain');
    }
}
