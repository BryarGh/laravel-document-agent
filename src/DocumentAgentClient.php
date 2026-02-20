<?php

namespace DocumentAgent;

use DocumentAgent\Exceptions\AgentUnavailableException;
use DocumentAgent\Exceptions\DocumentAgentException;
use DocumentAgent\Exceptions\ScanFailedException;
use DocumentAgent\Exceptions\ScanTimeoutException;
use DocumentAgent\Exceptions\UploadUrlMissingException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DocumentAgentClient
{
    private HttpFactory $http;
    private string $host;
    /** @var int[] */
    private array $ports;
    private float $healthTimeout;
    private float $requestTimeout;
    private int $pollIntervalMs;
    private int $pollTimeoutSeconds;

    private ?int $detectedPort = null;

    public function __construct(HttpFactory $http, array $config)
    {
        $this->http = $http;
        $this->host = $config['host'] ?? '127.0.0.1';
        $this->ports = $config['ports'] ?? [3333, 3334, 3335];
        $this->healthTimeout = (float)($config['health_timeout'] ?? 1.5);
        $this->requestTimeout = (float)($config['request_timeout'] ?? 10.0);
        $poll = $config['poll'] ?? [];
        $this->pollIntervalMs = (int)($poll['interval_ms'] ?? 1000);
        $this->pollTimeoutSeconds = (int)($poll['timeout_seconds'] ?? 300);
    }

    /**
     * List discovered scanners.
     */
    public function scanners(): array
    {
        return $this->get('/scanners')['profiles'] ?? [];
    }

    /**
     * List saved profiles.
     */
    public function profiles(): array
    {
        return $this->get('/profiles')['profiles'] ?? [];
    }

    /**
     * Create or update a profile.
     */
    public function createProfile(array $payload): array
    {
        return $this->post('/profiles', $payload);
    }

    /**
     * Request agent status.
     */
    public function status(): array
    {
        return $this->get('/status');
    }

    /**
     * Start a scan job. Returns the job_id and initial status.
     */
    public function startScan(string $documentId, string $profileName, ?string $clientRequestId = null): array
    {
        $body = [
            'document_id' => $documentId,
            'profile_name' => $profileName,
        ];

        if ($clientRequestId) {
            $body['client_request_id'] = $clientRequestId;
        }

        $response = $this->post('/scan', $body, true);
        return [
            'job_id' => $response['job_id'] ?? null,
            'status' => $response['status'] ?? null,
        ];
    }

    /**
     * Get current status of a scan job.
     */
    public function scanStatus(string $jobId): array
    {
        return $this->get('/scan/' . $jobId, tolerate404: false);
    }

    /**
     * Poll until the scan completes or fails. Returns final job payload.
     *
     * @throws ScanFailedException|ScanTimeoutException|UploadUrlMissingException
     */
    public function waitForCompletion(string $jobId, ?int $timeoutSeconds = null): array
    {
        $timeout = $timeoutSeconds ?? $this->pollTimeoutSeconds;
        $deadline = microtime(true) + $timeout;

        while (true) {
            $status = $this->scanStatus($jobId);
            $state = Str::lower((string)($status['status'] ?? ''));

            if ($state === 'completed') {
                return $status;
            }

            if ($state === 'failed') {
                $message = (string)($status['error_message'] ?? 'scan_failed');
                if (Str::contains($message, 'upload_url_missing')) {
                    throw new UploadUrlMissingException($message);
                }
                if (Str::contains($message, 'timeout')) {
                    throw new ScanTimeoutException('scan_timeout');
                }
                throw new ScanFailedException($message);
            }

            if (microtime(true) >= $deadline) {
                throw new ScanTimeoutException('scan_timeout');
            }

            usleep($this->pollIntervalMs * 1000);
        }
    }

    // ---- Internal helpers -------------------------------------------------

    private function get(string $path, bool $tolerate404 = false): array
    {
        $req = $this->request()->get($path);

        if ($req->successful()) {
            return $req->json();
        }

        if ($tolerate404 && $req->status() === 404) {
            return [];
        }

        $this->throwForResponse($req->status(), $req->json());
        return [];
    }

    private function post(string $path, array $payload, bool $expect202 = false): array
    {
        $req = $this->request()->post($path, $payload);

        if ($expect202 && $req->status() === 202) {
            return $req->json();
        }

        if ($req->successful()) {
            return $req->json();
        }

        $this->throwForResponse($req->status(), $req->json());
        return [];
    }

    private function request(): PendingRequest
    {
        $port = $this->detectPort();

        return $this->http
            ->timeout($this->requestTimeout)
            ->baseUrl(sprintf('http://%s:%d', $this->host, $port))
            ->acceptJson();
    }

    private function detectPort(): int
    {
        if ($this->detectedPort) {
            return $this->detectedPort;
        }

        foreach ($this->ports as $port) {
            $res = $this->http
                ->timeout($this->healthTimeout)
                ->get(sprintf('http://%s:%d/health', $this->host, $port));

            if ($res->successful()) {
                $this->detectedPort = $port;
                return $port;
            }
        }

        throw new AgentUnavailableException('agent_unavailable');
    }

    private function throwForResponse(int $status, ?array $body = null): void
    {
        $error = Arr::get($body, 'error') ?? Arr::get($body, 'error_message') ?? 'agent_error';

        if ($error === 'upload_url_missing') {
            throw new UploadUrlMissingException($error);
        }
        if ($error === 'scan_timeout') {
            throw new ScanTimeoutException($error);
        }
        if ($error === 'scan_failed') {
            throw new ScanFailedException($error);
        }

        if ($status === 400) {
            throw new DocumentAgentException($error);
        }

        throw new DocumentAgentException($error ?: 'agent_error');
    }
}
