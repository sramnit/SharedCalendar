<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Repos\EventRepo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class O365CalendarService
{
    protected $clientId;
    protected $clientSecret;
    protected $tenantId;
    protected $redirectUri;
    protected $eventRepo;

    public function __construct(EventRepo $eventRepo)
    {
        $this->clientId = config('services.microsoft.client_id');
        $this->clientSecret = config('services.microsoft.client_secret');
        $this->tenantId = config('services.microsoft.tenant_id', 'common');
        $this->redirectUri = config('services.microsoft.redirect_uri');
        $this->eventRepo = $eventRepo;
    }

    /**
     * Get the authorization URL for Microsoft OAuth
     */
    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'response_mode' => 'query',
            'scope' => 'openid profile email offline_access Calendars.ReadWrite User.Read',
            'prompt' => 'consent',
        ];

        return "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?" . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $code): array
    {
        try {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri,
                    'grant_type' => 'authorization_code',
                    'scope' => 'openid profile email offline_access Calendars.ReadWrite User.Read',
                ]
            );

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to exchange O365 auth code', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => 'Failed to get access token', 'error_description' => $response->body()];

        } catch (\Exception $e) {
            Log::error('Exception during O365 token exchange', [
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Exception', 'error_description' => $e->getMessage()];
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        try {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                    'scope' => 'openid profile email offline_access Calendars.ReadWrite User.Read',
                ]
            );

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to refresh O365 token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => 'Failed to refresh token'];

        } catch (\Exception $e) {
            Log::error('Exception during O365 token refresh', [
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Exception', 'error_description' => $e->getMessage()];
        }
    }

    /**
     * Refresh token if needed
     */
    public function refreshTokenIfNeeded(User $user): bool
    {
        if (! $user->microsoft_token || ! $user->microsoft_refresh_token) {
            Log::warning('User missing Microsoft tokens', [
                'user_id' => $user->id,
                'has_access_token' => ! is_null($user->microsoft_token),
                'has_refresh_token' => ! is_null($user->microsoft_refresh_token),
            ]);

            return false;
        }

        $expiresAt = $user->microsoft_token_expires_at;

        if ($expiresAt) {
            if (is_string($expiresAt)) {
                $expiresAt = Carbon::parse($expiresAt);
            }

            // Only refresh if token expires in the next 5 minutes
            $minutesUntilExpiry = $expiresAt->diffInMinutes(now());

            if ($minutesUntilExpiry > 5) {
                return true;
            }
        }

        Log::info('Refreshing O365 token', [
            'user_id' => $user->id,
            'expires_at' => $expiresAt,
            'minutes_until_expiry' => $expiresAt ? $expiresAt->diffInMinutes(now()) : 'unknown',
        ]);

        $refreshToken = $user->microsoft_refresh_token;
        if ($refreshToken) {
            try {
                $newToken = $this->refreshAccessToken($refreshToken);

                if (! isset($newToken['error'])) {
                    $user->update([
                        'microsoft_token' => $newToken['access_token'],
                        'microsoft_token_expires_at' => now()->addSeconds($newToken['expires_in']),
                    ]);

                    return true;
                } else {
                    Log::error('Failed to refresh O365 token', [
                        'user_id' => $user->id,
                        'error' => $newToken['error'],
                        'error_description' => $newToken['error_description'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Exception during O365 token refresh', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return false;
    }

    /**
     * Ensure user has valid O365 access with automatic token refresh
     */
    public function ensureValidToken(User $user): bool
    {
        return $this->refreshTokenIfNeeded($user);
    }

    /**
     * Get user's O365 calendars
     */
    public function getCalendars(User $user): array
    {
        try {
            if (! $this->ensureValidToken($user)) {
                return [];
            }

            $response = Http::withToken($user->microsoft_token)
                ->get('https://graph.microsoft.com/v1.0/me/calendars');

            if ($response->successful()) {
                $calendars = [];
                foreach ($response->json()['value'] ?? [] as $calendar) {
                    $calendars[] = [
                        'id' => $calendar['id'],
                        'name' => $calendar['name'],
                        'isDefaultCalendar' => $calendar['isDefaultCalendar'] ?? false,
                    ];
                }

                return $calendars;
            }

            Log::error('Failed to get O365 calendars', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Exception getting O365 calendars', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get a specific room calendar by email address
     * Uses application permissions to access room calendars
     */
    public function getRoomCalendar(string $roomEmail): ?array
    {
        try {
            // For room calendars, we need to use application permissions
            $response = Http::withToken($this->getAppAccessToken())
                ->get("https://graph.microsoft.com/v1.0/users/{$roomEmail}/calendar");

            if ($response->successful()) {
                $calendar = $response->json();
                return [
                    'id' => $calendar['id'] ?? null,
                    'name' => $calendar['name'] ?? $roomEmail,
                    'email' => $roomEmail,
                    'owner' => $calendar['owner'] ?? [],
                ];
            }

            Log::error('Failed to get O365 room calendar', [
                'room_email' => $roomEmail,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exception getting O365 room calendar', [
                'room_email' => $roomEmail,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get room calendar events
     */
    public function getRoomCalendarEvents(string $roomEmail, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        try {
            $start = $startDate ? $startDate->format('Y-m-d\TH:i:s\Z') : now()->startOfDay()->format('Y-m-d\TH:i:s\Z');
            $end = $endDate ? $endDate->format('Y-m-d\TH:i:s\Z') : now()->addDays(30)->endOfDay()->format('Y-m-d\TH:i:s\Z');

            $response = Http::withToken($this->getAppAccessToken())
                ->get("https://graph.microsoft.com/v1.0/users/{$roomEmail}/calendar/calendarView", [
                    'startDateTime' => $start,
                    'endDateTime' => $end,
                    '$orderby' => 'start/dateTime',
                ]);

            if ($response->successful()) {
                return $response->json()['value'] ?? [];
            }

            Log::error('Failed to get O365 room calendar events', [
                'room_email' => $roomEmail,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('Exception getting O365 room calendar events', [
                'room_email' => $roomEmail,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get app-only access token for accessing room calendars
     * Uses client credentials flow
     */
    protected function getAppAccessToken(): string
    {
        try {
            $tenantId = config('services.microsoft.tenant_id');
            $clientId = config('services.microsoft.client_id');
            $clientSecret = config('services.microsoft.client_secret');

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            );

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            Log::error('Failed to get app access token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Failed to get app access token');

        } catch (\Exception $e) {
            Log::error('Exception getting app access token', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create an event in O365 calendar
     */
    public function createEvent(Event $event, Role $role): ?array
    {
        try {
            $user = $role->user;

            if (! $user || ! $user->hasO365Connected()) {
                throw new \Exception('User does not have O365 connected');
            }

            if (! $this->ensureValidToken($user)) {
                throw new \Exception('Failed to refresh O365 token');
            }

            $calendarId = $role->getO365CalendarId();

            // Build event data
            $eventData = [
                'subject' => $event->name,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $event->description ?? '',
                ],
                'start' => [
                    'dateTime' => $event->getStartDateTime()->toIso8601String(),
                    'timeZone' => $role->timezone ?? 'UTC',
                ],
                'end' => [
                    'dateTime' => $event->getStartDateTime()->copy()->addHours($event->duration ?: 2)->toIso8601String(),
                    'timeZone' => $role->timezone ?? 'UTC',
                ],
            ];

            // Add location if venue exists
            if ($event->venue && $event->venue->bestAddress()) {
                $eventData['location'] = [
                    'displayName' => $event->venue->bestAddress(),
                ];
            }

            $endpoint = $calendarId === 'primary' 
                ? 'https://graph.microsoft.com/v1.0/me/calendar/events'
                : "https://graph.microsoft.com/v1.0/me/calendars/{$calendarId}/events";

            $response = Http::withToken($user->microsoft_token)
                ->post($endpoint, $eventData);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to create O365 event', [
                'event_id' => $event->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exception creating O365 event', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update an event in O365 calendar
     */
    public function updateEvent(Event $event, string $o365EventId, Role $role): ?array
    {
        try {
            $user = $role->user;

            if (! $user || ! $user->hasO365Connected()) {
                throw new \Exception('User does not have O365 connected');
            }

            if (! $this->ensureValidToken($user)) {
                throw new \Exception('Failed to refresh O365 token');
            }

            $calendarId = $role->getO365CalendarId();

            // Build event data
            $eventData = [
                'subject' => $event->name,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $event->description ?? '',
                ],
                'start' => [
                    'dateTime' => $event->getStartDateTime()->toIso8601String(),
                    'timeZone' => $role->timezone ?? 'UTC',
                ],
                'end' => [
                    'dateTime' => $event->getStartDateTime()->copy()->addHours($event->duration ?: 2)->toIso8601String(),
                    'timeZone' => $role->timezone ?? 'UTC',
                ],
            ];

            // Add location if venue exists
            if ($event->venue && $event->venue->bestAddress()) {
                $eventData['location'] = [
                    'displayName' => $event->venue->bestAddress(),
                ];
            }

            $endpoint = $calendarId === 'primary'
                ? "https://graph.microsoft.com/v1.0/me/calendar/events/{$o365EventId}"
                : "https://graph.microsoft.com/v1.0/me/calendars/{$calendarId}/events/{$o365EventId}";

            $response = Http::withToken($user->microsoft_token)
                ->patch($endpoint, $eventData);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to update O365 event', [
                'event_id' => $event->id,
                'o365_event_id' => $o365EventId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exception updating O365 event', [
                'event_id' => $event->id,
                'o365_event_id' => $o365EventId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete an event from O365 calendar
     */
    public function deleteEvent(string $o365EventId, string $calendarId, User $user): bool
    {
        try {
            if (! $this->ensureValidToken($user)) {
                throw new \Exception('Failed to refresh O365 token');
            }

            $endpoint = $calendarId === 'primary'
                ? "https://graph.microsoft.com/v1.0/me/calendar/events/{$o365EventId}"
                : "https://graph.microsoft.com/v1.0/me/calendars/{$calendarId}/events/{$o365EventId}";

            $response = Http::withToken($user->microsoft_token)
                ->delete($endpoint);

            if ($response->successful() || $response->status() === 404) {
                return true;
            }

            Log::error('Failed to delete O365 event', [
                'o365_event_id' => $o365EventId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exception deleting O365 event', [
                'o365_event_id' => $o365EventId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync all events for a user to O365 Calendar for a specific role
     */
    public function syncUserEvents(User $user, Role $role): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        if (! $this->refreshTokenIfNeeded($user)) {
            $results['errors']++;

            return $results;
        }

        // Get all events for the specific role
        $events = Event::whereHas('roles', function ($query) use ($role) {
            $query->where('roles.id', $role->id);
        })->get();

        foreach ($events as $event) {
            try {
                $o365EventId = $event->getO365EventIdForRole($role->id);

                if ($o365EventId) {
                    // Skip events that already exist
                    continue;
                } else {
                    // Create new event
                    $o365Event = $this->createEvent($event, $role);
                    if ($o365Event) {
                        $event->setO365EventIdForRole($role->id, $o365Event['id']);
                        if (isset($o365Event['@odata.etag'])) {
                            $event->setO365ChangeKeyForRole($role->id, $o365Event['@odata.etag']);
                        }
                        $results['created']++;
                    } else {
                        $results['errors']++;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync event to O365', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
                $results['errors']++;
            }
        }

        return $results;
    }
}
