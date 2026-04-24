/* --- adminDash.js --- */

// Chart.js instances
window.logsBarChart = null;
window.logsPieChart = null;

// Load all logs and update charts

function loadAllLogs() {
    fetch("getLogs.php")
    .then(res => res.json())
    .then(data => {
        // Display logs
        const container = document.getElementById("logsContainer");
        if (container) {
            container.innerHTML = data.map(log => `
                <div class="log-item" style="padding:5px; border-bottom:1px solid #ddd;">
                    <b>${log.username ?? 'Unknown'}</b>: ${log.action}
                    <br><span style="font-size:0.8em; color:gray;">${log.created_at}</span>
                </div>
            `).join('');
        }

        // Compute stats for charts (count per action)
        const stats = {};
        data.forEach(log => {
            stats[log.action] = (stats[log.action] || 0) + 1;
        });

        const statsArray = Object.keys(stats).map(key => ({ action: key, count: stats[key] }));

        updateCharts(statsArray);
    })
    .catch(err => console.error("Failed to load logs:", err));
}

// Create or update charts
function updateCharts(statsData) {
    const barCtx = document.getElementById('logsBarChart').getContext('2d');
    const pieCtx = document.getElementById('logsPieChart').getContext('2d');

    // Destroy previous instances if they exist
    if (window.logsBarChart instanceof Chart) window.logsBarChart.destroy();
    if (window.logsPieChart instanceof Chart) window.logsPieChart.destroy();

    // Bar chart
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
            scales: {
                y: { beginAtZero: true, ticks: { precision:0 } }
            }
        }
    });

    // Pie chart
    window.logsPieChart = new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: statsData.map(d => d.action),
            datasets: [{
                label: 'Count',
                data: statsData.map(d => d.count),
                backgroundColor: [
                    '#36A2EB','#FF6384','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#C9CBCF'
                ]
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}

// Initialize page
window.onload = () => {
    loadAllLogs();
    // Refresh every 5 seconds
    setInterval(loadAllLogs, 5000);
};