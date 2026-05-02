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
let selectedBuildingId = null;
let buildingInfoRefreshInterval = null;  // for auto‑refresh

// Chart instances
window.deviceLogsBarChart = null;
window.deviceLogsPieChart = null;

/* --- BUILDING FUNCTIONS --- */

function toggleBuildingForm() {
    const form = document.getElementById("formContainer");
    if (form) form.classList.toggle("hidden");
}

map.on("click", function(e){
    const latInput = document.getElementById("latitude");
    const lngInput = document.getElementById("longitude");
    if(latInput && lngInput) {
        latInput.value = e.latlng.lat.toFixed(6);
        lngInput.value = e.latlng.lng.toFixed(6);
    }
});

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
            if (!Array.isArray(data)) return;
            if (window.buildingMarkers) {
                window.buildingMarkers.forEach(m => map.removeLayer(m));
            }
            window.buildingMarkers = [];
            data.forEach(building => {
                const lat = parseFloat(building.latitude || building.lat);
                const lng = parseFloat(building.longitude || building.lng);
                if (isNaN(lat) || isNaN(lng)) return;
                const marker = L.marker([lat, lng])
                    .addTo(map)
                    .bindPopup(`<b>${building.name}</b>`);
                window.buildingMarkers.push(marker);
                marker.on('click', () => showBuildingInfo(building));
            });
            // If a building was selected, refresh its info
            if (selectedBuildingId) {
                refreshSelectedBuilding();
            }
        })
        .catch(err => console.error("Fetch error:", err));
}

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

    // Start auto‑refresh for this building (every 3 seconds)
    if (buildingInfoRefreshInterval) clearInterval(buildingInfoRefreshInterval);
    buildingInfoRefreshInterval = setInterval(refreshSelectedBuilding, 3000);
}

