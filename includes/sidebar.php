<?php
include_once __DIR__ . '/stock_units.php';
include_once __DIR__ . '/menu_requests.php';
$current_module = $current_module ?? '';
$current_role = function_exists('auth_current_role') ? auth_current_role() : '';
$is_owner = $current_role === 'owner';
$is_kasir = $current_role === 'kasir';
$is_barista = $current_role === 'barista';
$home_path = function_exists('auth_home_path') ? auth_home_path() : '/pos_olbeemi/login.php';
$current_user = function_exists('auth_current_user') ? auth_current_user() : null;
$pending_stock_requests = 0;
$pending_menu_requests = 0;
$pending_material_orders = 0;
$barista_rejected_stock_requests = 0;
$barista_rejected_menu_requests = 0;
$barista_active_orders = 0;
$barista_incoming_goods = 0;

if ($is_owner && isset($conn)) {
    $request_table = mysqli_query($conn, "SHOW TABLES LIKE 'permintaan_stok'");
    if ($request_table && mysqli_num_rows($request_table) > 0) {
        $stock_count_expression = stockRequestCountExpression($conn);
        $pending_query = mysqli_query($conn, "
            SELECT $stock_count_expression AS total
            FROM permintaan_stok
            WHERE status = 'Menunggu'
        ");
        $pending_data = $pending_query ? mysqli_fetch_assoc($pending_query) : null;
        $pending_stock_requests = (int) ($pending_data['total'] ?? 0);
    }
}

if (($is_barista || $is_kasir) && isset($conn)) {
    $orders_table = mysqli_query($conn, "SHOW TABLES LIKE 'pesanan'");
    if ($orders_table && mysqli_num_rows($orders_table) > 0) {
        $active_orders_query = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM pesanan
            WHERE status IN ('Menunggu', 'Diproses')
        ");
        $active_orders_data = $active_orders_query ? mysqli_fetch_assoc($active_orders_query) : null;
        $barista_active_orders = (int) ($active_orders_data['total'] ?? 0);
    }

    $supplier_orders_table = mysqli_query($conn, "SHOW TABLES LIKE 'pesanan_supplier'");
    if ($supplier_orders_table && mysqli_num_rows($supplier_orders_table) > 0) {
        $incoming_goods_query = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM pesanan_supplier
            WHERE status = 'Sudah Dipesan'
        ");
        $incoming_goods_data = $incoming_goods_query ? mysqli_fetch_assoc($incoming_goods_query) : null;
        $barista_incoming_goods = (int) ($incoming_goods_data['total'] ?? 0);
    }

    $menu_request_table = mysqli_query($conn, "SHOW TABLES LIKE 'permintaan_menu'");
    if ($menu_request_table && mysqli_num_rows($menu_request_table) > 0 && $current_user) {
        $menu_rejected_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM permintaan_menu WHERE user_id = " . (int) $current_user['id'] . " AND status = 'Ditolak'");
        $menu_rejected_data = $menu_rejected_query ? mysqli_fetch_assoc($menu_rejected_query) : null;
        $barista_rejected_menu_requests = (int) ($menu_rejected_data['total'] ?? 0);
    }
}

if ($is_owner && isset($conn)) {
    $menu_request_table = mysqli_query($conn, "SHOW TABLES LIKE 'permintaan_menu'");
    if ($menu_request_table && mysqli_num_rows($menu_request_table) > 0) {
        $menu_pending_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM permintaan_menu WHERE status = 'Menunggu'");
        $menu_pending_data = $menu_pending_query ? mysqli_fetch_assoc($menu_pending_query) : null;
        $pending_menu_requests = (int) ($menu_pending_data['total'] ?? 0);
    }
}

if ($is_barista && isset($conn) && $current_user) {
    $stock_request_table = mysqli_query($conn, "SHOW TABLES LIKE 'permintaan_stok'");
    if ($stock_request_table && mysqli_num_rows($stock_request_table) > 0) {
        $stock_count_expression = stockRequestCountExpression($conn);
        $stock_rejected_query = mysqli_query($conn, "SELECT $stock_count_expression AS total FROM permintaan_stok WHERE user_id = " . (int) $current_user['id'] . " AND status = 'Ditolak'");
        $stock_rejected_data = $stock_rejected_query ? mysqli_fetch_assoc($stock_rejected_query) : null;
        $barista_rejected_stock_requests = (int) ($stock_rejected_data['total'] ?? 0);
    }
}

if ($is_owner && isset($conn)) {
    $material_table = mysqli_query($conn, "SHOW TABLES LIKE 'bahan_baku'");
    $order_table = mysqli_query($conn, "SHOW TABLES LIKE 'pesanan_supplier'");
    if ($material_table && mysqli_num_rows($material_table) > 0 && $order_table && mysqli_num_rows($order_table) > 0) {
        $material_order_query = mysqli_query($conn, "
            SELECT COUNT(*) AS total
            FROM bahan_baku b
            WHERE b.stok <= b.minimum_stok
              AND NOT EXISTS (
                  SELECT 1 FROM pesanan_supplier p
                  WHERE p.bahan_id = b.id
                    AND p.status IN ('Sudah Dipesan', 'Menunggu Persetujuan')
              )
        ");
        $material_order_data = $material_order_query ? mysqli_fetch_assoc($material_order_query) : null;
        $pending_material_orders = (int) ($material_order_data['total'] ?? 0);
    }
}

function isActive($moduleName, $currentModule) {
    return $moduleName === $currentModule ? 'active' : '';
}
?>

<div class="sidebar" id="appSidebar">
    <div class="sidebar-content">
        <div class="sidebar-header">
            <a href="<?= htmlspecialchars($home_path) ?>" class="sidebar-home-link">
                <img src="/pos_olbeemi/images/logo.png" alt="Olbeemi" class="sidebar-logo">
            </a>
        </div>
        <?php if ($current_user): ?>
        <div class="sidebar-user-card">
            <strong><?= htmlspecialchars(ucfirst($current_role)) ?></strong>
        </div>
        <?php endif; ?>
        <?php
        $navigation = [];
        if ($is_owner || $is_kasir || $is_barista) {
            $navigation['Ringkasan'] = [
                ['dashboard', 'dashboard/index.php', 'Dashboard', '⌂'],
            ];
            $navigation['Penjualan'] = [];
            if ($is_owner || $is_kasir) {
                $navigation['Penjualan'][] = ['transaksi', 'transaksi/index.php', 'Kasir', '🧾'];
            }
            $navigation['Penjualan'][] = ['pesanan', 'pesanan/index.php', 'Pesanan', '🛒', ($is_barista || $is_kasir) ? $barista_active_orders : 0];
            if ($is_owner || $is_kasir) {
                $navigation['Penjualan'][] = ['qr', 'pesanan/qr.php', 'QR Menu', '📱'];
            }
        }
        if ($is_owner || $is_barista) {
            $navigation['Menu'] = [
                ['menu', 'menu/index.php', 'Daftar Menu', '🍽️'],
                ['permintaan_menu', 'menu/permintaan.php', 'Pengajuan Menu', '📋', $is_owner ? $pending_menu_requests : $barista_rejected_menu_requests],
            ];
            $navigation['Persediaan'] = [
                ['bahan', 'bahan/index.php', 'Stok Bahan Baku', '📦'],
                ['pesan_supplier', 'bahan/pesan_supplier.php', $is_owner ? 'Pesan Bahan Baku' : 'Penerimaan Bahan', '📥', $is_owner ? $pending_material_orders : $barista_incoming_goods],
                ['permintaan_stok', 'bahan/permintaan_stok.php', $is_owner ? 'Pengajuan Penyesuaian Stok' : 'Penyesuaian Stok', '📋', $is_owner ? $pending_stock_requests : $barista_rejected_stock_requests],
            ];
            if ($is_owner) {
                $navigation['Persediaan'][] = ['suppliers', 'bahan/suppliers.php', 'Supplier', '🚚'];
            }
            $navigation['Riwayat Stok'] = [
                ['riwayat_masuk', 'bahan/riwayat_masuk.php', 'Riwayat Stok Masuk', '📥'],
                ['riwayat_keluar', 'bahan/riwayat_keluar.php', 'Riwayat Stok Keluar', '📤'],
            ];
        }
        if ($is_owner) {
            $navigation['Laporan'] = [
                ['laporan', 'laporan/index.php', 'Laporan Penjualan', '📊'],
                ['pengeluaran', 'bahan/pengeluaran.php', 'Laporan Pengeluaran', '💸'],
            ];
        }
        ?>
        <?php foreach ($navigation as $group => $links): ?>
        <div class="sidebar-menu-group">
            <div class="sidebar-title"><?= htmlspecialchars($group) ?></div>
            <?php foreach ($links as $link): ?>
            <a href="/pos_olbeemi/<?= htmlspecialchars($link[1]) ?>" class="<?= isActive($link[0], $current_module) ?>"<?= $link[0] === $current_module ? ' aria-current="page"' : '' ?>>
                <span class="nav-icon" aria-hidden="true"><?= $link[3] ?></span>
                <span><?= htmlspecialchars($link[2]) ?></span>
                <?php if (($link[4] ?? 0) > 0 || in_array($link[0], ['permintaan_menu', 'permintaan_stok'], true)): ?>
                    <span class="sidebar-pending-count" data-request-badge="<?= htmlspecialchars($link[0]) ?>"<?= ($link[4] ?? 0) > 0 ? '' : ' hidden' ?>><?= (int) ($link[4] ?? 0) ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="sidebar-footer">
        <?php if (function_exists('auth_is_logged_in') && auth_is_logged_in()): ?>
            <a href="/pos_olbeemi/logout.php" class="logout-btn" title="Keluar POS">
                <span class="logout-icon">⏻</span>
                <span class="logout-text">Logout</span>
            </a>
        <?php endif; ?>
    </div>
</div>
<script>
(() => {
    const sidebar = document.getElementById('appSidebar');
    const content = sidebar.querySelector('.sidebar-content');
    const key = 'olbeemi.navigationScroll.' + <?= json_encode($current_role . '.' . ($_SESSION['navigation_login_key'] ?? 'existing')) ?>;
    let saved = {};
    try { saved = JSON.parse(sessionStorage.getItem(key) || '{}') || {}; } catch (error) {}

    const restore = () => {
        sidebar.scrollTop = Number(saved.top) || 0;
        content.scrollLeft = Number(saved.left) || 0;
    };
    const remember = () => {
        saved = { top: sidebar.scrollTop, left: content.scrollLeft };
        try { sessionStorage.setItem(key, JSON.stringify(saved)); } catch (error) {}
    };
    restore();
    window.addEventListener('load', restore, { once: true });
    sidebar.addEventListener('scroll', remember, { passive: true });
    content.addEventListener('scroll', remember, { passive: true });
    sidebar.addEventListener('click', remember);
    window.addEventListener('pagehide', remember);
})();
</script>
<script>
function bindRequestButtons(selector, handler) {
    document.addEventListener('click', event => {
        const button = event.target.closest(selector);
        if (button) handler.call(button, event);
    });
}
</script>
<?php if (in_array($current_role, ['owner', 'barista'], true)): ?>
<div id="requestLiveNotice" class="request-live-notice" role="status" aria-live="polite" hidden></div>
<script>
(() => {
    let snapshot = <?= json_encode(requestNotificationSnapshot($conn), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const module = <?= json_encode($current_module) ?>;
    const moduleKey = module === 'permintaan_menu' ? 'menu' : module === 'permintaan_stok' ? 'stok' : '';
    const notice = document.getElementById('requestLiveNotice');
    const soundEnabled = <?= $current_role === 'owner' ? 'true' : 'false' ?>;
    let audioContext, soundTimer, noticeTimer, pollTimer;
    let busy = false, stopped = false, listChanged = false, refreshing = false;
    const notify = text => {
        notice.textContent = text;
        notice.hidden = false;
        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(() => { notice.hidden = true; }, 6000);
    };
    const playSound = () => {
        if (!audioContext || audioContext.state !== 'running') return;
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.frequency.value = 880;
        gain.gain.setValueAtTime(.0001, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(.08, audioContext.currentTime + .02);
        gain.gain.exponentialRampToValueAtTime(.0001, audioContext.currentTime + .28);
        oscillator.connect(gain).connect(audioContext.destination);
        oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
        oscillator.start();
        oscillator.stop(audioContext.currentTime + .3);
    };
    const unlockAudio = () => {
        if (!soundEnabled) return;
        try {
            const Audio = window.AudioContext || window.webkitAudioContext;
            if (!Audio) return;
            if (!audioContext) audioContext = new Audio();
            if (audioContext.state !== 'running') audioContext.resume().catch(() => {});
        } catch (_) {}
    };
    if (soundEnabled) {
        document.addEventListener('pointerdown', unlockAudio);
        document.addEventListener('keydown', unlockAudio);
        unlockAudio();
    }
    // Refresh only the history, never the submission form or an open popup.
    const formBusy = () => [...document.querySelectorAll('.modal')].some(modal => getComputedStyle(modal).display !== 'none');
    async function refreshList() {
        if (!moduleKey || !listChanged || refreshing || formBusy()) return;
        refreshing = true;
        const version = snapshot[moduleKey].version;
        try {
            const response = await fetch(location.href, {cache: 'no-store', credentials: 'same-origin'});
            if (!response.ok || response.redirected) throw new Error('Daftar tidak tersedia');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const body = doc.getElementById('requestTableBody');
            const current = document.getElementById('requestTableBody');
            if (!body || !current || formBusy()) return;
            const content = document.querySelector('.main-content');
            const top = content?.scrollTop || 0;
            current.replaceChildren(...body.childNodes);
            const tabs = document.querySelector('.workflow-tabs');
            const nextTabs = doc.querySelector('.workflow-tabs');
            if (tabs && nextTabs) tabs.replaceChildren(...nextTabs.childNodes);
            document.getElementById('requestSearch')?.dispatchEvent(new Event('input'));
            if (content) content.scrollTop = top;
            listChanged = snapshot[moduleKey].version !== version;
        } catch (_) {
            notify('Daftar belum diperbarui. Sistem akan mencoba lagi.');
        } finally { refreshing = false; }
    }
    async function poll() {
        if (stopped || busy) return;
        clearTimeout(pollTimer);
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch('/pos_olbeemi/menu/permintaan.php?ajax=pending_count', {cache: 'no-store', credentials: 'same-origin', signal: controller.signal});
            if (!response.ok || response.redirected) throw new Error('Sesi atau jaringan tidak tersedia');
            const next = await response.json();
            let newRequest = false;
            for (const key of ['menu', 'stok']) {
                if (!next[key] || !Array.isArray(next[key].ids)) throw new Error('Respons tidak valid');
                newRequest ||= next[key].ids.some(id => !snapshot[key].ids.includes(id));
                const badge = document.querySelector('[data-request-badge="permintaan_' + key + '"]');
                if (badge) { badge.textContent = next[key].count; badge.hidden = next[key].count === 0; }
                const dashboardCount = document.querySelector('[data-request-dashboard="' + key + '"]');
                if (dashboardCount) dashboardCount.textContent = next[key].count;
                if (key === moduleKey && next[key].version !== snapshot[key].version) listChanged = true;
            }
            snapshot = next;
            if (newRequest) {
                notify('Ada pembaruan pengajuan. Silakan periksa detailnya.');
                if (soundEnabled) {
                    clearTimeout(soundTimer);
                    soundTimer = setTimeout(playSound, 3000);
                }
            }
            await refreshList();
        } catch (_) {
            // Keep the last successful values while offline or after session expiry.
        } finally {
            clearTimeout(timeout);
            busy = false;
            if (!stopped) pollTimer = setTimeout(poll, 5000);
        }
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(pollTimer); clearTimeout(soundTimer); });
    window.addEventListener('pageshow', () => { stopped = false; poll(); });
    pollTimer = setTimeout(poll, 5000);
})();
</script><?php endif; ?>
