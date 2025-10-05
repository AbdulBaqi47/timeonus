<?php

namespace App\Console\Commands;

use App\Models\HelpRequest;
use App\Models\User;
use App\Services\HelpDeskService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MonitorHelpRequests extends Command
{
    /**
     * @var string
     */
    protected $signature = 'time:monitor-help';

    /**
     * @var string
     */
    protected $description = 'Escalates help requests that exceed the configured assistance window';

    public function __construct(protected HelpDeskService $helpDesk)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $threshold = config('services.time_tracker.help_timeout_seconds', 900);
        $cutoff = Carbon::now()->subSeconds($threshold);
        $escalated = 0;

        HelpRequest::query()
            ->whereNull('escalated_at')
            ->whereIn('status', ['accepted', 'in_progress'])
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $cutoff)
            ->with(['teamLead', 'participants.user'])
            ->chunkById(100, function ($requests) use (&$escalated) {
                foreach ($requests as $request) {
                    $teamLead = $request->teamLead;

                    if (! $teamLead) {
                        $teamLead = $request->participants
                            ->first(fn ($participant) => $participant->role === 'team_lead')?->user;
                    }

                    $this->helpDesk->escalateRequest($request, $teamLead, now());
                    $escalated++;
                }
            });

        $this->info("Escalated {$escalated} help request(s).");

        return self::SUCCESS;
    }
}