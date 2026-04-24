/* --- adminDash.js --- */

window.logsBarChart = null;
window.logsPieChart = null;

function loadAllLogs() {
    fetch("getLogs.php")
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById("logsContainer");
        if (container) {
            container.innerHTML = data.map(log => `
                <div class="log-item" style="padding:8px; border-bottom:1px solid #ddd; margin-bottom:5px;">
                    <b>${log.username ?? 'Unknown'}</b><br>
                    <strong>Action:</strong> ${log.action}<br>
                    <strong>IP:</strong> ${log.ip_address || 'N/A'}<br>
                    <strong>Browser:</strong> ${log.user_agent ? log.user_agent.substring(0, 80) + (log.user_agent.length > 80 ? '…' : '') : 'N/A'}<br>
                    <strong>Page:</strong> ${log.page || 'N/A'}<br>
                    <strong>Time:</strong> <span style="color:gray;">${log.created_at}</span>
                </div>
            `).join('');
        }

        // Stats for charts (group by action)
        const stats = {};
        data.forEach(log => {
            stats[log.action] = (stats[log.action] || 0) + 1;
        });
        const statsArray = Object.keys(stats).map(key => ({ action: key, count: stats[key] }));

        updateCharts(statsArray);
    })
    .catch(err => console.error("Failed to load logs:", err));
}

function updateCharts(statsData) {
    const barCtx = document.getElementById('logsBarChart').getContext('2d');
    const pieCtx = document.getElementById('logsPieChart').getContext('2d');

    if (window.logsBarChart instanceof Chart) window.logsBarChart.destroy();
    if (window.logsPieChart instanceof Chart) window.logsPieChart.destroy();

    window.logsBarChart = new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: statsData.map(d => d.action),
            datasets: [{
                label: 'Count',
                data: statsData.map(d => d.count),
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision:0 } } }
        }
    });

    window.logsPieChart = new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: statsData.map(d => d.action),
            datasets: [{
                label: 'Count',
                data: statsData.map(d => d.count),
                backgroundColor: ['#36A2EB','#FF6384','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#C9CBCF']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}

window.onload = () => {
    loadAllLogs();
    setInterval(loadAllLogs, 5000);
};