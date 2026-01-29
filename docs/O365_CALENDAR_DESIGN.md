# Office 365 / Microsoft Graph Calendar Integration - Design Document

## Overview

This document outlines the design for integrating Office 365/Microsoft 365 calendars using the Microsoft Graph API. The design mirrors the existing Google Calendar integration architecture for consistency and maintainability.

## Architecture Components

### 1. Database Schema

#### User Model Extensions
Add the following columns to `users` table:

```php
// OAuth tokens for Microsoft Graph
'microsoft_id'                    // string, nullable - Microsoft user ID
'microsoft_token'                 // text, nullable - Access token (encrypted)
'microsoft_refresh_token'         // text, nullable - Refresh token (encrypted)
'microsoft_token_expires_at'      // timestamp, nullable - Token expiration
'microsoft_calendar_id'           // string, nullable - Selected calendar ID (can be user-specific)
```

#### Role Model Extensions
Add the following columns to `roles` table:

```php
// Per-role O365 calendar configuration
'o365_calendar_id'                // string, nullable - Which O365 calendar to use
'o365_calendar_name'              // string, nullable - Display name of calendar
'o365_webhook_subscription_id'    // string, nullable - Webhook subscription ID
'o365_webhook_expires_at'         // timestamp, nullable - Webhook expiration
'o365_sync_direction'             // enum('to', 'from', 'both'), nullable - Sync direction
```

#### EventRole Pivot Extensions
Add to `event_role` pivot table:

```php
'o365_event_id'                   // string, nullable - O365 event ID
'o365_event_change_key'           // string, nullable - O365 change tracking
```

### 2. Configuration

#### config/services.php
```php
'microsoft' => [
    'client_id'       => env('MICROSOFT_CLIENT_ID'),
    'client_secret'   => env('MICROSOFT_CLIENT_SECRET'),
    'tenant_id'       => env('MICROSOFT_TENANT_ID', 'common'), // 'common', 'organizations', or specific tenant
    'redirect_uri'    => env('MICROSOFT_REDIRECT_URI'),
    'webhook_secret'  => env('MICROSOFT_WEBHOOK_SECRET'),
],
```

#### .env additions
```env
MICROSOFT_CLIENT_ID=your_app_registration_client_id
MICROSOFT_CLIENT_SECRET=your_app_registration_secret
MICROSOFT_TENANT_ID=common
MICROSOFT_REDIRECT_URI=https://yourdomain.com/o365-calendar/callback
MICROSOFT_WEBHOOK_SECRET=random_secret_for_webhook_validation
```

### 3. Service Layer

#### app/Services/O365CalendarService.php

**Key Responsibilities:**
- OAuth 2.0 authentication via Microsoft identity platform
- Token management (access & refresh)
- Microsoft Graph API interactions
- Calendar CRUD operations
- Webhook subscription management

**Core Methods:**

```php
class O365CalendarService
{
    // Authentication
    public function getAuthUrl(): string
    public function getAccessToken(string $code): array
    public function refreshTokenIfNeeded(User $user): bool
    public function ensureValidToken(User $user): bool
    
    // Calendar Operations
    public function getCalendars(): array
    public function getCalendar(string $calendarId): ?array
    
    // Event Operations (mirroring Google implementation)
    public function createEvent(Event $event, Role $role): ?string
    public function updateEvent(Event $event, string $o365EventId, Role $role): ?string
    public function deleteEvent(string $o365EventId, string $calendarId): bool
    public function getEvent(string $o365EventId, string $calendarId): ?array
    public function getEvents(string $calendarId, ?\DateTime $timeMin = null, ?\DateTime $timeMax = null): array
    
    // Webhook/Subscription Management
    public function createWebhookSubscription(Role $role, string $calendarId): ?array
    public function renewWebhookSubscription(string $subscriptionId): ?array
    public function deleteWebhookSubscription(string $subscriptionId): bool
    
    // Sync Operations
    public function syncUserEvents(User $user, Role $role): array
    public function syncAllUserEvents(User $user): array
}
```

**Microsoft Graph API Endpoints Used:**
- Auth: `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize`
- Token: `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token`
- Calendars: `GET /me/calendars`
- Events: `GET|POST|PATCH|DELETE /me/calendars/{calendarId}/events/{eventId}`
- Subscriptions: `POST /subscriptions`

