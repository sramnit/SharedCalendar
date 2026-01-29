<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Role;
use App\Services\O365CalendarService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncEventToO365Calendar implements ShouldQueue
{
    use Queueable;

    protected $event;
    protected $role;
    protected $action; // 'create', 'update', 'delete'

    /**
     * Create a new job instance.
     */
    public function __construct(Event $event, Role $role, string $action = 'create')
    {
        $this->event = $event;
        $this->role = $role;
        $this->action = $action;
    }

    /**
     * Execute the job.
     */
    public function handle(O365CalendarService $o365CalendarService): void
    {
        try {
            $user = $this->role->user;

            if (! $user || ! $user->hasO365Connected()) {
                Log::warning('User does not have O365 Calendar connected', [
                    'user_id' => $user->id ?? null,
                    'event_id' => $this->event->id,
                    'role_id' => $this->role->id,
                ]);

                return;
            }

            // Ensure user has valid token before syncing
            if (! $o365CalendarService->ensureValidToken($user)) {
                Log::error('Failed to refresh O365 token for event sync', [
                    'user_id' => $user->id,
                    'event_id' => $this->event->id,
                ]);

                return;
            }

            switch ($this->action) {
                case 'create':
                    $this->createEvent($o365CalendarService);
                    break;
                case 'update':
                    $this->updateEvent($o365CalendarService);
                    break;
                case 'delete':
                    $this->deleteEvent($o365CalendarService);
                    break;
            }

        } catch (\Exception $e) {
            Log::error('Failed to sync event to O365 Calendar', [
                'event_id' => $this->event->id,
                'role_id' => $this->role->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);

            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Create event in O365 Calendar
     */
    private function createEvent(O365CalendarService $o365CalendarService): void
    {
        $o365Event = $o365CalendarService->createEvent($this->event, $this->role);

        if ($o365Event) {
            $this->event->setO365EventIdForRole($this->role->id, $o365Event['id']);
            
            if (isset($o365Event['@odata.etag'])) {
                $this->event->setO365ChangeKeyForRole($this->role->id, $o365Event['@odata.etag']);
            }

            Log::info('Event created in O365 Calendar', [
                'event_id' => $this->event->id,
                'o365_event_id' => $o365Event['id'],
                'calendar_id' => $this->role->getO365CalendarId(),
            ]);
        } else {
            Log::warning('Failed to create event in O365 Calendar (no event returned)', [
                'event_id' => $this->event->id,
                'role_id' => $this->role->id,
                'event_name' => $this->event->name,
                'has_start_date' => ! empty($this->event->starts_at),
            ]);
        }
    }

    /**
     * Update event in O365 Calendar
     */
    private function updateEvent(O365CalendarService $o365CalendarService): void
    {
        $o365EventId = $this->event->getO365EventIdForRole($this->role->id);

        if (! $o365EventId) {
            // If no O365 event ID for this role, create a new event
            $this->createEvent($o365CalendarService);

            return;
        }

        $o365Event = $o365CalendarService->updateEvent(
            $this->event,
            $o365EventId,
            $this->role
        );

        if ($o365Event) {
            if (isset($o365Event['@odata.etag'])) {
                $this->event->setO365ChangeKeyForRole($this->role->id, $o365Event['@odata.etag']);
            }

            Log::info('Event updated in O365 Calendar', [
                'event_id' => $this->event->id,
                'o365_event_id' => $o365EventId,
            ]);
        }
    }

    /**
     * Delete event from O365 Calendar
     */
    private function deleteEvent(O365CalendarService $o365CalendarService): void
    {
        $o365EventId = $this->event->getO365EventIdForRole($this->role->id);

        if (! $o365EventId) {
            Log::warning('No O365 event ID found for deletion', [
                'event_id' => $this->event->id,
                'role_id' => $this->role->id,
            ]);

            return;
        }

        $calendarId = $this->role->getO365CalendarId();
        $user = $this->role->user;

        $success = $o365CalendarService->deleteEvent($o365EventId, $calendarId, $user);

        if ($success) {
            Log::info('Event deleted from O365 Calendar', [
                'event_id' => $this->event->id,
                'o365_event_id' => $o365EventId,
            ]);
        }
    }

    /**
     * Helper method to dispatch the job
     */
    public static function dispatchSync(Event $event, Role $role, string $action = 'create'): void
    {
        dispatch(new static($event, $role, $action));
    }
}