function deleteSelectedBuilding(){
    if(!selectedBuildingId) return;
    if(!confirm("Are you sure you want to delete this building?")) return;
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
            document.getElementById("roomListContainer").innerHTML = "";
            const headerBtn = document.getElementById("deleteBuildingBtn");
            if(headerBtn) headerBtn.disabled = true;
            selectedBuildingId = null;
            if (buildingInfoRefreshInterval) {
                clearInterval(buildingInfoRefreshInterval);
                buildingInfoRefreshInterval = null;
            }
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
    if(!confirm("Delete this device?")) return;
    fetch("deleteDevice.php", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({device_id})
    }).then(res => res.json()).then(data => {
        if (data.status === "success") refreshAllDeviceUI();
        else alert(data.msg);
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
            <input type="text" id="sideDevUser" placeholder="Username">
            <input type="password" id="sideDevPass" placeholder="Password">
            <button onclick="submitNewDevice('sideDevUser', 'sideDevPass')">Add Device</button>
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

/* ========== ASSIGN DEVICE FEATURE (PERSISTENT ASSIGNMENT) ========== */

let assignedRoomId = null;
let assignedDeviceId = null;

let assignSerialPort = null;
let assignSerialReader = null;
let assignKeepReading = false;

function toggleAssignDeviceForm() {
    const sidebar = document.querySelector('.sidebar');
    let formDiv = document.getElementById('assignDeviceForm');
    
    if (formDiv) {
        formDiv.classList.toggle('hidden');
        // If we're showing the form again
        if (!formDiv.classList.contains('hidden')) {
            loadBuildingsDropdown();
            loadDevicesDropdown();
            // If there's an active assignment, restore the UI
            if (assignedRoomId && assignedDeviceId) {
                restoreAssignmentSelections();
            } else {
                // Ensure dropdowns are enabled if no assignment
                enableAssignmentFields();
            }
        }
        return;
    }
    
    // Create the form container
    formDiv = document.createElement('div');
    formDiv.id = 'assignDeviceForm';
    formDiv.style.marginTop = '20px';
    formDiv.style.padding = '15px';
    formDiv.style.background = 'white';
    formDiv.style.borderRadius = '8px';
    formDiv.style.border = '1px solid #ccc';
    formDiv.innerHTML = `
        <h3>Assign Device to Room</h3>
        <label>Building:</label>
        <select id="assignBuilding" style="width:100%; margin-bottom:10px;">
            <option value="">-- Select Building --</option>
        </select>
        
        <label>Room:</label>
        <select id="assignRoom" style="width:100%; margin-bottom:10px;" disabled>
            <option value="">-- First select a building --</option>
        </select>
        
        <label>Device:</label>
        <select id="assignDevice" style="width:100%; margin-bottom:10px;">
            <option value="">-- Select Device --</option>
        </select>
        
        <div id="serialSection" style="margin-bottom:10px;">
            <label>Serial Port:</label>
            <button id="connectSerialBtn" style="margin-top:5px;">🔌 Connect Serial</button>
            <span id="serialStatus" style="margin-left:10px; color:gray;">Not connected</span>
            <div id="serialOutput" style="display:none; margin-top:5px; max-height:100px; overflow-y:auto; background:#f5f5f5; padding:5px; font-family:monospace; font-size:12px;"></div>
        </div>
        
        <button id="assignBtn" style="width:100%;" disabled>Assign Device</button>
        <button id="unassignBtn" style="width:100%; margin-top:5px; background:#f0ad4e; display:none;">Unassign Device</button>
        <button id="cancelAssignBtn" style="width:100%; margin-top:5px; background:#aaa;">Close</button>
    `;
    
    // Insert after the board (Building Information)
    const board = document.querySelector('.board');
    board.parentNode.insertBefore(formDiv, board.nextSibling);
    
    // Populate dropdowns
    loadBuildingsDropdown();
    loadDevicesDropdown();
    
    // Event handlers
    document.getElementById('assignBuilding').addEventListener('change', function() {
        const buildingId = this.value;
        if (buildingId) {
            loadRoomsDropdown(buildingId);
        } else {
            document.getElementById('assignRoom').innerHTML = '<option value="">-- First select a building --</option>';
            document.getElementById('assignRoom').disabled = true;
        }
        checkAssignButton();
    });
    
    document.getElementById('assignRoom').addEventListener('change', checkAssignButton);
    document.getElementById('assignDevice').addEventListener('change', checkAssignButton);
    document.getElementById('connectSerialBtn').addEventListener('click', connectSerial);
    document.getElementById('assignBtn').addEventListener('click', assignDeviceToRoom);
    document.getElementById('unassignBtn').addEventListener('click', unassignDevice);
    document.getElementById('cancelAssignBtn').addEventListener('click', function() {
        // Just close the form – DO NOT clear assignment
        toggleAssignDeviceForm();
    });
    
    // If an assignment is already active, restore the UI
    if (assignedRoomId && assignedDeviceId) {
        restoreAssignmentSelections();
    }
}

// Helper: Enable all assignment fields (dropdowns, Assign button)
function enableAssignmentFields() {
    const building = document.getElementById('assignBuilding');
    const room = document.getElementById('assignRoom');
    const device = document.getElementById('assignDevice');
    if (building) building.disabled = false;
    if (room) {
        room.disabled = true; // must reselect building first
        room.innerHTML = '<option value="">-- First select a building --</option>';
    }
    if (device) device.disabled = false;
    document.getElementById('assignBtn').disabled = true;
    document.getElementById('unassignBtn').style.display = 'none';
}

// Restore selections when an assignment is active
function restoreAssignmentSelections() {
    // We need to figure out which building contains assignedRoomId.
    // Fetch room details to get building_id.
    fetch(`getRoomDetails.php?room_id=${assignedRoomId}`)
        .then(res => res.json())
        .then(room => {
            if (!room || !room.building_id) {
                console.warn('Cannot restore assignment: room not found');
                return;
            }
            const buildingSelect = document.getElementById('assignBuilding');
            const roomSelect = document.getElementById('assignRoom');
            const deviceSelect = document.getElementById('assignDevice');
            
            if (buildingSelect) {
                buildingSelect.value = room.building_id;
                // Trigger change to load rooms
                buildingSelect.dispatchEvent(new Event('change'));
                // After rooms load, set the room and lock everything
                setTimeout(() => {
                    if (roomSelect) {
                        roomSelect.value = assignedRoomId;
                        roomSelect.disabled = true; // lock
                    }
                }, 500);
                buildingSelect.disabled = true;
            }
            if (deviceSelect) {
                deviceSelect.value = assignedDeviceId;
                deviceSelect.disabled = true;
            }
            document.getElementById('assignBtn').disabled = true;
            document.getElementById('unassignBtn').style.display = 'block';
        })
        .catch(err => console.error('Failed to restore assignment:', err));
}

function unassignDevice() {
    if (!confirm('Remove the device assignment? Serial data will no longer update this room.')) return;
    assignedRoomId = null;
    assignedDeviceId = null;
    enableAssignmentFields();
    // Clear selections
    document.getElementById('assignBuilding').value = '';
    document.getElementById('assignDevice').value = '';
    checkAssignButton();
}

function loadBuildingsDropdown() {
    fetch('getBuildings.php')
        .then(res => res.json())
        .then(buildings => {
            const select = document.getElementById('assignBuilding');
            if (!select) return;
            select.innerHTML = '<option value="">-- Select Building --</option>';
            buildings.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.name;
                select.appendChild(opt);
            });
        })
        .catch(err => console.error('Failed to load buildings for assign:', err));
}

