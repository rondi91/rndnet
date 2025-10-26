<?php
// Sertakan service agar data router dapat digunakan pada dashboard.
require_once __DIR__ . '/../includes/RouterService.php';

// Inisialisasi repository dan service.
$repository = new RouterRepository(__DIR__ . '/../data/routers.json');
$routerService = new RouterService($repository);

// Ambil data dasar yang akan ditampilkan pada dashboard.
$routers = $routerService->listRouters();
$pppoeData = $routerService->getPppoeDashboardData();
$pppoeServers = $pppoeData['servers'] ?? [];
$pppoeTotals = $pppoeData['totals'] ?? [];

$totalRouters = count($routers);
$totalPppoeServers = $pppoeTotals['pppoe_servers'] ?? count($pppoeServers);
$totalPppoeSessions = $pppoeTotals['active_sessions'] ?? 0;
$totalInactiveUsers = $pppoeTotals['inactive_users'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard MikroTik</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="dashboard-layout" data-dashboard>
    <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="true" aria-controls="dashboard-sidebar" aria-label="Tampilkan atau sembunyikan navigasi">
        ☰
    </button>
    <!-- Navigasi samping untuk memudahkan perpindahan menu -->
    <aside class="sidebar" id="dashboard-sidebar">
        <h2>Router Control</h2>
        <nav>
            <a href="#overview">Ikhtisar</a>
            <a href="#pppoe">Manajemen PPPoE</a>
            <a href="index.php">Tambah Router</a>
        </nav>
    </aside>

    <section class="dashboard-content">
        <header class="dashboard-header" id="overview">
            <h1>Dashboard Monitoring MikroTik</h1>
            <p>Tampilan ringkas untuk memantau router dan koneksi PPPoE yang telah Anda registrasikan.</p>
        </header>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Total Router</h3>
                <p><strong data-total-routers><?php echo $totalRouters; ?></strong></p>
                <small>Jumlah seluruh router yang tersimpan di aplikasi.</small>
            </div>
            <div class="dashboard-card">
                <h3>Server PPPoE</h3>
                <p><strong data-total-pppoe-servers><?php echo $totalPppoeServers; ?></strong></p>
                <small>Router yang ditandai sebagai penyedia layanan PPPoE.</small>
            </div>
            <div class="dashboard-card">
                <h3>Koneksi PPPoE Aktif</h3>
                <p><strong data-total-pppoe-active><?php echo $totalPppoeSessions; ?></strong></p>
                <small>Total koneksi PPPoE yang sedang tersambung.</small>
            </div>
            <div class="dashboard-card">
                <h3>Pengguna Tidak Aktif</h3>
                <p><strong data-total-pppoe-inactive><?php echo $totalInactiveUsers; ?></strong></p>
                <small>Jumlah pengguna PPPoE yang tercatat namun tidak aktif.</small>
            </div>
        </div>

        <section class="dashboard-card pppoe-management-card" id="pppoe" style="margin-top: 24px;">
            <div class="section-heading">
                <h2>Manajemen PPPoE Real-time</h2>
                <p>Data di bawah diperbarui otomatis menggunakan API <code>evilfreelancer/routeros-api-php</code>. Anda dapat mencari, mengurutkan, serta memantau pengguna aktif dan tidak aktif setiap server.</p>
            </div>

            <?php if (empty($pppoeServers)): ?>
                <div class="alert alert-error">Belum ada server PPPoE yang ditambahkan. Silakan registrasikan melalui halaman utama.</div>
            <?php else: ?>
                <div class="pppoe-controls">
                    <div class="control-group">
                        <label for="pppoe-search">Pencarian Cepat</label>
                        <input type="search" id="pppoe-search" class="pppoe-search" placeholder="Cari pengguna, profil, atau alamat PPPoE..." aria-label="Pencarian cepat PPPoE">
                    </div>
                </div>

                <div class="pppoe-server-layout" data-pppoe-container>
                    <div class="pppoe-server-tabs" role="tablist" data-tab-list>
                        <?php foreach ($pppoeServers as $index => $server): ?>
                            <?php
                            $tabId = 'pppoe-server-' . $index;
                            $isActive = $index === 0;
                            ?>
                            <button
                                type="button"
                                class="pppoe-server-tab<?php echo $isActive ? ' active' : ''; ?>"
                                role="tab"
                                data-server-tab="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
                                aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                                aria-controls="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <?php echo htmlspecialchars($server['router_name'] ?? $server['router_ip'] ?? 'Server', ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="pppoe-server-panels" data-panel-list>
                        <?php foreach ($pppoeServers as $index => $server): ?>
                            <?php
                            $lastRefreshed = $server['last_refreshed'] ?? ($pppoeTotals['generated_at'] ?? '');
                            $lastRefreshedDisplay = '';

                            if (!empty($lastRefreshed)) {
                                try {
                                    $dt = new DateTime($lastRefreshed);
                                    $lastRefreshedDisplay = $dt->format('d-m-Y H:i:s');
                                } catch (Exception $exception) {
                                    $lastRefreshedDisplay = htmlspecialchars($lastRefreshed, ENT_QUOTES, 'UTF-8');
                                }
                            }

                            $tabId = 'pppoe-server-' . $index;
                            $isActive = $index === 0;
                            ?>
                            <article
                                class="pppoe-server-panel<?php echo $isActive ? ' active' : ''; ?>"
                                id="<?php echo htmlspecialchars($tabId, ENT_QUOTES, 'UTF-8'); ?>"
                                role="tabpanel"
                                data-router-ip="<?php echo htmlspecialchars($server['router_ip'], ENT_QUOTES, 'UTF-8'); ?>"
                                aria-hidden="<?php echo $isActive ? 'false' : 'true'; ?>"
                            >
                                <header class="pppoe-server-header">
                                    <div>
                                        <h3><?php echo htmlspecialchars($server['router_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="pppoe-server-meta">
                                            <span>IP: <?php echo htmlspecialchars($server['router_ip'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (!empty($server['notes'])): ?>
                                                <span>Catatan: <?php echo htmlspecialchars($server['notes'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <?php if ($lastRefreshedDisplay !== ''): ?>
                                                <span>Pembaruan: <?php echo htmlspecialchars($lastRefreshedDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="pppoe-server-stats">
                                        <div class="pppoe-server-stat">
                                            <strong><?php echo (int) ($server['total_sessions'] ?? 0); ?></strong>
                                            <span>Aktif</span>
                                        </div>
                                        <div class="pppoe-server-stat secondary">
                                            <strong><?php echo (int) ($server['total_inactive'] ?? 0); ?></strong>
                                            <span>Tidak Aktif</span>
                                        </div>
                                    </div>
                                </header>

                                <?php if (empty($server['reachable'])): ?>
                                    <div class="alert alert-error">
                                        Tidak dapat terhubung ke server PPPoE ini.
                                        <?php if (!empty($server['error'])): ?>
                                            <br><small><?php echo htmlspecialchars($server['error'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php if (!empty($server['error'])): ?>
                                        <div class="alert alert-error">
                                            Terjadi kesalahan saat membaca data PPPoE.<br>
                                            <small><?php echo htmlspecialchars($server['error'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </div>
                                    <?php endif; ?>

                                    <div class="pppoe-panel-tabs" role="tablist">
                                        <button type="button" class="pppoe-panel-tab active" data-panel-tab="active" aria-selected="true">Aktif</button>
                                        <button type="button" class="pppoe-panel-tab" data-panel-tab="inactive" aria-selected="false">Tidak Aktif</button>
                                    </div>

                                    <div class="pppoe-panel-views">
                                        <div class="pppoe-panel-view active" data-panel-view="active" aria-hidden="false">
                                            <?php if (empty($server['sessions'])): ?>
                                                <div class="alert alert-info">Belum ada koneksi PPPoE yang aktif saat ini.</div>
                                            <?php else: ?>
                                                <table class="pppoe-session-table" data-session-table data-per-page="10">
                                                    <thead>
                                                        <tr>
                                                            <th>
                                                                <button type="button" class="sort-button" data-sort="user">
                                                                    Pengguna
                                                                    <span class="sort-indicator" aria-hidden="true"></span>
                                                                </button>
                                                            </th>
                                                            <th>Profil</th>
                                                            <th>
                                                                <button type="button" class="sort-button" data-sort="address">
                                                                    Alamat PPPoE
                                                                    <span class="sort-indicator" aria-hidden="true"></span>
                                                                </button>
                                                            </th>
                                                            <th>
                                                                <button type="button" class="sort-button" data-sort="uptime">
                                                                    Uptime
                                                                    <span class="sort-indicator" aria-hidden="true"></span>
                                                                </button>
                                                            </th>
                                                            <th>Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($server['sessions'] as $session): ?>
                                                            <?php
                                                            $address = $session['address'] ?? '';
                                                            $addressUrl = filter_var($address, FILTER_VALIDATE_IP) ? 'http://' . $address : null;
                                                            $secretId = $session['secret_id'] ?? '';
                                                            ?>
                                                            <tr class="pppoe-session-row"
                                                                data-user="<?php echo htmlspecialchars(mb_strtolower($session['user'] ?? '', 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-profile="<?php echo htmlspecialchars(mb_strtolower($session['profile'] ?? '', 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-address="<?php echo htmlspecialchars(mb_strtolower($address, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-uptime="<?php echo (int) ($session['uptime_seconds'] ?? 0); ?>"
                                                                data-secret-id="<?php echo htmlspecialchars($secretId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <td><?php echo htmlspecialchars($session['user'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td><?php echo htmlspecialchars($session['profile'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td>
                                                                    <?php if ($addressUrl !== null): ?>
                                                                        <a href="<?php echo htmlspecialchars($addressUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                                                            <?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?>
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($session['uptime'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td>
                                                                    <button
                                                                        type="button"
                                                                        class="pppoe-delete-button"
                                                                        data-secret-id="<?php echo htmlspecialchars($secretId, ENT_QUOTES, 'UTF-8'); ?>"
                                                                        <?php echo $secretId === '' ? 'disabled title="Secret tidak ditemukan"' : ''; ?>
                                                                    >
                                                                        Hapus
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                <div class="pagination-controls" data-pagination>
                                                    <button type="button" data-page-action="prev" aria-label="Halaman sebelumnya">&laquo;</button>
                                                    <span data-pagination-info>Halaman 1</span>
                                                    <button type="button" data-page-action="next" aria-label="Halaman berikutnya">&raquo;</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="pppoe-panel-view" data-panel-view="inactive" aria-hidden="true">
                                            <?php if (empty($server['inactive_users'])): ?>
                                                <div class="alert alert-info subtle">Belum ada pengguna yang terdeteksi tidak aktif.</div>
                                            <?php else: ?>
                                                <table class="pppoe-inactive-table" data-inactive-table>
                                                    <thead>
                                                        <tr>
                                                            <th>Pengguna</th>
                                                            <th>Profil</th>
                                                            <th>Status</th>
                                                            <th>Terakhir Logout</th>
                                                            <th>Keterangan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($server['inactive_users'] as $inactive): ?>
                                                            <tr class="pppoe-inactive-row"
                                                                data-user="<?php echo htmlspecialchars(mb_strtolower($inactive['user'] ?? '', 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-profile="<?php echo htmlspecialchars(mb_strtolower($inactive['profile'] ?? '', 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                                                                <td><?php echo htmlspecialchars($inactive['user'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td><?php echo htmlspecialchars($inactive['profile'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td><?php echo !empty($inactive['disabled']) ? 'Disabled' : 'Enabled'; ?></td>
                                                                <td><?php echo htmlspecialchars($inactive['last_logged_out'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                                <td><?php echo htmlspecialchars($inactive['comment'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dashboard = document.querySelector('[data-dashboard]');
    const sidebar = document.getElementById('dashboard-sidebar');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const container = document.querySelector('[data-pppoe-container]');
    const tabList = container ? container.querySelector('[data-tab-list]') : null;
    const panelList = container ? container.querySelector('[data-panel-list]') : null;
    const searchInput = document.querySelector('.pppoe-search');
    const totals = {
        routers: document.querySelector('[data-total-routers]'),
        servers: document.querySelector('[data-total-pppoe-servers]'),
        active: document.querySelector('[data-total-pppoe-active]'),
        inactive: document.querySelector('[data-total-pppoe-inactive]'),
    };

    const sortState = {
        field: 'user',
        direction: 'asc',
    };

    let searchTerm = '';

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const normalize = (value) => escapeHtml(String(value ?? '').toLowerCase());

    const formatDateTime = (value) => {
        if (!value) {
            return '';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return escapeHtml(value);
        }

        try {
            return new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'short',
                timeStyle: 'medium',
            }).format(date);
        } catch (error) {
            return escapeHtml(value);
        }
    };

    const getActivePanel = () => (panelList ? panelList.querySelector('.pppoe-server-panel.active') : null);

    const buildSessionRows = (sessions = []) => {
        if (!sessions.length) {
            return '';
        }

        return sessions.map((session) => {
            const user = escapeHtml(session.user ?? '');
            const profile = escapeHtml(session.profile ?? '');
            const address = escapeHtml(session.address ?? '');
            const addressUrl = session.address && /^(?:\d{1,3}\.){3}\d{1,3}$/.test(session.address)
                ? `http://${escapeHtml(session.address)}`
                : null;
            const uptime = escapeHtml(session.uptime ?? '');
            const uptimeSeconds = Number(session.uptime_seconds ?? 0);
            const secretId = escapeHtml(session.secret_id ?? '');
            const buttonDisabled = secretId === '' ? ' disabled title="Secret tidak ditemukan"' : '';

            return `
                <tr class="pppoe-session-row"
                    data-user="${normalize(session.user)}"
                    data-profile="${normalize(session.profile)}"
                    data-address="${normalize(session.address)}"
                    data-uptime="${uptimeSeconds}"
                    data-secret-id="${secretId}"
                >
                    <td>${user}</td>
                    <td>${profile}</td>
                    <td>
                        ${addressUrl ? `<a href="${addressUrl}" target="_blank" rel="noopener noreferrer">${address}</a>` : address}
                    </td>
                    <td>${uptime}</td>
                    <td>
                        <button type="button" class="pppoe-delete-button" data-secret-id="${secretId}"${buttonDisabled}>Hapus</button>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const buildInactiveRows = (inactiveUsers = []) => {
        if (!inactiveUsers.length) {
            return '';
        }

        return inactiveUsers.map((user) => {
            const name = escapeHtml(user.user ?? '');
            const profile = escapeHtml(user.profile ?? '');
            const status = user.disabled ? 'Disabled' : 'Enabled';
            const lastLoggedOut = escapeHtml(user.last_logged_out ?? '-');
            const comment = escapeHtml(user.comment ?? '-');

            return `
                <tr class="pppoe-inactive-row"
                    data-user="${normalize(user.user)}"
                    data-profile="${normalize(user.profile)}">
                    <td>${name}</td>
                    <td>${profile}</td>
                    <td>${status}</td>
                    <td>${lastLoggedOut}</td>
                    <td>${comment}</td>
                </tr>
            `;
        }).join('');
    };

    const buildServerTab = (server, index) => {
        const tabId = `pppoe-server-${index}`;
        const isActive = index === 0;
        const label = escapeHtml(server.router_name ?? server.router_ip ?? `Server ${index + 1}`);

        return `
            <button
                type="button"
                class="pppoe-server-tab${isActive ? ' active' : ''}"
                role="tab"
                data-server-tab="${tabId}"
                aria-selected="${isActive ? 'true' : 'false'}"
                aria-controls="${tabId}"
            >
                ${label}
            </button>
        `;
    };

    const buildServerPanel = (server, index) => {
        const routerName = escapeHtml(server.router_name ?? '');
        const routerIp = escapeHtml(server.router_ip ?? '');
        const notes = escapeHtml(server.notes ?? '');
        const lastRefreshed = formatDateTime(server.last_refreshed ?? server.generated_at ?? '');
        const totalSessions = Number(server.total_sessions ?? 0);
        const totalInactive = Number(server.total_inactive ?? 0);
        const reachable = Boolean(server.reachable);
        const errorMessage = escapeHtml(server.error ?? '');
        const sessions = Array.isArray(server.sessions) ? server.sessions : [];
        const inactiveUsers = Array.isArray(server.inactive_users) ? server.inactive_users : [];
        const tabId = `pppoe-server-${index}`;
        const isActive = index === 0;

        const activeContent = sessions.length
            ? `
                <table class="pppoe-session-table" data-session-table data-per-page="10">
                    <thead>
                        <tr>
                            <th>
                                <button type="button" class="sort-button" data-sort="user">
                                    Pengguna
                                    <span class="sort-indicator" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th>Profil</th>
                            <th>
                                <button type="button" class="sort-button" data-sort="address">
                                    Alamat PPPoE
                                    <span class="sort-indicator" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th>
                                <button type="button" class="sort-button" data-sort="uptime">
                                    Uptime
                                    <span class="sort-indicator" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${buildSessionRows(sessions)}
                    </tbody>
                </table>
                <div class="pagination-controls" data-pagination>
                    <button type="button" data-page-action="prev" aria-label="Halaman sebelumnya">&laquo;</button>
                    <span data-pagination-info>Halaman 1</span>
                    <button type="button" data-page-action="next" aria-label="Halaman berikutnya">&raquo;</button>
                </div>
            `
            : '<div class="alert alert-info">Belum ada koneksi PPPoE yang aktif saat ini.</div>';

        const inactiveContent = inactiveUsers.length
            ? `
                <table class="pppoe-inactive-table" data-inactive-table>
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>Profil</th>
                            <th>Status</th>
                            <th>Terakhir Logout</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${buildInactiveRows(inactiveUsers)}
                    </tbody>
                </table>
            `
            : '<div class="alert alert-info subtle">Belum ada pengguna yang terdeteksi tidak aktif.</div>';

        const unreachableBlock = !reachable
            ? `
                <div class="alert alert-error">
                    Tidak dapat terhubung ke server PPPoE ini.
                    ${errorMessage ? `<br><small>${errorMessage}</small>` : ''}
                </div>
            `
            : '';

        const errorBlock = reachable && errorMessage
            ? `
                <div class="alert alert-error">
                    Terjadi kesalahan saat membaca data PPPoE.<br>
                    <small>${errorMessage}</small>
                </div>
            `
            : '';

        const reachableContent = reachable
            ? `
                ${errorBlock}
                <div class="pppoe-panel-tabs" role="tablist">
                    <button type="button" class="pppoe-panel-tab active" data-panel-tab="active" aria-selected="true">Aktif</button>
                    <button type="button" class="pppoe-panel-tab" data-panel-tab="inactive" aria-selected="false">Tidak Aktif</button>
                </div>
                <div class="pppoe-panel-views">
                    <div class="pppoe-panel-view active" data-panel-view="active" aria-hidden="false">
                        ${activeContent}
                    </div>
                    <div class="pppoe-panel-view" data-panel-view="inactive" aria-hidden="true">
                        ${inactiveContent}
                    </div>
                </div>
            `
            : unreachableBlock;

        return `
            <article
                class="pppoe-server-panel${isActive ? ' active' : ''}"
                id="${tabId}"
                role="tabpanel"
                data-router-ip="${routerIp}"
                aria-hidden="${isActive ? 'false' : 'true'}"
            >
                <header class="pppoe-server-header">
                    <div>
                        <h3>${routerName}</h3>
                        <p class="pppoe-server-meta">
                            <span>IP: ${routerIp}</span>
                            ${notes ? `<span>Catatan: ${notes}</span>` : ''}
                            ${lastRefreshed ? `<span>Pembaruan: ${lastRefreshed}</span>` : ''}
                        </p>
                    </div>
                    <div class="pppoe-server-stats">
                        <div class="pppoe-server-stat">
                            <strong>${totalSessions}</strong>
                            <span>Aktif</span>
                        </div>
                        <div class="pppoe-server-stat secondary">
                            <strong>${totalInactive}</strong>
                            <span>Tidak Aktif</span>
                        </div>
                    </div>
                </header>
                ${reachableContent || ''}
            </article>
        `;
    };

    const applyPagination = (table, requestedPage = 1) => {
        if (!table) {
            return;
        }

        const perPage = Number(table.dataset.perPage || 10);
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const visibleRows = rows.filter((row) => row.dataset.searchHidden !== '1');
        const totalPages = visibleRows.length ? Math.ceil(visibleRows.length / perPage) : 0;
        const page = totalPages ? Math.min(Math.max(requestedPage, 1), totalPages) : 1;

        rows.forEach((row) => {
            if (row.dataset.searchHidden === '1') {
                row.style.display = 'none';
            } else {
                row.style.display = '';
            }
        });

        if (totalPages) {
            const start = (page - 1) * perPage;
            const end = start + perPage;
            visibleRows.forEach((row, index) => {
                row.style.display = index >= start && index < end ? '' : 'none';
            });
        }

        table.dataset.currentPage = String(totalPages ? page : 1);

        const pagination = table.closest('.pppoe-panel-view')?.querySelector('[data-pagination]');
        if (pagination) {
            const info = pagination.querySelector('[data-pagination-info]');
            const prev = pagination.querySelector('[data-page-action="prev"]');
            const next = pagination.querySelector('[data-page-action="next"]');

            if (info) {
                info.textContent = totalPages ? `Halaman ${page} dari ${totalPages}` : 'Tidak ada data';
            }

            if (prev) {
                prev.disabled = !totalPages || page <= 1;
            }

            if (next) {
                next.disabled = !totalPages || page >= totalPages;
            }
        }
    };

    const setupPagination = (panel) => {
        const table = panel.querySelector('[data-session-table]');
        const pagination = panel.querySelector('[data-pagination]');

        if (!table || !pagination) {
            return;
        }

        applyPagination(table, Number(table.dataset.currentPage || 1));

        pagination.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-page-action]');

            if (!button) {
                return;
            }

            event.preventDefault();

            const current = Number(table.dataset.currentPage || 1);
            const action = button.dataset.pageAction;
            const perPage = Number(table.dataset.perPage || 10);
            const rows = Array.from(table.querySelectorAll('tbody tr')).filter((row) => row.dataset.searchHidden !== '1');
            const totalPages = rows.length ? Math.ceil(rows.length / perPage) : 0;

            if (!totalPages) {
                applyPagination(table, 1);
                return;
            }

            const nextPage = action === 'next' ? current + 1 : current - 1;
            applyPagination(table, nextPage);
        });
    };

    const setupPanelTabs = (panel) => {
        const tabButtons = panel.querySelectorAll('[data-panel-tab]');
        const views = panel.querySelectorAll('[data-panel-view]');

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.panelTab;

                tabButtons.forEach((btn) => {
                    btn.classList.toggle('active', btn === button);
                    btn.setAttribute('aria-selected', btn === button ? 'true' : 'false');
                });

                views.forEach((view) => {
                    const isActive = view.dataset.panelView === target;
                    view.classList.toggle('active', isActive);
                    view.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                });

                if (target === 'active') {
                    const table = panel.querySelector('[data-session-table]');
                    if (table) {
                        applyPagination(table, Number(table.dataset.currentPage || 1));
                    }
                }
            });
        });
    };

    const activateServerTab = (serverId) => {
        if (!container || !tabList || !panelList) {
            return;
        }

        const tabs = Array.from(tabList.querySelectorAll('[data-server-tab]'));
        const panels = Array.from(panelList.querySelectorAll('.pppoe-server-panel'));

        tabs.forEach((tab) => {
            const isActive = tab.dataset.serverTab === serverId;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const isActive = panel.id === serverId;
            panel.classList.toggle('active', isActive);
            panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        });

        container.dataset.activeServer = serverId;

        const activePanel = getActivePanel();
        if (activePanel) {
            const table = activePanel.querySelector('[data-session-table]');
            if (table) {
                applyPagination(table, Number(table.dataset.currentPage || 1));
            }
        }
    };

    const initializeServerPanels = () => {
        if (!container || !tabList || !panelList) {
            return;
        }

        const tabs = tabList.querySelectorAll('[data-server-tab]');
        const panels = panelList.querySelectorAll('.pppoe-server-panel');

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                activateServerTab(tab.dataset.serverTab);
            });
        });

        panels.forEach((panel) => {
            setupPanelTabs(panel);
            setupPagination(panel);
            setupSorting(panel);
        });

        const initialActive = container.dataset.activeServer
            || (tabList.querySelector('.pppoe-server-tab.active')?.dataset.serverTab)
            || (tabs.length ? tabs[0].dataset.serverTab : null);

        if (initialActive) {
            activateServerTab(initialActive);
        }
    };

    const renderServers = (servers) => {
        if (!container || !tabList || !panelList || !Array.isArray(servers)) {
            return;
        }

        const previousActive = container.dataset.activeServer || '';

        tabList.innerHTML = servers.map((server, index) => buildServerTab(server, index)).join('');
        panelList.innerHTML = servers.map((server, index) => buildServerPanel(server, index)).join('');

        if (previousActive) {
            container.dataset.activeServer = previousActive;
        } else {
            delete container.dataset.activeServer;
        }

        initializeServerPanels();

        updateSortIndicators();
        applySortToAllTables();
        applySearch(searchTerm);
    };

    const applySearch = (term) => {
        if (!panelList) {
            return;
        }

        const keyword = String(term ?? '').trim().toLowerCase();

        const panels = panelList.querySelectorAll('.pppoe-server-panel');
        panels.forEach((panel) => {
            const rows = panel.querySelectorAll('.pppoe-session-row, .pppoe-inactive-row');

            rows.forEach((row) => {
                if (!keyword) {
                    row.dataset.searchHidden = '';
                    row.style.display = '';
                    return;
                }

                const combined = [row.dataset.user, row.dataset.profile, row.dataset.address]
                    .filter(Boolean)
                    .join(' ');

                const match = combined.includes(keyword);
                row.dataset.searchHidden = match ? '' : '1';
                row.style.display = match ? '' : 'none';
            });

            const table = panel.querySelector('[data-session-table]');
            if (table) {
                applyPagination(table, Number(table.dataset.currentPage || 1));
            }
        });
    };

    const compareRows = (a, b, field, direction) => {
        const multiplier = direction === 'desc' ? -1 : 1;

        if (field === 'uptime') {
            const valueA = Number(a.dataset.uptime || 0);
            const valueB = Number(b.dataset.uptime || 0);

            if (valueA === valueB) {
                return 0;
            }

            return valueA > valueB ? multiplier : -multiplier;
        }

        const valueA = (a.dataset[field] || '').toString();
        const valueB = (b.dataset[field] || '').toString();

        return valueA.localeCompare(valueB) * multiplier;
    };

    const sortTable = (table, field, direction) => {
        if (!table) {
            return;
        }

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }

        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.sort((a, b) => compareRows(a, b, field, direction)).forEach((row) => tbody.appendChild(row));

        table.dataset.sortField = field;
        table.dataset.sortDirection = direction;
        table.dataset.currentPage = '1';
        applyPagination(table, 1);
    };

    const applySortToAllTables = () => {
        if (!panelList) {
            return;
        }

        const tables = panelList.querySelectorAll('[data-session-table]');
        tables.forEach((table) => sortTable(table, sortState.field, sortState.direction));
    };

    const updateSortIndicators = () => {
        const buttons = panelList ? panelList.querySelectorAll('.sort-button[data-sort]') : [];

        buttons.forEach((button) => {
            const field = button.dataset.sort;
            const isActive = field === sortState.field;

            button.classList.toggle('sorted', isActive);
            button.classList.toggle('sorted-asc', isActive && sortState.direction === 'asc');
            button.classList.toggle('sorted-desc', isActive && sortState.direction === 'desc');

            if (isActive) {
                button.setAttribute('aria-sort', sortState.direction === 'asc' ? 'ascending' : 'descending');
            } else {
                button.removeAttribute('aria-sort');
            }
        });
    };

    const setupSorting = (panel) => {
        const table = panel.querySelector('[data-session-table]');

        if (!table) {
            return;
        }

        const buttons = table.querySelectorAll('.sort-button[data-sort]');

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const field = button.dataset.sort || 'user';
                let direction = 'asc';

                if (sortState.field === field) {
                    direction = sortState.direction === 'asc' ? 'desc' : 'asc';
                }

                sortState.field = field;
                sortState.direction = direction;
                updateSortIndicators();
                applySortToAllTables();
            });
        });
    };

    const deleteSecret = async (button) => {
        if (!button || button.disabled) {
            return;
        }

        const panel = button.closest('.pppoe-server-panel');
        const routerIp = panel ? panel.dataset.routerIp : '';
        const secretId = button.dataset.secretId || '';

        if (!routerIp || !secretId) {
            alert('Informasi router atau secret PPPoE tidak lengkap.');
            return;
        }

        if (!window.confirm('Yakin ingin menghapus secret PPPoE ini?')) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Menghapus...';

        try {
            const response = await fetch('api/pppoe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    router_ip: routerIp,
                    secret_id: secretId,
                }),
            });

            let payload = null;

            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }

            if (response.ok && payload && payload.success) {
                await refreshData();

                return;
            }

            const message = payload && payload.message
                ? payload.message
                : 'Gagal menghapus secret PPPoE.';
            alert(message);
        } catch (error) {
            alert('Terjadi kesalahan saat menghapus secret PPPoE.');
        } finally {
            if (document.body.contains(button)) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    };

    const updateTotals = (summary = {}) => {
        if (totals.routers && typeof summary.routers !== 'undefined') {
            totals.routers.textContent = summary.routers;
        }

        if (totals.servers && typeof summary.pppoe_servers !== 'undefined') {
            totals.servers.textContent = summary.pppoe_servers;
        }

        if (totals.active && typeof summary.active_sessions !== 'undefined') {
            totals.active.textContent = summary.active_sessions;
        }

        if (totals.inactive && typeof summary.inactive_users !== 'undefined') {
            totals.inactive.textContent = summary.inactive_users;
        }
    };

    const refreshData = async () => {
        try {
            const response = await fetch('api/pppoe.php', { cache: 'no-store' });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();

            if (payload.totals) {
                updateTotals(payload.totals);
            }

            if (Array.isArray(payload.servers)) {
                renderServers(payload.servers);
            }
        } catch (error) {
            console.error('Gagal memperbarui data PPPoE:', error);
        }
    };

    if (sidebarToggle && dashboard && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            const isCollapsed = dashboard.classList.toggle('sidebar-collapsed');
            sidebarToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        });
    }

    if (searchInput) {
        searchTerm = searchInput.value || '';

        searchInput.addEventListener('input', (event) => {
            searchTerm = event.target.value || '';
            applySearch(searchTerm);
        });
    }

    if (container) {
        container.addEventListener('click', (event) => {
            const button = event.target.closest('.pppoe-delete-button');

            if (button) {
                event.preventDefault();
                deleteSecret(button);
            }
        });
    }

    initializeServerPanels();
    updateSortIndicators();
    applySortToAllTables();
    applySearch(searchTerm);

    setInterval(refreshData, 10000);
});
</script>
</body>
</html>
