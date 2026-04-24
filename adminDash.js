// adminDash.js

window.logsBarChart = null;
window.logsPieChart = null;

function truncate(str, maxLen = 50) {
    if (!str) return 'Unknown';
    return str.length > maxLen ? str.substring(0, maxLen) + '…' : str;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function loadAllLogs() {
    fetch("getLogs.php")
        .then(res => res.json())
        .then(data => {
            // Debug: log the first item to see all fields
            console.log("First log entry:", data[0]);
            
            const container = document.getElementById("logsContainer");
            if (container) {
                container.innerHTML = data.map(log => {
                    const username = escapeHtml(log.username || 'Unknown');
                    const action = escapeHtml(log.action || '');
                    const ip = escapeHtml(log.ip_address || 'N/A');
                    const browser = truncate(escapeHtml(log.user_agent || 'N/A'), 50);
                    const page = escapeHtml(log.page || 'N/A');
                    const time = escapeHtml(log.created_at || '');
                    
                    // Build the line with all details
                    return `<div class="log-item" style="padding:5px; border-bottom:1px solid #ddd; font-family:monospace; font-size:12px;">
                        <b>${username}</b>: ${action} 
                        | IP: ${ip} 
                        | Browser: <span title="${escapeHtml(log.user_agent || '')}">${browser}</span>
                        | Page: ${page} 
                        | ${time}
                    </div>`;
                }).join('');
            }

            // Charts (group by action)
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
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
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