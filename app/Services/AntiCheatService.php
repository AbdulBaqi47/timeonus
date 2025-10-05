<?php

namespace App\Services;

use App\Models\ActivitySample;
use App\Models\SuspiciousEvent;
use Illuminate\Support\Arr;

class AntiCheatService
{
    protected array $suspiciousExtensions = [
        'cursor-pro',
        'auto-mover',
        'anti-idle',
    ];

    public function evaluateSample(ActivitySample $sample): ?SuspiciousEvent
    {
        $payload = $sample->payload ?? [];
        $reasons = [];
        $confidence = 0.0;

        if (Arr::get($payload, 'synthetic') === true) {
            $reasons[] = 'Desktop agent marked input as synthetic.';
            $confidence = max($confidence, 0.9);
        }

        if ($this->looksAutomated($sample)) {
            $reasons[] = 'Low variance high-frequency cursor activity detected.';
            $confidence = max($confidence, 0.7);
        }

        if ($extension = $this->detectSuspiciousExtension($payload)) {
            $reasons[] = sprintf('Browser extension detected: %s', $extension);
            $confidence = max($confidence, 0.8);
        }

        if (empty($reasons)) {
            return null;
        }

        return SuspiciousEvent::query()->create([
            'user_id' => $sample->user_id,
            'work_session_id' => $sample->work_session_id,
            'activity_sample_id' => $sample->getKey(),
            'detected_at' => $sample->recorded_at,
            'category' => 'automation',
            'severity' => $confidence >= 0.85 ? 'high' : 'medium',
            'confidence' => round($confidence, 2),
            'summary' => implode(' ', $reasons),
            'status' => 'open',
            'metadata' => $payload,
        ]);
    }

    protected function looksAutomated(ActivitySample $sample): bool
    {
        $payload = $sample->payload ?? [];
        $movementVariance = Arr::get($payload, 'movement_variance', 1);
        $movementEntropy = Arr::get($payload, 'movement_entropy', 1);
        $keyboardEvents = $sample->keyboard_events ?? 0;
        $mouseEvents = $sample->mouse_events ?? 0;

        return $mouseEvents > 200
            && $keyboardEvents === 0
            && $movementVariance < 0.05
            && $movementEntropy < 0.2;
    }

    protected function detectSuspiciousExtension(array $payload): ?string
    {
        $extensions = collect(Arr::get($payload, 'extensions', []));

        return $extensions->first(function ($extension) {
            $slug = is_array($extension) ? Arr::get($extension, 'id') : $extension;
            if (! $slug) {
                return false;
            }

            $slug = strtolower((string) $slug);

            foreach ($this->suspiciousExtensions as $needle) {
                if (str_contains($slug, $needle)) {
                    return true;
                }
            }

            return false;
        });
    }
}
