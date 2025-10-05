# TimeOnUs Desktop Tracker Architecture

## Goals
- Provide a NativePHP desktop companion that auto-marks attendance on OS login and enforces geo-fenced office check-ins.
- Track productive vs idle time using keyboard/mouse/touchpad activity with a 3 minute inactivity threshold.
- Facilitate peer help sessions with automatic escalation to team leads when they run long.
- Detect suspicious anti-idle tooling (e.g. cursor jigglers) and notify supervisors.
- Aggregate monthly timesheets, lateness reasons, and salary calculations with override/adjustment workflows.

## Domain Model
- **User**: existing Laravel auth user. Extended via EmployeeProfile for HR metadata (employee code, role, base salary, hourly rate, timezone, geo-fence).
- **Role**: role-based access (super_admin, dmin, manager, 	eam_lead, 	eam, hr, etc.) stored in oles + pivot.
- **OfficeLocation**: configurable geo-fence polygons or radius plus business hours.
- **AttendanceDay**: date-scoped record containing first login, logout, effective work seconds, idle seconds, help seconds, approvals, lateness reason.
- **WorkSession**: contiguous span of on-duty time (auto-opens on login, pauses when idle/help forced) attached to an attendance day.
- **IdlePeriod**: inactivity windows > 3 minutes. Linked to work session and includes reason (uto, manual, orced, help_timeout, suspicious).
- **HelpRequest**: collaboration ticket between employees with optional watchers. Tracks topic, notes, started_at, ended_at, escalated_at, plus statuses (pending, ccepted, in_progress, esolved, cancelled).
- **HelpParticipant**: pivot table allowing multi-party sessions and acknowledgement timestamps.
- **SalaryRun**: monthly/periodic aggregates per user storing totals, deductions, adjustments, approvals, and computed payout.
- **SalaryAdjustment**: manual override (credit/debit time) created by HR or Team Lead for emergencies.
- **ActivitySample**: granular events streamed from the desktop agent (mouse, keyboard, window focus, network) used for analytics and anti-cheat heuristics.
- **SuspiciousEvent**: flagged anomalies (e.g. repetitive equal-interval mouse moves) notifying leads/supervisors.

## Key Workflows
### 1. Attendance on Login
1. NativePHP app boots on OS login (registered via autostart in NativeAppServiceProvider).
2. Desktop agent authenticates the signed-in user via saved token / OS keychain and sends a POST /api/attendance/login request.
3. Backend verifies geolocation vs office fences (matching office_locations) when "office mode" is required.
4. AttendanceDay is upserted for today, login_at stamped, primary WorkSession created.
5. AttendanceLogged event raised -> listeners for notifications / analytics.

### 2. Activity + Idle Tracking
- Native electron process listens to powerMonitor, systemPreferences, raw input hooks for pointer/keyboard events.
- Activity heartbeat pushed every 30 seconds to /api/activity/heartbeat with counters.
- Backend ActivityMonitorService holds the latest activity timestamp per session (Redis cache).
- Scheduler command DetectIdleUsers runs every minute: when last activity > 3 minutes, closes active WorkSession segment and opens an IdlePeriod.
- When activity resumes, IdlePeriod is closed and WorkSession re-opens; effective shift time excludes idle seconds.

### 3. Help Requests & Escalation
1. Employee initiates help via desktop UI -> POST /api/help-requests targeting teammate(s).
2. Recipients get in-app desktop notifications (NativePHP Notification).
3. Session start/stop tracked; time inside help counts towards help_seconds.
4. A queued job monitors in-progress help; after 15 minutes a HelpTimeoutExceeded event fires -> notifies Team Lead.
5. Lead gets control panel action to mark help as "stop" which tags remaining duration as idle (creating IdlePeriod with eason = help_timeout).

### 4. Anti-Cheat Detection
- Desktop agent collects event entropy (movement variance, active window titles) and posts to /api/activity/samples.
- Heuristics:
  - Constant cursor motion with zero keypresses for > X minutes.
  - Identical deltas across samples (typical of mouse jigglers).
  - Windows of known automation extensions.
- On trigger, create SuspiciousEvent, notify lead/admin, optionally auto-toggle idle for that span.

### 5. Monthly Timesheets & Salaries
- Nightly job CloseAttendanceDay finalizes previous day totals (work, idle, help) and marks tardiness (login_at vs scheduled start + grace). Late reasons captured via UI prompt.
- Monthly GenerateSalaryRuns aggregates AttendanceDay totals, subtracts idle beyond allowance, adds adjustments, multiplies by role-specific rates, writes salary_runs rows.
- Users (except Super Admin) can view their running salary snapshot.
- HR/Leads can create SalaryAdjustment to credit hours when outages happen. Requires approval chain tracked via pivot table.

## Components & Layers
- **HTTP API**: Sanctum-protected routes under /api powering desktop agent.
- **Services**: AttendanceService, WorkSessionService, IdleService, HelpDeskService, SalaryService, AntiCheatService for cohesive business rules.
- **Events/Listeners**: decoupled notifications, analytics, Slack/email hooks.
- **Jobs**: asynchronous processing for escalation, salary generation, anomaly detection.
- **Data Stores**: MySQL primary, Redis cache for heartbeat state, ActivitySample raw queue for optional warehousing.
- **Policies**: enforce role-specific permissions for adjustments, help visibility, salary views.

## Desktop (Native/Electron)
- Register app auto-launch and tray menu via NativeAppServiceProvider.
- Primary window: dashboard showing current shift status, timers, help queue, salary preview.
- Background process streams activity & location, detects inactivity locally for responsiveness.
- Leverage @nativephp/electron bridge for notifications, global shortcuts, deep links.
- Use Inertia + Vue/React or Blade view rendered into the desktop window.

## Security & Compliance
- Use short-lived Sanctum tokens stored in OS-protected keychain.
- Encrypt sensitive payloads (activity samples) in transit (HTTPS) and at rest (Laravel encryption column casts where needed).
- Audit logging on all manual adjustments and overrides.
- Configurable retention policies for granular activity data.

## Future Extensions
- Integration with HRIS/payroll exports (CSV/API).
- FaceID / camera checks for stricter presence validation.
- Offline caching for remote employees.
- Custom analytics dashboards.
