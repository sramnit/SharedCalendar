<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office 365 Distribution Lists</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
        }
        .header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 28px;
            color: #1a202c;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header p {
            font-size: 14px;
            color: #718096;
        }
        .stats-bar {
            display: flex;
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        .stat {
            display: flex;
            flex-direction: column;
        }
        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-top: 4px;
        }
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-radius: 12px;
            padding: 16px 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 10px 40px 10px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }
        .filter-buttons {
            display: flex;
            gap: 8px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background: #edf2f7;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            user-select: none;
            position: relative;
        }
        th:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        th::after {
            content: '⇅';
            position: absolute;
            right: 16px;
            opacity: 0.5;
            font-size: 12px;
        }
        tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        tbody tr:hover {
            background: #f7fafc;
        }
        tbody tr:last-child {
            border-bottom: none;
        }
        td {
            padding: 16px;
            font-size: 14px;
            color: #2d3748;
        }
        .dl-name {
            font-weight: 600;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dl-email {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #edf2f7;
            color: #4a5568;
        }
        .badge.large {
            background: #fef5e7;
            color: #d97706;
        }
        .badge.medium {
            background: #e0f2fe;
            color: #0369a1;
        }
        .badge.small {
            background: #f0fdf4;
            color: #15803d;
        }
        .date {
            color: #4a5568;
            font-size: 13px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .empty-state h3 {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 8px;
        }
        .empty-state p {
            font-size: 14px;
        }
        .loading {
            text-align: center;
            padding: 40px;
        }
        .spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <svg class="icon" viewBox="0 0 24 24">
                    <path d="M16 17v2H2v-2s0-4 7-4 7 4 7 4m-3.5-9.5A3.5 3.5 0 1 0 9 11a3.5 3.5 0 0 0 3.5-3.5m3.44 5.5A5.32 5.32 0 0 1 18 17v2h4v-2s0-3.63-6.06-4M15 4a3.39 3.39 0 0 0 0 6.74 3.5 3.5 0 0 0 0-6.74z"/>
                </svg>
                Office 365 Distribution Lists
            </h1>
            <p>Manage and view all your organization's distribution lists</p>
            
            <div class="stats-bar">
                <div class="stat">
                    <span class="stat-label">Total DLs</span>
                    <span class="stat-value">{{ count($distributionLists) }}</span>
                </div>
                <div class="stat">
                    <span class="stat-label">Total Members</span>
                    <span class="stat-value">{{ array_sum(array_column($distributionLists, 'member_count')) }}</span>
                </div>
                <div class="stat">
                    <span class="stat-label">Avg Members/DL</span>
                    <span class="stat-value">{{ count($distributionLists) > 0 ? round(array_sum(array_column($distributionLists, 'member_count')) / count($distributionLists), 1) : 0 }}</span>
                </div>
            </div>
        </div>

        <div class="controls">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search distribution lists..." onkeyup="filterTable()">
                <svg class="search-icon icon" viewBox="0 0 24 24">
                    <path d="M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5C14,7 12,5 9.5,5Z"/>
                </svg>
            </div>
            
            <div class="filter-buttons">
                <button class="btn btn-primary" onclick="exportToCSV()">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M10,19L12,15H9V10H15V15L13,19H10Z"/>
                    </svg>
                    Export CSV
                </button>
                <button class="btn btn-secondary" onclick="location.reload()">
                    <svg class="icon" viewBox="0 0 24 24">
                        <path d="M17.65,6.35C16.2,4.9 14.21,4 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20C15.73,20 18.84,17.45 19.73,14H17.65C16.83,16.33 14.61,18 12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6C13.66,6 15.14,6.69 16.22,7.78L13,11H20V4L17.65,6.35Z"/>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        @if(count($distributionLists) === 0)
            <div class="table-container">
                <div class="empty-state">
                    <svg viewBox="0 0 24 24">
                        <path fill="currentColor" d="M16 17v2H2v-2s0-4 7-4 7 4 7 4m-3.5-9.5A3.5 3.5 0 1 0 9 11a3.5 3.5 0 0 0 3.5-3.5m3.44 5.5A5.32 5.32 0 0 1 18 17v2h4v-2s0-3.63-6.06-4M15 4a3.39 3.39 0 0 0 0 6.74 3.5 3.5 0 0 0 0-6.74z"/>
                    </svg>
                    <h3>No Distribution Lists Found</h3>
                    <p>There are no distribution lists available in your Office 365 account.</p>
                </div>
            </div>
        @else
            <div class="table-container">
                <table id="dlTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">Distribution List Name</th>
                            <th onclick="sortTable(1)">Member Count</th>
                            <th onclick="sortTable(2)">Last Modified</th>
                            <th onclick="sortTable(3)">Created Date</th>
                            <th onclick="sortTable(4)">Last Used</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($distributionLists as $dl)
                            <tr>
                                <td>
                                    <div class="dl-name">
                                        <svg class="icon" viewBox="0 0 24 24">
                                            <path fill="currentColor" d="M12,5.5A3.5,3.5 0 0,1 15.5,9A3.5,3.5 0 0,1 12,12.5A3.5,3.5 0 0,1 8.5,9A3.5,3.5 0 0,1 12,5.5M5,8C5.56,8 6.08,8.15 6.53,8.42C6.38,9.85 6.8,11.27 7.66,12.38C7.16,13.34 6.16,14 5,14A3,3 0 0,1 2,11A3,3 0 0,1 5,8M19,8A3,3 0 0,1 22,11A3,3 0 0,1 19,14C17.84,14 16.84,13.34 16.34,12.38C17.2,11.27 17.62,9.85 17.47,8.42C17.92,8.15 18.44,8 19,8M5.5,18.25C5.5,16.18 8.41,14.5 12,14.5C15.59,14.5 18.5,16.18 18.5,18.25V20H5.5V18.25M0,20V18.5C0,17.11 1.89,15.94 4.45,15.6C3.86,16.28 3.5,17.22 3.5,18.25V20H0M24,20H20.5V18.25C20.5,17.22 20.14,16.28 19.55,15.6C22.11,15.94 24,17.11 24,18.5V20Z"/>
                                        </svg>
                                        <div>
                                            {{ $dl['name'] }}
                                            <div class="dl-email">{{ $dl['email'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $count = $dl['member_count'];
                                        $badgeClass = 'small';
                                        if ($count > 50) $badgeClass = 'large';
                                        elseif ($count > 20) $badgeClass = 'medium';
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ $count }} members</span>
                                </td>
                                <td>
                                    <span class="date">
                                        @if($dl['last_modified_date'])
                                            {{ \Carbon\Carbon::parse($dl['last_modified_date'])->format('M d, Y') }}
                                            <br>
                                            <small style="color: #a0aec0;">{{ \Carbon\Carbon::parse($dl['last_modified_date'])->diffForHumans() }}</small>
                                        @else
                                            N/A
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span class="date">
                                        @if($dl['created_date'])
                                            {{ \Carbon\Carbon::parse($dl['created_date'])->format('M d, Y') }}
                                            <br>
                                            <small style="color: #a0aec0;">{{ \Carbon\Carbon::parse($dl['created_date'])->diffForHumans() }}</small>
                                        @else
                                            N/A
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span class="date">
                                        @if($dl['last_used'])
                                            {{ \Carbon\Carbon::parse($dl['last_used'])->format('M d, Y h:i A') }}
                                            <br>
                                            <small style="color: #a0aec0;">{{ \Carbon\Carbon::parse($dl['last_used'])->diffForHumans() }}</small>
                                        @else
                                            <span style="color: #a0aec0;">Not tracked</span>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script>
        // Filter table based on search input
        function filterTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('dlTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }

        // Sort table by column
        let sortDirection = {};
        function sortTable(columnIndex) {
            const table = document.getElementById('dlTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Toggle sort direction
            sortDirection[columnIndex] = !sortDirection[columnIndex];
            const ascending = sortDirection[columnIndex];
            
            rows.sort((a, b) => {
                let aValue = a.cells[columnIndex].textContent.trim();
                let bValue = b.cells[columnIndex].textContent.trim();
                
                // Handle numeric values (member count)
                if (columnIndex === 1) {
                    aValue = parseInt(aValue);
                    bValue = parseInt(bValue);
                }
                
                // Handle dates
                if (columnIndex === 2 || columnIndex === 3 || columnIndex === 4) {
                    aValue = aValue === 'N/A' || aValue === 'Not tracked' ? 0 : new Date(aValue).getTime();
                    bValue = bValue === 'N/A' || bValue === 'Not tracked' ? 0 : new Date(bValue).getTime();
                }
                
                if (aValue < bValue) return ascending ? -1 : 1;
                if (aValue > bValue) return ascending ? 1 : -1;
                return 0;
            });
            
            // Re-append rows in sorted order
            rows.forEach(row => tbody.appendChild(row));
        }

        // Export to CSV
        function exportToCSV() {
            const table = document.getElementById('dlTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            // Get headers
            const headers = Array.from(rows[0].querySelectorAll('th')).map(th => th.textContent.trim());
            csv.push(headers.join(','));
            
            // Get data rows
            for (let i = 1; i < rows.length; i++) {
                if (rows[i].style.display !== 'none') {
                    const cells = rows[i].querySelectorAll('td');
                    const row = Array.from(cells).map(cell => {
                        let text = cell.textContent.trim().replace(/\n/g, ' ').replace(/,/g, ';');
                        return `"${text}"`;
                    });
                    csv.push(row.join(','));
                }
            }
            
            // Create download
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'distribution-lists-' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
