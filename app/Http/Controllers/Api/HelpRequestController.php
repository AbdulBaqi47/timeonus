<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelpRequest;
use App\Models\User;
use App\Services\HelpDeskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HelpRequestController extends Controller
{
    public function __construct(protected HelpDeskService $helpDesk)
    {
    }

    public function current(Request $request): JsonResponse
    {
        $active = $request->user()->helpRequestsInitiated()
            ->whereNull('ended_at')
            ->whereNull('cancelled_at')
            ->latest('requested_at')
            ->with('participants')
            ->first();

        return response()->json([
            'help_request' => $active ? $this->transformHelpRequest($active) : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'topic' => ['required', 'string', 'max:255'],
            'primary_recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_lead_id' => ['nullable', 'integer', 'exists:users,id'],
            'channel' => ['nullable', 'string', 'max:50'],
            'requested_at' => ['nullable', 'date'],
            'participants' => ['nullable', 'array'],
            'participants.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'participants.*.role' => ['nullable', 'string', 'max:50'],
        ]);

        $helpRequest = $this->helpDesk->createRequest($request->user(), $data);

        return response()->json([
            'help_request' => $this->transformHelpRequest($helpRequest),
        ], 201);
    }

    public function accept(Request $request, HelpRequest $helpRequest): JsonResponse
    {
        $timestamp = $this->resolveTimestamp($request->input('accepted_at'));
        $helpRequest = $this->helpDesk->acceptRequest($helpRequest, $request->user(), $timestamp);

        return response()->json([
            'help_request' => $this->transformHelpRequest($helpRequest),
        ]);
    }

    public function start(Request $request, HelpRequest $helpRequest): JsonResponse
    {
        $timestamp = $this->resolveTimestamp($request->input('started_at'));
        $helpRequest = $this->helpDesk->startRequest($helpRequest, $timestamp);

        return response()->json([
            'help_request' => $this->transformHelpRequest($helpRequest),
        ]);
    }

    public function finish(Request $request, HelpRequest $helpRequest): JsonResponse
    {
        $data = $this->validate($request, [
            'ended_at' => ['nullable', 'date'],
            'count_as_idle' => ['sometimes', 'boolean'],
        ]);

        $timestamp = $this->resolveTimestamp($data['ended_at'] ?? null);
        $helpRequest = $this->helpDesk->finishRequest($helpRequest, $timestamp, $data['count_as_idle'] ?? false);

        return response()->json([
            'help_request' => $this->transformHelpRequest($helpRequest),
        ]);
    }

    public function escalate(Request $request, HelpRequest $helpRequest): JsonResponse
    {
        $data = $this->validate($request, [
            'team_lead_id' => ['nullable', 'integer', 'exists:users,id'],
            'escalated_at' => ['nullable', 'date'],
        ]);

        $timestamp = $this->resolveTimestamp($data['escalated_at'] ?? null);
        $teamLead = isset($data['team_lead_id'])
            ? User::query()->find($data['team_lead_id'])
            : null;

        $helpRequest = $this->helpDesk->escalateRequest($helpRequest, $teamLead, $timestamp);

        return response()->json([
            'help_request' => $this->transformHelpRequest($helpRequest),
        ]);
    }

    public function cancel(Request $request, HelpRequest $helpRequest): JsonResponse
    {
        $data = $this->validate($request, [
            'cancelled_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $timestamp = $this->resolveTimestamp($data['cancelled_at'] ?? null);
        $helpRequest = $this->helpDesk->cancelRequest($helpRequest, $timestamp, $data['reason'] ?? null);

        return response()->json([
            'help_request' => $this->transformHelpRequest($helpRequest),
        ]);
    }

    protected function resolveTimestamp(?string $value): Carbon
    {
        return $value ? Carbon::parse($value) : now();
    }

    protected function transformHelpRequest(HelpRequest $request): array
    {
        $request->loadMissing('participants');

        return [
            'id' => $request->getKey(),
            'topic' => $request->topic,
            'status' => $request->status,
            'requested_at' => optional($request->requested_at)->toIso8601String(),
            'started_at' => optional($request->started_at)->toIso8601String(),
            'ended_at' => optional($request->ended_at)->toIso8601String(),
            'duration_seconds' => $request->duration_seconds,
            'count_as_idle' => $request->count_as_idle,
            'initiator_id' => $request->initiator_id,
            'primary_recipient_id' => $request->primary_recipient_id,
            'team_lead_id' => $request->team_lead_id,
            'participants' => $request->participants->map(fn ($participant) => [
                'user_id' => $participant->user_id,
                'role' => $participant->role,
                'status' => $participant->status,
                'joined_at' => optional($participant->joined_at)->toIso8601String(),
                'left_at' => optional($participant->left_at)->toIso8601String(),
            ])->all(),
        ];
    }
}