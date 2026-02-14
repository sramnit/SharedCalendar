<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Room Calendar Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Colfax', Arial, sans-serif;
            font-size: 9px;
            background: #54585A;
            padding: 5px;
        }
        .container { max-width: 1900px; margin: 0 auto; }
        .dashboard-header {
            background: white;
            color: #333;
            padding: 4px 8px;
            border-radius: 4px;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-header .left-section {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dashboard-header .logo {
            height: 24px;
            width: 24px;
        }
        .dashboard-header h1 { font-size: 12px; font-weight: bold; }
        .dashboard-header .meta { font-size: 7.5px; color: rgba(0,0,0,0.6); }
        .btn {
            background: #6C2DA7;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 7.5px;
            cursor: pointer;
        }
        .btn:hover { background: #5a2489; }
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
        }
        @media (max-width: 1600px) {
            .rooms-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 1200px) {
            .rooms-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .rooms-grid {
                grid-template-columns: 1fr;
            }
        }
        .room-card {
            background: #fff;
            border-radius: 2px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: 48vh;
            border: 1px solid #000;
        }
        .room-header {
            background: #6C2DA7;
            padding: 2px;
            color: white;
        }
        .room-header h2 { font-size: 10.5px; font-weight: bold; margin-bottom: 2px; }
        .room-header p { font-size: 7.5px; opacity: 0.9; }
        .room-header .status { 
            font-size: 6.75px; 
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .events-container {
            padding: 1px;
            overflow-y: auto;
            flex: 1;
        }
        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 6px;
        }
        .events-header h3 { font-size: 8.25px; font-weight: bold; }
        .event {
            background: #f9fafb;
            border-left: 2px solid #3b82f6;
            border-radius: 0 2px 2px 0;
            padding: 1px;
            margin-bottom: 1px;
        }
        .event.live { border-left-color: #10b981; background: #ecfdf5; }
        .event.past { border-left-color: #9ca3af; opacity: 0.6; }
        .event-title {
            font-size: 8.25px;
            font-weight: 600;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        .badge {
            font-size: 6px;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge.live { background: #10b981; color: white; }
        .badge.busy { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .badge.tentative { background: #fefce8; color: #854d0e; border: 1px solid #fef08a; }
        .badge.free { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .badge.oof { background: #faf5ff; color: #6b21a8; border: 1px solid #e9d5ff; }
        .event-info {
            font-size: 6.75px;
            color: #666;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 3px;
        }
        .event-organizer {
            font-size: 6px;
            color: #888;
        }
        .no-events {
            text-align: center;
            padding: 20px;
            color: #9ca3af;
            font-size: 8.25px;
        }
        .error-message {
            background: #fef2f2;
            color: #991b1b;
            padding: 8px;
            border-radius: 3px;
            font-size: 5.625px;
            margin: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <div class="left-section">
                <img src="{{ asset('images/den_logo.jpg') }}" alt="DEN" class="logo">
                <div>
                    <h1>DEN CEEA EVENT SPACE</h1>
                    <p class="meta">{{ count($rooms) }} rooms • Updated {{ now()->setTimezone('America/Denver')->format('M d, Y g:i A') }}</p>
                </div>
            </div>
            <button class="btn" onclick="location.reload()">Refresh All</button>
        </div>

        <div class="rooms-grid">
            @foreach($rooms as $roomData)
                <div class="room-card">
                    <div class="room-header">
                        <h2>{{ $roomData['display_name'] ?? $roomData['room']['owner']['name'] ?? $roomData['room']['name'] ?? 'Unknown Room' }}</h2>
                        <p>{{ $roomData['email'] }}</p>
                        <div class="status">
                            @if(isset($roomData['error']))
                                Error loading calendar
                            @else
                                Connected • {{ count($roomData['events']) }} events
                            @endif
                        </div>
                    </div>

                    <div class="events-container">
                        @if(isset($roomData['error']))
                            <div class="error-message">
                                Failed to load calendar: {{ $roomData['error'] }}
                            </div>
                        @elseif(count($roomData['events']) === 0)
                            <div class="no-events">No upcoming events</div>
                        @else
                            @foreach($roomData['events'] as $event)
                                @php
                                    $start = \Carbon\Carbon::parse($event['start']['dateTime'])->setTimezone('America/Denver');
                                    $end = \Carbon\Carbon::parse($event['end']['dateTime'])->setTimezone('America/Denver');
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
            @endforeach
        </div>
    </div>
</body>
</html>
