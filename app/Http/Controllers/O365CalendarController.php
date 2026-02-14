<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\O365CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class O365CalendarController extends Controller
{
    protected $o365CalendarService;

    public function __construct(O365CalendarService $o365CalendarService)
    {
        $this->o365CalendarService = $o365CalendarService;
    }

    /**
     * Redirect to Microsoft OAuth
     */
    public function redirect(): RedirectResponse
    {
        $authUrl = $this->o365CalendarService->getAuthUrl();

        return redirect($authUrl);
    }

    /**
     * Handle Microsoft OAuth callback
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $code = $request->get('code');
            $error = $request->get('error');

            if ($error) {
                Log::error('O365 OAuth error', [
                    'error' => $error,
                    'error_description' => $request->get('error_description'),
                ]);

                return redirect()->to(route('profile.edit').'#section-o365-calendar')
                    ->with('error', 'O365 authorization failed: '.$request->get('error_description'));
            }

            if (! $code) {
                return redirect()->to(route('profile.edit').'#section-o365-calendar')
                    ->with('error', 'O365 authorization failed. Please try again.');
            }

            $token = $this->o365CalendarService->getAccessToken($code);

            if (isset($token['error'])) {
                Log::error('O365 OAuth token error', ['error' => $token['error']]);

                return redirect()->to(route('profile.edit').'#section-o365-calendar')
                    ->with('error', 'O365 authorization failed: '.($token['error_description'] ?? $token['error']));
            }

            // Store tokens in user record
            $user = Auth::user();
            $user->update([
                'microsoft_token' => $token['access_token'],
                'microsoft_refresh_token' => $token['refresh_token'] ?? null,
                'microsoft_token_expires_at' => now()->addSeconds($token['expires_in']),
            ]);

            return redirect()->to(route('profile.edit').'#section-o365-calendar')
                ->with('message', 'O365 Calendar connected successfully!');

        } catch (\Exception $e) {
            Log::error('O365 Calendar OAuth callback error', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return redirect()->to(route('profile.edit').'#section-o365-calendar')
                ->with('error', 'Failed to connect O365 Calendar. Please try again.');
        }
    }

    /**
     * Disconnect O365 Calendar
     */
    public function disconnect(): RedirectResponse
    {
        $user = Auth::user();

        // Clear webhook subscriptions before disconnecting
        try {
            $roles = $user->roles()->whereNotNull('o365_webhook_subscription_id')->get();

            foreach ($roles as $role) {
                // Clear webhook data from role
                $role->update([
                    'o365_webhook_subscription_id' => null,
                    'o365_webhook_expires_at' => null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clean up webhooks during O365 disconnect', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        $user->update([
            'microsoft_id' => null,
            'microsoft_token' => null,
            'microsoft_refresh_token' => null,
            'microsoft_token_expires_at' => null,
            'microsoft_calendar_id' => null,
        ]);

        return redirect()->to(route('profile.edit').'#section-o365-calendar')
            ->with('message', 'O365 Calendar disconnected successfully.');
    }

    /**
     * Get user's O365 Calendars
     */
    public function getCalendars(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasO365Connected()) {
            return response()->json(['error' => 'O365 Calendar not connected'], 400);
        }

        try {
            $calendars = $this->o365CalendarService->getCalendars($user);

            return response()->json(['calendars' => $calendars]);

        } catch (\Exception $e) {
            Log::error('Failed to get O365 Calendars', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to fetch calendars'], 500);
        }
    }

    /**
     * Select calendar for a specific role
     */
    public function selectCalendar(Request $request, $subdomain): JsonResponse
    {
        $request->validate([
            'calendar_id' => 'required|string',
            'calendar_name' => 'required|string',
            'sync_direction' => 'nullable|in:to,from,both',
        ]);

        $role = Role::where('subdomain', $subdomain)
            ->whereHas('users', function ($query) {
                $query->where('users.id', auth()->id());
            })
            ->firstOrFail();

        $role->update([
            'o365_calendar_id' => $request->calendar_id,
            'o365_calendar_name' => $request->calendar_name,
            'o365_sync_direction' => $request->sync_direction ?? 'to',
        ]);

        return response()->json([
            'message' => 'O365 calendar selected successfully',
            'calendar' => [
                'id' => $role->o365_calendar_id,
                'name' => $role->o365_calendar_name,
                'sync_direction' => $role->o365_sync_direction,
            ],
        ]);
    }

    /**
     * Sync all events for a specific role
     */
    public function sync(Request $request, $subdomain): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasO365Connected()) {
            return response()->json(['error' => 'O365 Calendar not connected'], 400);
        }

        $role = Role::where('subdomain', $subdomain)
            ->whereHas('users', function ($query) {
                $query->where('users.id', auth()->id());
            })
            ->firstOrFail();

        if (! $role->hasO365Settings()) {
            return response()->json(['error' => 'O365 calendar not configured for this role'], 400);
        }

        try {
            $results = $this->o365CalendarService->syncUserEvents($user, $role);

            return response()->json([
                'message' => 'Sync completed',
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync events to O365', [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to sync events'], 500);
        }
    }

    /**
     * Display a room calendar (for testing/debugging)
     */
    public function showRoom(Request $request, string $roomEmail)
    {
        try {
            $room = $this->o365CalendarService->getRoomCalendar($roomEmail);
            
            if (!$room) {
                abort(404, 'Room calendar not found or access denied');
            }

            $events = $this->o365CalendarService->getRoomCalendarEvents($roomEmail);

            return view('o365.room-calendar', [
                'room' => $room,
                'events' => $events,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to display room calendar', [
                'room_email' => $roomEmail,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to fetch room calendar: ' . $e->getMessage());
        }
    }

    /**
     * Sync a specific event
     */
    public function syncEvent(Request $request, $subdomain, $eventId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->hasO365Connected()) {
            return response()->json(['error' => 'O365 Calendar not connected'], 400);
        }

        $role = Role::where('subdomain', $subdomain)
            ->whereHas('users', function ($query) {
                $query->where('users.id', auth()->id());
            })
            ->firstOrFail();

        if (! $role->hasO365Settings()) {
            return response()->json(['error' => 'O365 calendar not configured for this role'], 400);
        }

        try {
            $event = \App\Models\Event::findOrFail(\App\Utils\UrlUtils::decodeId($eventId));

            // Check if event belongs to this role
            if (! $event->roles->contains($role)) {
                return response()->json(['error' => 'Event not found for this role'], 404);
            }

            $o365EventId = $event->getO365EventIdForRole($role->id);

            if ($o365EventId) {
                // Update existing event
                $o365Event = $this->o365CalendarService->updateEvent($event, $o365EventId, $role);
                $action = 'updated';
            } else {
                // Create new event
                $o365Event = $this->o365CalendarService->createEvent($event, $role);
                if ($o365Event) {
                    $event->setO365EventIdForRole($role->id, $o365Event['id']);
                    if (isset($o365Event['@odata.etag'])) {
                        $event->setO365ChangeKeyForRole($role->id, $o365Event['@odata.etag']);
                    }
                }
                $action = 'created';
            }

            if ($o365Event) {
                return response()->json([
                    'message' => "Event {$action} successfully",
                    'o365_event_id' => $o365Event['id'],
                ]);
            } else {
                return response()->json(['error' => 'Failed to sync event'], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync event to O365', [
                'user_id' => $user->id,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to sync event'], 500);
        }
    }

    /**
     * Show multi-room dashboard
     */
    public function showDashboard(Request $request)
    {
        $roomConfig = config('o365_rooms.rooms', []);
        $roomLookup = [];

        foreach ($roomConfig as $room) {
            if (is_string($room)) {
                $roomLookup[strtolower($room)] = ['email' => $room];
                continue;
            }

            if (! empty($room['email'])) {
                $roomLookup[strtolower($room['email'])] = $room;
            }
        }

        $roomEmails = $request->get('rooms');

        if ($roomEmails) {
            if (is_string($roomEmails)) {
                $roomEmails = array_map('trim', explode(',', $roomEmails));
            }
        } elseif (! empty($roomLookup)) {
            $roomEmails = array_values(array_map(static fn ($room) => $room['email'], $roomLookup));
        } else {
            $roomEmails = [
                'room.mexicocity@flydenver.com',
                'Room.Dublin@flydenver.com',
                'Room.PressRoom@flydenver.com',
                'Room.Cancun@flydenver.com',
            ];
        }

        $batchSize = (int) $request->get('batch_size', (int) env('O365_DASHBOARD_BATCH_SIZE', 6));
        $batchSize = max(1, min(20, $batchSize));
        $batch = max(1, (int) $request->get('batch', 1));
        $offset = ($batch - 1) * $batchSize;
        $roomEmails = array_slice($roomEmails, $offset, $batchSize);

        $rooms = [];
        foreach ($roomEmails as $roomEmail) {
            try {
                $room = $this->o365CalendarService->getRoomCalendar($roomEmail);
                $events = $this->o365CalendarService->getRoomCalendarEvents($roomEmail);
                
                $roomMeta = $roomLookup[strtolower($roomEmail)] ?? null;

                $rooms[] = [
                    'email' => $roomEmail,
                    'room' => $room,
                    'events' => $events,
                    'display_name' => $roomMeta['role_name'] ?? $roomMeta['display_name'] ?? null,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to fetch room calendar for dashboard', [
                    'room_email' => $roomEmail,
                    'error' => $e->getMessage(),
                ]);
                $roomMeta = $roomLookup[strtolower($roomEmail)] ?? null;

                $rooms[] = [
                    'email' => $roomEmail,
                    'room' => ['name' => $roomEmail, 'email' => $roomEmail],
                    'events' => [],
                    'error' => $e->getMessage(),
                    'display_name' => $roomMeta['role_name'] ?? $roomMeta['display_name'] ?? null,
                ];
            }
        }

        return view('o365.dashboard', compact('rooms'));
    }

    /**
     * Get all distribution lists
     */
    public function distributionLists(): mixed
    {
        $user = Auth::user();

        if (!$user || !$user->hasO365Connected()) {
            return redirect()->to(route('profile.edit').'#section-o365-calendar')
                ->with('error', 'Please connect your Office 365 account first.');
        }

        $distributionLists = $this->o365CalendarService->getDistributionLists($user);

        if ($distributionLists === null) {
            return redirect()->to(route('profile.edit').'#section-o365-calendar')
                ->with('error', 'Failed to fetch distribution lists. Please try reconnecting your account.');
        }

        return view('o365.distribution-lists', compact('distributionLists'));
    }

    /**
     * Get distribution lists as JSON
     */
    public function distributionListsJson(): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->hasO365Connected()) {
            return response()->json(['error' => 'O365 Calendar not connected'], 400);
        }

        $distributionLists = $this->o365CalendarService->getDistributionLists($user);

        if ($distributionLists === null) {
            return response()->json(['error' => 'Failed to fetch distribution lists'], 500);
        }

        return response()->json(['distribution_lists' => $distributionLists]);
    }

    /**
     * Get a specific distribution list
     */
    public function distributionListDetails(string $groupId): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->hasO365Connected()) {
            return response()->json(['error' => 'O365 Calendar not connected'], 400);
        }

        $distributionList = $this->o365CalendarService->getDistributionList($user, $groupId);

        if ($distributionList === null) {
            return response()->json(['error' => 'Failed to fetch distribution list'], 500);
        }

        return response()->json(['distribution_list' => $distributionList]);
    }
}