**Required Scopes:**
- `Calendars.ReadWrite` - Full calendar access
- `User.Read` - User profile access
- `offline_access` - Refresh token

### 4. Job Layer (Queue Workers)

#### app/Jobs/SyncEventToO365Calendar.php

```php
class SyncEventToO365Calendar implements ShouldQueue
{
    use Queueable;
    
    protected $event;
    protected $role;
    protected $action; // 'create', 'update', 'delete'
    
    public function __construct(Event $event, Role $role, string $action = 'create')
    
    public function handle(O365CalendarService $o365CalendarService): void
    
    private function createEvent(O365CalendarService $o365CalendarService): void
    private function updateEvent(O365CalendarService $o365CalendarService): void
    private function deleteEvent(O365CalendarService $o365CalendarService): void
}
```

#### app/Jobs/SyncEventFromO365Calendar.php

```php
class SyncEventFromO365Calendar implements ShouldQueue
{
    use Queueable;
    
    protected $role;
    protected $o365EventId;
    protected $action; // 'created', 'updated', 'deleted'
    
    public function __construct(Role $role, string $o365EventId, string $action)
    
    public function handle(O365CalendarService $o365CalendarService): void
}
```

### 5. Controller Layer

#### app/Http/Controllers/O365CalendarController.php

**Routes handled:**
- OAuth flow (redirect, callback)
- Connection management (connect, disconnect, reauthorize)
- Calendar selection
- Manual sync triggers
- Calendar listing

**Key Methods:**

```php
class O365CalendarController extends Controller
{
    // OAuth Flow
    public function redirect(): RedirectResponse
    public function callback(Request $request): RedirectResponse
    public function reauthorize(): RedirectResponse
    
    // Connection Management
    public function disconnect(): RedirectResponse
    
    // Calendar Operations
    public function getCalendars(Request $request): JsonResponse
    public function selectCalendar(Request $request): JsonResponse
    
    // Sync Operations
    public function sync(Request $request, $subdomain): JsonResponse
    public function syncEvent(Request $request, $subdomain, $eventId): JsonResponse
    public function syncAll(Request $request): JsonResponse
}
```

#### app/Http/Controllers/O365CalendarWebhookController.php

**Handles webhook notifications from Microsoft Graph**

```php
class O365CalendarWebhookController extends Controller
{
    // Webhook validation (responds to validation token)
    public function verify(Request $request): Response
    
    // Webhook notification handling
    public function handle(Request $request): Response
    
    // Subscription renewal (called by scheduled task)
    public function renewSubscriptions(): void
}
```

### 6. Model Updates

#### app/Models/User.php

```php
// Add to $fillable
'microsoft_id',
'microsoft_token',
'microsoft_refresh_token',
'microsoft_token_expires_at',
'microsoft_calendar_id',

// Add to $hidden
'microsoft_token',
'microsoft_refresh_token',
'microsoft_token_expires_at',

// Add to $casts
'microsoft_token_expires_at' => 'datetime',

// Add helper methods
public function hasO365Connected(): bool
{
    return !is_null($this->microsoft_token) && !is_null($this->microsoft_refresh_token);
}
```

#### app/Models/Role.php

```php
// Add to $fillable
'o365_calendar_id',
'o365_calendar_name',
'o365_webhook_subscription_id',
'o365_webhook_expires_at',
'o365_sync_direction',

// Add to $casts
'o365_webhook_expires_at' => 'datetime',

// Add helper methods
public function getO365CalendarId(): string
{
    return $this->o365_calendar_id ?: 'primary';
}

public function hasO365Settings(): bool
{
    return !is_null($this->o365_calendar_id);
}

public function syncsToO365(): bool
{
    return in_array($this->o365_sync_direction, ['to', 'both']);
}

public function syncsFromO365(): bool
{
    return in_array($this->o365_sync_direction, ['from', 'both']);
}
```

#### app/Models/Event.php

