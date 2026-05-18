<!DOCTYPE html>
<html>
<head>
    <title>CCS Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; padding: 20px; max-width: 1400px; margin: 0 auto; }
        .analytics-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; border-radius: 16px; text-align: center; }
        .kpi h3 { font-size: 28px; margin: 10px 0 5px; }
        .trend-up { color: #10b981; font-size: 14px; }
        .trend-down { color: #ef4444; font-size: 14px; }
    </style>
</head>
<body style="background:#f0f2f5; font-family:'Segoe UI',sans-serif; padding:20px;">
    <h1 style="margin-bottom:20px;">📊 CCS Analytics Dashboard</h1>
    
    <div class="kpi-row">
        <div class="kpi"><div>📈 Total Sit-ins</div><h3>1,284</h3><span class="trend-up">↑ 12% vs last month</span></div>
        <div class="kpi"><div>👥 Active Students</div><h3>347</h3><span class="trend-up">↑ 8%</span></div>
        <div class="kpi"><div>⏱️ Avg. Session</div><h3>2.4h</h3><span class="trend-up">↑ 5%</span></div>
        <div class="kpi"><div>⭐ Total Points</div><h3>47,280</h3><span class="trend-up">↑ 23%</span></div>
    </div>
    
    <div class="analytics-grid">
        <div class="analytics-card"><canvas id="weeklyChart"></canvas></div>
        <div class="analytics-card"><canvas id="labUtilizationChart"></canvas></div>
        <div class="analytics-card"><canvas id="hourlyChart"></canvas></div>
        <div class="analytics-card"><canvas id="courseChart2"></canvas></div>
    </div>

    <script>
        new Chart(document.getElementById('weeklyChart'), { type: 'line', data: { labels: ['Week 1','Week 2','Week 3','Week 4'], datasets: [{ label: 'Sit-ins', data: [245, 312, 398, 329], borderColor: '#3b82f6', tension: 0.4 }] } });
        new Chart(document.getElementById('labUtilizationChart'), { type: 'doughnut', data: { labels: ['Lab 1 (32%)','Lab 2 (45%)','Lab 3 (23%)'], datasets: [{ data: [32,45,23], backgroundColor: ['#3b82f6','#10b981','#f59e0b'] }] } });
        new Chart(document.getElementById('hourlyChart'), { type: 'bar', data: { labels: ['8AM','10AM','12PM','2PM','4PM','6PM'], datasets: [{ label: 'Students', data: [45, 78, 92, 67, 54, 32], backgroundColor: '#8b5cf6' }] } });
        new Chart(document.getElementById('courseChart2'), { type: 'polarArea', data: { labels: ['BSIT','BSCS','BSIS'], datasets: [{ data: [156, 89, 47], backgroundColor: ['#3b82f6','#10b981','#f59e0b'] }] } });
    </script>
</body>
</html>