<?php
// Start session
session_start();

// Include the utility functions
require_once 'utils.php';

// Check if the user is logged in and is admin, otherwise redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header('location: login.php');
    exit;
}

// Include the database connection file
require_once 'db_config.php';

// Fetch data for the dashboard
// Total Clients (only regular, non-banned users who are not reseller clients)
$total_clients_stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "user" AND banned = 0 AND reseller_id IS NULL');
$total_clients = $total_clients_stmt->fetchColumn();

// Total Connected (only regular, non-banned users who are not reseller clients)
$total_connected_stmt = $pdo->query('SELECT COUNT(DISTINCT user_id) FROM vpn_sessions JOIN users ON vpn_sessions.user_id = users.id WHERE vpn_sessions.end_time IS NULL AND users.role = \'user\' AND users.banned = 0 AND users.reseller_id IS NULL');
$total_connected = $total_connected_stmt->fetchColumn();

// Total Disconnected
$total_disconnected = $total_clients - $total_connected;

// Total Banned (only regular users who are not reseller clients)
$total_banned_stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE banned = 1 AND role = "user" AND reseller_id IS NULL');
$total_banned = $total_banned_stmt->fetchColumn();

// Data for charts
$connection_data = [$total_connected, $total_disconnected];
$user_stats_data = [$total_clients, $total_connected, $total_disconnected, $total_banned];

// Translated chart labels
$chart_labels_connection = [translate('connected'), translate('disconnected')];
$chart_labels_user_stats = [translate('total_clients'), translate('connected'), translate('disconnected'), translate('banned')];