```php
// Add helper methods
public function getO365EventIdForRole($roleId)
{
    $eventRole = $this->roles()
        ->wherePivot('role_id', $roleId)
        ->first();
        
    return $eventRole ? $eventRole->pivot->o365_event_id : null;
}

public function setO365EventIdForRole($roleId, $o365EventId)
{
    $this->roles()
        ->wherePivot('role_id', $roleId)
        ->update(['o365_event_id' => $o365EventId]);
}

public function getO365ChangeKeyForRole($roleId)
{
    $eventRole = $this->roles()
        ->wherePivot('role_id', $roleId)
        ->first();
        
    return $eventRole ? $eventRole->pivot->o365_event_change_key : null;
}

public function setO365ChangeKeyForRole($roleId, $changeKey)
{
    $this->roles()
        ->wherePivot('role_id', $roleId)
        ->update(['o365_event_change_key' => $changeKey]);
}

public function hasO365EventForRole($roleId): bool
{
    return !is_null($this->getO365EventIdForRole($roleId));
}
```

#### Update Event Model Observers

In `Event::boot()` add O365 sync triggers:

```php
static::created(function ($event) {
    // Existing Google Calendar sync...
    
    // O365 Calendar sync
    foreach ($event->roles as $role) {
        if ($role->syncsToO365()) {
            $user = $role->user;
            if ($user && $user->hasO365Connected()) {
                SyncEventToO365Calendar::dispatchSync($event, $role, 'create');
            }
        }
    }
});

static::updated(function ($event) {
    // Existing Google Calendar sync...
    
    // O365 Calendar sync
    foreach ($event->roles as $role) {
        if ($role->syncsToO365()) {
            $user = $role->user;
            if ($user && $user->hasO365Connected()) {
                SyncEventToO365Calendar::dispatchSync($event, $role, 'update');
            }
        }
    }
});

static::deleting(function ($event) {
    // Existing Google Calendar sync...
    
    // O365 Calendar sync
    foreach ($event->roles as $role) {
        if ($role->syncsToO365()) {
            $user = $role->user;
            if ($user && $user->hasO365Connected()) {
                SyncEventToO365Calendar::dispatchSync($event, $role, 'delete');
            }
        }
    }
});
```

### 7. Routes

#### routes/web.php

```php
// O365 Calendar webhook routes (no auth required)
Route::get('/o365-calendar/webhook', [O365CalendarWebhookController::class, 'verify'])
    ->name('o365.calendar.webhook.verify')
    ->middleware('throttle:10,1');
    
Route::post('/o365-calendar/webhook', [O365CalendarWebhookController::class, 'handle'])
    ->name('o365.calendar.webhook.handle')
    ->middleware('throttle:60,1');

// O365 Calendar routes (authenticated)
Route::middleware(['auth'])->group(function () {
    Route::get('/o365-calendar/redirect', [O365CalendarController::class, 'redirect'])
        ->name('o365.calendar.redirect');
        
    Route::get('/o365-calendar/callback', [O365CalendarController::class, 'callback'])
        ->name('o365.calendar.callback');
        
    Route::get('/o365-calendar/reauthorize', [O365CalendarController::class, 'reauthorize'])
        ->name('o365.calendar.reauthorize');
        
    Route::get('/o365-calendar/disconnect', [O365CalendarController::class, 'disconnect'])
        ->name('o365.calendar.disconnect');
        
    Route::get('/o365-calendar/calendars', [O365CalendarController::class, 'getCalendars'])
        ->name('o365.calendar.calendars');
        
    Route::post('/o365-calendar/select-calendar/{subdomain}', [O365CalendarController::class, 'selectCalendar'])
        ->name('o365.calendar.select_calendar');
        
    Route::post('/o365-calendar/sync/{subdomain}', [O365CalendarController::class, 'sync'])
        ->name('o365.calendar.sync');
        
    Route::post('/o365-calendar/sync-event/{subdomain}/{eventId}', [O365CalendarController::class, 'syncEvent'])
        ->name('o365.calendar.sync_event');
        
    Route::post('/o365-calendar/sync-all', [O365CalendarController::class, 'syncAll'])
        ->name('o365.calendar.sync_all');
});
```

### 8. Migrations

#### Migration 1: Add O365 fields to users table

