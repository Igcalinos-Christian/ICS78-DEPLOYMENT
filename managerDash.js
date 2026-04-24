// 1. Initialize Map Immediately
var map = L.map('map').setView([8.359997, 124.868352], 17);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

L.polygon([
    [8.361465, 124.867623],
    [8.358812, 124.867054],
    [8.358302, 124.869071],
    [8.361060, 124.869640]
], { color: 'green' }).addTo(map);

let buildings = [];
let originalBoardHTML = "";

/* --- BUILDING FUNCTIONS --- */

// Toggle Form Visibility
function toggleBuildingForm() {
    const form = document.getElementById("formContainer");
    if (form) {
        form.classList.toggle("hidden");
    } else {
        console.error("formContainer not found in HTML");
    }
}

// Map click → fill coordinates
map.on("click", function(e){
    const latInput = document.getElementById("latitude");
    const lngInput = document.getElementById("longitude");
    if(latInput && lngInput) {
        latInput.value = e.latlng.lat.toFixed(6);
        lngInput.value = e.latlng.lng.toFixed(6);
    }
});

// Generate room inputs
document.getElementById("floorCount")?.addEventListener("change", function(){
    const roomContainer = document.getElementById("roomInputs");
    roomContainer.innerHTML = "";
    let floors = parseInt(this.value);
    for(let i=1; i<=floors; i++){
        let div = document.createElement("div");
        div.innerHTML = `<label>Rooms on Floor ${i}</label><input type="number" min="0" id="floorRooms${i}" required>`;
        roomContainer.appendChild(div);
    }
});

// Submit Building
document.getElementById("buildingForm")?.addEventListener("submit", function(e){
    e.preventDefault();
    const name = document.getElementById("buildingName").value;
    const floors = parseInt(document.getElementById("floorCount").value);
    const lat = parseFloat(document.getElementById("latitude").value);
    const lng = parseFloat(document.getElementById("longitude").value);

    let roomsPerFloor = [];
    for(let i=1; i<=floors; i++){
        roomsPerFloor.push(parseInt(document.getElementById("floorRooms"+i).value));
    }

    fetch("addBuilding.php", {
        method: "POST",
        headers: { "Content-Type":"application/json" },
        body: JSON.stringify({ name, latitude: lat, longitude: lng, rooms: roomsPerFloor })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success") {
            loadBuildings();
            this.reset();
            document.getElementById("roomInputs").innerHTML = "";
            toggleBuildingForm();
        } else {
            alert("Error: " + data.msg);
        }
    });
});

function loadBuildings() {
    fetch('getBuildings.php')
        .then(response => response.json())
        .then(data => {
            console.log("Data received:", data);

            if (!Array.isArray(data)) return;

            // Clear old markers
            if (window.buildingMarkers) {
                window.buildingMarkers.forEach(m => map.removeLayer(m));
            }
            window.buildingMarkers = [];

            data.forEach(building => {
                const lat = parseFloat(building.latitude || building.lat);
                const lng = parseFloat(building.longitude || building.lng);

                if (isNaN(lat) || isNaN(lng)) {
                    console.warn(`Skipping building "${building.name}" due to invalid coords:`, building);
                    return;
                }

                try {
                    const marker = L.marker([lat, lng])
                        .addTo(map)
                        .bindPopup(`<b>${building.name}</b>`);

                    window.buildingMarkers.push(marker);
                    marker.on('click', () => showBuildingInfo(building));
                } catch (err) {
                    console.error("Leaflet error adding marker:", err);
                }
            });
        })
        .catch(err => console.error("Fetch error:", err));
}

let selectedBuildingId = null;

function showBuildingInfo(building){
    selectedBuildingId = building.id;

    document.getElementById("infoName").innerText = building.name;
    document.getElementById("infoFloors").innerText = building.floors;
    const roomContainer = document.getElementById("roomListContainer");
    roomContainer.innerHTML = "";

    building.rooms.forEach((floorRooms, floorIndex) => {
        let floorHeader = document.createElement("h4");
        floorHeader.innerText = "Floor " + (floorIndex + 1);
        roomContainer.appendChild(floorHeader);
        let grid = document.createElement("div");
        grid.className = "room-grid";
        floorRooms.forEach((occupants, roomIndex) => {
            let btn = document.createElement("button");
            btn.className = "room-btn";
            btn.innerText = `R${roomIndex + 1} : ${occupants}`;
            grid.appendChild(btn);
        });
        roomContainer.appendChild(grid);
    });

    const headerBtn = document.getElementById("deleteBuildingBtn");
    if(headerBtn) headerBtn.disabled = false;
}

function deleteSelectedBuilding(){
    if(!selectedBuildingId) return;
    if(!confirm("Are you sure you want to delete this building? This will remove all floors and rooms.")) return;

    fetch("deleteBuilding.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ building_id: selectedBuildingId })
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === "success"){
            loadBuildings();
            document.getElementById("infoName").innerText = "None";
            document.getElementById("infoFloors").innerText = "0";
            document.getElementById("infoRooms").innerText = "0";
            document.getElementById("roomListContainer").innerHTML = "";

            const headerBtn = document.getElementById("deleteBuildingBtn");
            if(headerBtn) headerBtn.disabled = true;

            selectedBuildingId = null;
        } else {
            alert("Error: " + data.msg);
        }
    });
}

