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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Ultra Responsive Modern Dashboard - Smartwatch to 8K */
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        .dashboard-container {
            padding: clamp(0.5rem, 2vw, 2.5rem);
            max-width: min(100%, 1600px);
            margin: 0 auto;
        }
        
        .dashboard-title {
            font-size: clamp(1.2rem, 3vw + 0.5rem, 3rem);
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: clamp(1rem, 2vw, 2rem);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
        }
        
        /* Ultra Responsive Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
            gap: clamp(0.75rem, 1.5vw, 1.5rem);
            margin-bottom: clamp(1.5rem, 3vw, 3rem);
        }
        
        /* Modern Colorful Card Design */
        .stat-card {
            color: white;
            border-radius: clamp(12px, 1.5vw, 20px);
            padding: clamp(1rem, 2vw, 2rem);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            min-height: clamp(100px, 12vw, 140px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }
        
        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.2);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }
        
        .stat-card h3 {
            font-size: clamp(0.75rem, 1.5vw + 0.2rem, 1.2rem);
            font-weight: 600;
            margin-bottom: clamp(0.5rem, 1vw, 1rem);
            opacity: 0.95;
            z-index: 2;
            position: relative;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card p {
            font-size: clamp(1.5rem, 4vw + 0.5rem, 3rem);
            font-weight: 800;
            margin: 0;
            z-index: 2;
            position: relative;
            text-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .stat-card .material-icons {
            position: absolute;
            top: 50%;
            right: clamp(0.5rem, 2vw, 1.5rem);
            transform: translateY(-50%);
            font-size: clamp(2.5rem, 6vw, 5rem);
            opacity: 0.15;
            z-index: 1;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .material-icons {
            opacity: 0.25;
            transform: translateY(-50%) scale(1.1) rotate(5deg);
        }
        
        /* Colorful Modern Gradients */
        .stat-card:nth-child(1) {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        /* Ultra Responsive Charts Container */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
            gap: clamp(0.75rem, 1.5vw, 1.5rem);
        }
        
        .chart-card {
            background: white;
            border-radius: clamp(12px, 1.5vw, 20px);
            padding: clamp(1rem, 2vw, 2rem);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            min-height: clamp(300px, 40vw, 450px);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .chart-card:hover {
            box-shadow: 0 12px 36px rgba(102, 126, 234, 0.15);
            transform: translateY(-3px);
        }
        
        .chart-card h3 {
            font-size: clamp(1rem, 2vw + 0.2rem, 1.5rem);
            font-weight: 600;
            margin-bottom: clamp(1rem, 2vw, 1.5rem);
            color: #1a1a2e;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .chart-wrapper {
            flex: 1;
            position: relative;
            min-height: clamp(250px, 35vw, 380px);
        }
        
        /* Smartwatch (240px - 320px) */
        @media (max-width: 320px) {
            .dashboard-container {
                padding: 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .stat-card {
                min-height: 90px;
                padding: 0.75rem;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .chart-card {
                min-height: 250px;
            }
        }
        
        /* Mobile Portrait (321px - 480px) */
        @media (min-width: 321px) and (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
        
        /* Mobile Landscape & Small Tablets (481px - 768px) */
        @media (min-width: 481px) and (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
        }
        
        /* Tablets (769px - 1024px) */
        @media (min-width: 769px) and (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Laptops & Desktops (1025px - 1920px) */
        @media (min-width: 1025px) and (max-width: 1920px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .charts-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Full HD & 2K (1921px - 2560px) */
        @media (min-width: 1921px) and (max-width: 2560px) {
            .dashboard-container {
                max-width: 1800px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .charts-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* 4K (2561px - 3840px) */
        @media (min-width: 2561px) and (max-width: 3840px) {
            .dashboard-container {
                max-width: 2400px;
                padding: 3rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 2rem;
            }
            
            .charts-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 2rem;
            }
        }
        
        /* 8K (3841px+) */
        @media (min-width: 3841px) {
            .dashboard-container {
                max-width: 4800px;
                padding: 4rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 3rem;
            }
            
            .charts-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 3rem;
            }
            
            .stat-card {
                min-height: 200px;
            }
            
            .chart-card {
                min-height: 600px;
            }
        }
        
        /* Animation for cards */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-card, .chart-card {
            animation: fadeInUp 0.6s ease-out backwards;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .chart-card:nth-child(1) { animation-delay: 0.5s; }
        .chart-card:nth-child(2) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1 class="dashboard-title"><?php echo translate('dashboard_title'); ?></h1>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo translate('total_clients'); ?></h3>
                <p><?php echo $total_clients; ?></p>
                <span class="material-icons">people</span>
            </div>
            <div class="stat-card">
                <h3><?php echo translate('connected'); ?></h3>
                <p><?php echo $total_connected; ?></p>
                <span class="material-icons">wifi</span>
            </div>
            <div class="stat-card">
                <h3><?php echo translate('disconnected'); ?></h3>
                <p><?php echo $total_disconnected; ?></p>
                <span class="material-icons">wifi_off</span>
            </div>
            <div class="stat-card">
                <h3><?php echo translate('banned'); ?></h3>
                <a href="banned_users.php" style="text-decoration: none; color: white; display: block;">
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
        // Responsive font size calculation
        function getResponsiveFontSize() {
            const width = window.innerWidth;
            if (width <= 320) return 10;
            if (width <= 480) return 11;
            if (width <= 768) return 12;
            if (width <= 1024) return 13;
            if (width <= 1920) return 14;
            if (width <= 2560) return 16;
            if (width <= 3840) return 18;
            return 20; // 8K
        }

        // Chart.js Global Configuration with Poppins
        Chart.defaults.font.family = "'Poppins', sans-serif";
        Chart.defaults.font.size = getResponsiveFontSize();
        Chart.defaults.color = '#4a5568';

        // Modern color palette
        const modernColors = {
            purple: ['rgba(102, 126, 234, 0.9)', 'rgba(118, 75, 162, 0.9)'],
            pink: ['rgba(240, 147, 251, 0.9)', 'rgba(245, 87, 108, 0.9)'],
            cyan: ['rgba(79, 172, 254, 0.9)', 'rgba(0, 242, 254, 0.9)'],
            green: ['rgba(67, 233, 123, 0.9)', 'rgba(56, 249, 215, 0.9)']
        };

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
                        modernColors.green[0],
                        modernColors.pink[1]
                    ],
                    borderColor: [
                        'rgba(255, 255, 255, 0.8)',
                        'rgba(255, 255, 255, 0.8)'
                    ],
                    borderWidth: 3,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10,
                        left: 10,
                        right: 10
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: window.innerWidth <= 768 ? 10 : 15,
                            font: {
                                size: getResponsiveFontSize(),
                                weight: '600'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: window.innerWidth <= 768 ? 10 : 12,
                        titleFont: {
                            size: getResponsiveFontSize() + 1,
                            weight: '600'
                        },
                        bodyFont: {
                            size: getResponsiveFontSize()
                        },
                        cornerRadius: 8,
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
                        modernColors.purple[0],
                        modernColors.cyan[0],
                        modernColors.pink[1],
                        modernColors.green[0]
                    ],
                    borderColor: [
                        'rgba(255, 255, 255, 0.5)',
                        'rgba(255, 255, 255, 0.5)',
                        'rgba(255, 255, 255, 0.5)',
                        'rgba(255, 255, 255, 0.5)'
                    ],
                    borderWidth: 2,
                    borderRadius: window.innerWidth <= 768 ? 6 : 8,
                    hoverBackgroundColor: [
                        modernColors.purple[1],
                        modernColors.cyan[1],
                        modernColors.pink[0],
                        modernColors.green[1]
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: window.innerWidth <= 768 ? 10 : 12,
                        titleFont: {
                            size: getResponsiveFontSize() + 1,
                            weight: '600'
                        },
                        bodyFont: {
                            size: getResponsiveFontSize()
                        },
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: getResponsiveFontSize(),
                                weight: '500'
                            }
                        },
                        grid: {
                            color: 'rgba(102, 126, 234, 0.1)',
                            lineWidth: 1
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: getResponsiveFontSize(),
                                weight: '500'
                            }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            Chart.defaults.font.size = getResponsiveFontSize();
        });
    });
    </script>

<?php include 'footer.php'; ?>
