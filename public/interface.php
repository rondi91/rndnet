<?php
require_once __DIR__ . '/../includes/RouterService.php';

$repository = new RouterRepository(__DIR__ . '/../data/routers.json');
$service = new RouterService($repository);
$bootstrapData = $service->getEthernetTrafficBootstrap();
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
                <label class="interface-filter-control">
                    <span>Cari Router</span>
                    <input type="search" placeholder="Nama, IP, interface..." data-router-filter>
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
            <span class="interface-filter-summary" data-router-filter-summary hidden></span>
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

<script type="application/json" id="interface-initial-data"><?php echo json_encode($bootstrapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>

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
            <div class="modal-selection-preview" data-client-preview>
                <p>Pilih akun PPPoE aktif maupun tidak aktif untuk menambahkan router ke monitoring interface.</p>
                <p class="hint">Router akan disimpan otomatis dengan kredensial <code>rondi</code> / <code>21184662</code> dan ditandai sebagai server PPPoE.</p>
            </div>
            <div class="modal-footer-actions">
                <button type="button" class="button" data-client-submit disabled>Tambahkan Router</button>
            </div>
            <div class="modal-feedback" data-client-feedback></div>
        </footer>
</div>
</div>


<div class="modal-backdrop" data-bandwidth-config-modal hidden>
    <div class="modal modal-bandwidth-config" role="dialog" aria-modal="true" aria-labelledby="bandwidth-config-title">
        <header class="modal-header">
            <h2 id="bandwidth-config-title">Bandwidth Test</h2>
            <button type="button" class="modal-close" data-close-bandwidth-config aria-label="Tutup">&times;</button>
        </header>
        <div class="modal-body">
            <form data-bandwidth-config-form>
                <div class="bandwidth-config-info">
                    <span class="bandwidth-config-label">AP:</span>
                    <span class="bandwidth-config-value" data-bandwidth-config-router>-</span>
                </div>
                <div class="bandwidth-config-info">
                    <span class="bandwidth-config-label">Interface:</span>
                    <span class="bandwidth-config-value" data-bandwidth-config-interface>-</span>
                </div>
                <div class="bandwidth-config-info">
                    <span class="bandwidth-config-label">Server:</span>
                    <span class="bandwidth-config-value" data-bandwidth-config-server>-</span>
                </div>
                <div class="bandwidth-config-grid">
                    <label>
                        <span>Direction</span>
                        <select data-bandwidth-config-direction>
                            <option value="both">Both</option>
                            <option value="tx">Transmit (TX)</option>
                            <option value="rx">Receive (RX)</option>
                        </select>
                    </label>
                    <label>
                        <span>Protocol</span>
                        <select data-bandwidth-config-protocol>
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                        </select>
                    </label>
                    <label>
                        <span>Duration (s)</span>
                        <input type="number" min="1" max="60" step="1" value="10" data-bandwidth-config-duration>
                    </label>
                </div>
                <div class="bandwidth-config-actions">
                    <button type="button" class="button button-outline" data-close-bandwidth-config>Tutup</button>
                    <button type="submit" class="button router-bandwidth-button" data-bandwidth-config-run aria-busy="false">
                        <span class="button-spinner" aria-hidden="true"></span>
                        <span data-bandwidth-config-run-label>Jalankan</span>
                    </button>
                </div>
                <div class="bandwidth-config-feedback" data-bandwidth-config-feedback hidden></div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop" data-bandwidth-modal hidden>
    <div class="modal modal-bandwidth" role="dialog" aria-modal="true" aria-labelledby="bandwidth-modal-title">
        <header class="modal-header">
            <h2 id="bandwidth-modal-title" data-bandwidth-modal-title>Hasil Bandwidth Test</h2>
            <button type="button" class="modal-close" data-close-bandwidth-modal aria-label="Tutup">&times;</button>
        </header>
        <div class="modal-body">
            <div class="bandwidth-modal-router" data-bandwidth-modal-router></div>
            <div class="bandwidth-modal-content" data-bandwidth-modal-content>
                <!-- Konten hasil bandwidth test akan dimuat oleh JavaScript -->
            </div>
        </div>
        <footer class="modal-footer">
            <button type="button" class="button" data-close-bandwidth-modal data-close-bandwidth-modal-primary>Tutup</button>
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
    const dashboardLayout = document.querySelector('[data-dashboard]');
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
    const clientPreview = document.querySelector('[data-client-preview]');
    const clientSubmitButton = document.querySelector('[data-client-submit]');
    const routerFilterInput = document.querySelector('[data-router-filter]');
    const routerFilterSummary = document.querySelector('[data-router-filter-summary]');
    const clientFeedback = document.querySelector('[data-client-feedback]');
    const refreshClientButton = document.querySelector('[data-refresh-client-list]');
    const manualScaleInput = document.querySelector('[data-scale-input]');
    const bandwidthModal = document.querySelector('[data-bandwidth-modal]');
    const bandwidthModalContent = document.querySelector('[data-bandwidth-modal-content]');
    const bandwidthModalRouter = document.querySelector('[data-bandwidth-modal-router]');
    const bandwidthModalTitle = document.querySelector('[data-bandwidth-modal-title]');
    const bandwidthModalCloseButtons = document.querySelectorAll('[data-close-bandwidth-modal]');
    const bandwidthModalPrimaryClose = document.querySelector('[data-close-bandwidth-modal-primary]');

    const bandwidthConfigModal = document.querySelector('[data-bandwidth-config-modal]');
    const bandwidthConfigForm = document.querySelector('[data-bandwidth-config-form]');
    const bandwidthConfigRouterLabel = document.querySelector('[data-bandwidth-config-router]');
    const bandwidthConfigInterfaceLabel = document.querySelector('[data-bandwidth-config-interface]');
    const bandwidthConfigDirectionField = document.querySelector('[data-bandwidth-config-direction]');
    const bandwidthConfigProtocolField = document.querySelector('[data-bandwidth-config-protocol]');
    const bandwidthConfigDurationField = document.querySelector('[data-bandwidth-config-duration]');
    const bandwidthConfigServerLabel = document.querySelector('[data-bandwidth-config-server]');
    const bandwidthConfigFeedback = document.querySelector('[data-bandwidth-config-feedback]');
    const bandwidthConfigRunButton = document.querySelector('[data-bandwidth-config-run]');
    const bandwidthConfigRunLabel = bandwidthConfigRunButton
        ? bandwidthConfigRunButton.querySelector('[data-bandwidth-config-run-label]')
        : null;
    const defaultBandwidthRunLabel = bandwidthConfigRunLabel?.textContent?.trim() || 'Jalankan';
    const bandwidthConfigCloseButtons = document.querySelectorAll('[data-close-bandwidth-config]');
    let state = {};
    let refreshTimer = null;
    let isFetching = false;
    let queuedFetch = false;
    let clientsState = { clients: [] };
    let selectedClient = null;
    let selectedClientKey = null;
    const interfaceSelections = new Map();
    const rateHistory = new Map();
    const bandwidthSettings = new Map();
    const bandwidthResults = new Map();
    const bandwidthCooldowns = new Map();
    const HISTORY_WINDOW_MS = 5 * 60 * 1000;
    const DEFAULT_BANDWIDTH_DURATION = 10;
    const BANDWIDTH_NEAR_CAPACITY_THRESHOLD = 0.85;
    const BANDWIDTH_RATE_LIMIT_SECONDS = 15;
    const BANDWIDTH_RATE_LIMIT_MS = BANDWIDTH_RATE_LIMIT_SECONDS * 1000;
    const BANDWIDTH_DEFAULT_CAPACITY_MBPS = 100;
    const BANDWIDTH_TIMEOUT_BUFFER_MS = 5000;
    let routerFilterTerm = '';
    let manualScaleBps = null;
    let activeBandwidthModalKey = null;
    let bandwidthConfigContext = null;
    const defaultClientSubmitLabel = clientSubmitButton?.textContent?.trim() || 'Tambahkan Router';

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

    const findRouterStateByKey = (routerKey) => {
        if (!state || !Array.isArray(state.routers)) {
            return null;
        }

        const target = String(routerKey ?? '').toLowerCase();

        if (target === '') {
            return null;
        }

        return state.routers.find((item) => {
            if (!item) {
                return false;
            }

            const clientKey = String(item.client_key ?? '').toLowerCase();
            const routerIp = String(item.router_ip ?? item.ip ?? item.ip_address ?? '').toLowerCase();

            if (clientKey && clientKey === target) {
                return true;
            }

            return routerIp !== '' && routerIp === target;
        }) || null;
    };

    const updateLocalPreferredInterface = (routerKey, interfaceName, options = {}) => {
        const { clientKey = '', routerIp = '' } = options;
        const preferred = typeof interfaceName === 'string' ? interfaceName : '';
        const routerState = routerKey ? findRouterStateByKey(routerKey) : null;

        if (routerState) {
            routerState.preferred_interface = preferred;
            routerState.iface = preferred;
        }

        const targetKey = String(clientKey ?? '').toLowerCase();
        const targetIp = String(routerIp ?? '').toLowerCase();

        if (clientsState && Array.isArray(clientsState.clients)) {
            clientsState.clients = clientsState.clients.map((client) => {
                if (!client) {
                    return client;
                }

                const clientKeyValue = String(client.client_key ?? buildClientKey(client) ?? '').toLowerCase();
                const clientIp = String(client.ip_address ?? client.address ?? client.ip ?? '').toLowerCase();

                const matchesKey = targetKey !== '' && clientKeyValue === targetKey;
                const matchesIp = targetKey === '' && targetIp !== '' && clientIp === targetIp;

                if (!matchesKey && !matchesIp) {
                    return client;
                }

                const next = { ...client };

                if (preferred === '') {
                    delete next.preferred_interface;
                    delete next.iface;
                } else {
                    next.preferred_interface = preferred;
                    next.iface = preferred;
                }

                return next;
            });
        }
    };

    const applyRouterClientSnapshot = (snapshot) => {
        if (!snapshot || typeof snapshot !== 'object') {
            return;
        }

        if (Array.isArray(snapshot.clients)) {
            clientsState.clients = snapshot.clients;
        }

        if (!state || !Array.isArray(state.routers) || !Array.isArray(snapshot.routers)) {
            return;
        }

        const lookup = new Map();

        snapshot.routers.forEach((item) => {
            if (!item) {
                return;
            }

            const key = String(item.client_key ?? '').toLowerCase()
                || String(item.ip ?? item.ip_address ?? '').toLowerCase();

            if (key) {
                lookup.set(key, item);
            }
        });

        state.routers.forEach((router) => {
            if (!router) {
                return;
            }

            const stateKey = String(
                router.client_key ?? router.router_ip ?? router.ip ?? router.ip_address ?? ''
            ).toLowerCase();

            if (!stateKey) {
                return;
            }

            const snapshotRouter = lookup.get(stateKey);

            if (!snapshotRouter) {
                return;
            }

            const preferred = snapshotRouter.preferred_interface ?? snapshotRouter.iface ?? '';

            router.preferred_interface = preferred;
            router.iface = preferred;
        });
    };

    const updateBandwidthCooldown = (routerKey, expiresAtMs) => {
        if (!routerKey) {
            return;
        }

        if (!Number.isFinite(expiresAtMs) || expiresAtMs <= Date.now()) {
            bandwidthCooldowns.delete(routerKey);

            return;
        }

        bandwidthCooldowns.set(routerKey, expiresAtMs);
    };

    const getBandwidthCooldownRemainingMs = (routerKey) => {
        const expiresAt = bandwidthCooldowns.get(routerKey) ?? 0;

        return expiresAt - Date.now();
    };

    const sanitiseBandwidthDirection = (value) => {
        const normalized = String(value ?? '').toLowerCase();

        if (normalized === 'tx' || normalized === 'transmit') {
            return 'tx';
        }

        if (normalized === 'rx' || normalized === 'receive') {
            return 'rx';
        }

        if (normalized === 'both' || normalized === 'txrx' || normalized === 'rtx') {
            return 'both';
        }

        return 'both';
    };

    const sanitiseBandwidthDuration = (value) => {
        const numeric = Number(value);

        if (!Number.isFinite(numeric)) {
            return DEFAULT_BANDWIDTH_DURATION;
        }

        return Math.min(60, Math.max(1, Math.round(numeric)));
    };

    const sanitiseBandwidthProtocol = (value) => {
        const normalized = String(value ?? '').toLowerCase();

        if (normalized === 'udp') {
            return 'udp';
        }

        return 'tcp';
    };

    const hasOwn = (object, key) => Object.prototype.hasOwnProperty.call(object, key);

    const updateBandwidthSettingsMap = (routerKey, updates = {}) => {
        const current = bandwidthSettings.get(routerKey) || {};

        const next = {
            direction: sanitiseBandwidthDirection(
                hasOwn(updates, 'direction') ? updates.direction : (current.direction ?? 'both')
            ),
            duration: sanitiseBandwidthDuration(
                hasOwn(updates, 'duration') ? updates.duration : (current.duration ?? DEFAULT_BANDWIDTH_DURATION)
            ),
            protocol: sanitiseBandwidthProtocol(
                hasOwn(updates, 'protocol') ? updates.protocol : (current.protocol ?? 'tcp')
            ),
        };

        bandwidthSettings.set(routerKey, next);

        return { ...next };
    };

    const ensureBandwidthSettings = (routerKey, defaults = {}) => updateBandwidthSettingsMap(routerKey, defaults);

    const buildClientKey = (client) => {
        if (!client) {
            return '';
        }

        const existing = client.client_key ?? client.clientKey;

        if (existing) {
            return String(existing).toLowerCase();
        }

        const username = String(client.pppoe_username ?? client.username ?? '').toLowerCase();
        const serverIp = String(client.server_ip ?? '').toLowerCase();
        const address = String(client.address ?? client.client_address ?? '').toLowerCase();

        if (username && serverIp) {
            return `${username}@${serverIp}`;
        }

        if (username) {
            return username;
        }

        if (address) {
            return address;
        }

        return String(client.client_name ?? client.comment ?? '').toLowerCase();
    };

    const buildRouterPayloadFromClient = (client) => {
        const key = buildClientKey(client);
        const ipAddress = client?.address
            ?? client?.client_address
            ?? client?.ip_address
            ?? client?.router_ip
            ?? client?.remote_address
            ?? '';
        const serverLabel = client?.server_name || client?.server_ip || '';
        const baseName = client?.client_name
            ?? client?.comment
            ?? client?.pppoe_username
            ?? client?.username
            ?? key
            ?? ipAddress
            ?? 'Router PPPoE';
        const notes = serverLabel
            ? `Ditambahkan dari ${serverLabel}`
            : 'Ditambahkan dari PPPoE';

        return {
            name: baseName,
            ip_address: ipAddress || client?.server_ip || '',
            username: 'rondi',
            password: '21184662',
            notes,
            is_pppoe_server: 1,
            client_key: key,
            pppoe_client: {
                client_key: key,
                server_ip: client?.server_ip ?? '',
                server_name: client?.server_name ?? '',
                pppoe_username: client?.pppoe_username ?? client?.username ?? '',
                client_name: client?.client_name ?? client?.comment ?? baseName,
                profile: client?.profile ?? '',
                status: client?.status ?? '',
                address: client?.address ?? client?.client_address ?? '',
                comment: client?.comment ?? '',
                last_logged_out: client?.last_logged_out ?? '',
                secret_id: client?.secret_id ?? '',
            },
        };
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

    const computeRouterKey = (router, index) => {
        if (router && typeof router === 'object') {
            const clientKey = router.client_key ?? '';

            if (clientKey) {
                return String(clientKey);
            }

            const routerIp = router.router_ip ?? router.ip ?? '';

            if (routerIp) {
                return String(routerIp);
            }
        }

        return `router-${index}`;
    };

    const escapeSelector = (value) => {
        const stringValue = String(value ?? '');

        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(stringValue);
        }

        return stringValue.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
    };

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
            const routerKey = computeRouterKey(router, index);
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

    const determineBandwidthBaseline = (routerKey, interfaceName, summary) => {
        const router = findRouterStateByKey(routerKey);
        const interfaces = Array.isArray(router?.interfaces) ? router.interfaces : [];
        const selectedInterface = interfaces.find((item) => item?.name === interfaceName) || interfaces[0] || null;
        const capacityMbps = getInterfaceCapacityMbps(selectedInterface, router);

        const txValue = Math.max(
            Number(summary?.tx_total_average_bps ?? 0),
            Number(summary?.tx_current_bps ?? 0)
        );
        const rxValue = Math.max(
            Number(summary?.rx_total_average_bps ?? 0),
            Number(summary?.rx_current_bps ?? 0)
        );
        const txPeak = Number(summary?.tx_peak_bps ?? 0);
        const rxPeak = Number(summary?.rx_peak_bps ?? 0);

        let baselineMbps = Number.isFinite(capacityMbps) && capacityMbps > 0
            ? capacityMbps
            : BANDWIDTH_DEFAULT_CAPACITY_MBPS;

        const highestBps = Math.max(txValue, rxValue, txPeak, rxPeak);

        if (highestBps > baselineMbps * 1_000_000) {
            baselineMbps = highestBps / 1_000_000;
        }

        if (!Number.isFinite(baselineMbps) || baselineMbps <= 0) {
            baselineMbps = BANDWIDTH_DEFAULT_CAPACITY_MBPS;
        }

        return {
            router,
            interfaceData: selectedInterface,
            baselineMbps,
            baselineBps: baselineMbps * 1_000_000,
            txValue,
            rxValue,
            txPeak,
            rxPeak,
        };
    };

    const buildBandwidthChart = (routerKey, interfaceName, summary) => {
        if (!summary) {
            return '';
        }

        const info = determineBandwidthBaseline(routerKey, interfaceName, summary);

        if (!Number.isFinite(info.baselineBps) || info.baselineBps <= 0) {
            return '';
        }

        const txPercent = computeRatePercent(info.txValue, info.baselineBps);
        const rxPercent = computeRatePercent(info.rxValue, info.baselineBps);
        const txLevel = determineRateLevel(info.txValue / 1_000_000);
        const rxLevel = determineRateLevel(info.rxValue / 1_000_000);
        const scaleLabel = formatRate(info.baselineBps);
        const txLabel = formatRate(info.txValue);
        const rxLabel = formatRate(info.rxValue);

        return `
            <div class="bandwidth-chart" data-bandwidth-scale="${escapeHtml(scaleLabel)}">
                <div class="bandwidth-chart-row bandwidth-chart-row--tx">
                    <span class="bandwidth-chart-label">TX</span>
                    ${buildTrafficBar('tx', txPercent, txLevel)}
                    <span class="bandwidth-chart-value">${escapeHtml(txLabel)}</span>
                </div>
                <div class="bandwidth-chart-row bandwidth-chart-row--rx">
                    <span class="bandwidth-chart-label">RX</span>
                    ${buildTrafficBar('rx', rxPercent, rxLevel)}
                    <span class="bandwidth-chart-value">${escapeHtml(rxLabel)}</span>
                </div>
                <div class="bandwidth-chart-scale">Skala: ${escapeHtml(scaleLabel)}</div>
            </div>
        `;
    };

    const buildBandwidthCooldownNote = (stateItem) => {
        const nextAvailable = stateItem?.next_available_at
            || stateItem?.rate_limit?.next_available_at
            || null;

        if (!nextAvailable) {
            return '';
        }

        const timestamp = Date.parse(nextAvailable);

        if (Number.isNaN(timestamp)) {
            return '';
        }

        const remainingMs = timestamp - Date.now();

        if (remainingMs <= 0) {
            return '<div class="bandwidth-cooldown">Tes dapat dijalankan kembali sekarang.</div>';
        }

        const seconds = Math.ceil(remainingMs / 1000);

        return `<div class="bandwidth-cooldown">Tes berikutnya dapat dijalankan dalam ${escapeHtml(String(seconds))} detik.</div>`;
    };

    const buildBandwidthRawDetails = (stateItem) => {
        if (!stateItem?.result) {
            return '';
        }

        try {
            const clone = JSON.parse(JSON.stringify(stateItem.result));

            if (Array.isArray(clone.entries) && clone.entries.length > 10) {
                clone.entries_preview = clone.entries.slice(0, 10);
                clone.entries_total = clone.entries.length;
                delete clone.entries;
            }

            const json = JSON.stringify(clone, null, 2);

            if (!json) {
                return '';
            }

            return `
                <details class="bandwidth-raw">
                    <summary>Lihat raw response</summary>
                    <pre>${escapeHtml(json)}</pre>
                </details>
            `;
        } catch (error) {
            return '';
        }
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const guessServerFromIp = (ip) => {
        const value = String(ip ?? '').trim();

        if (/^172\.16\.30\./.test(value)) {
            return '172.16.30.1';
        }

        if (/^172\.16\.40\./.test(value)) {
            return '172.16.40.1';
        }

        return '';
    };

    const describeServerSource = (source) => {
        const normalized = String(source ?? '').toLowerCase();

        switch (normalized) {
            case 'network_prefix':
                return 'otomatis prefix jaringan';
            case 'router_snapshot':
                return 'router_client.json';
            case 'request':
                return 'alamat permintaan';
            default:
                return source ? String(source) : '';
        }
    };

    const buildBandwidthPanel = (routerKey, interfaceName) => {
        const stateItem = bandwidthResults.get(routerKey);
        const effectiveInterface = stateItem?.interface || interfaceName || '';
        const interfaceHtml = effectiveInterface
            ? `<div class="router-row-bandwidth-interface">Interface: ${escapeHtml(effectiveInterface)}</div>`
            : '';
        const cooldownHtml = stateItem ? buildBandwidthCooldownNote(stateItem) : '';

        if (!stateItem) {
            return `
                <div class="router-row-bandwidth" data-bandwidth-panel data-router-key="${escapeHtml(routerKey)}">
                    <div class="router-row-bandwidth-status router-row-bandwidth-status--idle">
                        Klik <strong>Bandwidth Test</strong> untuk mengukur throughput.
                    </div>
                    ${interfaceHtml}
                </div>
            `;
        }

        if (stateItem.status === 'running') {
            const serverGuess = stateItem.result?.server_label
                || stateItem.server_label
                || stateItem.result?.server_ip
                || stateItem.server_ip
                || stateItem.guessed_server
                || '';
            const serverText = serverGuess ? ` ke ${escapeHtml(serverGuess)}` : '';
            const summaryParts = [];

            if (stateItem.direction) {
                summaryParts.push(`Mode: ${stateItem.direction.toUpperCase()}`);
            }

            if (stateItem.protocol) {
                summaryParts.push(`Protocol: ${stateItem.protocol.toUpperCase()}`);
            }

            if (stateItem.duration) {
                summaryParts.push(`Durasi: ${stateItem.duration}s`);
            }

            const summaryHtml = summaryParts.length
                ? `<div class="router-row-bandwidth-summary">${summaryParts
                    .map((part) => escapeHtml(part))
                    .join(' • ')}</div>`
                : '';

            return `
                <div class="router-row-bandwidth" data-bandwidth-panel data-router-key="${escapeHtml(routerKey)}">
                    <div class="router-row-bandwidth-status router-row-bandwidth-status--running">
                        <span class="bandwidth-status-spinner" aria-hidden="true"></span>
                        <span>Menjalankan bandwidth test${serverText}...</span>
                    </div>
                    ${interfaceHtml}
                    ${summaryHtml}
                    ${cooldownHtml}
                </div>
            `;
        }

        if (stateItem.status === 'error') {
            const serverGuess = stateItem.result?.server_label
                || stateItem.server_label
                || stateItem.result?.server_ip
                || stateItem.server_ip
                || stateItem.guessed_server
                || '';
            const header = `
                <div class="router-row-bandwidth-header">
                    <span class="router-row-bandwidth-title">Bandwidth Test</span>
                    ${serverGuess ? `<span class="router-row-bandwidth-server">${escapeHtml(serverGuess)}</span>` : ''}
                </div>
            `;
            const timestamp = stateItem.completed_at
                ? `<div class="router-row-bandwidth-timestamp">Selesai: ${escapeHtml(formatDateTime(stateItem.completed_at))}</div>`
                : '';
            const summaryParts = [];

            if (stateItem.direction) {
                summaryParts.push(`Mode: ${stateItem.direction.toUpperCase()}`);
            }

            if (stateItem.protocol) {
                summaryParts.push(`Protocol: ${stateItem.protocol.toUpperCase()}`);
            }

            if (stateItem.duration) {
                summaryParts.push(`Durasi: ${stateItem.duration}s`);
            }

            const summaryHtml = summaryParts.length
                ? `<div class="router-row-bandwidth-summary">${summaryParts
                    .map((part) => escapeHtml(part))
                    .join(' • ')}</div>`
                : '';

            return `
                <div class="router-row-bandwidth" data-bandwidth-panel data-router-key="${escapeHtml(routerKey)}">
                    ${header}
                    ${interfaceHtml}
                    ${summaryHtml}
                    <div class="router-row-bandwidth-status router-row-bandwidth-status--error">
                        <span class="router-row-bandwidth-message">${escapeHtml(stateItem.message || 'Bandwidth test gagal dijalankan.')}</span>
                    </div>
                    ${timestamp}
                    ${cooldownHtml}
                </div>
            `;
        }

        if (stateItem.status !== 'success') {
            return `
                <div class="router-row-bandwidth" data-bandwidth-panel data-router-key="${escapeHtml(routerKey)}">
                    <div class="router-row-bandwidth-status router-row-bandwidth-status--idle">
                        Klik <strong>Bandwidth Test</strong> untuk mengukur throughput.
                    </div>
                    ${interfaceHtml}
                </div>
            `;
        }

        const result = stateItem.result || {};
        const summary = result.summary || {};
        const serverLabel = result.server_label
            || stateItem.server_label
            || result.server_ip
            || stateItem.server_ip
            || stateItem.guessed_server
            || '';
        const serverSourceLabel = describeServerSource(result.server_source || stateItem.server_source);
        const txAverageLabel = summary.tx_total_average_label
            || summary.tx_current_label
            || (summary.tx_total_average_bps ? formatRate(summary.tx_total_average_bps) : '0 bps');
        const rxAverageLabel = summary.rx_total_average_label
            || summary.rx_current_label
            || (summary.rx_total_average_bps ? formatRate(summary.rx_total_average_bps) : '0 bps');
        const txPeakLabel = summary.tx_peak_label
            || (summary.tx_peak_bps ? formatRate(summary.tx_peak_bps) : '');
        const rxPeakLabel = summary.rx_peak_label
            || (summary.rx_peak_bps ? formatRate(summary.rx_peak_bps) : '');
        const options = result.options || {};
        const duration = options.duration ? `${options.duration}s` : '';
        const connectionCount = options.connection_count ? `${options.connection_count}` : '';
        const directionRaw = options.direction || result.direction || stateItem.direction || '';
        const protocolRaw = options.protocol || result.protocol || stateItem.protocol || '';
        const footerParts = [];

        if (directionRaw || protocolRaw) {
            const modeParts = [];

            if (directionRaw) {
                modeParts.push(String(directionRaw).toUpperCase());
            }

            if (protocolRaw) {
                modeParts.push(String(protocolRaw).toUpperCase());
            }

            if (modeParts.length > 0) {
                footerParts.push(`Mode: ${modeParts.join(' • ')}`);
            }
        }

        if (connectionCount) {
            footerParts.push(`Koneksi: ${connectionCount}`);
        }

        if (duration) {
            footerParts.push(`Durasi: ${duration}`);
        }

        const footerHtml = footerParts.length > 0
            ? `<div class="router-row-bandwidth-footer">${footerParts.map((part) => `<span>${escapeHtml(part)}</span>`).join(' • ')}</div>`
            : '';

        const peaksHtml = (txPeakLabel || rxPeakLabel)
            ? `<div class="router-row-bandwidth-peaks">
                    ${txPeakLabel ? `<span class="bandwidth-peak bandwidth-peak--tx">Puncak TX: ${escapeHtml(txPeakLabel)}</span>` : ''}
                    ${rxPeakLabel ? `<span class="bandwidth-peak bandwidth-peak--rx">Puncak RX: ${escapeHtml(rxPeakLabel)}</span>` : ''}
               </div>`
            : '';

        const completedAt = result.completed_at || stateItem.completed_at || '';
        const timestampHtml = completedAt
            ? `<div class="router-row-bandwidth-timestamp">Selesai: ${escapeHtml(formatDateTime(completedAt))}</div>`
            : '';
        const chartHtml = buildBandwidthChart(routerKey, effectiveInterface, summary);
        const rawHtml = buildBandwidthRawDetails(stateItem);
        const detailButtonHtml = `<div class="router-row-bandwidth-actions-inline"><button type="button" class="button button-outline" data-open-bandwidth-modal data-router-key="${escapeHtml(routerKey)}">Lihat detail</button></div>`;

        return `
            <div class="router-row-bandwidth" data-bandwidth-panel data-router-key="${escapeHtml(routerKey)}">
                <div class="router-row-bandwidth-header">
                    <span class="router-row-bandwidth-title">Bandwidth Test</span>
                    ${serverLabel ? `<span class="router-row-bandwidth-server">${escapeHtml(serverLabel)}</span>` : ''}
                    ${serverSourceLabel ? `<span class="router-row-bandwidth-source">${escapeHtml(serverSourceLabel)}</span>` : ''}
                </div>
                ${interfaceHtml}
                ${chartHtml}
                <div class="router-row-bandwidth-metrics">
                    <div class="bandwidth-metric bandwidth-metric--tx">
                        <span class="bandwidth-metric-label">TX</span>
                        <span class="bandwidth-metric-value">${escapeHtml(txAverageLabel || '0 bps')}</span>
                    </div>
                    <div class="bandwidth-metric bandwidth-metric--rx">
                        <span class="bandwidth-metric-label">RX</span>
                        <span class="bandwidth-metric-value">${escapeHtml(rxAverageLabel || '0 bps')}</span>
                    </div>
                </div>
                ${peaksHtml}
                ${footerHtml}
                ${timestampHtml}
                ${cooldownHtml}
                ${detailButtonHtml}
                ${rawHtml}
            </div>
        `;
    };

    const renderBandwidthModalContent = (routerKey) => {
        if (!bandwidthModalContent) {
            return;
        }

        const stateItem = bandwidthResults.get(routerKey);
        const interfaceName = stateItem?.interface || interfaceSelections.get(routerKey) || '';
        const panelHtml = buildBandwidthPanel(routerKey, interfaceName);

        bandwidthModalContent.innerHTML = panelHtml;

        if (bandwidthModalTitle) {
            const routerLabel = stateItem?.router_name || stateItem?.router_ip || '';
            bandwidthModalTitle.textContent = routerLabel
                ? `Hasil Bandwidth Test – ${routerLabel}`
                : 'Hasil Bandwidth Test';
        }

        if (!bandwidthModalRouter) {
            return;
        }

        if (!stateItem) {
            bandwidthModalRouter.textContent = 'Belum ada hasil bandwidth test untuk router ini.';

            return;
        }

        const detailParts = [];
        const routerLabel = stateItem.router_name && stateItem.router_name !== stateItem.router_ip
            ? `${stateItem.router_name} (${stateItem.router_ip || '-'})`
            : (stateItem.router_ip || stateItem.router_name || '-');

        detailParts.push(routerLabel);

        if (stateItem.interface) {
            detailParts.push(`Interface: ${stateItem.interface}`);
        }

        const serverLabel = stateItem.result?.server_label
            || stateItem.server_label
            || stateItem.result?.server_ip
            || stateItem.server_ip
            || stateItem.guessed_server
            || '';

        if (serverLabel) {
            const serverSource = describeServerSource(
                stateItem.result?.server_source || stateItem.server_source
            );
            const serverText = serverSource
                ? `${serverLabel} (${serverSource})`
                : serverLabel;
            detailParts.push(`Server: ${serverText}`);
        }

        const directionLabel = stateItem.result?.options?.direction
            || (stateItem.direction ? stateItem.direction.toUpperCase() : '');

        if (directionLabel) {
            detailParts.push(`Mode: ${directionLabel}`);
        }

        const durationValue = stateItem.result?.options?.duration ?? stateItem.duration;

        if (Number.isFinite(durationValue) && durationValue > 0) {
            detailParts.push(`Durasi: ${durationValue}s`);
        }

        bandwidthModalRouter.innerHTML = detailParts
            .map((detail) => `<span>${escapeHtml(detail)}</span>`)
            .join('<span class="bandwidth-modal-router-sep">•</span>');
    };

    const openBandwidthResultModal = (routerKey) => {
        if (!bandwidthModal) {
            return;
        }

        renderBandwidthModalContent(routerKey);
        bandwidthModal.removeAttribute('hidden');
        bandwidthModal.classList.add('is-visible');
        activeBandwidthModalKey = routerKey;

        requestAnimationFrame(() => {
            bandwidthModalPrimaryClose?.focus();
        });
    };

    const closeBandwidthModal = () => {
        if (!bandwidthModal) {
            return;
        }

        bandwidthModal.setAttribute('hidden', '');
        bandwidthModal.classList.remove('is-visible');

        if (bandwidthModalContent) {
            bandwidthModalContent.innerHTML = '';
        }

        if (bandwidthModalRouter) {
            bandwidthModalRouter.innerHTML = '';
        }

        activeBandwidthModalKey = null;
    };

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
        const sourceKey = typeof data.source === 'string' ? data.source : '';
        const fromClientSnapshot = sourceKey.startsWith('router_clients');
        const sourceLabel = fromClientSnapshot ? 'router_client.json' : 'daftar router utama';
        const deviceLabel = fromClientSnapshot ? 'router client' : 'router';

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

    const buildRouterSearchContent = (router) => {
        if (!router || typeof router !== 'object') {
            return '';
        }

        const tokens = [];
        const push = (value) => {
            if (value === null || value === undefined) {
                return;
            }

            const text = String(value).trim();

            if (text !== '') {
                tokens.push(text.toLowerCase());
            }
        };

        push(router.router_name);
        push(router.router_identity);
        push(router.identity);
        push(router.name);
        push(router.alias);
        push(router.router_ip);
        push(router.ip);
        push(router.ip_address);
        push(router.router_address);
        push(router.address);
        push(router.client_key);
        push(router.pppoe_username);
        push(router.pppoe_profile);
        push(router.profile_name);
        push(router.server_ip);
        push(router.server_name);
        push(router.server_source);
        push(router.notes);
        push(router.note);
        push(router.preferred_interface);
        push(router.iface);

        if (Array.isArray(router.tags)) {
            router.tags.forEach((tag) => push(tag));
        }

        if (Array.isArray(router.interfaces)) {
            router.interfaces.forEach((item) => {
                if (!item || typeof item !== 'object') {
                    return;
                }

                push(item.name);
                push(item.comment);
                push(item.type);
                push(item.description);
                push(item.actual_interface);
            });
        }

        return tokens.join(' ');
    };

    const getRouterFilterTokens = () => routerFilterTerm.trim().toLowerCase().split(/\s+/).filter(Boolean);

    const applyRouterFilter = () => {
        renderRouters(state);
    };

    const renderRouter = (router, index) => {
        const interfaces = Array.isArray(router.interfaces) ? router.interfaces : [];
        const routerIp = router.router_ip ?? '';
        const clientKey = router.client_key ?? '';
        const routerKey = computeRouterKey(router, index);
        const routerName = router.router_name ?? routerIp ?? 'Router';
        const serverSource = describeServerSource(router.server_source);
        const serverLabel = router.server_ip
            ? `Server: ${router.server_name || router.server_ip}${serverSource ? ` (${serverSource})` : ''}`
            : '';
        const pppoeLabel = router.pppoe_username ? `PPPoe: ${router.pppoe_username}` : '';
        const noteLabel = router.notes ? `Catatan: ${router.notes}` : '';

        const metaLines = [serverLabel, pppoeLabel, noteLabel].filter((line) => line !== '');
        const metaHtml = metaLines.length > 0
            ? `<div class="router-row-meta">${metaLines.map((line) => `<span class="router-row-meta__item">${escapeHtml(line)}</span>`).join('')}</div>`
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

        const bandwidthButtonBaseAttributes = [
            'type="button"',
            'class="button button-secondary router-bandwidth-button"',
            'data-open-bandwidth-config',
            `data-router-key="${escapeHtml(routerKey)}"`,
            `data-router-ip="${escapeHtml(routerIp)}"`,
            `data-router-name="${escapeHtml(routerName)}"`,
        ];

        if (router.server_ip) {
            bandwidthButtonBaseAttributes.push(`data-router-server-ip="${escapeHtml(router.server_ip)}"`);
        }

        if (router.server_name) {
            bandwidthButtonBaseAttributes.push(`data-router-server-label="${escapeHtml(router.server_name)}"`);
        }

        if (router.server_source) {
            bandwidthButtonBaseAttributes.push(`data-router-server-source="${escapeHtml(router.server_source)}"`);
        }

        const bandwidthState = bandwidthResults.get(routerKey);
        const settings = ensureBandwidthSettings(routerKey);
        let capacityBadgeHtml = '';

        if (bandwidthState?.status === 'running') {
            bandwidthButtonBaseAttributes.push('disabled', 'aria-busy="true"');
        }

        let initialSelectedName = interfaceSelections.get(routerKey) || router.preferred_interface || router.iface || '';

        const buildBandwidthButton = (interfaceName) => {
            const attributes = [...bandwidthButtonBaseAttributes];

            if (interfaceName) {
                attributes.push(`data-interface-name="${escapeHtml(interfaceName)}"`);
            }

            attributes.push(`data-bandwidth-direction-value="${escapeHtml(settings.direction)}"`);
            attributes.push(`data-bandwidth-duration-value="${escapeHtml(String(settings.duration))}"`);
            attributes.push(`data-bandwidth-protocol-value="${escapeHtml(settings.protocol)}"`);

            if (bandwidthState?.status === 'running') {
                return `
                    <button ${attributes.join(' ')}>
                        <span class="button-spinner" aria-hidden="true"></span>
                        <span>Menjalankan...</span>
                    </button>
                `;
            }

            return `<button ${attributes.join(' ')}>Bandwidth Test</button>`;
        };

        const buildBandwidthControls = (interfaceName) => {
            const directionLabelMap = {
                both: 'TX & RX',
                tx: 'TX',
                rx: 'RX',
            };
            const modeParts = [];
            const directionLabel = directionLabelMap[settings.direction] || (settings.direction || '').toUpperCase();

            if (directionLabel) {
                modeParts.push(directionLabel);
            }

            if (settings.protocol) {
                modeParts.push(settings.protocol.toUpperCase());
            }

            const summaryParts = [];

            if (modeParts.length > 0) {
                summaryParts.push(`Mode: ${modeParts.join(' • ')}`);
            }

            if (settings.duration) {
                summaryParts.push(`Durasi: ${settings.duration}s`);
            }

            const summaryText = summaryParts.length > 0 ? summaryParts.join(' • ') : '';
            const summaryHtml = summaryText
                ? `<span class="bandwidth-control-summary">${escapeHtml(summaryText)}</span>`
                : '';

            return `
                <div class="router-row-bandwidth-controls" data-bandwidth-controls data-router-key="${escapeHtml(routerKey)}">
                    ${buildBandwidthButton(interfaceName)}
                    ${summaryHtml}
                </div>
            `;
        };

        const deleteButtonHtml = `<button ${deleteButtonAttributes.join(' ')}>Hapus</button>`;
        const buildIdentityHtml = (badgeHtml) => `
            <div class="router-row-identity">
                <div class="router-row-identity-main">
                    <span class="router-row-name">${escapeHtml(routerName)}</span>
                    ${badgeHtml || ''}
                </div>
                <div class="router-row-identity-sub">
                    <span class="router-row-ip">${escapeHtml(routerIp || '-')}</span>
                    ${metaHtml}
                </div>
            </div>
        `;

        if (!router.reachable) {
            const error = escapeHtml(router.error || 'Gagal terhubung ke router.');
            const bandwidthControlsHtml = buildBandwidthControls(initialSelectedName);
            const bandwidthPanelHtml = buildBandwidthPanel(routerKey, initialSelectedName);

            return {
                key: routerKey,
                markup: `
                    <div class="interface-router-row interface-router-row--error" data-router-ip="${escapeHtml(routerIp)}" data-router-key="${escapeHtml(routerKey)}" data-client-key="${escapeHtml(clientKey)}">
                        ${buildIdentityHtml(capacityBadgeHtml)}
                        <div class="router-row-message" role="alert">${error}</div>
                        <div class="router-row-controls router-row-controls--error">
                            <div class="router-row-actions">
                                ${bandwidthControlsHtml}
                                <div class="router-row-delete">${deleteButtonHtml}</div>
                            </div>
                            ${bandwidthPanelHtml}
                        </div>
                    </div>
                `,
            };
        }

        const availableInterfaces = interfaces.filter((item) => Boolean(item?.name));
        const availableNames = availableInterfaces.map((item) => item.name);
        let selectedName = initialSelectedName;

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
            <option value="${escapeHtml(item.name)}" ${item.name === selectedName ? 'selected' : ''} title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</option>
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
        const rxRatio = Number.isFinite(rxBaseline) && rxBaseline > 0 ? throughputRx / rxBaseline : 0;
        const txRatio = Number.isFinite(txBaseline) && txBaseline > 0 ? throughputTx / txBaseline : 0;
        const hasCapacityReference = Boolean(manualBaselineBps && manualBaselineBps > 0)
            || (Number.isFinite(interfaceCapacityBps) && interfaceCapacityBps > 0);
        const rxNearCapacity = hasCapacityReference && rxRatio >= BANDWIDTH_NEAR_CAPACITY_THRESHOLD;
        const txNearCapacity = hasCapacityReference && txRatio >= BANDWIDTH_NEAR_CAPACITY_THRESHOLD;

        if (rxNearCapacity || txNearCapacity) {
            const parts = [];
            let badgeClass = 'both';

            if (txNearCapacity) {
                parts.push('TX');
            }

            if (rxNearCapacity) {
                parts.push('RX');
            }

            if (txNearCapacity && !rxNearCapacity) {
                badgeClass = 'tx';
            } else if (rxNearCapacity && !txNearCapacity) {
                badgeClass = 'rx';
            }

            const percentValue = Math.max(rxRatio, txRatio) * 100;
            const percentLabel = `${Math.min(999, Math.round(percentValue))}%`;
            const label = parts.join(' & ');
            const title = `${label} ~${percentLabel} dari kapasitas`;

            capacityBadgeHtml = `<span class="router-row-capacity-badge router-row-capacity-badge--${badgeClass}" title="${escapeHtml(title)}">${escapeHtml(label)}<span class="router-row-capacity-badge__percent">${escapeHtml(percentLabel)}</span></span>`;
        }

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
            scaleLegendHtml = `<span class="router-row-scale router-row-scale--manual">Skala: ${escapeHtml(formatRate(manualBaselineBps))}</span>`;
        } else if (Number.isFinite(interfaceCapacityMbps) && interfaceCapacityMbps > 0) {
            const capacityLabel = interfaceCapacityMbps >= 100
                ? interfaceCapacityMbps.toFixed(0)
                : interfaceCapacityMbps.toFixed(1);

            scaleLegendHtml = `<span class="router-row-scale router-row-scale--capacity">Skala kapasitas: ${escapeHtml(capacityLabel)} Mbps</span>`;
        } else if (Math.max(fallbackRxBaseline, fallbackTxBaseline) > 0) {
            const dynamicBaseline = Math.max(fallbackRxBaseline, fallbackTxBaseline);

            scaleLegendHtml = `<span class="router-row-scale router-row-scale--dynamic">Skala dinamis: ${escapeHtml(formatRate(dynamicBaseline))}</span>`;
        }

        const statusClass = selectedInterface ? (statusClassMap[selectedInterface.status] || 'status-chip--warning') : 'status-chip--muted';
        const placeholderMessage = typeof router.placeholder_message === 'string'
            ? router.placeholder_message.trim()
            : '';

        const metricsHtml = selectedInterface
            ? `
                <div class="router-row-metrics" ${metricsAttributes}>
                    <div class="router-row-metrics-header">
                        <span class="router-row-interface-name">Interface: ${escapeHtml(selectedInterface.name)}</span>
                        ${scaleLegendHtml}
                    </div>
                    <div class="router-row-bars router-row-bars--compact">
                        <div class="router-row-bar-line router-row-bar-line--rx router-row-bar-line--level-${escapeHtml(rxLevel)}">
                            <span class="router-row-bar-label router-row-bar-label--rx router-row-bar-label--level-${escapeHtml(rxLevel)}">${rxLabel}</span>
                            ${buildTrafficBar('rx', rxPercent, rxLevel)}
                        </div>
                        <div class="router-row-bar-line router-row-bar-line--tx router-row-bar-line--level-${escapeHtml(txLevel)}">
                            <span class="router-row-bar-label router-row-bar-label--tx router-row-bar-label--level-${escapeHtml(txLevel)}">${txLabel}</span>
                            ${buildTrafficBar('tx', txPercent, txLevel)}
                        </div>
                    </div>
                </div>
            `
            : `<div class="router-row-metrics router-row-metrics--empty">${escapeHtml(placeholderMessage || 'Tidak ada interface ethernet.')}</div>`;

        const identityHtml = buildIdentityHtml(capacityBadgeHtml);

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

        const bandwidthPanelHtml = buildBandwidthPanel(routerKey, selectedName);
        const bandwidthControlsHtml = buildBandwidthControls(selectedName);
        const actionsHtml = `
            <div class="router-row-actions">
                ${bandwidthControlsHtml}
                <div class="router-row-delete">${deleteButtonHtml}</div>
            </div>
        `;

        return {
            key: routerKey,
            markup: `
                <div class="interface-router-row" data-router-ip="${escapeHtml(routerIp)}" data-router-key="${escapeHtml(routerKey)}" data-client-key="${escapeHtml(clientKey)}">
                    ${identityHtml}
                    ${metricsHtml}
                    <div class="router-row-controls">
                        ${selectorHtml}
                        ${actionsHtml}
                        ${bandwidthPanelHtml}
                    </div>
                </div>
            `,
        };
    };

    const renderRouters = (data, options = {}) => {
        const updateOnly = options.updateOnly === true;
        const routers = Array.isArray(data?.routers) ? data.routers : [];

        if (!routersContainer) {
            return;
        }

        if (routers.length === 0) {
            routersContainer.innerHTML = '<div class="alert alert-info subtle">Belum ada router client yang tersimpan. Gunakan tombol "Tambah Router AP" untuk memilih PPPoE dan menyimpannya ke router_client.json.</div>';
            interfaceSelections.clear();
            bandwidthResults.clear();
            bandwidthSettings.clear();
            bandwidthCooldowns.clear();

            if (routerFilterSummary) {
                routerFilterSummary.hidden = true;
                routerFilterSummary.textContent = '';
            }

            return;
        }

        const filterTokens = getRouterFilterTokens();
        const rows = routers.map((router, index) => ({
            router,
            row: renderRouter(router, index),
            searchContent: buildRouterSearchContent(router),
        }));
        const activeKeys = new Set(rows.map((entry) => entry.row.key));
        const visibleRows = filterTokens.length === 0
            ? rows
            : rows.filter((entry) => {
                if (entry.searchContent === '') {
                    return false;
                }

                return filterTokens.every((token) => entry.searchContent.includes(token));
            });

        if (routerFilterSummary) {
            if (filterTokens.length > 0) {
                routerFilterSummary.hidden = false;
                routerFilterSummary.textContent = `Filter: menampilkan ${formatInteger(visibleRows.length)} dari ${formatInteger(routers.length)} router`;
            } else {
                routerFilterSummary.hidden = true;
                routerFilterSummary.textContent = '';
            }
        }

        const filterLabel = routerFilterTerm.trim() || routerFilterTerm;

        if (visibleRows.length === 0) {
            if (filterTokens.length === 0) {
                routersContainer.innerHTML = '<div class="alert alert-info subtle">Belum ada router client yang tersimpan. Gunakan tombol "Tambah Router AP" untuk memilih PPPoE dan menyimpannya ke router_client.json.</div>';
            } else {
                routersContainer.innerHTML = `<div class="alert alert-info subtle">Tidak ada router yang cocok dengan filter <strong>${escapeHtml(filterLabel)}</strong>. Gunakan kata kunci lain atau bersihkan filter untuk menampilkan semua router.</div>`;
            }
        } else if (updateOnly) {
            const expectedKeys = visibleRows.map((entry) => entry.row.key);
            const currentRows = Array.from(routersContainer.querySelectorAll('[data-router-key]'));

            if (currentRows.length !== expectedKeys.length) {
                renderRouters(data);

                return;
            }

            let missing = false;

            for (const key of expectedKeys) {
                const selector = `[data-router-key="${escapeSelector(key)}"]`;

                if (!routersContainer.querySelector(selector)) {
                    missing = true;
                    break;
                }
            }

            if (missing) {
                renderRouters(data);

                return;
            }

            visibleRows.forEach((entry) => {
                const selector = `[data-router-key="${escapeSelector(entry.row.key)}"]`;
                const element = routersContainer.querySelector(selector);

                if (element) {
                    element.outerHTML = entry.row.markup;
                }
            });
        } else {
            routersContainer.innerHTML = visibleRows.map((entry) => entry.row.markup).join('');
        }

        bandwidthResults.forEach((_, key) => {
            if (!activeKeys.has(key)) {
                bandwidthResults.delete(key);
            }
        });

        interfaceSelections.forEach((_, key) => {
            if (!activeKeys.has(key)) {
                interfaceSelections.delete(key);
            }
        });

        bandwidthSettings.forEach((_, key) => {
            if (!activeKeys.has(key)) {
                bandwidthSettings.delete(key);
            }
        });

        bandwidthCooldowns.forEach((_, key) => {
            if (!activeKeys.has(key)) {
                bandwidthCooldowns.delete(key);
            }
        });

        if (activeBandwidthModalKey) {
            if (!activeKeys.has(activeBandwidthModalKey)) {
                closeBandwidthModal();
            } else {
                renderBandwidthModalContent(activeBandwidthModalKey);
            }
        }
    };

    const buildRouterKeyList = (snapshot) => {
        const routers = Array.isArray(snapshot?.routers) ? snapshot.routers : [];

        return routers.map((router, index) => computeRouterKey(router, index));
    };

    const hasRouterOrderChanged = (previousState, nextState) => {
        if (!previousState) {
            return true;
        }

        const previousKeys = buildRouterKeyList(previousState);
        const nextKeys = buildRouterKeyList(nextState);

        if (previousKeys.length !== nextKeys.length) {
            return true;
        }

        for (let index = 0; index < nextKeys.length; index += 1) {
            if (previousKeys[index] !== nextKeys[index]) {
                return true;
            }
        }

        return false;
    };

    const applyDataUpdate = (data, options = {}) => {
        const forceFull = options.forceFull === true;

        const nextData = (data && typeof data === 'object') ? data : {};

        updateRateHistory(nextData);

        const requiresFullRender = forceFull || !state || hasRouterOrderChanged(state, nextData);

        state = nextData;
        updateScaleIndicator();
        renderSummary(nextData);
        renderSourceInfo(nextData);

        if (requiresFullRender) {
            renderRouters(nextData);
        } else {
            renderRouters(nextData, { updateOnly: true });
        }

        const generatedLabel = formatDateTime(nextData.generated_at);
        let updatedText = `Terakhir diperbarui: ${generatedLabel}`;

        if (nextData.source === 'router_clients' && nextData.client_snapshot_generated_at) {
            updatedText += ` • Daftar klien: ${formatDateTime(nextData.client_snapshot_generated_at)}`;
        }

        lastUpdated.textContent = updatedText;
        errorBox.hidden = true;
    };

    const renderAll = (data, options = {}) => {
        applyDataUpdate(data, options);
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
            const data = await parseJsonSafe(response);

            if (!response.ok) {
                const message = data?.message || `Gagal memuat data (status ${response.status})`;

                throw new Error(message);
            }

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

    const runBandwidthTest = async (config) => {
        if (!config) {
            return;
        }

        const routerIp = config.routerIp || '';
        const routerKey = config.routerKey || '';
        const routerName = config.routerName || routerIp || 'Router';
        const interfaceName = config.interfaceName || '';
        const directionValue = config.direction;
        const durationValue = config.duration;
        const protocolValue = config.protocol;
        const onStart = typeof config.onStart === 'function' ? config.onStart : null;
        const onSuccess = typeof config.onSuccess === 'function' ? config.onSuccess : null;
        const onError = typeof config.onError === 'function' ? config.onError : null;
        const onFinally = typeof config.onFinally === 'function' ? config.onFinally : null;

        const cooldownUntilMs = Number.isFinite(config.cooldownUntil)
            ? Number(config.cooldownUntil)
            : Date.now() + BANDWIDTH_RATE_LIMIT_MS;
        const cooldownWindowSeconds = Number.isFinite(config.cooldownWindowSeconds)
            ? Number(config.cooldownWindowSeconds)
            : BANDWIDTH_RATE_LIMIT_SECONDS;

        if (!routerIp || !routerKey) {
            return;
        }

        const settings = updateBandwidthSettingsMap(routerKey, {
            direction: directionValue,
            duration: durationValue,
            protocol: protocolValue,
        });

        errorBox.hidden = true;

        const guessedServer = guessServerFromIp(routerIp);
        const hintedServerIp = config.serverIp || guessedServer || '';
        const hintedServerLabel = config.serverLabel || hintedServerIp || guessedServer || '';
        const hintedServerSource = config.serverSource || (config.serverIp ? 'network_prefix' : '');
        const startedAtMs = Date.now();
        const startedAtIso = new Date(startedAtMs).toISOString();
        const cooldownIso = new Date(Math.max(cooldownUntilMs, startedAtMs + BANDWIDTH_RATE_LIMIT_MS)).toISOString();

        bandwidthResults.set(routerKey, {
            status: 'running',
            router_ip: routerIp,
            router_name: routerName,
            interface: interfaceName,
            guessed_server: guessedServer,
            server_ip: hintedServerIp || null,
            server_label: hintedServerLabel || null,
            server_source: hintedServerSource || null,
            started_at: startedAtIso,
            direction: settings.direction,
            duration: settings.duration,
            protocol: settings.protocol,
            next_available_at: cooldownIso,
            rate_limit: {
                window_seconds: cooldownWindowSeconds,
                next_available_at: cooldownIso,
                last_started_at: startedAtIso,
            },
        });

        updateBandwidthCooldown(routerKey, Math.max(cooldownUntilMs, startedAtMs + BANDWIDTH_RATE_LIMIT_MS));

        if (onStart) {
            try {
                onStart();
            } catch (error) {
                console.error(error);
            }
        }

        renderRouters(state);

        const payload = {
            router_ip: routerIp,
            interface: interfaceName,
            direction: settings.direction,
            duration: settings.duration,
            protocol: settings.protocol,
        };

        const controller = new AbortController();
        const expectedDuration = Math.max(1, Number(settings.duration ?? DEFAULT_BANDWIDTH_DURATION));
        const timeoutMs = Math.max((expectedDuration * 1000) + BANDWIDTH_TIMEOUT_BUFFER_MS, 15000);
        const timeoutHandle = setTimeout(() => controller.abort(), timeoutMs);

        try {
            const response = await fetch('api/bandwidth_test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                signal: controller.signal,
            });

            const result = await parseJsonSafe(response);

            if (!response.ok || !result.success) {
                const errorObject = new Error(result?.message || 'Bandwidth test gagal dijalankan.');
                errorObject.details = result;
                errorObject.status = response.status;
                throw errorObject;
            }

            result.interface = result.interface || interfaceName;

            const updatedSettings = updateBandwidthSettingsMap(routerKey, {
                direction: result.options?.direction_value ?? result.options?.direction ?? settings.direction,
                duration: result.options?.duration ?? settings.duration,
                protocol: result.options?.protocol ?? settings.protocol,
            });

            const completedAtIso = result.completed_at || new Date().toISOString();
            const completedAtTimestamp = Date.parse(completedAtIso) || Date.now();
            const rateLimitInfo = result.rate_limit || {};
            const nextAvailableIso = rateLimitInfo.next_available_at
                || new Date(Math.max(cooldownUntilMs, completedAtTimestamp)).toISOString();
            const nextAvailableTimestamp = Date.parse(nextAvailableIso);

            if (!Number.isNaN(nextAvailableTimestamp)) {
                updateBandwidthCooldown(routerKey, nextAvailableTimestamp);
            } else {
                updateBandwidthCooldown(routerKey, Math.max(Date.now(), cooldownUntilMs));
            }

            bandwidthResults.set(routerKey, {
                status: 'success',
                router_ip: routerIp,
                router_name: routerName,
                interface: result.interface || interfaceName,
                guessed_server: guessedServer,
                server_ip: result.server_ip ?? null,
                server_label: result.server_label ?? null,
                server_source: result.server_source ?? null,
                started_at: result.started_at || startedAtIso,
                completed_at: completedAtIso,
                direction: updatedSettings.direction,
                duration: updatedSettings.duration,
                protocol: updatedSettings.protocol,
                next_available_at: nextAvailableIso,
                rate_limit: {
                    window_seconds: rateLimitInfo.window_seconds ?? cooldownWindowSeconds,
                    next_available_at: nextAvailableIso,
                    last_started_at: rateLimitInfo.last_started_at || (result.started_at ?? startedAtIso),
                    last_completed_at: rateLimitInfo.last_completed_at || completedAtIso,
                },
                result,
            });

            if (onSuccess) {
                try {
                    onSuccess(result);
                } catch (callbackError) {
                    console.error(callbackError);
                }
            }

            renderRouters(state);
            openBandwidthResultModal(routerKey);
        } catch (error) {
            const nowMs = Date.now();
            let message = error?.message || 'Bandwidth test gagal dijalankan.';
            const details = error?.details || {};
            const retryAfterSeconds = Number(details.retry_after ?? 0);

            if (error?.name === 'AbortError') {
                message = 'Bandwidth test melewati batas waktu tunggu. Pastikan router merespons lebih cepat.';
            }

            let cooldownExpiryMs = Math.max(cooldownUntilMs, startedAtMs + BANDWIDTH_RATE_LIMIT_MS);

            if (Number.isFinite(retryAfterSeconds) && retryAfterSeconds > 0) {
                cooldownExpiryMs = nowMs + (retryAfterSeconds * 1000);
            }

            updateBandwidthCooldown(routerKey, cooldownExpiryMs);

            const nextAvailableIso = new Date(Math.max(cooldownExpiryMs, nowMs)).toISOString();

            const errorState = {
                status: 'error',
                router_ip: routerIp,
                router_name: routerName,
                interface: interfaceName,
                guessed_server: guessedServer,
                server_ip: hintedServerIp || null,
                server_label: hintedServerLabel || null,
                server_source: hintedServerSource || null,
                started_at: startedAtIso,
                completed_at: new Date().toISOString(),
                direction: settings.direction,
                duration: settings.duration,
                protocol: settings.protocol,
                message,
                next_available_at: nextAvailableIso,
                rate_limit: {
                    window_seconds: details?.rate_limit?.window_seconds ?? cooldownWindowSeconds,
                    next_available_at: nextAvailableIso,
                    last_started_at: details?.rate_limit?.last_started_at ?? startedAtIso,
                    last_completed_at: details?.rate_limit?.last_completed_at ?? null,
                },
                result: details,
                retry_after: Number.isFinite(retryAfterSeconds) && retryAfterSeconds > 0
                    ? retryAfterSeconds
                    : undefined,
            };

            if (onError) {
                try {
                    onError(message, { error, result: errorState });
                } catch (callbackError) {
                    console.error(callbackError);
                }
            }

            bandwidthResults.set(routerKey, errorState);

            renderRouters(state);
            errorBox.textContent = message;
            errorBox.hidden = false;
        } finally {
            clearTimeout(timeoutHandle);

            if (onFinally) {
                try {
                    onFinally();
                } catch (callbackError) {
                    console.error(callbackError);
                }
            }
        }
    };

    const resetBandwidthConfigFeedback = () => {
        if (!bandwidthConfigFeedback) {
            return;
        }

        bandwidthConfigFeedback.textContent = '';
        bandwidthConfigFeedback.classList.remove('is-error', 'is-success', 'is-info');
        bandwidthConfigFeedback.setAttribute('hidden', '');
    };

    const showBandwidthConfigFeedback = (message, type = 'error') => {
        if (!bandwidthConfigFeedback) {
            return;
        }

        if (!message) {
            resetBandwidthConfigFeedback();

            return;
        }

        bandwidthConfigFeedback.textContent = message;
        bandwidthConfigFeedback.classList.toggle('is-success', type === 'success');
        bandwidthConfigFeedback.classList.toggle('is-error', type === 'error');
        bandwidthConfigFeedback.classList.toggle('is-info', type === 'info');
        bandwidthConfigFeedback.removeAttribute('hidden');
    };

    const setBandwidthConfigLoading = (isLoading, labelText = null) => {
        if (!bandwidthConfigRunButton) {
            return;
        }

        const spinner = bandwidthConfigRunButton.querySelector('.button-spinner');
        const label = bandwidthConfigRunLabel;

        if (isLoading) {
            bandwidthConfigRunButton.dataset.loading = 'true';
            bandwidthConfigRunButton.setAttribute('aria-busy', 'true');
            bandwidthConfigRunButton.disabled = true;
        } else {
            delete bandwidthConfigRunButton.dataset.loading;
            bandwidthConfigRunButton.setAttribute('aria-busy', 'false');
            bandwidthConfigRunButton.disabled = false;
        }

        if (spinner) {
            spinner.style.display = isLoading ? 'inline-block' : 'none';
        }

        if (label) {
            if (labelText) {
                label.textContent = labelText;
            } else if (!isLoading) {
                label.textContent = defaultBandwidthRunLabel;
            }
        }
    };

    const closeBandwidthConfigModal = () => {
        if (!bandwidthConfigModal) {
            return;
        }

        bandwidthConfigModal.setAttribute('hidden', '');
        bandwidthConfigModal.classList.remove('is-visible');
        bandwidthConfigContext = null;
        resetBandwidthConfigFeedback();
        setBandwidthConfigLoading(false);
    };

    const openBandwidthConfigModal = (button) => {
        if (!bandwidthConfigModal || !button) {
            return;
        }

        const routerKey = button.getAttribute('data-router-key') || '';
        const routerIp = button.getAttribute('data-router-ip') || '';
        const routerName = button.getAttribute('data-router-name') || routerIp || 'Router';
        const routerServerIp = String(button.getAttribute('data-router-server-ip') || '').trim();
        const routerServerLabel = button.getAttribute('data-router-server-label') || routerServerIp || '';
        const routerServerSource = button.getAttribute('data-router-server-source') || '';
        const guessedServer = guessServerFromIp(routerIp);
        const interfaceName = button.getAttribute('data-interface-name')
            || interfaceSelections.get(routerKey)
            || '';

        if (!routerKey || !routerIp) {
            errorBox.textContent = 'Router tidak memiliki informasi IP yang valid untuk bandwidth test.';
            errorBox.hidden = false;

            return;
        }

        const settings = ensureBandwidthSettings(routerKey);

        const cooldownRemainingMs = getBandwidthCooldownRemainingMs(routerKey);

        bandwidthConfigContext = {
            routerKey,
            routerIp,
            routerName,
            interfaceName,
            serverIp: routerServerIp || guessedServer || '',
            serverLabel: routerServerLabel || routerServerIp || guessedServer || '',
            serverSource: routerServerSource || (routerServerIp || guessedServer ? 'network_prefix' : ''),
            cooldownRemainingMs: Math.max(0, cooldownRemainingMs),
        };

        setBandwidthConfigLoading(false);

        if (bandwidthConfigRouterLabel) {
            bandwidthConfigRouterLabel.textContent = routerName && routerName !== routerIp
                ? `${routerName} (${routerIp})`
                : routerIp || routerName;
        }

        if (bandwidthConfigInterfaceLabel) {
            bandwidthConfigInterfaceLabel.textContent = interfaceName || '-';
        }

        if (bandwidthConfigServerLabel) {
            const labelParts = [];

            if (bandwidthConfigContext.serverLabel) {
                labelParts.push(bandwidthConfigContext.serverLabel);
            }

            if (bandwidthConfigContext.serverIp && bandwidthConfigContext.serverLabel !== bandwidthConfigContext.serverIp) {
                labelParts.push(`(${bandwidthConfigContext.serverIp})`);
            }

            bandwidthConfigServerLabel.textContent = labelParts.length
                ? labelParts.join(' ')
                : (bandwidthConfigContext.serverIp || guessedServer || '-');
        }

        if (bandwidthConfigDirectionField) {
            bandwidthConfigDirectionField.value = settings.direction;
        }

        if (bandwidthConfigProtocolField) {
            bandwidthConfigProtocolField.value = settings.protocol || 'tcp';
        }

        if (bandwidthConfigDurationField) {
            bandwidthConfigDurationField.value = String(settings.duration ?? DEFAULT_BANDWIDTH_DURATION);
        }

        if (cooldownRemainingMs > 0) {
            const seconds = Math.ceil(cooldownRemainingMs / 1000);
            showBandwidthConfigFeedback(
                `Bandwidth test terakhir baru dijalankan. Tunggu ${seconds} detik sebelum mencoba lagi.`,
                'info',
            );
        } else {
            resetBandwidthConfigFeedback();
        }

        bandwidthConfigModal.removeAttribute('hidden');
        bandwidthConfigModal.classList.add('is-visible');

        window.requestAnimationFrame(() => {
            bandwidthConfigDirectionField?.focus();
        });
    };

    const persistInterfaceSelection = async (config) => {
        const routerKey = config?.routerKey || '';
        const clientKey = config?.clientKey || '';
        const routerIp = config?.routerIp || '';
        const interfaceName = typeof config?.interfaceName === 'string' ? config.interfaceName : '';
        const previousInterfaceName = typeof config?.previousInterfaceName === 'string'
            ? config.previousInterfaceName
            : '';

        if (clientKey === '' && routerIp === '') {
            return;
        }

        const payload = {
            interface: interfaceName,
        };

        if (clientKey !== '') {
            payload.client_key = clientKey;
        }

        if (routerIp !== '') {
            payload.ip_address = routerIp;
        }

        try {
            const response = await fetch('api/router_interface.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            const result = await parseJsonSafe(response);

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Gagal menyimpan pilihan interface.');
            }

            errorBox.hidden = true;

            if (result.snapshot) {
                applyRouterClientSnapshot(result.snapshot);
            }

            fetchLatest();
        } catch (error) {
            errorBox.textContent = error.message || 'Gagal menyimpan pilihan interface.';
            errorBox.hidden = false;

            if (routerKey) {
                if (previousInterfaceName && previousInterfaceName !== '') {
                    interfaceSelections.set(routerKey, previousInterfaceName);
                } else {
                    interfaceSelections.delete(routerKey);
                }
            }

            updateLocalPreferredInterface(routerKey, previousInterfaceName || '', { clientKey, routerIp });
            renderRouters(state);
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
            bandwidthResults.delete(routerKey);
            if (activeBandwidthModalKey === routerKey) {
                closeBandwidthModal();
            }
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
        selectedClient = null;
        selectedClientKey = null;
        updateClientSelection();
        await loadClients(true);
        requestAnimationFrame(() => {
            clientSearchInput?.focus();
        });
    };

    const closeModal = () => {
        clientModal?.setAttribute('hidden', '');
        clientModal?.classList.remove('is-visible');
        selectedClient = null;
        clientFeedback.textContent = '';
        clientFeedback.classList.remove('error', 'success');
        updateClientSelection();
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
                    updateClientSelection();
                }
            }

            updateClientSelection();
            renderClientList();
        } catch (error) {
            clientsState = defaultClientSnapshot();
            renderClientList();
            clientFeedback.textContent = error.message || 'Gagal memuat daftar AP.';
            clientFeedback.classList.add('error');
            updateClientSelection();
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

    const updateClientSelection = () => {
        if (!clientPreview) {
            if (clientSubmitButton) {
                clientSubmitButton.disabled = !selectedClient;
            }

            return;
        }

        if (!selectedClient) {
            clientPreview.innerHTML = `
                <p>Pilih akun PPPoE aktif maupun tidak aktif untuk menambahkan router ke monitoring interface.</p>
                <p class="hint">Router akan disimpan otomatis dengan kredensial <code>rondi</code> / <code>21184662</code> dan ditandai sebagai server PPPoE.</p>
            `;

            if (clientSubmitButton) {
                clientSubmitButton.disabled = true;
                clientSubmitButton.textContent = defaultClientSubmitLabel;
            }

            selectedClientKey = null;

            return;
        }

        const key = selectedClient.client_key ? String(selectedClient.client_key) : buildClientKey(selectedClient);
        const usernameLabel = selectedClient.pppoe_username
            ?? selectedClient.username
            ?? selectedClient.client_name
            ?? selectedClient.client_key
            ?? '-';
        const displayName = selectedClient.client_name
            ?? selectedClient.comment
            ?? usernameLabel;
        const addressLabel = selectedClient.address
            ?? selectedClient.client_address
            ?? selectedClient.ip_address
            ?? '-';
        const profileLabel = selectedClient.profile ?? '-';
        const statusLabel = selectedClient.status === 'active' ? 'Aktif' : 'Tidak aktif';
        const serverLabel = selectedClient.server_name || selectedClient.server_ip || '-';

        clientPreview.innerHTML = `
            <p><strong>${escapeHtml(displayName)}</strong> &bull; ${escapeHtml(statusLabel)} &bull; Profil ${escapeHtml(profileLabel)}</p>
            <p>${escapeHtml(addressLabel)} &bull; Server ${escapeHtml(serverLabel)}</p>
            <p class="hint">Router akan otomatis menggunakan kredensial <code>rondi</code> / <code>21184662</code>.</p>
        `;

        selectedClientKey = key;

        if (clientSubmitButton) {
            clientSubmitButton.disabled = false;
            clientSubmitButton.textContent = defaultClientSubmitLabel;
        }
    };

    let sidebarUserOverridden = false;

    const setSidebarCollapsed = (collapsed, { fromUser = false } = {}) => {
        if (fromUser) {
            sidebarUserOverridden = true;
        } else if (!collapsed) {
            sidebarUserOverridden = false;
        }

        sidebarToggle?.setAttribute('aria-expanded', String(!collapsed));
        dashboardLayout?.classList.toggle('sidebar-collapsed', collapsed);
        sidebar?.classList.toggle('collapsed', collapsed);
    };

    const sidebarMediaQuery = window.matchMedia('(max-width: 960px)');

    const handleSidebarMediaChange = () => {
        if (sidebarUserOverridden) {
            return;
        }

        setSidebarCollapsed(sidebarMediaQuery.matches);
    };

    sidebarToggle?.addEventListener('click', () => {
        const currentlyCollapsed = dashboardLayout?.classList.contains('sidebar-collapsed');
        setSidebarCollapsed(!currentlyCollapsed, { fromUser: true });
    });

    if (sidebarMediaQuery.addEventListener) {
        sidebarMediaQuery.addEventListener('change', handleSidebarMediaChange);
    } else if (sidebarMediaQuery.addListener) {
        sidebarMediaQuery.addListener(handleSidebarMediaChange);
    }

    handleSidebarMediaChange();

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

    const handleRouterFilterInput = (event) => {
        routerFilterTerm = event?.target?.value ?? '';
        applyRouterFilter();
    };

    routerFilterInput?.addEventListener('input', handleRouterFilterInput);
    routerFilterInput?.addEventListener('search', handleRouterFilterInput);

    routersContainer?.addEventListener('change', (event) => {
        const select = event.target.closest('[data-interface-select]');

        if (!select) {
            return;
        }

        const routerKey = select.getAttribute('data-router-key') || '';
        const row = select.closest('[data-router-key]');
        const clientKey = row?.getAttribute('data-client-key') || '';
        const routerIp = row?.getAttribute('data-router-ip') || '';
        const selectedValue = typeof select.value === 'string' ? select.value : '';
        const routerState = routerKey ? findRouterStateByKey(routerKey) : null;
        const previousValue = interfaceSelections.has(routerKey)
            ? interfaceSelections.get(routerKey)
            : (routerState?.preferred_interface ?? routerState?.iface ?? '');

        if (errorBox) {
            errorBox.hidden = true;
        }

        if (routerKey) {
            interfaceSelections.set(routerKey, selectedValue);
        }

        updateLocalPreferredInterface(routerKey, selectedValue, { clientKey, routerIp });
        renderRouters(state);

        persistInterfaceSelection({
            routerKey,
            clientKey,
            routerIp,
            interfaceName: selectedValue,
            previousInterfaceName: previousValue ?? '',
        });
    });

    routersContainer?.addEventListener('click', (event) => {
        const configButton = event.target.closest('[data-open-bandwidth-config]');

        if (configButton) {
            openBandwidthConfigModal(configButton);

            return;
        }

        const openButton = event.target.closest('[data-open-bandwidth-modal]');

        if (openButton) {
            const routerKey = openButton.getAttribute('data-router-key') || '';

            if (routerKey) {
                openBandwidthResultModal(routerKey);
            }

            return;
        }

        const button = event.target.closest('[data-remove-router]');

        if (!button || button.disabled) {
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

    bandwidthConfigCloseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closeBandwidthConfigModal();
        });
    });

    bandwidthConfigModal?.addEventListener('click', (event) => {
        if (event.target === bandwidthConfigModal) {
            closeBandwidthConfigModal();
        }
    });

    bandwidthConfigForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!bandwidthConfigContext) {
            closeBandwidthConfigModal();

            return;
        }

        const context = { ...bandwidthConfigContext };
        const directionValue = sanitiseBandwidthDirection(bandwidthConfigDirectionField?.value);
        const protocolValue = sanitiseBandwidthProtocol(bandwidthConfigProtocolField?.value);
        const durationValue = sanitiseBandwidthDuration(bandwidthConfigDurationField?.value ?? DEFAULT_BANDWIDTH_DURATION);
        const now = Date.now();
        const cooldownRemainingMs = getBandwidthCooldownRemainingMs(context.routerKey);

        if (bandwidthConfigDirectionField) {
            bandwidthConfigDirectionField.value = directionValue;
        }

        if (bandwidthConfigProtocolField) {
            bandwidthConfigProtocolField.value = protocolValue;
        }

        if (bandwidthConfigDurationField) {
            bandwidthConfigDurationField.value = String(durationValue);
        }

        if (!context.interfaceName) {
            showBandwidthConfigFeedback('Pilih interface terlebih dahulu sebelum menjalankan bandwidth test.');

            return;
        }

        if (cooldownRemainingMs > 0) {
            const seconds = Math.ceil(cooldownRemainingMs / 1000);
            showBandwidthConfigFeedback(
                `Bandwidth test terakhir baru dijalankan. Tunggu ${seconds} detik sebelum mencoba lagi.`,
                'info',
            );

            return;
        }

        const cooldownUntil = now + BANDWIDTH_RATE_LIMIT_MS;
        updateBandwidthCooldown(context.routerKey, cooldownUntil);

        setBandwidthConfigLoading(true, 'Menjalankan…');
        showBandwidthConfigFeedback('Menjalankan bandwidth test…', 'info');

        runBandwidthTest({
            routerKey: context.routerKey,
            routerIp: context.routerIp,
            routerName: context.routerName,
            interfaceName: context.interfaceName,
            direction: directionValue,
            duration: durationValue,
            protocol: protocolValue,
            serverLabel: context.serverLabel,
            serverIp: context.serverIp,
            serverSource: context.serverSource,
            cooldownUntil,
            cooldownWindowSeconds: BANDWIDTH_RATE_LIMIT_SECONDS,
            onSuccess: () => {
                resetBandwidthConfigFeedback();
                closeBandwidthConfigModal();
            },
            onError: (message) => {
                showBandwidthConfigFeedback(message || 'Bandwidth test gagal dijalankan.', 'error');
            },
            onFinally: () => {
                setBandwidthConfigLoading(false);
            },
        });
    });

    bandwidthModalCloseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closeBandwidthModal();
        });
    });

    bandwidthModal?.addEventListener('click', (event) => {
        if (event.target === bandwidthModal) {
            closeBandwidthModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        let handled = false;

        if (bandwidthConfigModal && !bandwidthConfigModal.hasAttribute('hidden')) {
            closeBandwidthConfigModal();
            handled = true;
        }

        if (!handled && bandwidthModal && !bandwidthModal.hasAttribute('hidden')) {
            closeBandwidthModal();
            handled = true;
        }

        if (!handled && clientModal && !clientModal.hasAttribute('hidden')) {
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
        updateClientSelection();
        renderClientList();
    });

    clientSubmitButton?.addEventListener('click', async () => {
        if (!selectedClient) {
            clientFeedback.textContent = 'Silakan pilih PPPoE client terlebih dahulu.';
            clientFeedback.classList.add('error');

            return;
        }

        const payload = buildRouterPayloadFromClient(selectedClient);

        if (!payload.ip_address || !payload.name) {
            clientFeedback.textContent = 'Data PPPoE tidak memiliki alamat IP atau nama yang valid.';
            clientFeedback.classList.add('error');

            return;
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
            clientSubmitButton.textContent = defaultClientSubmitLabel;
            clientSubmitButton.disabled = false;
        }
    });

    syncManualScaleFromInput();

    const initialData = parseInitialData();
    renderAll(initialData, { forceFull: true });
    scheduleRefresh(true);

});
</script>
</body>
</html>
