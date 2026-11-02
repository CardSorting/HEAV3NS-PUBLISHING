<?php

namespace App\Services\Media;

class CoconutJobStatusService
{
    public function isFinished(string $jobId, ?string $region = null): bool
    {
        $options = $region && $region !== 'us-east-1' ? ['region' => $region] : [];
        $client = new \Coconut\Client(getSetting('media.coconut_api_key'), $options);
        $job = (new \Coconut\Job($client))->retrieve($jobId);
        // https://docs.coconut.co/jobs/api — only job-level terminal statuses stop uploads.
        return in_array(data_get($job, 'status'), ['job.completed', 'job.failed'], true);
    }
}
