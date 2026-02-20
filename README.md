# Laravel Document Agent SDK

Integration helpers for the local DocumentAgent worker (NAPS2-based scanning agent). Compatible with Laravel 10–12 and PHP 8.4+.

Companion agent repository (C#/.NET 8): https://github.com/BryarGh/DocumentAgent.Worker-for-NAPS2

## Features

- Detects the local agent (preferred ports 3333–3335) and caches the port.
- Manage scanners and profiles via `/scanners` and `/profiles`.
- Start scans via `/scan`, poll `/scan/{job_id}` until completion, and surface errors (`scan_failed`, `scan_timeout`, `upload_url_missing`).
- Uses `client_request_id` to dedupe/skip when a PDF already exists or was recently queued.
- Fetch agent status via `/status` (includes degraded state, disk, scanner connectivity).

## Install

```bash
composer require document-agent/laravel-document-agent
php artisan vendor:publish --provider="DocumentAgent\DocumentAgentServiceProvider" --tag=document-agent-config
php artisan vendor:publish --provider="DocumentAgent\DocumentAgentServiceProvider" --tag=document-agent-assets
```

Config file: `config/document-agent.php`

Set your agent upload config on the machine running the agent (DocumentAgent/agent.config.json):

```json
{
  "naps2_path": "/Applications/NAPS2.app/Contents/MacOS/NAPS2",
  "upload_url": "https://your-app.test/api/document-agent/upload",
  "agent_token": "YOUR_TOKEN"
}
```

## Usage

```php
use DocumentAgent\DocumentAgentClient;

$agent = app(DocumentAgentClient::class);

// List scanners
$scanners = $agent->scanners();

// Create or update a profile (uses first discovered scanner if you pass that name)
$agent->createProfile([
    'profile_name' => 'HomePrinter',
    'scanner_name' => 'EPSON L3250 Series',
    'dpi' => 300,
    'color_mode' => 'color',
    'source' => 'ADF',
    'duplex' => false,
    'paper_size' => 'A4',
]);

// Start a scan (client_request_id enables dedupe/skip if already queued or PDF exists)
$start = $agent->startScan('DOC-123', 'HomePrinter', 'DOC-123');
$jobId = $start['job_id'];

// Poll until done
$result = $agent->waitForCompletion($jobId);
// When completed, the PDF is uploaded by the agent to your upload_url; handle it server-side there.

// Check status anytime
$status = $agent->status();
```

### Error handling

`waitForCompletion` can throw:

- `DocumentAgent\Exceptions\AgentUnavailableException` (agent not running)
- `DocumentAgent\Exceptions\UploadUrlMissingException` (configure upload_url in agent.config.json)
- `DocumentAgent\Exceptions\ScanTimeoutException`
- `DocumentAgent\Exceptions\ScanFailedException`
- `DocumentAgent\Exceptions\DocumentAgentException` (other 400s)

Wrap calls as needed:

```php
try {
    $result = $agent->waitForCompletion($jobId, timeoutSeconds: 600);
} catch (DocumentAgent\Exceptions\UploadUrlMissingException $e) {
    // prompt user to configure the agent
}
```

## Blade helper (optional)

If you publish assets, you can include a lightweight status indicator:

```blade
@include('document-agent::components.agent-status')
```

The script probes local ports and shows agent/scanner availability.

## Notes

- The SDK talks only to the local agent over loopback; uploads are handled by the agent using the `upload_url` and `agent_token` you configure locally.
- To avoid re-scanning, reuse the same `client_request_id` for a document; the agent will dedupe and skip if a PDF already exists.
- Agent requires a reachable scanner and at least 1 GB free disk by default.
