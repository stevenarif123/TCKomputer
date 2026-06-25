<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/admin-auth.php';

requireAdmin();
$current_page = 'system-tester.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Pre-Flight Tester - Admin TCKomputer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .tester-container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .tester-header { text-align: center; margin-bottom: 30px; }
        .tester-header h1 { font-size: 28px; color: var(--admin-on-surface); font-weight: 800; margin-bottom: 8px; }
        .tester-header p { color: var(--admin-on-surface-variant); }
        .tester-card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; border: 1px solid var(--admin-outline-variant); }
        .tester-card-header { padding: 16px 20px; border-bottom: 1px solid var(--admin-outline-variant); display: flex; justify-content: space-between; align-items: center; background: #f8fafc; cursor: pointer; }
        .tester-card-title { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 8px; }
        .tester-card-body { padding: 0; display: none; }
        .tester-card-body.open { display: block; }
        .test-item { padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .test-item:last-child { border-bottom: none; }
        .test-name { font-weight: 500; color: #334155; }
        .test-result { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #e2e8f0; color: #475569; }
        .status-running { background: #dbeafe; color: #1e40af; animation: pulse 2s infinite; }
        .status-pass { background: #dcfce7; color: #166534; }
        .status-fail { background: #fee2e2; color: #991b1b; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .run-all-btn { display: block; width: 100%; max-width: 300px; margin: 0 auto 30px; padding: 14px; background: var(--admin-primary); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(var(--admin-primary-rgb), 0.3); }
        .run-all-btn:hover { background: #000; transform: translateY(-2px); }
        .run-all-btn:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; box-shadow: none; }
        .summary-box { text-align: center; padding: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-top: 20px; display: none; }
        .summary-box.fail { background: #fef2f2; border-color: #fecaca; }
        .summary-title { font-size: 20px; font-weight: 800; margin-bottom: 8px; }
    </style>
</head>
<body class="admin-body">

    <?php include 'includes/admin-header.php'; ?>

    <main class="admin-main">
        <div class="tester-container">
            <div class="tester-header">
                <h1>System Pre-Flight Tester</h1>
                <p>Verifikasi kesiapan sistem sebelum diluncurkan ke publik.</p>
            </div>

            <button id="btnRunAll" class="run-all-btn" onclick="runAllTests()">
                <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">play_circle</span>
                Jalankan Semua Pengujian
            </button>

            <!-- Test Module: Environment -->
            <div class="tester-card" id="module-env">
                <div class="tester-card-header" onclick="toggleCard('env')">
                    <div class="tester-card-title">
                        <span class="material-symbols-outlined">dns</span>
                        1. Environment & Server Health
                    </div>
                    <span class="status-badge status-pending" id="badge-env">Pending</span>
                </div>
                <div class="tester-card-body" id="body-env">
                    <!-- results injected here -->
                    <div style="padding: 15px; text-align:center; color: #94a3b8; font-size: 13px;">Menunggu untuk dijalankan...</div>
                </div>
            </div>

            <!-- Test Module: Database -->
            <div class="tester-card" id="module-db">
                <div class="tester-card-header" onclick="toggleCard('db')">
                    <div class="tester-card-title">
                        <span class="material-symbols-outlined">database</span>
                        2. Database Integrity
                    </div>
                    <span class="status-badge status-pending" id="badge-db">Pending</span>
                </div>
                <div class="tester-card-body" id="body-db">
                    <div style="padding: 15px; text-align:center; color: #94a3b8; font-size: 13px;">Menunggu untuk dijalankan...</div>
                </div>
            </div>

            <!-- Test Module: E2E Public Pages -->
            <div class="tester-card" id="module-e2e">
                <div class="tester-card-header" onclick="toggleCard('e2e')">
                    <div class="tester-card-title">
                        <span class="material-symbols-outlined">public</span>
                        3. Public Pages Access (E2E)
                    </div>
                    <span class="status-badge status-pending" id="badge-e2e">Pending</span>
                </div>
                <div class="tester-card-body" id="body-e2e">
                    <div style="padding: 15px; text-align:center; color: #94a3b8; font-size: 13px;">Menunggu untuk dijalankan...</div>
                </div>
            </div>

            <!-- Test Module: Unit Tests -->
            <div class="tester-card" id="module-unit">
                <div class="tester-card-header" onclick="toggleCard('unit')">
                    <div class="tester-card-title">
                        <span class="material-symbols-outlined">bug_report</span>
                        4. Business Logic (Unit Tests)
                    </div>
                    <span class="status-badge status-pending" id="badge-unit">Pending</span>
                </div>
                <div class="tester-card-body" id="body-unit">
                    <div style="padding: 15px; text-align:center; color: #94a3b8; font-size: 13px;">Menunggu untuk dijalankan...</div>
                </div>
            </div>

            <div id="summary-box" class="summary-box">
                <div class="summary-title" id="summary-title">Semua Pengujian Lulus! 🎉</div>
                <p id="summary-desc" style="color: #4b5563;">Sistem TCKomputer 100% siap untuk melayani pembeli.</p>
            </div>
        </div>
    </main>

    <script>
        function toggleCard(id) {
            document.getElementById('body-' + id).classList.toggle('open');
        }

        async function runTest(moduleId) {
            const badge = document.getElementById('badge-' + moduleId);
            const body = document.getElementById('body-' + moduleId);
            
            badge.className = 'status-badge status-running';
            badge.innerText = 'Running...';
            body.innerHTML = '<div style="padding: 20px; text-align:center;"><span class="material-symbols-outlined" style="animation: spin 1s linear infinite;">sync</span></div>';
            body.classList.add('open');

            try {
                const response = await fetch(`api-tester.php?action=${moduleId}`);
                const data = await response.json();
                
                let html = '';
                if(data.details && data.details.length > 0) {
                    data.details.forEach(item => {
                        const statusClass = item.status === 'PASS' ? 'status-pass' : 'status-fail';
                        const icon = item.status === 'PASS' ? 'check_circle' : 'cancel';
                        const iconColor = item.status === 'PASS' ? '#166534' : '#991b1b';
                        html += `
                            <div class="test-item">
                                <div>
                                    <div class="test-name">${item.name}</div>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">${item.message}</div>
                                </div>
                                <div class="test-result">
                                    <span class="status-badge ${statusClass}">${item.status}</span>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    html = `<div style="padding: 15px; color: red;">${data.message || 'Unknown error'}</div>`;
                }
                
                body.innerHTML = html;

                if (data.success) {
                    badge.className = 'status-badge status-pass';
                    badge.innerText = 'PASSED';
                    return true;
                } else {
                    badge.className = 'status-badge status-fail';
                    badge.innerText = 'FAILED';
                    return false;
                }
            } catch (err) {
                body.innerHTML = `<div style="padding: 15px; color: red;">Error executing test: ${err.message}</div>`;
                badge.className = 'status-badge status-fail';
                badge.innerText = 'ERROR';
                return false;
            }
        }

        async function runAllTests() {
            const btn = document.getElementById('btnRunAll');
            const summaryBox = document.getElementById('summary-box');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px; animation: spin 1s linear infinite;">sync</span> Menguji Sistem...';
            summaryBox.style.display = 'none';

            // Reset all
            ['env', 'db', 'e2e', 'unit'].forEach(id => {
                document.getElementById('badge-' + id).className = 'status-badge status-pending';
                document.getElementById('badge-' + id).innerText = 'Pending';
            });

            // Run sequentially
            const r1 = await runTest('env');
            const r2 = await runTest('db');
            const r3 = await runTest('e2e');
            const r4 = await runTest('unit');

            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">play_circle</span> Ulangi Pengujian';

            const allPassed = r1 && r2 && r3 && r4;
            summaryBox.style.display = 'block';
            if (allPassed) {
                summaryBox.className = 'summary-box';
                document.getElementById('summary-title').innerText = 'Semua Pengujian Lulus! 🎉';
                document.getElementById('summary-desc').innerText = 'Sistem TCKomputer 100% siap untuk melayani pembeli di Production.';
            } else {
                summaryBox.className = 'summary-box fail';
                document.getElementById('summary-title').innerText = 'Ditemukan Masalah ⚠️';
                document.getElementById('summary-desc').innerText = 'Mohon perbaiki pengujian yang gagal (warna merah) sebelum merilis aplikasi ke publik.';
            }
        }
    </script>
    <style>
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</body>
</html>
