<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MailCore - System Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
        }

        .header .subtitle {
            color: #666;
            font-size: 16px;
        }

        .header .timestamp {
            color: #999;
            font-size: 14px;
            margin-top: 10px;
        }

        .overall-status {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .overall-status.healthy {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .overall-status.unhealthy {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .overall-status h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .overall-status .status-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .service-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .service-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .service-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .service-status.healthy {
            background: #d4edda;
            color: #155724;
        }

        .service-status.unhealthy {
            background: #f8d7da;
            color: #721c24;
        }

        .service-status.degraded {
            background: #fff3cd;
            color: #856404;
        }

        .service-status-icon {
            font-size: 16px;
        }

        .service-message {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .service-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #333;
            font-weight: 600;
            text-align: right;
        }

        .refresh-button {
            background: white;
            border: none;
            border-radius: 12px;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            color: #667eea;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
            display: block;
            margin: 0 auto;
        }

        .refresh-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }

        .auto-refresh {
            text-align: center;
            color: white;
            margin-top: 20px;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            color: white;
            margin-top: 40px;
            padding: 20px;
        }

        .footer a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🚀 MailCore System Status</h1>
            <div class="subtitle">Real-time monitoring of all services and infrastructure</div>
            <div class="timestamp">Last updated: {{ now()->format('Y-m-d H:i:s') }}</div>
        </div>

        <!-- Overall Status -->
        @php
            $allHealthy = collect($services)->every(fn($s) => $s['status'] === 'healthy');
        @endphp
        <div class="overall-status {{ $allHealthy ? 'healthy' : 'unhealthy' }}">
            <div class="status-icon">{{ $allHealthy ? '✅' : '⚠️' }}</div>
            <h2>{{ $allHealthy ? 'All Systems Operational' : 'Some Services Degraded' }}</h2>
            <p>{{ count($services) }} services monitored</p>
        </div>

        <!-- Services Grid -->
        <div class="services-grid">
            @foreach($services as $key => $service)
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-name">{{ $service['name'] }}</div>
                        <div class="service-status {{ $service['status'] }}">
                            <span class="service-status-icon">
                                @if($service['status'] === 'healthy')
                                    ✓
                                @elseif($service['status'] === 'unhealthy')
                                    ✗
                                @else
                                    ⚠
                                @endif
                            </span>
                            {{ ucfirst($service['status']) }}
                        </div>
                    </div>

                    <div class="service-message">
                        {{ $service['message'] }}
                    </div>

                    @if(!empty($service['details']))
                        <div class="service-details">
                            @foreach($service['details'] as $label => $value)
                                <div class="detail-row">
                                    <span class="detail-label">{{ ucwords(str_replace('_', ' ', $label)) }}</span>
                                    <span class="detail-value">
                                        @if(is_bool($value))
                                            {{ $value ? 'Yes' : 'No' }}
                                        @elseif(is_array($value))
                                            {{ json_encode($value) }}
                                        @else
                                            {{ $value }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Refresh Button -->
        <button class="refresh-button" onclick="location.reload()">
            🔄 Refresh Status
        </button>

        <div class="auto-refresh">
            Page auto-refreshes every 60 seconds
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>MailCore v1.0 | <a href="/admin">Admin Panel</a> | <a href="/api/health">API Health Check</a></p>
        </div>
    </div>

    <script>
        // Auto-refresh every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>
