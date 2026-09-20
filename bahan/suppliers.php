<?php
include '../config/database.php';
include '../includes/suppliers.php';

ensureSupplierTables($conn);

function supplier_redirect() {
    header('Location: suppliers.php');
    exit;
}

function supplier_format_number($value) {
    return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        supplier_redirect();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_supplier') {
        $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
        $nama_supplier = trim($_POST['nama_supplier'] ?? '');
        $no_telepon = trim($_POST['no_telepon'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        $bahan_ids = $_POST['bahan_id'] ?? [];
        $harga_list = $_POST['harga'] ?? [];

        if ($nama_supplier === '') {
            supplier_redirect();
        }

        if (!is_array($bahan_ids) || !is_array($harga_list) || count($bahan_ids) !== count($harga_list)) {
            supplier_redirect();
        }

        $selected_bahan = [];
        $prepared_rows = [];

        foreach ($bahan_ids as $index => $bahan_id_raw) {
            $bahan_id = (int) $bahan_id_raw;
            $harga = (float) ($harga_list[$index] ?? 0);

            if ($bahan_id <= 0) {
                continue;
            }

            if (isset($selected_bahan[$bahan_id])) {
                supplier_redirect();
            }

            $selected_bahan[$bahan_id] = true;
            $prepared_rows[] = [
                'bahan_id' => $bahan_id,
                'harga' => max(0, $harga),
            ];
        }

        $nama_safe = mysqli_real_escape_string($conn, $nama_supplier);
        $telepon_safe = mysqli_real_escape_string($conn, $no_telepon);
        $alamat_safe = mysqli_real_escape_string($conn, $alamat);

        $duplicate_query = mysqli_query($conn, "
            SELECT id
            FROM supplier
            WHERE nama_supplier = '$nama_safe'
            AND id != $supplier_id
            LIMIT 1
        ");

        if ($duplicate_query && mysqli_num_rows($duplicate_query) > 0) {
            supplier_redirect();
        }

        mysqli_begin_transaction($conn);

        try {
            if ($supplier_id > 0) {
                $update_supplier = mysqli_query($conn, "
                    UPDATE supplier
                    SET
                        nama_supplier = '$nama_safe',
                        no_telepon = " . ($no_telepon !== '' ? "'$telepon_safe'" : "NULL") . ",
                        alamat = " . ($alamat !== '' ? "'$alamat_safe'" : "NULL") . "
                    WHERE id = $supplier_id
                ");

                if (!$update_supplier) {
                    throw new Exception(mysqli_error($conn));
                }

                $delete_items = mysqli_query($conn, "
                    DELETE FROM supplier_bahan
                    WHERE supplier_id = $supplier_id
                ");

                if (!$delete_items) {
                    throw new Exception(mysqli_error($conn));
                }
            } else {
                $insert_supplier = mysqli_query($conn, "
                    INSERT INTO supplier (
                        nama_supplier,
                        no_telepon,
                        alamat
                    ) VALUES (
                        '$nama_safe',
                        " . ($no_telepon !== '' ? "'$telepon_safe'" : "NULL") . ",
                        " . ($alamat !== '' ? "'$alamat_safe'" : "NULL") . "
                    )
                ");

                if (!$insert_supplier) {
                    throw new Exception(mysqli_error($conn));
                }

                $supplier_id = (int) mysqli_insert_id($conn);
            }

            foreach ($prepared_rows as $row) {
                $insert_item = mysqli_query($conn, "
                    INSERT INTO supplier_bahan (
                        supplier_id,
                        bahan_id,
                        harga
                    ) VALUES (
                        $supplier_id,
                        " . (int) $row['bahan_id'] . ",
                        '" . (float) $row['harga'] . "'
                    )
                ");

                if (!$insert_item) {
                    throw new Exception(mysqli_error($conn));
                }
            }

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            die($e->getMessage());
        }

        supplier_redirect();
    }

    if ($action === 'delete_supplier') {
        $supplier_id = (int) ($_POST['supplier_id'] ?? 0);

        if ($supplier_id <= 0) {
            supplier_redirect();
        }

        mysqli_begin_transaction($conn);

        try {
            $delete_items = mysqli_query($conn, "
                DELETE FROM supplier_bahan
                WHERE supplier_id = $supplier_id
            ");

            if (!$delete_items) {
                throw new Exception(mysqli_error($conn));
            }

            $delete_supplier = mysqli_query($conn, "
                DELETE FROM supplier
                WHERE id = $supplier_id
            ");

            if (!$delete_supplier) {
                throw new Exception(mysqli_error($conn));
            }

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            die($e->getMessage());
        }

        supplier_redirect();
    }
}

$bahan_options_query = mysqli_query($conn, "
    SELECT id, nama_bahan, satuan
    FROM bahan_baku
    ORDER BY nama_bahan ASC
");
$bahan_options = [];

while ($bahan = mysqli_fetch_assoc($bahan_options_query)) {
    $bahan_options[] = $bahan;
}

$supplier_query = mysqli_query($conn, "
    SELECT
        supplier.id,
        supplier.nama_supplier,
        supplier.no_telepon,
        supplier.alamat,
        supplier.created_at,
        COUNT(supplier_bahan.id) AS total_bahan
    FROM supplier
    LEFT JOIN supplier_bahan ON supplier_bahan.supplier_id = supplier.id
    GROUP BY supplier.id
    ORDER BY supplier.nama_supplier ASC
");

$suppliers = [];
while ($supplier = mysqli_fetch_assoc($supplier_query)) {
    $supplier['id'] = (int) $supplier['id'];
    $supplier['total_bahan'] = (int) $supplier['total_bahan'];
    $supplier['items'] = [];
    $suppliers[$supplier['id']] = $supplier;
}

if (!empty($suppliers)) {
    $supplier_ids = implode(',', array_keys($suppliers));
    $supplier_bahan_query = mysqli_query($conn, "
        SELECT
            supplier_bahan.id,
            supplier_bahan.supplier_id,
            supplier_bahan.bahan_id,
            supplier_bahan.harga,
            bahan_baku.nama_bahan,
            bahan_baku.satuan
        FROM supplier_bahan
        JOIN bahan_baku ON bahan_baku.id = supplier_bahan.bahan_id
        WHERE supplier_bahan.supplier_id IN ($supplier_ids)
        ORDER BY supplier_bahan.supplier_id ASC, bahan_baku.nama_bahan ASC
    ");

    while ($item = mysqli_fetch_assoc($supplier_bahan_query)) {
        $supplier_id = (int) $item['supplier_id'];

        if (!isset($suppliers[$supplier_id])) {
            continue;
        }

        $suppliers[$supplier_id]['items'][] = [
            'id' => (int) $item['id'],
            'bahan_id' => (int) $item['bahan_id'],
            'nama_bahan' => $item['nama_bahan'],
            'satuan' => $item['satuan'],
            'harga' => (float) $item['harga'],
        ];
    }
}

$supplier_json = [];
foreach ($suppliers as $supplier) {
    $supplier_json[] = [
        'id' => $supplier['id'],
        'nama_supplier' => $supplier['nama_supplier'],
        'no_telepon' => $supplier['no_telepon'],
        'alamat' => $supplier['alamat'],
        'total_bahan' => $supplier['total_bahan'],
        'created_at' => $supplier['created_at'],
        'items' => array_map(function ($item) {
            return [
                'id' => $item['id'],
                'bahan_id' => $item['bahan_id'],
                'nama_bahan' => $item['nama_bahan'],
                'satuan' => $item['satuan'],
                'harga' => supplier_format_number($item['harga']),
            ];
        }, $supplier['items']),
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/bahan.css">
</head>
<body>

<?php
$current_module = 'suppliers';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Supplier</h1>
            <p>Kelola supplier dan daftar bahan yang mereka suplai</p>
        </div>

        <div class="bahan-header-actions">
            <a href="index.php" class="btn-stock">Kembali ke Bahan</a>
            <button type="button" class="btn-primary" onclick="openSupplierFormModal()">
                + Tambah Supplier
            </button>
        </div>
    </div>

    <div class="search-box">
        <input type="text" id="supplierSearchInput" placeholder="Cari supplier atau nama bahan...">
    </div>

    <?php if (!empty($suppliers)): ?>
        <div class="supplier-page-grid" id="supplierCardGrid">
            <?php $no = 1; ?>
            <?php foreach ($suppliers as $supplier): ?>
                <article class="supplier-page-card" data-supplier-card data-original-order="<?= $no ?>" data-search="<?= htmlspecialchars(strtolower($supplier['nama_supplier'] . ' ' . implode(' ', array_column($supplier['items'], 'nama_bahan'))), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="supplier-page-head">
                        <div>
                            <span class="supplier-card-number">Supplier <?= str_pad((string) $no++, 2, '0', STR_PAD_LEFT) ?></span>
                            <h2><?= htmlspecialchars($supplier['nama_supplier']) ?></h2>
                            <p><?= htmlspecialchars($supplier['no_telepon'] ?: 'Nomor telepon belum tersedia') ?></p>
                        </div>
                        <span class="supplier-count-pill"><?= $supplier['total_bahan'] ?> bahan</span>
                    </div>

                    <div class="supplier-card-address">
                        <span>Alamat</span>
                        <p><?= htmlspecialchars($supplier['alamat'] ?: 'Alamat belum tersedia') ?></p>
                    </div>

                    <div class="supplier-card-material-head">
                        <h3>Daftar Bahan</h3>
                        <span>Nama &amp; harga</span>
                    </div>
                    <div class="supplier-table-bahan">
                        <div class="supplier-bahan-head">
                            <span>Nama</span>
                            <span>Harga</span>
                        </div>
                        <div class="supplier-table-bahan-list">
                            <?php if (!empty($supplier['items'])): ?>
                                <?php foreach ($supplier['items'] as $item): ?>
                                    <div class="supplier-table-bahan-row" data-bahan-name="<?= htmlspecialchars(strtolower($item['nama_bahan']), ENT_QUOTES, 'UTF-8') ?>" data-bahan-price="<?= (float) $item['harga'] ?>">
                                        <span class="supplier-bahan-name"><?= htmlspecialchars($item['nama_bahan']) ?></span>
                                        <span class="supplier-bahan-price">Rp<?= number_format($item['harga'], 0, ',', '.') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="supplier-bahan-empty">Belum ada bahan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="supplier-card-actions">
                        <button type="button" class="btn-stock" onclick="openSupplierDetailModal(<?= $supplier['id'] ?>)">Detail</button>
                        <button type="button" class="btn-edit" onclick="openSupplierFormModal(<?= $supplier['id'] ?>)">Edit</button>
                        <form method="POST" onsubmit="return confirm('Hapus supplier ini?')">
                            <?= auth_csrf_input() ?>
                            <input type="hidden" name="action" value="delete_supplier">
                            <input type="hidden" name="supplier_id" value="<?= $supplier['id'] ?>">
                            <button type="submit" class="btn-delete">Hapus</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="supplier-search-empty" id="supplierSearchEmpty" hidden>Supplier atau bahan tidak ditemukan.</div>
    <?php else: ?>
        <div class="supplier-search-empty">Belum ada supplier.</div>
    <?php endif; ?>
</div>

<div id="supplierDetailModal" class="modal">
    <div class="modal-content supplier-detail-modal-content">
        <div class="modal-header">
            <h2 id="supplierDetailTitle">Detail Supplier</h2>
            <span class="close-btn" onclick="closeSupplierDetailModal()">&times;</span>
        </div>
        <div class="supplier-detail-grid">
            <div><span>No Telepon</span><strong id="supplierDetailPhone">-</strong></div>
            <div class="supplier-detail-full"><span>Alamat</span><strong id="supplierDetailAddress">-</strong></div>
        </div>
        <div class="supplier-section-title">Daftar Bahan</div>
        <table class="supplier-inline-table supplier-detail-table">
            <thead><tr><th>Nama</th><th>Harga</th></tr></thead>
            <tbody id="supplierDetailItems"></tbody>
        </table>
    </div>
</div>

<div id="supplierFormModal" class="modal">
    <div class="modal-content supplier-form-modal-content">
        <div class="modal-header">
            <h2 id="supplierFormTitle">Tambah Supplier</h2>
            <span class="close-btn" onclick="closeSupplierFormModal()">&times;</span>
        </div>

        <form method="POST" id="supplierForm">
            <?= auth_csrf_input() ?>
            <input type="hidden" name="action" value="save_supplier">
            <input type="hidden" name="supplier_id" id="supplier_id">

            <div class="form-group">
                <label>Nama Supplier</label>
                <input type="text" name="nama_supplier" id="nama_supplier" required>
            </div>

            <div class="form-group">
                <label>No Telepon</label>
                <input type="text" name="no_telepon" id="no_telepon">
            </div>

            <div class="form-group">
                <label>Alamat</label>
                <textarea name="alamat" id="alamat" rows="3"></textarea>
            </div>

            <div class="supplier-section-title">Bahan yang Disuplai</div>

            <div class="supplier-supply-grid">
                <div class="supplier-bahan-builder">
                    <div class="supplier-bahan-row supplier-bahan-row-head">
                        <span>Nama Bahan</span>
                        <span>Harga</span>
                        <span>Hapus</span>
                    </div>
                    <div id="supplierBahanRows"></div>
                    <button type="button" class="btn-stock" onclick="addSupplierBahanRow()">+ Tambah Bahan</button>
                </div>

                <div class="supplier-preview-panel">
                    <table class="supplier-inline-table supplier-inline-table-head">
                        <thead>
                            <tr>
                                <th>Nama Bahan</th>
                                <th>Harga</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="supplier-preview-scroll">
                        <table class="supplier-inline-table supplier-inline-table-body">
                            <tbody id="supplierPreviewBody">
                                <tr>
                                    <td colspan="2">Belum ada bahan dipilih.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary">Simpan Supplier</button>
        </form>
    </div>
</div>

<template id="supplierBahanRowTemplate">
    <div class="supplier-bahan-row">
        <select name="bahan_id[]" class="supplier-bahan-select" required>
            <option value="">Pilih bahan</option>
            <?php foreach ($bahan_options as $bahan): ?>
                <option value="<?= (int) $bahan['id'] ?>">
                    <?= htmlspecialchars($bahan['nama_bahan']) ?> (<?= htmlspecialchars($bahan['satuan']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="harga[]" class="supplier-harga-input" min="0" step="0.01" placeholder="Harga" required>
        <button type="button" class="btn-delete" onclick="removeSupplierBahanRow(this)">Hapus</button>
    </div>
</template>

<script>
const supplierData = <?= json_encode($supplier_json, JSON_UNESCAPED_UNICODE) ?>;
const supplierFormModal = document.getElementById('supplierFormModal');
const supplierDetailModal = document.getElementById('supplierDetailModal');
const supplierRowsContainer = document.getElementById('supplierBahanRows');
const supplierPreviewBody = document.getElementById('supplierPreviewBody');
const supplierRowTemplate = document.getElementById('supplierBahanRowTemplate');

function resetSupplierForm() {
    document.getElementById('supplierForm').reset();
    document.getElementById('supplier_id').value = '';
    document.getElementById('supplierFormTitle').textContent = 'Tambah Supplier';
    supplierRowsContainer.innerHTML = '';
    addSupplierBahanRow();
    renderSupplierPreview();
}

function addSupplierBahanRow(selectedBahanId = '', harga = '') {
    const row = supplierRowTemplate.content.firstElementChild.cloneNode(true);
    const select = row.querySelector('.supplier-bahan-select');
    const input = row.querySelector('.supplier-harga-input');

    select.value = selectedBahanId ? String(selectedBahanId) : '';
    input.value = harga;

    select.addEventListener('change', renderSupplierPreview);
    input.addEventListener('input', renderSupplierPreview);

    supplierRowsContainer.appendChild(row);
    renderSupplierPreview();
}

function removeSupplierBahanRow(button) {
    const row = button.closest('.supplier-bahan-row');
    if (row) {
        row.remove();
    }

    if (!supplierRowsContainer.children.length) {
        addSupplierBahanRow();
        return;
    }

    renderSupplierPreview();
}

function renderSupplierPreview() {
    const rows = supplierRowsContainer.querySelectorAll('.supplier-bahan-row');
    const usedIds = new Set();
    let hasDuplicate = false;
    const preview = [];

    rows.forEach(row => {
        const select = row.querySelector('.supplier-bahan-select');
        const input = row.querySelector('.supplier-harga-input');
        const bahanId = select.value;
        const label = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '-';
        const harga = input.value.trim();

        row.classList.remove('supplier-row-duplicate');

        if (bahanId) {
            if (usedIds.has(bahanId)) {
                hasDuplicate = true;
                row.classList.add('supplier-row-duplicate');
            }
            usedIds.add(bahanId);
        }

        if (bahanId || harga !== '') {
            preview.push({
                nama: bahanId ? label : '-',
                harga: harga !== '' ? harga : '0',
            });
        }
    });

    if (preview.length === 0) {
        supplierPreviewBody.innerHTML = '<tr><td colspan="2">Belum ada bahan dipilih.</td></tr>';
    } else {
        supplierPreviewBody.innerHTML = preview.map((item, index) => `
            <tr>
                <td>${item.nama}</td>
                <td>Rp${Number(item.harga || 0).toLocaleString('id-ID')}</td>
            </tr>
        `).join('');
    }

    if (hasDuplicate) {
        supplierPreviewBody.insertAdjacentHTML('afterbegin', '<tr><td colspan="2">Ada bahan yang dipilih lebih dari satu kali. Periksa kembali.</td></tr>');
    }
}

function openSupplierFormModal(supplierId = 0) {
    resetSupplierForm();

    if (supplierId) {
        const supplier = supplierData.find(item => item.id === Number(supplierId));
        if (!supplier) {
            return;
        }

        document.getElementById('supplierFormTitle').textContent = 'Edit Supplier';
        document.getElementById('supplier_id').value = supplier.id;
        document.getElementById('nama_supplier').value = supplier.nama_supplier || '';
        document.getElementById('no_telepon').value = supplier.no_telepon || '';
        document.getElementById('alamat').value = supplier.alamat || '';

        supplierRowsContainer.innerHTML = '';
        if (supplier.items.length) {
            supplier.items.forEach(item => addSupplierBahanRow(item.bahan_id, item.harga));
        } else {
            addSupplierBahanRow();
        }
    }

    supplierFormModal.style.display = 'flex';
}

function closeSupplierFormModal() {
    supplierFormModal.style.display = 'none';
}

function openSupplierDetailModal(supplierId) {
    const supplier = supplierData.find(item => item.id === Number(supplierId));
    if (!supplier) return;

    document.getElementById('supplierDetailTitle').textContent = supplier.nama_supplier;
    document.getElementById('supplierDetailPhone').textContent = supplier.no_telepon || '-';
    document.getElementById('supplierDetailAddress').textContent = supplier.alamat || '-';
    document.getElementById('supplierDetailItems').innerHTML = supplier.items.length
        ? supplier.items.map(item => `<tr><td>${escapeSupplierHtml(item.nama_bahan)}</td><td>Rp${Number(item.harga || 0).toLocaleString('id-ID')}</td></tr>`).join('')
        : '<tr><td colspan="2">Belum ada bahan.</td></tr>';
    supplierDetailModal.style.display = 'flex';
}

function closeSupplierDetailModal() {
    supplierDetailModal.style.display = 'none';
}

function escapeSupplierHtml(value) {
    const element = document.createElement('div');
    element.textContent = value || '';
    return element.innerHTML;
}

let supplierSortTimer;

document.getElementById('supplierSearchInput').addEventListener('input', function() {
    const keyword = this.value.toLowerCase().trim();
    const cards = document.querySelectorAll('[data-supplier-card]');
    const cardGrid = document.getElementById('supplierCardGrid');
    const emptyState = document.getElementById('supplierSearchEmpty');
    let visibleCards = 0;

    clearTimeout(supplierSortTimer);

    cards.forEach(card => {
        const visible = card.dataset.search.includes(keyword);
        card.hidden = !visible;
        if (visible) visibleCards++;

        const bahanItems = Array.from(card.querySelectorAll('.supplier-table-bahan-row'));
        const matchedItems = bahanItems.filter(item => keyword !== '' && item.dataset.bahanName.includes(keyword));
        const bahanList = card.querySelector('.supplier-table-bahan-list');

        bahanItems.forEach(item => {
            const isMatch = matchedItems.includes(item);
            item.classList.toggle('is-search-match', isMatch);
            item.classList.toggle('is-search-muted', matchedItems.length > 0 && !isMatch);
        });

        if (bahanList) {
            bahanList.scrollTop = matchedItems.length
                ? matchedItems[0].offsetTop - bahanList.offsetTop
                : 0;
        }
    });

    if (emptyState) emptyState.hidden = visibleCards !== 0;

    const sortCards = () => {
        const sortedCards = Array.from(cards).sort((cardA, cardB) => {
            if (keyword.length < 2) {
                return Number(cardA.dataset.originalOrder) - Number(cardB.dataset.originalOrder);
            }

            const getMatchedPrice = card => {
                const prices = Array.from(card.querySelectorAll('.supplier-table-bahan-row'))
                    .filter(item => item.dataset.bahanName.includes(keyword))
                    .map(item => Number(item.dataset.bahanPrice));

                return prices.length ? Math.min(...prices) : Number.POSITIVE_INFINITY;
            };

            return getMatchedPrice(cardA) - getMatchedPrice(cardB);
        });

        sortedCards.forEach(card => cardGrid.appendChild(card));
    };

    if (keyword.length < 2) {
        sortCards();
    } else {
        supplierSortTimer = setTimeout(sortCards, 350);
    }
});

window.onclick = function(event) {
    if (event.target === supplierFormModal) {
        closeSupplierFormModal();
    }
    if (event.target === supplierDetailModal) {
        closeSupplierDetailModal();
    }
};

document.getElementById('supplierForm').addEventListener('submit', function(event) {
    const rows = supplierRowsContainer.querySelectorAll('.supplier-bahan-row');
    const usedIds = new Set();

    for (const row of rows) {
        const bahanId = row.querySelector('.supplier-bahan-select').value;
        if (!bahanId) {
            continue;
        }

        if (usedIds.has(bahanId)) {
            alert('Bahan yang sama tidak boleh duplikat pada supplier yang sama.');
            event.preventDefault();
            return;
        }

        usedIds.add(bahanId);
    }
});

resetSupplierForm();
</script>

</body>
</html>