```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_o365_fields_to_users_table.php

Schema::table('users', function (Blueprint $table) {
    $table->string('microsoft_id')->nullable()->after('google_token_expires_at');
    $table->text('microsoft_token')->nullable()->after('microsoft_id');
    $table->text('microsoft_refresh_token')->nullable()->after('microsoft_token');
    $table->timestamp('microsoft_token_expires_at')->nullable()->after('microsoft_refresh_token');
    $table->string('microsoft_calendar_id')->nullable()->after('microsoft_token_expires_at');
});
```

#### Migration 2: Add O365 fields to roles table

```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_o365_fields_to_roles_table.php

Schema::table('roles', function (Blueprint $table) {
    $table->string('o365_calendar_id')->nullable()->after('caldav_last_sync_at');
    $table->string('o365_calendar_name')->nullable()->after('o365_calendar_id');
    $table->string('o365_webhook_subscription_id')->nullable()->after('o365_calendar_name');
    $table->timestamp('o365_webhook_expires_at')->nullable()->after('o365_webhook_subscription_id');
    $table->enum('o365_sync_direction', ['to', 'from', 'both'])->nullable()->after('o365_webhook_expires_at');
});
```

#### Migration 3: Add O365 fields to event_role pivot table

```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_o365_fields_to_event_role_table.php

Schema::table('event_role', function (Blueprint $table) {
    $table->string('o365_event_id')->nullable()->after('caldav_event_etag');
    $table->string('o365_event_change_key')->nullable()->after('o365_event_id');
});
```

### 9. Dependencies

#### Composer packages
```bash
composer require microsoft/microsoft-graph
```

This package provides:
- Microsoft Graph API client
- OAuth 2.0 authentication helpers
- Request/response models

### 10. Authentication Flow

```
User clicks "Connect O365 Calendar"
    ↓
Redirect to /o365-calendar/redirect
    ↓
O365CalendarService::getAuthUrl()
    ↓
Microsoft login.microsoftonline.com consent screen
    ↓
User approves scopes
    ↓
Redirect to /o365-calendar/callback?code=xxx
    ↓
O365CalendarService::getAccessToken(code)
    ↓
Microsoft returns access_token + refresh_token
    ↓
Store tokens in User model
    ↓
Redirect to profile with success message
```

### 11. Sync Flow (TO O365)

```
Event created/updated/deleted
    ↓
Event Model observer fires
    ↓
Check role->syncsToO365() && user->hasO365Connected()
    ↓
Dispatch SyncEventToO365Calendar job
    ↓
Job: Validate/refresh token
    ↓
Job: Call O365CalendarService->createEvent()
    ↓
O365CalendarService: POST to Graph API
    ↓
Store o365_event_id in event_role pivot
    ↓
Success
```

### 12. Sync Flow (FROM O365)

```
Change happens in O365 calendar
    ↓
Microsoft sends webhook notification to /o365-calendar/webhook
    ↓
O365CalendarWebhookController::handle()
    ↓
Validate webhook signature/token
    ↓
Find role by subscription ID
    ↓
Check role->syncsFromO365()
    ↓
Dispatch SyncEventFromO365Calendar job
    ↓
Job: Fetch event details from Graph API
    ↓
Job: Create/update/delete Event in database
    ↓
Success
```

### 13. Webhook Management

Microsoft Graph webhooks (subscriptions) expire after a maximum of 3 days for calendar events.

**Scheduled Task** (add to `app/Console/Kernel.php`):
```php
$schedule->call(function () {
    app(O365CalendarWebhookController::class)->renewSubscriptions();
})->daily();
```

**Renewal Logic:**
- Query roles where `o365_webhook_expires_at < now()->addDay()`
- For each role, call `O365CalendarService::renewWebhookSubscription()`
- Update `o365_webhook_expires_at` in database

### 14. Security Considerations

1. **Token Storage:** Consider encrypting tokens in database
2. **Webhook Validation:** Verify webhook signatures or use secret tokens
3. **Rate Limiting:** Microsoft Graph has rate limits (apply throttling)
4. **Scope Minimization:** Only request necessary permissions
5. **Token Refresh:** Handle refresh failures gracefully (notify user to reconnect)

### 15. Error Handling

```php
try {
    // O365 API call
} catch (\Microsoft\Graph\Exception\GraphException $e) {
    if ($e->getCode() === 401) {
        // Token expired, try refresh
        if (!$this->refreshTokenIfNeeded($user)) {
            // Refresh failed, notify user to reconnect
            Log::error('O365 token refresh failed', ['user_id' => $user->id]);
            // Optionally: Notify user via email/notification
        }
    } else {
        // Other Graph API errors
        Log::error('O365 API error', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
    }
}
```