function loadRoomsDropdown(buildingId) {
    fetch(`getRoomsByBuilding.php?building_id=${buildingId}`)
        .then(res => res.json())
        .then(rooms => {
            const select = document.getElementById('assignRoom');
            if (!select) return;
            select.innerHTML = '<option value="">-- Select Room --</option>';
            rooms.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.id;
                opt.textContent = `Floor ${r.floor_no} - Room ${r.room_no}`;
                select.appendChild(opt);
            });
            select.disabled = false;
        })
        .catch(err => {
            console.error('Failed to load rooms:', err);
            alert('Could not load rooms for this building. Check endpoint.');
        });
}

function loadDevicesDropdown() {
    fetch('getDevices.php')
        .then(res => res.json())
        .then(devices => {
            const select = document.getElementById('assignDevice');
            if (!select) return;
            select.innerHTML = '<option value="">-- Select Device --</option>';
            devices.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.username;
                select.appendChild(opt);
            });
        })
        .catch(err => console.error('Failed to load devices for assign:', err));
}

function checkAssignButton() {
    const building = document.getElementById('assignBuilding').value;
    const room = document.getElementById('assignRoom').value;
    const device = document.getElementById('assignDevice').value;
    const serialConnected = assignSerialPort !== null;
    const btn = document.getElementById('assignBtn');
    if (btn) btn.disabled = !(building && room && device && serialConnected);
}

// ── Serial functions ──

async function connectSerial() {
    const statusSpan = document.getElementById('serialStatus');
    const outputDiv = document.getElementById('serialOutput');
    const connectBtn = document.getElementById('connectSerialBtn');
    
    try {
        assignSerialPort = await navigator.serial.requestPort();
        await assignSerialPort.open({ baudRate: 9600 });
        
        statusSpan.textContent = 'Connected';
        statusSpan.style.color = 'green';
        connectBtn.textContent = '🔌 Disconnect';
        outputDiv.style.display = 'block';
        
        assignKeepReading = true;
        readSerialLoop();
        
        connectBtn.removeEventListener('click', connectSerial);
        connectBtn.addEventListener('click', disconnectSerial);
    } catch (err) {
        statusSpan.textContent = 'Connection failed';
        statusSpan.style.color = 'red';
        console.error(err);
    }
    checkAssignButton();
}

async function readSerialLoop() {
    const outputDiv = document.getElementById('serialOutput');
    const decoder = new TextDecoder();
    let buffer = '';
    
    try {
        assignSerialReader = assignSerialPort.readable.getReader();
        while (assignKeepReading) {
            const { value, done } = await assignSerialReader.read();
            if (done) {
                outputDiv.textContent += '\n[Stream closed]';
                break;
            }
            if (value) {
                const text = decoder.decode(value, { stream: true });
                outputDiv.textContent += text;
                outputDiv.scrollTop = outputDiv.scrollHeight;
                
                buffer += text;
                const lines = buffer.split('\n');
                buffer = lines.pop();
                for (const line of lines) {
                    processSerialLine(line.trim());
                }
            }
        }
    } catch (error) {
        outputDiv.textContent += '\n[Read error: ' + error.message + ']';
    } finally {
        if (assignSerialReader) {
            assignSerialReader.releaseLock();
            assignSerialReader = null;
        }
    }
}

function processSerialLine(line) {
    if (!line || !assignedRoomId || !assignedDeviceId) return;
    
    const match = line.match(/\d+/);
    if (!match) return;
    
    const occupantCount = parseInt(match[0], 10);
    
    fetch('updateOccupants.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            room_id: assignedRoomId,
            device_id: assignedDeviceId,
            occupants: occupantCount
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            console.log('Occupants updated:', occupantCount);
            // Immediately refresh building info (if a building is selected)
            refreshSelectedBuilding();
        } else {
            console.warn('Occupant update warning:', data.msg);
        }
    })
    .catch(err => console.error('Occupant update failed:', err));
}

