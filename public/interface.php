
<?php
require_once __DIR__ . '/../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../data/routers.json');
$service = new RouterService($repository);
$trafficData = $service->getEthernetTrafficByRouter();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Interface Router</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="dashboard-layout" data-dashboard>
    <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="true" aria-controls="dashboard-sidebar" aria-label="Tampilkan atau sembunyikan navigasi">
        ☰
    </button>
    <aside class="sidebar" id="dashboard-sidebar">
        <h2>Router Control</h2>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="dashboard.php#pppoe">Manajemen PPPoE</a>
            <a class="active" href="interface.php">Interface</a>
            <a href="index.php">Tambah Router</a>
        </nav>
    </aside>

    <section class="dashboard-content interface-content">
        <header class="dashboard-header interface-header">
            <div class="interface-header-text">
                <h1>Monitoring Trafik Interface Ethernet</h1>
                <p>Pantau performa ethernet dari setiap router dan AP yang terhubung.</p>
            </div>
            <div class="interface-actions">
                <label class="refresh-interval" for="refresh-interval">Interval</label>
                <select id="refresh-interval" data-refresh-interval>
                    <option value="1000">1s</option>
                    <option value="3000">3s</option>
                    <option value="5000" selected>5s</option>
                    <option value="10000">10s</option>
                    <option value="15000">15s</option>
                    <option value="30000">30s</option>
                </select>
                <label class="manual-scale-control">
                    <span>Skala manual (Mbps)</span>
                    <input type="number" min="1" step="1" inputmode="decimal" placeholder="Auto" data-scale-input>
                </label>
                <button class="button" type="button" data-open-client-modal>+ Tambah Router AP</button>
                <button class="refresh-button" type="button" data-refresh-interfaces>Muat Ulang Data</button>
            </div>
        </header>

        <div class="interface-meta">
            <div class="interface-meta-item" data-interface-summary>
                <!-- Ringkasan akan dimuat oleh JavaScript -->
            </div>
            <span class="interface-scale-indicator" data-scale-indicator>Skala bar: otomatis</span>
            <span class="last-updated" data-interface-updated>Terakhir diperbarui: -</span>
        </div>

        <div class="interface-source-note" data-interface-source hidden></div>

        <div class="alert alert-error" data-interface-error hidden></div>
        <div class="alert alert-success subtle" data-interface-success hidden></div>

        <section class="interface-router-container" data-interface-routers>
            <!-- Data interface akan dirender oleh JavaScript -->
        </section>
    </section>
</div>