### 16. Testing Strategy

1. **Unit Tests:**
   - O365CalendarService methods
   - Token refresh logic
   - Event mapping logic

2. **Integration Tests:**
   - OAuth flow
   - Event sync (create/update/delete)
   - Webhook handling

3. **Manual Testing:**
   - Connect O365 account
   - Create event → verify in O365
   - Update event in app → verify in O365
   - Create event in O365 → verify webhook triggers sync

### 17. User Interface Updates

Add O365 calendar connection UI to profile page (mirroring Google Calendar):

```blade
{{-- resources/views/profile/partials/o365-calendar-section.blade.php --}}
<section id="section-o365-calendar">
    <header>
        <h2>Office 365 Calendar Integration</h2>
        <p>Connect your Microsoft 365 calendar to sync events automatically.</p>
    </header>
    
    @if(auth()->user()->hasO365Connected())
        <div>Connected to Office 365</div>
        <form method="GET" action="{{ route('o365.calendar.disconnect') }}">
            <button type="submit">Disconnect O365</button>
        </form>
    @else
        <form method="GET" action="{{ route('o365.calendar.redirect') }}">
            <button type="submit">Connect Office 365 Calendar</button>
        </form>
    @endif
</section>
```

### 18. API Input Requirements

Based on user's mention: "I would be supplying an API from an app registration and a calendar name"

**App Registration Setup:**
1. User creates Azure AD App Registration
2. User provides:
   - Client ID (Application ID)
   - Client Secret (Client Secret Value)
   - Tenant ID (optional, defaults to 'common')
3. User selects calendar name from dropdown

**Implementation:**
```php
// After OAuth connection, user can select calendar per role
public function selectCalendar(Request $request, $subdomain)
{
    $request->validate([
        'calendar_id' => 'required|string',
        'calendar_name' => 'required|string',
    ]);
    
    $role = Role::where('subdomain', $subdomain)
        ->where('user_id', auth()->id())
        ->firstOrFail();
        
    $role->update([
        'o365_calendar_id' => $request->calendar_id,
        'o365_calendar_name' => $request->calendar_name,
    ]);
    
    return response()->json(['message' => 'Calendar selected successfully']);
}
```

### 19. Documentation Files to Create

1. **docs/O365_CALENDAR_SETUP.md** - Setup instructions for end users
2. **docs/AZURE_APP_REGISTRATION.md** - How to create Azure AD app registration
3. **docs/O365_API_REFERENCE.md** - Developer reference for O365 service

### 20. Implementation Phases

**Phase 1: Foundation** (Database & Configuration)
- Create migrations
- Update models
- Add configuration
- Install dependencies

**Phase 2: Authentication** (OAuth Flow)
- Create O365CalendarService (auth methods)
- Create O365CalendarController (redirect/callback)
- Add routes
- Test OAuth flow

**Phase 3: Sync TO O365** (Push to O365)
- Implement create/update/delete in service
- Create SyncEventToO365Calendar job
- Update Event observers
- Test event sync

**Phase 4: Sync FROM O365** (Pull from O365)
- Implement webhook subscription
- Create O365CalendarWebhookController
- Create SyncEventFromO365Calendar job
- Test webhook notifications

**Phase 5: UI & Polish**
- Add profile page UI
- Add calendar selection dropdown
- Add sync status indicators
- Add error notifications

**Phase 6: Testing & Documentation**
- Write unit tests
- Write integration tests
- Complete documentation
- User acceptance testing

---

## Summary

This design provides a complete, production-ready architecture for Office 365 calendar integration that:

✅ Mirrors the existing Google Calendar implementation  
✅ Uses Microsoft Graph API best practices  
✅ Supports two-way sync (to/from/both)  
✅ Handles token refresh automatically  
✅ Supports webhooks for real-time updates  
✅ Scales with role-based multi-tenancy  
✅ Maintains security and error handling  
✅ Allows user-supplied App Registration credentials  
✅ Enables per-role calendar selection  

The implementation can be done incrementally following the phased approach outlined above.