async function disconnectSerial() {
    assignKeepReading = false;
    if (assignSerialReader) {
        try { await assignSerialReader.cancel(); } catch (err) {}
    }
    if (assignSerialPort) {
        try { await assignSerialPort.close(); } catch (err) {}
        assignSerialPort = null;
    }
    document.getElementById('serialStatus').textContent = 'Disconnected';
    document.getElementById('serialStatus').style.color = 'gray';
    document.getElementById('serialOutput').style.display = 'none';
    const connectBtn = document.getElementById('connectSerialBtn');
    connectBtn.textContent = '🔌 Connect Serial';
    connectBtn.removeEventListener('click', disconnectSerial);
    connectBtn.addEventListener('click', connectSerial);
    checkAssignButton();
}

function assignDeviceToRoom() {
    const roomId = document.getElementById('assignRoom').value;
    const deviceId = document.getElementById('assignDevice').value;
    
    if (!roomId || !deviceId || !assignSerialPort) {
        alert('Please complete all fields and connect a serial port.');
        return;
    }
    
    fetch('assignDevice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_id: roomId, device_id: deviceId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            assignedRoomId = roomId;
            assignedDeviceId = deviceId;
            alert('Device assigned! Serial data will now update room ' + roomId);
            // Lock UI
            document.getElementById('assignBuilding').disabled = true;
            document.getElementById('assignRoom').disabled = true;
            document.getElementById('assignDevice').disabled = true;
            document.getElementById('assignBtn').disabled = true;
            document.getElementById('unassignBtn').style.display = 'block';
            // Refresh building info
            refreshSelectedBuilding();
        } else {
            alert('Assignment failed: ' + data.msg);
        }
    })
    .catch(err => {
        console.error('Assign error:', err);
        alert('Server error during assignment.');
    });
}

/* ── Building info auto‑refresh ── */
function refreshSelectedBuilding() {
    if (!selectedBuildingId) return;
    fetch('getBuildings.php')
        .then(res => res.json())
        .then(buildings => {
            if (!Array.isArray(buildings)) return;
            const building = buildings.find(b => b.id == selectedBuildingId);
            if (building) {
                showBuildingInfo(building);
            }
        })
        .catch(err => console.error('Refresh building info failed:', err));
}

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    assignKeepReading = false;
    if (assignSerialReader) {
        assignSerialReader.cancel().catch(() => {});
    }
    if (assignSerialPort) {
        assignSerialPort.close().catch(() => {});
    }
});

/* --- DEVICE LOGS & CHARTS (FIXED) --- */

function loadDeviceLogs() {
    fetch("getDeviceLogs.php")
    .then(res => res.json())
    .then(deviceLogs => {
        const container = document.getElementById("logsContainer");
        if (container) {
            if (deviceLogs.length === 0) {
                container.innerHTML = "<div class='log-item'>No device logs found.</div>";
            } else {
                container.innerHTML = deviceLogs.map(log => `
                    <div class="log-item">
                        <strong>${log.username}</strong>: ${log.action}<br>
                        <span class="log-time">${log.created_at}</span>
                    </div>
                `).join('');
            }
        }
        
        return fetch("getLogStats.php");
    })
    .then(res => res.json())
    .then(stats => {
        if (window.deviceLogsBarChart instanceof Chart) window.deviceLogsBarChart.destroy();
        if (window.deviceLogsPieChart instanceof Chart) window.deviceLogsPieChart.destroy();

        const barCtx = document.getElementById('logsBarChart').getContext('2d');
        const pieCtx = document.getElementById('logsPieChart').getContext('2d');

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

        window.deviceLogsPieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: stats.map(s => s.action),
                datasets: [{
                    label: 'Count',
                    data: stats.map(s => s.count),
                    backgroundColor: ['#36A2EB','#FF6384','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#C9CBCF']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    })
    .catch(err => console.error("Failed to load device logs/charts:", err));
}

function initDashboard() {
    loadBuildings();
    refreshAllDeviceUI();
    loadDeviceLogs();

    document.getElementById("addDeviceBtn")?.addEventListener("click", () => {
        submitNewDevice('newDeviceUsername', 'newDevicePassword');
    });
    document.getElementById("logoutBtn")?.addEventListener("click", () => {
        window.location.href = "logout.php";
    });
    
    setInterval(loadDeviceLogs, 5000);
}

window.onload = initDashboard;