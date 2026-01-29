<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>{{ $room['email'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            background: #667eea;
            padding: 10px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 14px; font-weight: bold; margin-bottom: 2px; }
        .header p { font-size: 11px; color: #666; }
        .header .status { font-size: 10px; color: #666; }
        .events {
            background: #fff;
            border-radius: 4px;
            padding: 8px;
        }
        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 6px;
        }
        .events-header h2 { font-size: 12px; font-weight: bold; }
        .btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 10px;
            cursor: pointer;
        }
        .btn:hover { background: #1d4ed8; }
        .event {
            border-left: 2px solid #2563eb;
            background: #f9fafb;
            padding: 4px 8px;
            margin-bottom: 4px;
            border-radius: 0 2px 2px 0;
        }
        .event.live { border-left-color: #10b981; }
        .event.past { opacity: 0.6; border-left-color: #9ca3af; }
        .event-title {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .badge {
            display: inline-block;
            padding: 0 4px;
            border-radius: 8px;
            font-size: 8px;
            font-weight: bold;
        }
        .badge.live { background: #10b981; color: white; }
        .badge.busy { background: #fca5a5; color: #991b1b; border: 1px solid #f87171; }
        .badge.tentative { background: #fde68a; color: #92400e; border: 1px solid #fbbf24; }
        .event-info {
            font-size: 10px;
            color: #4b5563;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .event-organizer {
            font-size: 9px;
            color: #6b7280;
            margin-top: 1px;
        }
        .no-events {
            text-align: center;
            padding: 20px;
            color: #9ca3af;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>{{ $room['owner']['name'] ?? $room['name'] }}</h1>
                <p>{{ $room['email'] }}</p>
            </div>
            <div class="status">
                Connected • {{ now()->format('M d, g:i A') }}
            </div>
        </div>

        <div class="events">
            <div class="events-header">
                <h2>Events ({{ count($events) }})</h2>
                <button class="btn" onclick="location.reload()">Refresh</button>
            </div>

            @if (count($events) === 0)
                <div class="no-events">No upcoming events</div>
            @else
                @foreach($events as $event)
                    @php
                        $start = \Carbon\Carbon::parse($event['start']['dateTime']);
                        $end = \Carbon\Carbon::parse($event['end']['dateTime']);
                        $isAllDay = $event['isAllDay'] ?? false;
                        $isPast = $end->isPast();
                        $isNow = $start->isPast() && $end->isFuture();
                        $status = $event['showAs'] ?? 'busy';
                    @endphp
                    
                    <div class="event @if($isNow) live @elseif($isPast) past @endif">
                        <div class="event-title">
                            <span>{{ $event['subject'] }}</span>
                            @if($isNow)
                                <span class="badge live">LIVE</span>
                            @endif
                            <span class="badge {{ $status }}">{{ strtoupper($status) }}</span>
                        </div>
                        
                        <div class="event-info">
                            <span>@if($isAllDay){{ $start->format('M d') }} - {{ $end->format('M d') }}@else{{ $start->format('M d, Y') }}@endif</span>
                            @if(!$isAllDay)
                                <span>{{ $start->format('g:iA') }} - {{ $end->format('g:iA') }}</span>
                                <span>({{ $end->diffInMinutes($start) }}m)</span>
                            @endif
                        </div>
                        
                        @if(isset($event['organizer']['emailAddress']))
                            <div class="event-organizer">
                                Organizer: {{ $event['organizer']['emailAddress']['name'] }}
                                @if(isset($event['attendees']) && count($event['attendees']) > 1)
                                    • {{ count($event['attendees']) }} attendees
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</body>
</html>
