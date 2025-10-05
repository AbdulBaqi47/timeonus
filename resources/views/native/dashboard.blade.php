<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'TimeOnUs') }} Desktop</title>
    @vite(['resources/css/app.css', 'resources/js/native/dashboard.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="max-w-5xl mx-auto py-10 px-6 space-y-8">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold">TimeOnUs Desktop Tracker</h1>
                <p class="text-slate-500">Monitor your shift, request help, and stay synced with the team.</p>
            </div>
            <div class="text-right">
                @auth
                    <p class="text-sm text-slate-500">Signed in as</p>
                    <p class="font-medium">{{ auth()->user()->name }}</p>
                @endauth
            </div>
        </header>

        @auth
            <section id="attendance-status" class="section-card grid gap-4 md:grid-cols-3">
                <div>
                    <h2 class="text-sm uppercase tracking-wide text-slate-500">Status</h2>
                    <p class="text-2xl font-semibold" data-field="status">--</p>
                </div>
                <div>
                    <h2 class="text-sm uppercase tracking-wide text-slate-500">Login</h2>
                    <p class="text-lg" data-field="login">--</p>
                </div>
                <div>
                    <h2 class="text-sm uppercase tracking-wide text-slate-500">Logout</h2>
                    <p class="text-lg" data-field="logout">--</p>
                </div>
                <div>
                    <h2 class="text-sm uppercase tracking-wide text-slate-500">Work Time</h2>
                    <p class="text-lg" data-field="work">--</p>
                </div>
                <div>
                    <h2 class="text-sm uppercase tracking-wide text-slate-500">Idle Time</h2>
                    <p class="text-lg" data-field="idle">--</p>
                </div>
                <div>
                    <h2 class="text-sm uppercase tracking-wide text-slate-500">Effective</h2>
                    <p class="text-lg" data-field="effective">--</p>
                </div>
            </section>

            <section class="grid gap-6 md:grid-cols-2">
                <div id="salary-summary" class="section-card space-y-2">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Salary Snapshot</h2>
                        <span class="text-sm text-slate-500" data-field="range">--</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-slate-500">Effective Hours</dt>
                            <dd class="text-lg font-medium" data-field="hours">--</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Hourly Rate</dt>
                            <dd class="text-lg font-medium">{{ optional(auth()->user()->employeeProfile)->hourly_rate ? sprintf('$%.2f', auth()->user()->employeeProfile->hourly_rate) : 'Configured by HR' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Gross Pay</dt>
                            <dd class="text-lg font-medium" data-field="gross">--</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Net Pay</dt>
                            <dd class="text-lg font-medium" data-field="net">--</dd>
                        </div>
                    </dl>
                </div>

                <div class="section-card space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">Request Help</h2>
                        <p class="text-sm text-slate-500">Ping a teammate or lead for support. Sessions auto-escalate after 15 minutes.</p>
                    </div>
                    <form id="help-request-form" class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-slate-600">Topic</label>
                            <input type="text" name="topic" required class="mt-1 w-full rounded border border-slate-200 px-3 py-2 focus:outline-none focus:ring focus:ring-sky-300" placeholder="Describe the task" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-600">Primary Recipient (optional)</label>
                            <input type="number" name="recipient" class="mt-1 w-full rounded border border-slate-200 px-3 py-2 focus:outline-none focus:ring focus:ring-sky-300" placeholder="User ID" />
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Send Help Request</button>
                    </form>
                </div>
            </section>

            <section class="section-card space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Active Help Sessions</h2>
                    <button type="button" class="text-sm text-sky-600" onclick="window.location.reload()">Refresh</button>
                </div>
                <ul id="help-requests" class="grid gap-3"></ul>
            </section>
        @else
            <section class="section-card space-y-4 text-center">
                <h2 class="text-xl font-semibold">Sign in to get started</h2>
                <p class="text-slate-500">Log in with your company credentials to enable automatic attendance and salary tracking.</p>
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Open Login</a>
            </section>
        @endauth
    </div>
</body>
</html>