/* --- DEVICE FUNCTIONS --- */

function refreshAllDeviceUI() {
    fetch("getDevices.php").then(res => res.json()).then(data => {
        const bottomList = document.getElementById("deviceList");
        if (bottomList) {
            bottomList.innerHTML = data.map(d => `
                <div style="display:flex; justify-content:space-between; padding:8px; border-bottom:1px solid #ddd; background:white; margin-bottom:5px;">
                    <span>${d.username}</span>
                    <button onclick="deleteDeviceAction(${d.id})" style="color:red; cursor:pointer;">Delete</button>
                </div>
            `).join('');
        }
        const tbody = document.getElementById('deviceTableBody');
        if (tbody) {
            tbody.innerHTML = data.map(dev => `
                <tr><td>${dev.username}</td><td style="text-align:right;">
                <button onclick="deleteDeviceAction(${dev.id})" style="color:red; border:none; background:none; cursor:pointer;">Delete</button>
                </td></tr>
            `).join('');
        }
    });
}

function submitNewDevice(userFieldId, passFieldId) {
    const username = document.getElementById(userFieldId).value;
    const password = document.getElementById(passFieldId).value;
    fetch("addDevice.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({username, password})
    }).then(res => res.json()).then(data => {
        if (data.status === "success") {
            if(userFieldId === 'sideDevUser') revertBoard();
            refreshAllDeviceUI();
        } else { alert(data.msg); }
    });
}

function deleteDeviceAction(device_id) {
    if(!confirm("Delete?")) return;
    fetch("deleteDevice.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({device_id})
    }).then(res => res.json()).then(data => {
        if (data.status === "success") refreshAllDeviceUI();
    });
}

function showDeviceManager() {
    const board = document.querySelector('.board');
    if (!originalBoardHTML) originalBoardHTML = board.innerHTML;
    board.innerHTML = `
        <div class="device-manager-inner">
            <h3>Manage Devices</h3>
            <div style="max-height:150px; overflow-y:auto;">
                <table style="width:100%; font-size:0.85em;">
                    <tbody id="deviceTableBody"></tbody>
                </table>
            </div>
            <input type="text" id="sideDevUser" placeholder="User">
            <input type="password" id="sideDevPass" placeholder="Pass">
            <button onclick="submitNewDevice('sideDevUser', 'sideDevPass')">Add</button>
            <button onclick="revertBoard()">Back</button>
        </div>`;
    refreshAllDeviceUI();
}

function revertBoard() {
    const board = document.querySelector('.board');
    if (board && originalBoardHTML) {
        board.innerHTML = originalBoardHTML;
        originalBoardHTML = "";
    }
}

/* --- LOGS (Device-only) & CHARTS --- */

// Loads ONLY device logs into the #logsContainer (uses getDeviceLogs.php)
function loadLogs() {
    fetch("getDeviceLogs.php")
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById("logsContainer");
            if (container) {
                container.innerHTML = data.map(log => {
                    const action = escapeHtml(log.action || '');
                    const time = escapeHtml(log.created_at || '');
                    return `<div class="log-item" style="padding:5px; border-bottom:1px solid #ddd; font-size:12px;">
                        ${action} <span style="color:gray; margin-left:8px;">${time}</span>
                    </div>`;
                }).join('');
            }
        })
        .catch(err => console.error("Failed to load device logs:", err));
}

// Loads chart statistics (only device actions) – does NOT touch logs container
function loadDeviceLogs() {
    fetch("getLogStats.php")
        .then(res => res.json())
        .then(stats => {
            // Destroy old charts if they exist
            if (window.deviceLogsBarChart instanceof Chart) window.deviceLogsBarChart.destroy();
            if (window.deviceLogsPieChart instanceof Chart) window.deviceLogsPieChart.destroy();

            const barCtx = document.getElementById('logsBarChart').getContext('2d');
            const pieCtx = document.getElementById('logsPieChart').getContext('2d');

            // Bar chart
            window.deviceLogsBarChart = new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: stats.map(s => s.action),
                    datasets: [{
                        label: 'Count',
                        data: stats.map(s => s.count),
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

            // Pie chart
            window.deviceLogsPieChart = new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: stats.map(s => s.action),
                    datasets: [{
                        label: 'Count',
                        data: stats.map(s => s.count),
                        backgroundColor: [
                            '#36A2EB','#FF6384','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#C9CBCF'
                        ]
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        })
        .catch(err => console.error("Failed to load device stats:", err));
}

// Initialization
function initDashboard() {
    loadBuildings();
    loadLogs();               // fills device logs container
    refreshAllDeviceUI();
    loadDeviceLogs();         // loads charts only

    document.getElementById("addDeviceBtn")?.addEventListener("click", () => {
        submitNewDevice('newDeviceUsername', 'newDevicePassword');
    });

    document.getElementById("logoutBtn")?.addEventListener("click", () => {
        window.location.href = "logout.php";
    });
}

// Run initialization when page loads
window.onload = initDashboard;

// Auto-refresh charts every 5 seconds (does not interfere with logs container)
setInterval(loadDeviceLogs, 5000);