include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN Dashboard</title>
    <style>
        /* Modern Dashboard Styles with Poppins */
        .dashboard-container {
            padding: clamp(16px, 2vw, 40px);
            max-width: clamp(100%, 95vw, 1600px);
            margin: 0 auto;
        }

        .dashboard-title {
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            font-weight: 700;
            color: var(--dark);
            margin-bottom: clamp(20px, 3vw, 40px);
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(clamp(220px, 25vw, 300px), 1fr));
            gap: clamp(16px, 2vw, 24px);
            margin-bottom: clamp(24px, 3vw, 40px);
        }

        .stat-card {
            color: white;
            border-radius: clamp(12px, 2vw, 20px);
            padding: clamp(20px, 3vw, 32px);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            min-height: clamp(120px, 15vw, 180px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.15) 0%, transparent 50%);
            pointer-events: none;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(10%, 10%); }
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
        }

        .stat-card h3 {
            font-size: clamp(0.9rem, 2vw, 1.2rem);
            font-weight: 600;
            margin-bottom: clamp(8px, 1.5vw, 12px);
            opacity: 0.95;
            position: relative;
            z-index: 1;
            letter-spacing: 0.5px;
        }

        .stat-card p {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
            line-height: 1.1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .stat-card a {
            text-decoration: none;
            color: white;
            display: block;
        }

        .stat-card .material-icons {
            position: absolute;
            top: 50%;
            right: clamp(16px, 2.5vw, 28px);
            transform: translateY(-50%);
            font-size: clamp(3rem, 6vw, 5rem);
            opacity: 0.25;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .stat-card:hover .material-icons {
            opacity: 0.35;
            transform: translateY(-50%) scale(1.1);
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(clamp(280px, 35vw, 500px), 1fr));
            gap: clamp(16px, 2vw, 24px);
        }

        .chart-card {
            background: white;
            border-radius: clamp(12px, 2vw, 20px);
            padding: clamp(20px, 3vw, 32px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            min-height: clamp(300px, 40vw, 450px);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .chart-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: clamp(12px, 2vw, 20px) clamp(12px, 2vw, 20px) 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
        }

        .chart-card:hover::before {
            opacity: 1;
        }

        .chart-card h3 {
            font-size: clamp(1.1rem, 2.5vw, 1.4rem);
            font-weight: 600;
            margin-bottom: clamp(16px, 2vw, 24px);
            color: var(--dark);
            letter-spacing: -0.5px;
            position: relative;
        }

        .chart-wrapper {
            flex: 1;
            position: relative;
            min-height: 0;
        }

        /* Ultra Small Devices (Smart Watch) - < 320px */
        @media (max-width: 319px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 12px;
                min-height: 80px;
            }

            .stat-card h3 {
                font-size: 0.7rem;
            }

            .stat-card p {
                font-size: 1.5rem;
            }

            .stat-card .material-icons {
                font-size: 1.5rem;
                right: 10px;
            }

            .charts-container {
                grid-template-columns: 1fr;
            }

            .chart-card {
                padding: 12px;
                min-height: 200px;
            }

            .chart-card h3 {
                font-size: 0.85rem;
            }
        }

        /* Extra Small Devices (Small Phones) - 320px - 480px */
        @media (min-width: 320px) and (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: clamp(16px, 4vw, 24px);
                min-height: clamp(100px, 20vw, 140px);
            }

            .stat-card h3 {
                font-size: clamp(0.85rem, 2vw, 1rem);
            }

            .stat-card p {
                font-size: clamp(1.8rem, 5vw, 2.5rem);
            }

            .stat-card .material-icons {
                font-size: clamp(2rem, 6vw, 3rem);
                right: clamp(12px, 2.5vw, 20px);
            }

            .charts-container {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .chart-card {
                padding: clamp(14px, 3vw, 20px);
                min-height: clamp(240px, 45vw, 320px);
            }

            .chart-card h3 {
                font-size: clamp(0.95rem, 2.5vw, 1.1rem);
            }
        }

        /* Small Devices (Large Phones) - 481px - 640px */
        @media (min-width: 481px) and (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 14px;
            }

            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        /* Medium Devices (Tablets) - 641px - 768px */
        @media (min-width: 641px) and (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .charts-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Landscape Mobile Optimizations */
        @media (max-height: 500px) and (orientation: landscape) {
            .stat-card {
                min-height: 90px;
                padding: 12px;
            }

            .stat-card h3 {
                margin-bottom: 4px;
            }

            .stat-card p {
                font-size: 1.8rem;
            }

            .chart-card {
                min-height: 220px;
                padding: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1 class="dashboard-title"><?php echo translate('dashboard_title'); ?></h1>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="background: var(--primary-gradient);">
                <h3><?php echo translate('total_clients'); ?></h3>
                <p><?php echo $total_clients; ?></p>
                <span class="material-icons">people</span>
            </div>
            <div class="stat-card" style="background: var(--success-gradient);">
                <h3><?php echo translate('connected'); ?></h3>
                <p><?php echo $total_connected; ?></p>
                <span class="material-icons">wifi</span>
            </div>
            <div class="stat-card" style="background: var(--secondary-gradient);">
                <h3><?php echo translate('disconnected'); ?></h3>
                <p><?php echo $total_disconnected; ?></p>
                <span class="material-icons">wifi_off</span>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #1e293b 0%, #475569 100%);">
                <h3><?php echo translate('banned'); ?></h3>
                <a href="banned_users.php" style="text-decoration: none; color: white;">
                    <p><?php echo $total_banned; ?></p>
                </a>
                <span class="material-icons">block</span>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-container">
            <!-- Connection Status Chart -->
            <div class="chart-card">
                <h3><?php echo translate('connection_status'); ?></h3>
                <div class="chart-wrapper">
                    <canvas id="connectionStatusChart"></canvas>
                </div>
            </div>

            <!-- User Statistics Chart -->
            <div class="chart-card">
                <h3><?php echo translate('user_statistics'); ?></h3>
                <div class="chart-wrapper">
                    <canvas id="userStatsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Chart.js Global Configuration with Poppins font
        Chart.defaults.font.family = 'Poppins, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        Chart.defaults.font.size = clamp(12px, 1.5vw, 14px);
        Chart.defaults.color = '#64748b';
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;

        // Connection Status Doughnut Chart
        const connectionCtx = document.getElementById('connectionStatusChart').getContext('2d');
        new Chart(connectionCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($chart_labels_connection); ?>,
                datasets: [{
                    label: '<?php echo translate('connection_status'); ?>',
                    data: <?php echo json_encode($connection_data); ?>,
                    backgroundColor: [
                        'rgba(76, 201, 240, 0.8)', // success
                        'rgba(247, 37, 133, 0.8)'  // danger
                    ],
                    borderColor: [
                        '#4cc9f0',
                        '#f72585'
                    ],
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        bottom: 20
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });

        // User Statistics Bar Chart
        const userStatsCtx = document.getElementById('userStatsChart').getContext('2d');
        new Chart(userStatsCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels_user_stats); ?>,
                datasets: [{
                    label: 'User Count',
                    data: <?php echo json_encode($user_stats_data); ?>,
                    backgroundColor: [
                        'rgba(67, 97, 238, 0.8)',  // primary
                        'rgba(76, 201, 240, 0.8)', // success
                        'rgba(248, 150, 30, 0.8)', // warning
                        'rgba(33, 37, 41, 0.8)'   // dark
                    ],
                    borderColor: [
                        '#4361ee',
                        '#4cc9f0',
                        '#f8961e',
                        '#212529'
                    ],
                    borderWidth: 2,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    });
    </script>

<?php include 'footer.php'; ?>
