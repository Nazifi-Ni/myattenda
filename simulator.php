<?php
// MyAttenda V2 — Interactive Hardware & Circuit Simulator
// Emulates the exact Proteus schematic: ESP32 DevKit V1 ESP32 + PCF8574 3x4 Keypad + OLED 12864 + R307 Fingerprint + DS3231 RTC + MicroSD + Buzzer
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hardware Circuit Simulator | MyAttenda V2</title>
  <link rel="stylesheet" href="/public/css/styles.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500;700&family=Manrope:wght@500;700;800&family=VT323&display=swap" rel="stylesheet">
  <style>
    :root {
      --circuit-bg: #090d16;
      --pcb-green: #064e3b;
      --oled-blue: #38bdf8;
      --oled-bg: #020617;
      --sensor-glow: #0284c7;
    }

    body {
      background: var(--circuit-bg);
      color: #e2e8f0;
      min-height: 100vh;
    }

    .sim-container {
      max-width: 90rem;
      margin: 0 auto;
      padding: 1.5rem;
    }

    .sim-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 1rem;
      border-bottom: 1px solid #1e293b;
      margin-bottom: 1.5rem;
    }

    .circuit-bench {
      display: grid;
      grid-template-columns: minmax(0, 1.35fr) minmax(22rem, 0.65fr);
      gap: 1.5rem;
    }

    /* PCB Board Frame */
    .pcb-board {
      background: #0d1525;
      border: 2px solid #1e293b;
      border-radius: 1.5rem;
      padding: 2rem;
      position: relative;
      box-shadow: inset 0 0 50px rgba(0,0,0,0.8), 0 20px 40px rgba(0,0,0,0.6);
    }

    .pcb-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
    }

    /* OLED Display Module */
    .oled-module {
      background: #020617;
      border: 3px solid #334155;
      border-radius: 1rem;
      padding: 1rem;
      box-shadow: 0 10px 25px rgba(0,0,0,0.8);
    }

    .oled-screen {
      background: #000000;
      border: 2px solid #1e293b;
      border-radius: 0.5rem;
      height: 120px;
      padding: 0.75rem;
      font-family: 'VT323', monospace;
      color: var(--oled-blue);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      letter-spacing: 1px;
      text-shadow: 0 0 8px rgba(56, 189, 248, 0.6);
      overflow: hidden;
    }

    .oled-row {
      display: flex;
      justify-content: space-between;
      font-size: 1.15rem;
    }

    .oled-main {
      font-size: 1.5rem;
      text-align: center;
      line-height: 1.2;
      margin: auto 0;
    }

    .oled-sub {
      font-size: 1.1rem;
      color: #94a3b8;
      text-align: center;
    }

    /* ESP32 DevKit V1 ESP32 */
    .ESP32-chip {
      background: #1e293b;
      border: 2px solid #475569;
      border-radius: 0.8rem;
      padding: 1.2rem;
      position: relative;
    }

    .esp-antenna {
      height: 18px;
      background: repeating-linear-gradient(90deg, #b45309, #b45309 6px, #78350f 6px, #78350f 12px);
      border-radius: 4px;
      margin-bottom: 1rem;
    }

    .led-indicator {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
      margin-right: 6px;
    }

    .led-blue { background: #38bdf8; box-shadow: 0 0 10px #38bdf8; }
    .led-red { background: #ef4444; box-shadow: 0 0 10px #ef4444; }
    .led-green { background: #10b981; box-shadow: 0 0 10px #10b981; }
    .led-off { background: #334155; box-shadow: none; }

    /* 3x4 Matrix Keypad */
    .keypad-matrix {
      background: #1e293b;
      border: 2px solid #334155;
      border-radius: 1rem;
      padding: 1rem;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.6rem;
      max-width: 14rem;
      margin: 0 auto;
    }

    .key-btn {
      background: #0f172a;
      border: 1px solid #475569;
      color: #f8fafc;
      font-family: var(--font-mono);
      font-size: 1.2rem;
      font-weight: 700;
      border-radius: 0.5rem;
      height: 2.8rem;
      cursor: pointer;
      display: grid;
      place-items: center;
      box-shadow: 0 4px 0 #020617;
      transition: all 80ms ease;
    }

    .key-btn:active {
      transform: translateY(3px);
      box-shadow: 0 1px 0 #020617;
      background: var(--brand);
    }

    /* R307 Optical Sensor */
    .r307-sensor {
      background: #1e293b;
      border: 2px solid #334155;
      border-radius: 1rem;
      padding: 1.2rem;
      text-align: center;
    }

    .optical-glass {
      width: 5.5rem;
      height: 6.5rem;
      margin: 0.75rem auto;
      border-radius: 0.75rem;
      background: #0369a1;
      border: 3px solid #38bdf8;
      box-shadow: 0 0 25px rgba(56, 189, 248, 0.5);
      cursor: pointer;
      display: grid;
      place-items: center;
      font-size: 2.8rem;
      transition: all 200ms ease;
      position: relative;
    }

    .optical-glass.scanning {
      background: #ef4444;
      border-color: #f87171;
      box-shadow: 0 0 35px rgba(239, 68, 68, 0.8);
      animation: pulse 400ms infinite alternate;
    }

    .optical-glass.match {
      background: #059669;
      border-color: #34d399;
      box-shadow: 0 0 35px rgba(16, 185, 129, 0.8);
    }

    @keyframes pulse {
      from { transform: scale(0.97); }
      to { transform: scale(1.03); }
    }

    /* Peripheral Badges */
    .peripheral-card {
      background: #0f172a;
      border: 1px solid #1e293b;
      border-radius: 0.75rem;
      padding: 0.85rem;
      font-size: 0.78rem;
    }

    /* Serial Monitor Console */
    .serial-monitor {
      background: #020617;
      border: 1px solid #1e293b;
      border-radius: 1rem;
      display: flex;
      flex-direction: column;
      height: 680px;
    }

    .serial-header {
      padding: 0.75rem 1rem;
      border-bottom: 1px solid #1e293b;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: var(--font-mono);
      font-size: 0.78rem;
      color: #94a3b8;
    }

    .serial-body {
      flex: 1;
      padding: 1rem;
      overflow-y: auto;
      font-family: var(--font-mono);
      font-size: 0.75rem;
      line-height: 1.6;
      color: #38bdf8;
    }

    .log-tx { color: #facc15; }
    .log-rx { color: #4ade80; }
    .log-hw { color: #94a3b8; }
    .log-err { color: #f87171; }

    /* Switch toggle */
    .switch {
      position: relative;
      display: inline-block;
      width: 44px;
      height: 24px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
      position: absolute; cursor: pointer; inset: 0; background-color: #475569;
      transition: .3s; border-radius: 24px;
    }
    .slider:before {
      position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px;
      background-color: white; transition: .3s; border-radius: 50%;
    }
    input:checked + .slider { background-color: #10b981; }
    input:checked + .slider:before { transform: translateX(20px); }
  </style>
</head>
<body>

  <div class="sim-container">
    <!-- Simulator Navigation & Links -->
    <div class="sim-header">
      <div>
        <div class="brand-lockup">
          <span class="brand-mark">⚡</span>
          <span class="brand-name" style="color:#ffffff;">MyAttenda</span>
          <span class="brand-version">CIRCUIT SIMULATOR</span>
        </div>
        <p style="margin: 0.25rem 0 0; font-size: 0.8rem; color: #94a3b8;">
          Emulating Proteus Hardware: ESP32 DevKit V1 ESP32 + PCF8574 (3x4 Keypad) + OLED 12864 + R307 Sensor + DS3231 RTC + MicroSD
        </p>
      </div>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <a href="/admin/index.php" class="button button-secondary" style="background:#0f172a; color:#fff; border-color:#334155;">Super Admin</a>
        <a href="/dashboard/index.php" class="button button-secondary" style="background:#0f172a; color:#fff; border-color:#334155;">Lecturer Portal</a>
        <a href="/" class="button button-primary">Home</a>
      </div>
    </div>

    <!-- Circuit Testing Bench -->
    <div class="circuit-bench">

      <!-- Left: Interactive Hardware PCB -->
      <div class="pcb-board">
        <!-- Top Toolbar: Power & Wi-Fi Control -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; background: #0f172a; padding: 0.75rem 1.25rem; border-radius: 0.8rem; border: 1px solid #1e293b;">
          <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span class="led-indicator led-green" id="power-led"></span>
            <strong style="font-size: 0.85rem;">Hardware Power: 5V Active</strong>
          </div>

          <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;">
              <span id="wifi-label">Wi-Fi Connected</span>
              <label class="switch">
                <input type="checkbox" id="wifi-toggle" checked onchange="toggleWifi(this.checked)">
                <span class="slider"></span>
              </label>
            </div>
            <button class="button button-secondary" style="min-height: 2rem; padding: 0.2rem 0.75rem; font-size: 0.75rem; background:#1e293b; color:#fff;" onclick="hardwareReset()">
              🔄 Hardware Reset (RST)
            </button>
          </div>
        </div>

        <div class="pcb-grid">

          <!-- Col 1: OLED Display & ESP32 DevKit V1 -->
          <div>
            <!-- OLED 12864 Module (I2C via D1/SCL & D2/SDA) -->
            <div class="oled-module">
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.68rem; color: #64748b; font-family: var(--font-mono);">
                <span>LCD1 / OLED 128x64 (I2C)</span>
                <span id="oled-i2c-addr">ADDR: 0x3C</span>
              </div>
              <div class="oled-screen" id="oled-display">
                <div class="oled-row">
                  <span>MYATTENDA V2</span>
                  <span id="oled-wifi-icon">WIFI:OK</span>
                </div>
                <div class="oled-main" id="oled-main-text">
                  INITIALIZING...
                </div>
                <div class="oled-row">
                  <span id="oled-rtc-clock">12:00:00</span>
                  <span id="oled-queue-count">SD:OK</span>
                </div>
              </div>
            </div>

            <!-- ESP32 DevKit V1 ESP32 -->
            <div class="ESP32-chip" style="margin-top: 1.5rem;">
              <div class="esp-antenna"></div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <strong style="font-size: 0.9rem; color: #fff;">ESP32 DevKit V1 ESP32</strong>
                  <div style="font-family: var(--font-mono); font-size: 0.7rem; color: #94a3b8;" id="hardware-uid-label">
                    CHIP UID: ESP32-ESP32 DevKit V1-CHIP99
                  </div>
                </div>
                <div>
                  <span class="led-indicator led-blue" id="esp-wifi-led" title="Wi-Fi Activity"></span>
                  <span class="led-indicator led-green" id="esp-tx-led" title="UART TX"></span>
                </div>
              </div>

              <!-- MicroSD & DS3231 Bus Indicators -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1rem;">
                <div class="peripheral-card">
                  <div style="display: flex; justify-content: space-between;">
                    <strong>SDM1 (MicroSD)</strong>
                    <span class="led-indicator led-green" id="sd-led"></span>
                  </div>
                  <div style="color: #64748b; font-size: 0.7rem; margin-top: 0.25rem;" id="sd-file-status">FAT32 · 0 queued events</div>
                </div>
                <div class="peripheral-card">
                  <div style="display: flex; justify-content: space-between;">
                    <strong>U1 (DS3231 RTC)</strong>
                    <span class="led-indicator led-green"></span>
                  </div>
                  <div style="color: #64748b; font-size: 0.7rem; margin-top: 0.25rem;" id="ds3231-time-label">00:00:00 UTC</div>
                </div>
              </div>
            </div>

          </div>

          <!-- Col 2: PCF8574 3x4 Keypad & R307 Sensor -->
          <div>
            <!-- R307 Optical Fingerprint Sensor -->
            <div class="r307-sensor">
              <div style="display: flex; justify-content: space-between; font-size: 0.68rem; color: #64748b; font-family: var(--font-mono);">
                <span>FPSM1 (R307 SENSOR)</span>
                <span>UART: 57600 BAUD</span>
              </div>
              
              <div class="optical-glass" id="fingerprint-glass" onclick="triggerFingerprintScan()">
                <span>👆</span>
              </div>

              <strong style="display: block; font-size: 0.85rem;">Place Finger on Scanner</strong>
              <small style="color: #94a3b8; font-size: 0.72rem;">Click the glowing optical glass to simulate biometric touch</small>

              <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem; justify-content: center;">
                <select id="sim-student-select" style="background:#0f172a; color:#fff; border:1px solid #334155; padding: 0.35rem 0.6rem; border-radius: var(--radius-sm); font-size: 0.78rem;">
                  <option value="T-001">Student (Slot #1 - T-001)</option>
                  <option value="T-002">Student (Slot #2 - T-002)</option>
                  <option value="UNKNOWN">Unregistered Fingerprint</option>
                </select>
              </div>
            </div>

            <!-- PCF8574 + 3x4 Matrix Keypad -->
            <div style="margin-top: 1.5rem; background: #0f172a; padding: 1rem; border-radius: 1rem; border: 1px solid #1e293b;">
              <div style="display: flex; justify-content: space-between; font-size: 0.68rem; color: #64748b; font-family: var(--font-mono); margin-bottom: 0.75rem;">
                <span>U2 / PCF8574 I2C EXPANDER</span>
                <span>3x4 MATRIX</span>
              </div>

              <div class="keypad-matrix">
                <button class="key-btn" onclick="pressKey('1')">1</button>
                <button class="key-btn" onclick="pressKey('2')">2</button>
                <button class="key-btn" onclick="pressKey('3')">3</button>
                <button class="key-btn" onclick="pressKey('4')">4</button>
                <button class="key-btn" onclick="pressKey('5')">5</button>
                <button class="key-btn" onclick="pressKey('6')">6</button>
                <button class="key-btn" onclick="pressKey('7')">7</button>
                <button class="key-btn" onclick="pressKey('8')">8</button>
                <button class="key-btn" onclick="pressKey('9')">9</button>
                <button class="key-btn" onclick="pressKey('*')" style="color:#f59e0b;">*</button>
                <button class="key-btn" onclick="pressKey('0')">0</button>
                <button class="key-btn" onclick="pressKey('#')" style="color:#10b981;">#</button>
              </div>

              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem;">
                <span style="font-size: 0.72rem; color: #64748b;">Buffer: <strong id="keypad-buffer" style="color:#fff; font-family:var(--font-mono);">—</strong></span>
                <button class="button button-quiet" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;" onclick="clearKeypad()">Clear</button>
              </div>
            </div>

          </div>

        </div>
      </div>

      <!-- Right: Live Firmware UART & REST Communication Monitor -->
      <div class="serial-monitor">
        <div class="serial-header">
          <span>SERIAL MONITOR / 115200 BAUD</span>
          <button class="button button-quiet" style="color:#94a3b8; font-size:0.7rem;" onclick="document.getElementById('serial-log').innerHTML = '';">Clear</button>
        </div>
        <div class="serial-body" id="serial-log"></div>
      </div>

    </div>
  </div>

  <script>
    // Hardware State Emulation
    const HARDWARE_UID = 'ESP32-ESP32 DevKit V1-CHIP99';
    let deviceApiKey = ''; // Obtained upon activation
    let isOnline = true;
    let keypadBuffer = '';
    let offlineEventQueue = [];
    let heartbeatTimer = null;
    let rtcTimer = null;
    let activeSession = null;

    // Audio Buzzer Context
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    function beep(freq = 2400, duration = 120) {
      try {
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.frequency.value = freq;
        gain.gain.value = 0.1;
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        setTimeout(() => osc.stop(), duration);
      } catch (e) {}
    }

    function logSerial(text, type = 'hw') {
      const el = document.getElementById('serial-log');
      const time = new Date().toTimeString().split(' ')[0];
      const div = document.createElement('div');
      div.className = `log-${type}`;
      div.innerText = `[${time}] ${text}`;
      el.appendChild(div);
      el.scrollTop = el.scrollHeight;
    }

    function updateOled(mainText, subText = '', topRowRight = null) {
      document.getElementById('oled-main-text').innerText = mainText;
      if (topRowRight !== null) document.getElementById('oled-wifi-icon').innerText = topRowRight;
      document.getElementById('oled-queue-count').innerText = offlineEventQueue.length > 0 ? `Q:${offlineEventQueue.length}` : 'SD:OK';
    }

    // Hardware Boot Sequence
    async function hardwareBoot() {
      logSerial('--- SYSTEM BOOTING ---', 'hw');
      logSerial('Checking I2C Bus (SDA=GPIO4, SCL=GPIO5)...', 'hw');
      logSerial('Found 0x3C (OLED12864), 0x20 (PCF8574 Keypad), 0x68 (DS3231 RTC)', 'hw');
      logSerial('Initializing SPI MicroSD card...', 'hw');
      logSerial('Opening UART to R307 Optical Sensor (57600 baud)... Ready', 'hw');

      updateOled('MYATTENDA V2', 'STARTING FIRMWARE');
      beep(1800, 100);

      // Start DS3231 RTC Clock
      if (rtcTimer) clearInterval(rtcTimer);
      rtcTimer = setInterval(() => {
        const now = new Date();
        const str = now.toTimeString().split(' ')[0];
        document.getElementById('oled-rtc-clock').innerText = str;
        document.getElementById('ds3231-time-label').innerText = str + ' RTC';
      }, 1000);

      // Check device status via Backend
      await pollDeviceConfig();

      // Start Heartbeat interval
      if (heartbeatTimer) clearInterval(heartbeatTimer);
      heartbeatTimer = setInterval(sendHeartbeat, 15000);
    }

    async function pollDeviceConfig() {
      if (!isOnline) {
        logSerial('Wi-Fi Offline: Using local cached configuration from MicroSD', 'hw');
        updateOled('OFFLINE MODE', 'STANDALONE SD');
        return;
      }

      logSerial(`Connecting to Wi-Fi SSID "Campus-IoT"... Connected (RSSI: -58 dBm)`, 'hw');

      try {
        // Send heartbeat to check status
        const res = await fetch('/api/v1/device/heartbeat.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer test_key' // Middleware accepts device test
          },
          body: JSON.stringify({
            firmware_version: '2.1.0',
            battery_voltage: 4.15,
            wifi_rssi: -58,
            sd_status: 'ok',
            fingerprint_count: 1,
            sync_status: offlineEventQueue.length > 0 ? 'pending_events' : 'synced'
          })
        });

        const data = await res.json();
        logSerial(`HTTP Heartbeat 200 OK | Active Session: ${data.has_active_session ? 'YES' : 'NONE'}`, 'rx');

        if (data.has_active_session) {
          activeSession = data.active_session;
          updateOled(`${activeSession.course_code}`, 'SCAN FINGER', 'WIFI:OK');
        } else {
          activeSession = null;
          updateOled('READY FOR CLASS', 'NO ACTIVE SESSION', 'WIFI:OK');
        }

      } catch (err) {
        logSerial(`Heartbeat Network Error: ${err.message}`, 'err');
        updateOled('API ERROR', 'RETRYING...');
      }
    }

    async function sendHeartbeat() {
      if (!isOnline) return;
      await pollDeviceConfig();
      // If we have queued events, trigger sync
      if (offlineEventQueue.length > 0) {
        await syncOfflineEvents();
      }
    }

    // Keypad Matrix Input (via PCF8574)
    function pressKey(key) {
      beep(2200, 60);
      keypadBuffer += key;
      document.getElementById('keypad-buffer').innerText = keypadBuffer;
      logSerial(`PCF8574 Key Press Detected: '${key}' (Buffer: ${keypadBuffer})`, 'hw');

      // If key is '#', trigger activation code submit
      if (key === '#') {
        const pin = keypadBuffer.replace('#', '').trim();
        keypadBuffer = '';
        document.getElementById('keypad-buffer').innerText = '—';
        if (pin.length > 0) {
          submitActivationCode(pin);
        }
      }
    }

    function clearKeypad() {
      keypadBuffer = '';
      document.getElementById('keypad-buffer').innerText = '—';
    }

    async function submitActivationCode(pin) {
      logSerial(`Ingesting One-Time PIN '${pin}' for Activation Handshake...`, 'tx');
      updateOled('ACTIVATING...', 'CONNECTING SERVER');
      try {
        const res = await fetch('/api/v1/device/activation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            hardware_uid: HARDWARE_UID,
            activation_code: pin
          })
        });
        const data = await res.json();
        if (data.success) {
          deviceApiKey = data.api_key;
          beep(3000, 250);
          logSerial(`ACTIVATION SUCCESS! Received Bearer Token: ${data.api_key.substring(0, 16)}...`, 'rx');
          updateOled('ACTIVATED!', 'DEVICE READY');
          setTimeout(pollDeviceConfig, 2000);
        } else {
          beep(1200, 300);
          logSerial(`Activation Rejected: ${data.error}`, 'err');
          updateOled('INVALID PIN', 'TRY AGAIN');
        }
      } catch (e) {
        logSerial(`Activation Request Failed: ${e.message}`, 'err');
      }
    }

    // Optical R307 Fingerprint Touch Trigger
    async function triggerFingerprintScan() {
      const glass = document.getElementById('fingerprint-glass');
      const student = document.getElementById('sim-student-select').value;

      glass.classList.add('scanning');
      beep(2600, 120);
      updateOled('SCANNING FINGER...', 'READING R307');
      logSerial('R307 Optical Sensor: Touch detected. Capturing minutiae points...', 'hw');

      setTimeout(async () => {
        glass.classList.remove('scanning');

        if (student === 'UNKNOWN') {
          glass.classList.add('error');
          beep(1000, 300);
          logSerial('R307 Sensor: No matching fingerprint template in local slots.', 'err');
          updateOled('NO MATCH', 'TRY AGAIN');
          setTimeout(pollDeviceConfig, 2500);
          return;
        }

        // Matched Fingerprint
        glass.classList.add('match');
        beep(3200, 150);
        setTimeout(() => glass.classList.remove('match'), 1500);

        const eventUid = `EVT-${HARDWARE_UID.substring(0,8)}-${Date.now()}`;
        const timestamp = new Date().toISOString().replace('T', ' ').substring(0, 19);
        const slot = student === 'T-001' ? 1 : 2;

        logSerial(`R307 Match! Slot #${slot} -> Student ${student}. Event UID: ${eventUid}`, 'hw');
        updateOled(`MATCH: ${student}`, `RECORDED [${slot}]`);

        const eventPayload = {
          event_uid: eventUid,
          session_id: activeSession ? activeSession.id : 1,
          slot_number: slot,
          temporary_id: student,
          timestamp: timestamp,
          status: 'present'
        };

        if (isOnline) {
          // Direct Sync to Server
          logSerial(`Transmitting Attendance Event ${eventUid} to REST API...`, 'tx');
          try {
            const res = await fetch('/api/v1/device/sync.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer test_key'
              },
              body: JSON.stringify({ events: [eventPayload] })
            });
            const data = await res.json();
            logSerial(`REST Sync Response 200 OK: Processed ${data.synced_count}, Skipped ${data.skipped_count}`, 'rx');
          } catch (e) {
            logSerial(`Sync failed over Wi-Fi. Queuing to MicroSD...`, 'err');
            queueToSD(eventPayload);
          }
        } else {
          // Offline MicroSD Logging
          queueToSD(eventPayload);
        }

        setTimeout(pollDeviceConfig, 2000);
      }, 700);
    }

    function queueToSD(eventPayload) {
      offlineEventQueue.push(eventPayload);
      document.getElementById('sd-led').classList.add('led-red');
      setTimeout(() => document.getElementById('sd-led').classList.remove('led-red'), 300);
      document.getElementById('sd-file-status').innerText = `FAT32 · ${offlineEventQueue.length} queued events`;
      logSerial(`[OFFLINE] Wrote event to MicroSD "ATTEND.LOG" (Queue size: ${offlineEventQueue.length})`, 'hw');
      updateOled('SAVED TO SD', `OFFLINE Q:${offlineEventQueue.length}`);
    }

    async function syncOfflineEvents() {
      if (offlineEventQueue.length === 0 || !isOnline) return;

      logSerial(`Reconnection detected! Syncing ${offlineEventQueue.length} queued events from MicroSD...`, 'tx');
      try {
        const res = await fetch('/api/v1/device/sync.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer test_key'
          },
          body: JSON.stringify({ events: offlineEventQueue })
        });
        const data = await res.json();
        if (data.success) {
          logSerial(`BATCH SYNC COMPLETE! MariaDB updated: ${data.synced_count} inserted, ${data.skipped_count} deduplicated.`, 'rx');
          offlineEventQueue = [];
          document.getElementById('sd-file-status').innerText = 'FAT32 · 0 queued events';
          updateOled('SYNC COMPLETE', 'ROSTER UPDATED');
          beep(3000, 150);
        }
      } catch (e) {
        logSerial(`Batch sync error: ${e.message}`, 'err');
      }
    }

    // Toggle Wi-Fi Online/Offline Switch
    function toggleWifi(checked) {
      isOnline = checked;
      const led = document.getElementById('esp-wifi-led');
      const label = document.getElementById('wifi-label');

      if (isOnline) {
        led.className = 'led-indicator led-blue';
        label.innerText = 'Wi-Fi Connected';
        logSerial('Wi-Fi Radio re-enabled: IP assigned 192.168.1.104', 'hw');
        pollDeviceConfig();
      } else {
        led.className = 'led-indicator led-off';
        label.innerText = 'Wi-Fi Offline (SD Mode)';
        logSerial('Wi-Fi Radio disconnected. Entering Autonomous Offline Mode.', 'hw');
        updateOled('OFFLINE MODE', 'STORING TO SD', 'WIFI:OFF');
      }
    }

    function hardwareReset() {
      hardwareBoot();
    }

    // Boot on page load
    window.addEventListener('load', hardwareBoot);
  </script>
</body>
</html>
