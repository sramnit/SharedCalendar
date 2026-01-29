# O365 Calendar Integration - Phase 1 Complete

## ✅ Completed Tasks

### Configuration
- ✅ Added Microsoft OAuth configuration to `config/services.php`
- ✅ Credentials configured:
  - Client ID: `91ed54f9-d6d9-4d0b-b7aa-b4beb8b97320`
  - Tenant ID: `79c62162-b85e-4b0e-a863-ebe7817ad70d`
  - Secret Value: `HWG8Q~63npk2DP6RLrqQscbPXlNDETxp1Xib-a9H`

### Database Migrations
Created 3 migration files:
- ✅ `2026_01_29_000001_add_o365_fields_to_users_table.php`
  - Adds: `microsoft_id`, `microsoft_token`, `microsoft_refresh_token`, `microsoft_token_expires_at`, `microsoft_calendar_id`
- ✅ `2026_01_29_000002_add_o365_fields_to_roles_table.php`
  - Adds: `o365_calendar_id`, `o365_calendar_name`, `o365_webhook_subscription_id`, `o365_webhook_expires_at`, `o365_sync_direction`
- ✅ `2026_01_29_000003_add_o365_fields_to_event_role_table.php`
  - Adds: `o365_event_id`, `o365_event_change_key`

### Model Updates
- ✅ **User Model** - Added O365 fields to `$fillable`, `$hidden`, `$casts` and added `hasO365Connected()` method
- ✅ **Role Model** - Added O365 fields to `$fillable`, `$casts` and added helper methods:
  - `getO365CalendarId()`
  - `hasO365Settings()`
  - `syncsToO365()`
  - `syncsFromO365()`
  - `getO365SyncDirectionLabel()`
- ✅ **Event Model** - Added O365 helper methods:
  - `getO365EventIdForRole($roleId)`
  - `setO365EventIdForRole($roleId, $o365EventId)`
  - `getO365ChangeKeyForRole($roleId)`
  - `setO365ChangeKeyForRole($roleId, $changeKey)`
  - `hasO365EventForRole($roleId)`
  - `syncToO365Calendar($action)`

## 📦 Next Steps

### 1. Install Dependencies
Run in your terminal (requires Composer):
```bash
composer require microsoft/microsoft-graph
```

### 2. Run Migrations
```bash
php artisan migrate
```

### 3. Add Environment Variables
Add to your `.env` file:
```env
MICROSOFT_CLIENT_ID=91ed54f9-d6d9-4d0b-b7aa-b4beb8b97320
MICROSOFT_CLIENT_SECRET=HWG8Q~63npk2DP6RLrqQscbPXlNDETxp1Xib-a9H
MICROSOFT_TENANT_ID=79c62162-b85e-4b0e-a863-ebe7817ad70d
MICROSOFT_REDIRECT_URI=https://yourdomain.com/o365-calendar/callback
MICROSOFT_WEBHOOK_SECRET=your_random_webhook_secret_here
```

### 4. Azure App Registration - Redirect URI
In your Azure App Registration, add the redirect URI:
- `https://yourdomain.com/o365-calendar/callback` (production)
- `http://localhost:8000/o365-calendar/callback` (development)

### 5. Azure App Registration - API Permissions
Ensure these Microsoft Graph permissions are granted:
- `Calendars.ReadWrite` (Delegated)
- `User.Read` (Delegated)
- `offline_access` (Delegated) - for refresh token

## 🎯 Phase 2: Authentication & Service Layer

Ready to proceed with:
1. Create `O365CalendarService` - OAuth & Graph API interactions
2. Create `O365CalendarController` - OAuth flow (redirect/callback/disconnect)
3. Add routes for O365 calendar
4. Test OAuth connection

Would you like to continue with Phase 2?
