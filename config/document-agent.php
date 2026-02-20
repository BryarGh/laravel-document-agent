<?php

return [
    // Host where the local DocumentAgent is listening.
    'host' => env('DOCUMENT_AGENT_HOST', '127.0.0.1'),

    // Ports to probe for the agent. The first responsive /health wins and is cached.
    'ports' => [3333, 3334, 3335],

    // Timeout (seconds) for the initial /health probe when detecting the agent port.
    'health_timeout' => 1.5,

    // Default timeout (seconds) for API calls to the agent.
    'request_timeout' => 10.0,

    'poll' => [
        // Interval (ms) between polling attempts for /scan/{jobId}.
        'interval_ms' => 1000,
        // Maximum time (seconds) to wait for scan completion before giving up.
        'timeout_seconds' => 300,
    ],
];
