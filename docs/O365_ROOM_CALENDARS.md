# Office 365 Room Calendar Access

This document explains how to access Microsoft 365 room calendars (meeting rooms, conference rooms, etc.) in the application.

## Overview

Room calendars in Microsoft 365 are special mailbox types that represent physical spaces. To access them programmatically, we use **Application Permissions** with the Microsoft Graph API instead of delegated user permissions.

## Azure AD Configuration

### Required API Permissions

Your Azure AD App Registration needs the following **Application permissions**:

1. **Calendars.Read** - Read calendars in all mailboxes
2. **User.Read.All** - Read all users' full profiles (needed to access room mailboxes)

#### Steps to Configure:

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to **Azure Active Directory** → **App registrations**
3. Select your app: **EventSchedule** (Client ID: `91ed54f9-d6d9-4d0b-b7aa-b4beb8b97320`)
4. Click **API permissions** in the left menu
5. Click **Add a permission**
6. Select **Microsoft Graph** → **Application permissions**
7. Search for and add:
   - `Calendars.Read`
   - `User.Read.All`
8. Click **Grant admin consent for [Your Organization]** (requires admin privileges)

### Authentication Flow

The room calendar access uses the **Client Credentials Flow** (OAuth 2.0):
- No user login required
- The app authenticates using Client ID + Client Secret
- Access token is obtained directly from Azure AD
- Suitable for server-to-server scenarios

## Usage

### Display Room Calendar

To view a room calendar and its events, visit:

```
GET /o365-calendar/room/{roomEmail}
```

**Example:**
```
https://your-app-url/o365-calendar/room/room.mexicocity@flydenver.com
```

**Response:**
```json
{
  "success": true,
  "room": {
    "id": "AAMkADU5...",
    "name": "Mexico City Conference Room",
    "email": "room.mexicocity@flydenver.com",
    "owner": {...}
  },
  "events": [
    {
      "id": "AAMkADU5...",
      "subject": "Team Meeting",
      "start": {
        "dateTime": "2026-01-29T14:00:00.0000000",
        "timeZone": "UTC"
      },
      "end": {
        "dateTime": "2026-01-29T15:00:00.0000000",
        "timeZone": "UTC"
      },
      "organizer": {
        "emailAddress": {
          "name": "John Doe",
          "address": "john.doe@flydenver.com"
        }
      }
    }
  ],
  "event_count": 5
}
```

## Code Implementation

### Service Layer

The `O365CalendarService` class includes three new methods:

#### 1. getRoomCalendar()
Fetches room calendar metadata:
```php
$service = app(O365CalendarService::class);
$room = $service->getRoomCalendar('room.mexicocity@flydenver.com');
```

#### 2. getRoomCalendarEvents()
Fetches events from the room calendar:
```php
$startDate = now();
$endDate = now()->addDays(30);
$events = $service->getRoomCalendarEvents(
    'room.mexicocity@flydenver.com',
    $startDate,
    $endDate
);
```

#### 3. getAppAccessToken() (protected)
Obtains app-only access token using client credentials flow:
```php
// Automatically called by getRoomCalendar() and getRoomCalendarEvents()
// Exchanges Client ID + Client Secret for an access token
```

### Controller

The `O365CalendarController` includes the `showRoom()` method:

```php
Route::get('/o365-calendar/room/{roomEmail}', [O365CalendarController::class, 'showRoom']);
```

## Testing

### 1. Verify Azure Permissions
```bash
# Check if your app has the required permissions granted
# Visit: https://portal.azure.com → Azure AD → App registrations → Your App → API permissions
```

### 2. Test Room Calendar Access
```bash
# Using curl
curl http://localhost:8000/o365-calendar/room/room.mexicocity@flydenver.com

# Or visit in browser:
http://localhost:8000/o365-calendar/room/room.mexicocity@flydenver.com
```

### 3. Check Logs
If errors occur, check Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

## Troubleshooting

### Error: "Access denied"
**Cause:** Application permissions not granted or admin consent not provided

**Solution:**
1. Verify permissions are added in Azure Portal
2. Click "Grant admin consent" button
3. Wait a few minutes for permissions to propagate

### Error: "Resource not found"
**Cause:** Room email address doesn't exist or is incorrect

**Solution:**
1. Verify the room exists in Microsoft 365
2. Check the email address spelling
3. Ensure the room has a calendar enabled

### Error: "Failed to get app access token"
**Cause:** Invalid Client ID, Client Secret, or Tenant ID

**Solution:**
1. Verify credentials in `.env` file:
   ```
   MICROSOFT_CLIENT_ID=91ed54f9-d6d9-4d0b-b7aa-b4beb8b97320
   MICROSOFT_CLIENT_SECRET=HWG8Q~63npk2DP6RLrqQscbPXlNDETxp1Xib-a9H
   MICROSOFT_TENANT_ID=79c62162-b85e-4b0e-a863-ebe7817ad70d
   ```
2. Regenerate client secret if expired

## Security Considerations

1. **Admin Consent Required:** Application permissions require tenant admin approval
2. **Broad Access:** These permissions grant access to ALL room calendars in the organization
3. **Client Secret Protection:** Never expose the client secret in client-side code
4. **Token Caching:** Consider caching app tokens (valid for ~1 hour) to reduce API calls

## Future Enhancements

Potential improvements:
- Cache room calendar data
- Support room booking/reservation
- Display room availability
- Integration with venue management
- Room resource filtering (capacity, equipment, location)

## References

- [Microsoft Graph API - Calendar](https://docs.microsoft.com/en-us/graph/api/resources/calendar)
- [Application Permissions](https://docs.microsoft.com/en-us/graph/auth-v2-service)
- [Room Resources in Microsoft 365](https://docs.microsoft.com/en-us/graph/api/resources/room)