<script type="application/json" id="interface-initial-data"><?php echo json_encode($trafficData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>

<div class="modal-backdrop" data-client-modal hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="client-modal-title">
        <header class="modal-header">
            <h2 id="client-modal-title">Pilih AP dari PPPoE Active</h2>
            <button type="button" class="modal-close" data-close-client-modal aria-label="Tutup">&times;</button>
        </header>
        <div class="modal-body">
            <div class="modal-search">
                <input type="search" placeholder="Filter identity atau IP..." data-client-search>
                <button type="button" data-refresh-client-list>Segarkan</button>
            </div>
            <div class="modal-client-summary" data-client-summary></div>
            <div class="modal-client-list" data-client-list>
                <!-- Daftar AP dimuat melalui JavaScript -->
            </div>
        </div>
        <footer class="modal-footer">
            <form data-client-form>
                <div class="form-grid">
                    <label>
                        Nama AP
                        <input type="text" name="name" data-client-name readonly>
                    </label>
                    <label>
                        IP Address
                        <input type="text" name="ip_address" data-client-address readonly>
                    </label>
                    <label>
                        Username Router
                        <input type="text" name="username" data-client-username placeholder="admin">
                    </label>
                    <label>
                        Password Router
                        <input type="password" name="password" data-client-password placeholder="******">
                    </label>
                </div>
                <label>
                    Catatan
                    <textarea name="notes" rows="2" data-client-notes placeholder="Contoh: ditambahkan dari PPPoE"></textarea>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="is_pppoe_server" value="1" data-client-is-server>
                    Tandai sebagai server PPPoE
                </label>
                <div class="modal-footer-actions">
                    <button type="submit" class="button" data-client-submit disabled>Simpan Router</button>
                </div>
                <div class="modal-feedback" data-client-feedback></div>
            </form>
        </footer>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.getElementById('dashboard-sidebar');
    const refreshButton = document.querySelector('[data-refresh-interfaces]');
    const refreshSelect = document.querySelector('[data-refresh-interval]');
    const openModalButton = document.querySelector('[data-open-client-modal]');
    const clientModal = document.querySelector('[data-client-modal]');
    const closeModalButton = document.querySelector('[data-close-client-modal]');
    const summaryContainer = document.querySelector('[data-interface-summary]');
    const sourceInfoBox = document.querySelector('[data-interface-source]');
    const routersContainer = document.querySelector('[data-interface-routers]');
    const lastUpdated = document.querySelector('[data-interface-updated]');
    const scaleIndicator = document.querySelector('[data-scale-indicator]');
    const errorBox = document.querySelector('[data-interface-error]');
    const successBox = document.querySelector('[data-interface-success]');
    const initialDataElement = document.getElementById('interface-initial-data');
    const clientListContainer = document.querySelector('[data-client-list]');
    const clientSearchInput = document.querySelector('[data-client-search]');
    const clientSummary = document.querySelector('[data-client-summary]');
    const clientForm = document.querySelector('[data-client-form]');
    const clientNameField = document.querySelector('[data-client-name]');
    const clientAddressField = document.querySelector('[data-client-address]');
    const clientUsernameField = document.querySelector('[data-client-username]');
    const clientPasswordField = document.querySelector('[data-client-password]');
    const clientNotesField = document.querySelector('[data-client-notes]');
    const clientIsServerField = document.querySelector('[data-client-is-server]');
    const clientSubmitButton = document.querySelector('[data-client-submit]');
    const clientFeedback = document.querySelector('[data-client-feedback]');
    const refreshClientButton = document.querySelector('[data-refresh-client-list]');
    const manualScaleInput = document.querySelector('[data-scale-input]');

    let state = {};
    let refreshTimer = null;
    let isFetching = false;
    let queuedFetch = false;
    let clientsState = { clients: [] };
    let selectedClient = null;
    let selectedClientKey = null;
    const interfaceSelections = new Map();
    const rateHistory = new Map();
    const HISTORY_WINDOW_MS = 5 * 60 * 1000;
    let manualScaleBps = null;

    const parseInitialData = () => {
        if (!initialDataElement) {
            return {};
        }

        try {
            return JSON.parse(initialDataElement.textContent || '{}');
        } catch (error) {
            return {};
        }
    };

    const defaultClientSnapshot = () => ({
        generated_at: null,
        total_servers: 0,
        total_clients: 0,
        clients: [],
        routers: [],
    });

    clientsState = defaultClientSnapshot();

    const buildClientKey = (client) => {
        const username = String(client?.pppoe_username ?? client?.username ?? '').toLowerCase();
        const serverIp = String(client?.server_ip ?? '').toLowerCase();
        const address = String(client?.address ?? client?.client_address ?? '').toLowerCase();

        return `${serverIp}::${username}::${address}`;
    };

    const formatDateTime = (value) => {
        if (!value) {
            return '-';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        try {
            return new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'medium',
            }).format(date);
        } catch (error) {
            return value;
        }
    };

    const parseNumber = (value) => {
        if (typeof value === 'number' && Number.isFinite(value)) {
            return value;
        }

        const numeric = Number(String(value ?? '').replace(/[^\d.-]/g, ''));

        return Number.isFinite(numeric) ? numeric : 0;
    };

    const parseManualScaleValue = (value) => {
        const text = String(value ?? '').trim();

        if (text === '') {
            return null;
        }

        const numeric = Number(text.replace(',', '.'));

        if (!Number.isFinite(numeric) || numeric <= 0) {
            return null;
        }

        return numeric * 1_000_000;
    };

    const getInterfaceCapacityMbps = (iface, router) => {
        if (!iface) {
            const routerLevel = parseNumber(router?.link_capacity_mbps);

            return routerLevel > 0 ? routerLevel : null;
        }

        const numericCandidates = [
            parseNumber(iface.if_speed_mbps),
            parseNumber(iface.link_capacity_mbps),
        ];

        for (const candidate of numericCandidates) {
            if (Number.isFinite(candidate) && candidate > 0) {
                return candidate;
            }
        }

        const routerLevel = parseNumber(router?.link_capacity_mbps);

        if (Number.isFinite(routerLevel) && routerLevel > 0) {
            return routerLevel;
        }

        const bpsCandidates = [
            parseNumber(iface.if_speed_bps),
        ];

        for (const candidate of bpsCandidates) {
            if (Number.isFinite(candidate) && candidate > 0) {
                return candidate / 1_000_000;
            }
        }

        const labelCandidates = [iface.if_speed, iface.link_capacity];

        for (const label of labelCandidates) {
            const parsed = parseRateValue(label);

            if (Number.isFinite(parsed) && parsed > 0) {
                return parsed / 1_000_000;
            }
        }

        return null;
    };

    const parseRateValue = (value) => {
        if (typeof value === 'number' && Number.isFinite(value)) {
            return value;
        }

        const text = String(value ?? '').trim();

        if (text === '') {
            return 0;
        }

        const match = text.match(/([\d.,]+)/);

        if (!match) {
            return 0;
        }

        const numeric = parseFloat(match[1].replace(',', '.'));

        if (!Number.isFinite(numeric)) {
            return 0;
        }

        if (/g(bps|bit|b)?/i.test(text)) {
            return numeric * 1_000_000_000;
        }

        if (/m(bps|bit|b)?/i.test(text)) {
            return numeric * 1_000_000;
        }

        if (/k(bps|bit|b)?/i.test(text)) {
            return numeric * 1_000;
        }

        if (/bps/i.test(text)) {
            return numeric;
        }

        return numeric;
    };

    const formatRate = (value) => {
        const rate = parseRateValue(value);

        if (rate <= 0) {
            return '0 bps';
        }

        const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        const exponent = Math.min(Math.floor(Math.log(rate) / Math.log(1000)), units.length - 1);
        const converted = rate / (1000 ** exponent);

        return `${converted.toFixed(exponent === 0 ? 0 : 2)} ${units[exponent]}`;
    };

    const updateScaleIndicator = () => {
        if (!scaleIndicator) {
            return;
        }

        if (manualScaleBps && manualScaleBps > 0) {
            scaleIndicator.textContent = `Skala bar manual: ${formatRate(manualScaleBps)}`;
            scaleIndicator.dataset.mode = 'manual';
            scaleIndicator.hidden = false;
        } else {
            scaleIndicator.textContent = 'Skala bar: otomatis';
            scaleIndicator.dataset.mode = 'auto';
            scaleIndicator.hidden = false;
        }
    };

    const syncManualScaleFromInput = () => {
        if (!manualScaleInput) {
            manualScaleBps = null;
            updateScaleIndicator();

            return;
        }

        const rawValue = manualScaleInput.value;
        const parsed = parseManualScaleValue(rawValue);
        const isEmpty = rawValue.trim() === '';

        if (isEmpty) {
            manualScaleBps = null;
        } else if (parsed !== null) {
            manualScaleBps = parsed;
        } else {
            manualScaleBps = null;
            manualScaleInput.value = '';
        }

        manualScaleInput.classList.toggle('has-value', Boolean(manualScaleBps));

        updateScaleIndicator();

        if (state && Array.isArray(state.routers)) {
            renderRouters(state);
        }
    };

    const formatBytes = (value) => {
        const bytes = parseNumber(value);

        if (bytes === 0) {
            return '0 B';
        }

        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const converted = bytes / (1024 ** exponent);

        return `${converted.toFixed(exponent === 0 ? 0 : 2)} ${units[exponent]}`;
    };

    const formatInteger = (value) => {
        const numeric = parseNumber(value);

        if (!Number.isFinite(numeric) || numeric <= 0) {
            return '0';
        }

        try {
            return Math.round(numeric).toLocaleString('id-ID');
        } catch (error) {
            return `${Math.round(numeric)}`;
        }
    };

    const makeHistoryKey = (routerKey, interfaceName) => `${routerKey}::${interfaceName}`;

    const parseJsonSafe = async (response) => {
        const text = await response.text();
        const trimmed = text.trim();

        if (trimmed === '') {
            return {};
        }

        try {
            return JSON.parse(trimmed);
        } catch (error) {
            const snippet = trimmed.split(/\r?\n/).slice(0, 3).join(' ').slice(0, 200);
            const status = response?.status ?? 0;
            const message = snippet !== ''
                ? `Respons tidak valid dari server (status ${status}): ${snippet}`
                : `Respons tidak valid dari server (status ${status}).`;

            throw new Error(message);
        }
    };

    const updateRateHistory = (data) => {
        const routers = Array.isArray(data?.routers) ? data.routers : [];
        const parsedTimestamp = Date.parse(data?.generated_at ?? '');
        const timestamp = Number.isNaN(parsedTimestamp) ? Date.now() : parsedTimestamp;
        const cutoff = timestamp - HISTORY_WINDOW_MS;
        const activeKeys = new Set();

        routers.forEach((router, index) => {
            const routerIp = router?.router_ip ?? '';
            const clientKey = router?.client_key ?? '';
            const routerKey = clientKey || (routerIp !== '' ? routerIp : `router-${index}`);
            const interfaces = Array.isArray(router?.interfaces) ? router.interfaces : [];

            interfaces.forEach((iface) => {
                const name = iface?.name;

                if (!name) {
                    return;
                }

                const key = makeHistoryKey(routerKey, name);
                const rxRate = parseRateValue(iface.rx_rate);
                const txRate = parseRateValue(iface.tx_rate);
                const history = rateHistory.get(key) ?? [];

                history.push({ timestamp, rx: rxRate, tx: txRate });

                const filtered = history.filter((entry) => entry.timestamp >= cutoff);
                rateHistory.set(key, filtered);
                activeKeys.add(key);
            });
        });

        const now = Date.now();
        const currentCutoff = now - HISTORY_WINDOW_MS;

        for (const [key, history] of Array.from(rateHistory.entries())) {
            if (!activeKeys.has(key)) {
                const filtered = (history || []).filter((entry) => entry.timestamp >= currentCutoff);

                if (filtered.length > 0) {
                    rateHistory.set(key, filtered);
                } else {
                    rateHistory.delete(key);
                }
            }
        }
    };

    const getHistoryPeak = (routerKey, interfaceName, direction) => {
        const key = makeHistoryKey(routerKey, interfaceName);
        const history = rateHistory.get(key);

        if (!history || history.length === 0) {
            return 0;
        }

        const field = direction === 'tx' ? 'tx' : 'rx';

        return history.reduce((max, entry) => {
            const value = Number.isFinite(entry[field]) ? entry[field] : 0;

            return Math.max(max, value);
        }, 0);
    };

    const computeRatePercent = (value, baseline) => {
        if (!Number.isFinite(value) || value <= 0) {
            return 0;
        }

        const base = Number.isFinite(baseline) && baseline > 0 ? baseline : value;
        const percent = (value / base) * 100;

        if (percent <= 0) {
            return 0;
        }

        return Math.max(2, Math.min(100, percent));
    };

    const determineRateLevel = (mbps) => {
        if (!Number.isFinite(mbps) || mbps < 0) {
            return 'low';
        }

        if (mbps >= 100) {
            return 'high';
        }

        if (mbps >= 50) {
            return 'medium';
        }

        return 'low';
    };

    const buildTrafficBar = (variant, percent, level) => {
        const clampedPercent = Number.isFinite(percent) ? Math.max(0, Math.min(100, percent)) : 0;
        const levelSuffix = level ? ` router-bar--level-${escapeHtml(level)}` : '';
        const progressLevelSuffix = level ? ` router-bar-progress--level-${escapeHtml(level)}` : '';
        const trackLevelSuffix = level ? ` router-bar-track--level-${escapeHtml(level)}` : '';

        return `
            <div class="router-bar router-bar--${escapeHtml(variant)}${levelSuffix}">
                <div class="router-bar-track${trackLevelSuffix}">
                    <span class="router-bar-progress router-bar-progress--${escapeHtml(variant)}${progressLevelSuffix}" style="width: ${clampedPercent}%"></span>
                </div>
            </div>
        `;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const showSuccess = (message) => {
        if (!successBox) {
            return;
        }

        successBox.textContent = message;
        successBox.hidden = false;

        setTimeout(() => {
            successBox.hidden = true;
        }, 6000);
    };

    const renderSummary = (data) => {
        const totalRouters = data.total_routers ?? 0;
        const totalInterfaces = data.total_interfaces ?? 0;
        const sourceLabel = data.source === 'router_clients' ? 'router_client.json' : 'daftar router utama';
        const deviceLabel = data.source === 'router_clients' ? 'router client' : 'router';

        summaryContainer.innerHTML = `
            Memantau <strong>${escapeHtml(totalInterfaces)}</strong> interface dari
            <strong>${escapeHtml(totalRouters)}</strong> ${escapeHtml(deviceLabel)}
            (sumber: <em>${escapeHtml(sourceLabel)}</em>).
        `;
    };

    const renderSourceInfo = (data) => {
        if (!sourceInfoBox) {
            return;
        }

        const fileMap = data?.data_files;

        if (!fileMap || typeof fileMap !== 'object') {
            sourceInfoBox.hidden = true;
            sourceInfoBox.innerHTML = '';

            return;
        }

        const labelMap = {
            client_snapshot: 'Snapshot router_client.json',
            router_storage: 'Daftar router utama',
            api_endpoint: 'Endpoint API',
            service_class: 'Service pengolah data',
            client_class: 'Klien RouterOS',
            active_source: 'Mode sumber aktif',
        };

        const entries = Object.entries(fileMap)
            .filter(([, value]) => typeof value === 'string' && value.trim() !== '')
            .map(([key, value]) => {
                const label = labelMap[key] || key;

                return `<li><strong>${escapeHtml(label)}</strong>: <code>${escapeHtml(value)}</code></li>`;
            });

        if (entries.length === 0) {
            sourceInfoBox.hidden = true;
            sourceInfoBox.innerHTML = '';

            return;
        }

        sourceInfoBox.hidden = false;
        sourceInfoBox.innerHTML = `
            <p>Data trafik interface dibaca dari berkas berikut:</p>
            <ul>${entries.join('')}</ul>
            <p class="interface-source-note__hint">Jika angka RX/TX belum bergerak, periksa kredensial router dan pastikan dependensi <code>evilfreelancer/routeros-api-php</code> sudah terpasang melalui <code>composer install</code>.</p>
        `;
    };

    const renderRouter = (router, index) => {
        const interfaces = Array.isArray(router.interfaces) ? router.interfaces : [];
        const routerIp = router.router_ip ?? '';
        const clientKey = router.client_key ?? '';
        const routerKey = clientKey || (routerIp !== '' ? routerIp : `router-${index}`);
        const routerName = router.router_name ?? routerIp ?? 'Router';
        const serverLabel = router.server_name || router.server_ip ? `Server: ${router.server_name || router.server_ip}` : '';
        const pppoeLabel = router.pppoe_username ? `PPPoe: ${router.pppoe_username}` : '';
        const noteLabel = router.notes ? `Catatan: ${router.notes}` : '';

        const metaLines = [serverLabel, pppoeLabel, noteLabel].filter((line) => line !== '');
        const metaHtml = metaLines.length > 0
            ? `<div class="router-card-meta">${metaLines.map((line) => `<span>${escapeHtml(line)}</span>`).join('')}</div>`
            : '';

        const canDelete = clientKey !== '';
        const deleteButtonAttributes = [
            'type="button"',
            'class="button button-danger"',
            'data-remove-router',
            `data-router-key="${escapeHtml(routerKey)}"`,
            `data-router-ip="${escapeHtml(routerIp)}"`,
            `data-client-key="${escapeHtml(clientKey)}"`,
        ];

        if (!canDelete) {
            deleteButtonAttributes.push('disabled', 'title="Router ini berasal dari daftar utama dan tidak dapat dihapus di sini."');
        }

        const actionsHtml = `
            <div class="router-row-actions">
                <button ${deleteButtonAttributes.join(' ')}>Hapus</button>
            </div>
        `;

        if (!router.reachable) {
            const error = escapeHtml(router.error || 'Gagal terhubung ke router.');

            return {
                key: routerKey,
                markup: `
                    <div class="interface-router-row interface-router-row--error" data-router-ip="${escapeHtml(routerIp)}" data-router-key="${escapeHtml(routerKey)}" data-client-key="${escapeHtml(clientKey)}">
                        <div class="router-row-identity">
                            <strong>${escapeHtml(routerName)}</strong>
                            <span>${escapeHtml(routerIp || '-')}</span>
                            ${metaHtml}
                        </div>
                        <div class="router-row-message" role="alert">${error}</div>
                        ${actionsHtml}
                    </div>
                `,
            };
        }

        const availableInterfaces = interfaces.filter((item) => Boolean(item?.name));
        const availableNames = availableInterfaces.map((item) => item.name);
        let selectedName = interfaceSelections.get(routerKey);

        const preferredName = router.preferred_interface || router.iface || '';

        if (!selectedName && preferredName && availableNames.includes(preferredName)) {
            selectedName = preferredName;
            interfaceSelections.set(routerKey, selectedName);
        }

        if (!selectedName || !availableNames.includes(selectedName)) {
            selectedName = availableNames[0] ?? '';

            if (selectedName) {
                interfaceSelections.set(routerKey, selectedName);
            } else {
                interfaceSelections.delete(routerKey);
            }
        }

        const selectedInterface = availableInterfaces.find((item) => item.name === selectedName) ?? null;
        const selectOptions = availableInterfaces.map((item) => `
            <option value="${escapeHtml(item.name)}" ${item.name === selectedName ? 'selected' : ''}>${escapeHtml(item.name)}</option>
        `).join('');

        const statusClassMap = {
            running: 'status-chip--success',
            disabled: 'status-chip--muted',
            stopped: 'status-chip--warning',
        };

        const throughputRx = selectedInterface ? parseRateValue(selectedInterface.rx_rate) : 0;
        const throughputTx = selectedInterface ? parseRateValue(selectedInterface.tx_rate) : 0;
        const throughputRxMbps = throughputRx / 1_000_000;
        const throughputTxMbps = throughputTx / 1_000_000;
        const maxInterfaceRate = availableInterfaces.reduce((max, item) => {
            const rxValue = parseRateValue(item.rx_rate);
            const txValue = parseRateValue(item.tx_rate);

            return Math.max(max, rxValue, txValue);
        }, 0);
        const interfaceCapacityMbps = getInterfaceCapacityMbps(selectedInterface, router);
        const interfaceCapacityBps = Number.isFinite(interfaceCapacityMbps) && interfaceCapacityMbps > 0
            ? interfaceCapacityMbps * 1_000_000
            : null;
        const manualBaselineBps = manualScaleBps && manualScaleBps > 0 ? manualScaleBps : null;
        const fallbackRxBaseline = Number.isFinite(interfaceCapacityBps) && interfaceCapacityBps > 0
            ? interfaceCapacityBps
            : Math.max(getHistoryPeak(routerKey, selectedName, 'rx'), maxInterfaceRate, throughputRx);
        const fallbackTxBaseline = Number.isFinite(interfaceCapacityBps) && interfaceCapacityBps > 0
            ? interfaceCapacityBps
            : Math.max(getHistoryPeak(routerKey, selectedName, 'tx'), maxInterfaceRate, throughputTx);
        const rxBaseline = manualBaselineBps ?? fallbackRxBaseline;
        const txBaseline = manualBaselineBps ?? fallbackTxBaseline;
        const rxPercent = computeRatePercent(throughputRx, rxBaseline);
        const txPercent = computeRatePercent(throughputTx, txBaseline);
        const rxLevel = determineRateLevel(throughputRxMbps);
        const txLevel = determineRateLevel(throughputTxMbps);
        const manualScaleMbps = manualBaselineBps ? manualBaselineBps / 1_000_000 : null;

        let metricsAttributes = `data-interface-name="${escapeHtml(selectedInterface?.name ?? '')}"`;

        if (manualBaselineBps) {
            metricsAttributes += ` data-capacity-mbps="${escapeHtml(manualScaleMbps.toFixed(2))}" data-scale-mode="manual"`;
        } else if (Number.isFinite(interfaceCapacityMbps) && interfaceCapacityMbps > 0) {
            metricsAttributes += ` data-capacity-mbps="${escapeHtml(interfaceCapacityMbps.toFixed(2))}" data-scale-mode="capacity"`;
        } else {
            metricsAttributes += ' data-scale-mode="dynamic"';
        }

        const rxPercentOfScale = manualBaselineBps ? (throughputRx / manualBaselineBps) * 100 : null;
        const txPercentOfScale = manualBaselineBps ? (throughputTx / manualBaselineBps) * 100 : null;

        const formatPercent = (value) => {
            if (!Number.isFinite(value)) {
                return null;
            }

            const clamped = Math.max(0, Math.min(999, value));

            return `${clamped.toFixed(clamped >= 100 ? 0 : 1)}%`;
        };

        let rxLabelText = `RX ${formatRate(selectedInterface?.rx_rate)}`;
        const rxPercentLabel = formatPercent(rxPercentOfScale);

        if (rxPercentLabel) {
            rxLabelText += ` (${rxPercentLabel})`;
        }

        let txLabelText = `TX ${formatRate(selectedInterface?.tx_rate)}`;
        const txPercentLabel = formatPercent(txPercentOfScale);

        if (txPercentLabel) {
            txLabelText += ` (${txPercentLabel})`;
        }

        const rxLabel = escapeHtml(rxLabelText);
        const txLabel = escapeHtml(txLabelText);

        let scaleLegendHtml = '';

        if (manualBaselineBps) {
            scaleLegendHtml = `<div class="router-row-scale router-row-scale--manual">Skala manual: ${escapeHtml(formatRate(manualBaselineBps))}</div>`;
        } else if (Number.isFinite(interfaceCapacityMbps) && interfaceCapacityMbps > 0) {
            const capacityLabel = interfaceCapacityMbps >= 100
                ? interfaceCapacityMbps.toFixed(0)
                : interfaceCapacityMbps.toFixed(1);

            scaleLegendHtml = `<div class="router-row-scale router-row-scale--capacity">Skala kapasitas: ${escapeHtml(capacityLabel)} Mbps</div>`;
        } else if (Math.max(fallbackRxBaseline, fallbackTxBaseline) > 0) {
            const dynamicBaseline = Math.max(fallbackRxBaseline, fallbackTxBaseline);

            scaleLegendHtml = `<div class="router-row-scale router-row-scale--dynamic">Skala dinamis: ${escapeHtml(formatRate(dynamicBaseline))} (puncak 5 menit)</div>`;
        }

        const statusClass = selectedInterface ? (statusClassMap[selectedInterface.status] || 'status-chip--warning') : 'status-chip--muted';
        const metricsHtml = selectedInterface
            ? `
                <div class="router-row-metrics" ${metricsAttributes}>
                    <div class="router-row-interface-label">Interface: ${escapeHtml(selectedInterface.name)}</div>
                    <div class="router-row-bars">
                        <div class="router-row-bar-line router-row-bar-line--rx router-row-bar-line--level-${escapeHtml(rxLevel)}">
                            <span class="router-row-bar-label router-row-bar-label--rx router-row-bar-label--level-${escapeHtml(rxLevel)}">${rxLabel}</span>
                            ${buildTrafficBar('rx', rxPercent, rxLevel)}
                        </div>
                        <div class="router-row-bar-line router-row-bar-line--tx router-row-bar-line--level-${escapeHtml(txLevel)}">
                            <span class="router-row-bar-label router-row-bar-label--tx router-row-bar-label--level-${escapeHtml(txLevel)}">${txLabel}</span>
                            ${buildTrafficBar('tx', txPercent, txLevel)}
                        </div>
                    </div>
                    ${scaleLegendHtml}
                </div>
            `
            : '<div class="router-row-metrics router-row-metrics--empty">Tidak ada interface ethernet.</div>';

        const selectorHtml = availableInterfaces.length > 0
            ? `
                <div class="router-row-selector">
                    <select data-interface-select data-router-key="${escapeHtml(routerKey)}">
                        ${selectOptions}
                    </select>
                    <span class="status-chip ${statusClass}">${escapeHtml(selectedInterface?.status || 'Tidak diketahui')}</span>
                </div>
            `
            : '<div class="router-row-selector router-row-selector--empty">Tidak ada pilihan</div>';

        return {
            key: routerKey,
            markup: `
                <div class="interface-router-row" data-router-ip="${escapeHtml(routerIp)}" data-router-key="${escapeHtml(routerKey)}" data-client-key="${escapeHtml(clientKey)}">
                    <div class="router-row-identity">
                        <strong>${escapeHtml(routerName)}</strong>
                        <span>${escapeHtml(routerIp)}</span>
                        ${metaHtml}
                    </div>
                    ${metricsHtml}
                    <div class="router-row-controls">
                        ${selectorHtml}
                        ${actionsHtml}
                    </div>
                </div>
            `,
        };
    };

    const renderRouters = (data) => {
        const routers = Array.isArray(data.routers) ? data.routers : [];

        if (routers.length === 0) {
            routersContainer.innerHTML = '<div class="alert alert-info subtle">Belum ada router client yang tersimpan. Gunakan tombol "Tambah Router AP" untuk memilih PPPoE dan menyimpannya ke router_client.json.</div>';
            interfaceSelections.clear();

            return;
        }

        const rows = routers.map((router, index) => renderRouter(router, index));
        const activeKeys = new Set(rows.map((row) => row.key));

        routersContainer.innerHTML = rows.map((row) => row.markup).join('');

        interfaceSelections.forEach((_, key) => {
            if (!activeKeys.has(key)) {
                interfaceSelections.delete(key);
            }
        });
    };

    const renderAll = (data) => {
        updateRateHistory(data);
        state = data;
        updateScaleIndicator();
        renderSummary(data);
        renderSourceInfo(data);
        renderRouters(data);
        const generatedLabel = formatDateTime(data.generated_at);
        let updatedText = `Terakhir diperbarui: ${generatedLabel}`;

        if (data.source === 'router_clients' && data.client_snapshot_generated_at) {
            updatedText += ` • Daftar klien: ${formatDateTime(data.client_snapshot_generated_at)}`;
        }

        lastUpdated.textContent = updatedText;
        errorBox.hidden = true;
    };

    const fetchLatest = async () => {
        if (isFetching) {
            queuedFetch = true;
            return;
        }

        isFetching = true;
        try {
            refreshButton.disabled = true;
            const response = await fetch('api/interfaces.php', { cache: 'no-store' });

            if (!response.ok) {
                throw new Error(`Gagal memuat data (status ${response.status})`);
            }

            const data = await response.json();
            renderAll(data);
        } catch (error) {
            errorBox.textContent = error.message || 'Gagal memuat data interface.';
            errorBox.hidden = false;
        } finally {
            refreshButton.disabled = false;
            isFetching = false;

            if (queuedFetch) {
                queuedFetch = false;
                fetchLatest();
            }
        }
    };

    const scheduleRefresh = (triggerImmediate = false) => {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }

        const interval = Number(refreshSelect?.value ?? 5000);

        if (!Number.isFinite(interval) || interval <= 0) {
            return;
        }

        refreshTimer = setInterval(() => {
            fetchLatest();
        }, interval);

        if (triggerImmediate) {
            fetchLatest();
        }
    };

    const deleteRouterClient = async (button) => {
        const clientKey = button.getAttribute('data-client-key') || '';
        const routerIp = button.getAttribute('data-router-ip') || '';
        const routerKey = button.getAttribute('data-router-key') || '';

        if (!clientKey) {
            return;
        }

        if (!window.confirm('Hapus router ini dari daftar pemantauan interface?')) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Menghapus...';
        errorBox.hidden = true;

        try {
            const response = await fetch('api/router_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ client_key: clientKey, ip_address: routerIp }),
            });

            const result = await parseJsonSafe(response);

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Gagal menghapus router.');
            }

            interfaceSelections.delete(routerKey);
            showSuccess('Router berhasil dihapus dari daftar pemantauan.');
            await fetchLatest();
        } catch (error) {
            errorBox.textContent = error.message || 'Gagal menghapus router.';
            errorBox.hidden = false;
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    };

    const openModal = async () => {
        clientModal?.removeAttribute('hidden');
        clientModal?.classList.add('is-visible');
        clientFeedback.textContent = '';
        clientFeedback.classList.remove('error', 'success');
        await loadClients(true);
        requestAnimationFrame(() => {
            clientSearchInput?.focus();
        });
    };

    const closeModal = () => {
        clientModal?.setAttribute('hidden', '');
        clientModal?.classList.remove('is-visible');
        selectedClient = null;
        clientForm?.reset();
        clientFeedback.textContent = '';
        clientFeedback.classList.remove('error', 'success');
        updateClientForm();
    };

    const loadClients = async (force = false) => {
        try {
            clientFeedback.textContent = '';
            clientFeedback.classList.remove('error', 'success');

            const params = new URLSearchParams({ directory: '1' });

            if (force) {
                params.set('refresh', '1');
            }

            const response = await fetch(`api/router_clients.php?${params.toString()}`, { cache: 'no-store' });

            if (!response.ok) {
                throw new Error(`Gagal memuat daftar AP (status ${response.status})`);
            }

            const data = await response.json();
            clientsState = Object.assign(defaultClientSnapshot(), data);

            if (selectedClientKey) {
                const matched = Array.isArray(clientsState.clients)
                    ? clientsState.clients.find((client) => buildClientKey(client) === selectedClientKey)
                    : null;

                if (matched) {
                    selectedClient = matched;
                } else {
                    selectedClient = null;
                    selectedClientKey = null;
                    updateClientForm();
                }
            }

            updateClientForm();
            renderClientList();
        } catch (error) {
            clientsState = defaultClientSnapshot();
            renderClientList();
            clientFeedback.textContent = error.message || 'Gagal memuat daftar AP.';
            clientFeedback.classList.add('error');
        }
    };

    const renderClientList = () => {
        if (!clientListContainer) {
            return;
        }

        const clients = Array.isArray(clientsState.clients) ? clientsState.clients : [];
        const query = (clientSearchInput?.value || '').toLowerCase();

        const filtered = clients.filter((client) => {
            const fields = [
                client.client_name ?? '',
                client.address ?? client.client_address ?? '',
                client.server_name ?? '',
                client.server_ip ?? '',
                client.pppoe_username ?? client.username ?? '',
                client.profile ?? '',
                client.comment ?? '',
            ];

            return fields.some((field) => String(field).toLowerCase().includes(query));
        });

        if (clientSummary) {
            const totalClients = clientsState.total_clients ?? clients.length;
            const serverCount = clientsState.total_servers ?? 0;

            clientSummary.innerHTML = `Menemukan <strong>${escapeHtml(filtered.length)}</strong> dari <strong>${escapeHtml(totalClients)}</strong> akun PPPoE pada <strong>${escapeHtml(serverCount)}</strong> server.`;
        }

        if (filtered.length === 0) {
            clientListContainer.innerHTML = '<p class="empty">Tidak ada akun PPPoE yang cocok dengan pencarian.</p>';
            clientsState.filtered = [];

            return;
        }

        clientsState.filtered = filtered;
        const selectedKey = selectedClientKey || (selectedClient ? buildClientKey(selectedClient) : null);

        clientListContainer.innerHTML = filtered.map((client) => {
            const key = buildClientKey(client);
            const selected = selectedKey && key === selectedKey;
            const statusLabel = client.status === 'active' ? 'Aktif' : 'Tidak aktif';
            const statusClass = client.status === 'active' ? 'status-active' : 'status-inactive';
            const address = client.address ?? client.client_address ?? '-';
            const usernameLabel = client.pppoe_username ?? client.username ?? client.client_name ?? '-';
            const serverLabel = client.server_name || client.server_ip || '-';
            const lastInfo = client.status === 'active'
                ? `Uptime ${escapeHtml(client.uptime || '-')}`
                : `Logout ${escapeHtml(formatDateTime(client.last_logged_out) || '-')}`;

            return `
                <article class="client-option ${selected ? 'client-option--selected' : ''}" data-client-entry>
                    <div class="client-option-content">
                        <div class="client-option-text">
                            <span class="client-option-name">${escapeHtml(client.client_name || usernameLabel || 'Tanpa nama')}</span>
                            <span class="client-option-detail">${escapeHtml(address || '-')}</span>
                            <span class="client-option-subdetail">server ${escapeHtml(serverLabel)}</span>
                        </div>
                    </div>
                    <div class="client-option-meta">
                        <span class="client-option-status ${statusClass}">${escapeHtml(statusLabel)}</span>
                        <span class="client-option-info">${lastInfo}</span>
                        <button type="button" class="client-option-button" data-pick-client data-client-key="${escapeHtml(key)}">Pilih</button>
                    </div>
                </article>
            `;
        }).join('');
    };

    const updateClientForm = () => {
        if (!selectedClient) {
            clientNameField.value = '';
            clientAddressField.value = '';
            selectedClientKey = null;
            if (!clientUsernameField.value) {
                clientUsernameField.value = 'rondi';
            }

            if (!clientPasswordField.value) {
                clientPasswordField.value = '21184662';
            }
        } else {
            clientNameField.value = selectedClient.client_name || '';
            const addressValue = selectedClient.client_address;
            clientAddressField.value = addressValue && addressValue !== '-' ? addressValue : '';
            if (!clientNotesField.value) {
                clientNotesField.value = `Ditambahkan dari ${selectedClient.server_name || selectedClient.server_ip || 'PPPoe'}`;
            }
            selectedClientKey = buildClientKey(selectedClient);
        }

        if (selectedClient) {
            if (!clientUsernameField.value) {
                clientUsernameField.value = 'rondi';
            }

            if (!clientPasswordField.value) {
                clientPasswordField.value = '21184662';
            }
        }

        const usernameFilled = clientUsernameField.value.trim() !== '';
        const passwordFilled = clientPasswordField.value.trim() !== '';

        clientSubmitButton.disabled = !(selectedClient && usernameFilled && passwordFilled);
    };

    sidebarToggle?.addEventListener('click', () => {
        const isExpanded = sidebarToggle.getAttribute('aria-expanded') === 'true';
        sidebarToggle.setAttribute('aria-expanded', String(!isExpanded));
        sidebar?.classList.toggle('collapsed');
    });

    refreshButton?.addEventListener('click', () => {
        fetchLatest();
    });

    refreshSelect?.addEventListener('change', () => {
        scheduleRefresh(true);
    });

    manualScaleInput?.addEventListener('input', () => {
        syncManualScaleFromInput();
    });

    manualScaleInput?.addEventListener('change', () => {
        syncManualScaleFromInput();
    });

    manualScaleInput?.addEventListener('blur', () => {
        syncManualScaleFromInput();
    });

    manualScaleInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            syncManualScaleFromInput();
        }
    });

    routersContainer?.addEventListener('change', (event) => {
        const select = event.target.closest('[data-interface-select]');

        if (!select) {
            return;
        }

        const routerKey = select.getAttribute('data-router-key') || '';

        if (routerKey) {
            interfaceSelections.set(routerKey, select.value);
        }

        renderRouters(state);
    });

    routersContainer?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-router]');

        if (!button) {
            return;
        }

        if (button.disabled) {
            return;
        }

        deleteRouterClient(button);
    });

    openModalButton?.addEventListener('click', () => {
        openModal();
    });

    closeModalButton?.addEventListener('click', () => {
        closeModal();
    });

    clientModal?.addEventListener('click', (event) => {
        if (event.target === clientModal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !clientModal?.hasAttribute('hidden')) {
            closeModal();
        }
    });

    clientSearchInput?.addEventListener('input', () => {
        renderClientList();
    });

    refreshClientButton?.addEventListener('click', () => {
        loadClients(true);
    });

    clientListContainer?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-pick-client]');

        if (!button) {
            return;
        }

        const key = button.getAttribute('data-client-key') || '';

        if (!key) {
            return;
        }

        const clients = Array.isArray(clientsState.clients) ? clientsState.clients : [];
        const matched = clients.find((client) => buildClientKey(client) === key);

        if (!matched) {
            clientFeedback.textContent = 'Akun PPPoE tidak ditemukan. Muat ulang daftar dan coba lagi.';
            clientFeedback.classList.add('error');

            return;
        }

        selectedClient = matched;
        selectedClientKey = key;
        clientFeedback.textContent = '';
        clientFeedback.classList.remove('error', 'success');
        updateClientForm();
        renderClientList();
    });

    clientForm?.addEventListener('input', () => {
        updateClientForm();
    });

    clientForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!selectedClient) {
            clientFeedback.textContent = 'Silakan pilih AP terlebih dahulu.';
            clientFeedback.classList.add('error');

            return;
        }

        const payload = {
            name: clientNameField.value,
            ip_address: clientAddressField.value,
            username: clientUsernameField.value,
            password: clientPasswordField.value,
            notes: clientNotesField.value,
            is_pppoe_server: clientIsServerField.checked ? 1 : 0,
            pppoe_client: {
                server_ip: selectedClient.server_ip ?? '',
                server_name: selectedClient.server_name ?? '',
                pppoe_username: selectedClient.pppoe_username ?? selectedClient.username ?? '',
                client_name: selectedClient.client_name ?? selectedClient.pppoe_username ?? '',
                profile: selectedClient.profile ?? '',
                status: selectedClient.status ?? '',
                address: selectedClient.address ?? selectedClient.client_address ?? '',
                comment: selectedClient.comment ?? '',
                last_logged_out: selectedClient.last_logged_out ?? '',
                secret_id: selectedClient.secret_id ?? '',
            },
        };

        if (!payload.ip_address && selectedClient) {
            payload.ip_address = selectedClient.address ?? selectedClient.client_address ?? '';
        }

        clientSubmitButton.disabled = true;
        clientSubmitButton.textContent = 'Menyimpan...';
        clientFeedback.textContent = '';
        clientFeedback.classList.remove('error', 'success');

        try {
            const response = await fetch('api/router_add.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                const errors = Array.isArray(result.errors) ? result.errors.join(', ') : result.message;
                throw new Error(errors || 'Gagal menambahkan router.');
            }

            showSuccess('Router berhasil ditambahkan dari PPPoE active.');
            closeModal();
            fetchLatest();
        } catch (error) {
            clientFeedback.textContent = error.message || 'Gagal menambahkan router.';
            clientFeedback.classList.add('error');
        } finally {
            clientSubmitButton.textContent = 'Simpan Router';
            clientSubmitButton.disabled = false;
        }
    });

    syncManualScaleFromInput();

    const initialData = parseInitialData();
    renderAll(initialData);
    scheduleRefresh(true);

});
</script>
</body>
</html>
