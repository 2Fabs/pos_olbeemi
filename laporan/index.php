<?php
include '../config/database.php';
include '../includes/pesanan_transaksi.php';

syncLegacyKodeTransaksi($conn);

$filter = $_GET['filter'] ?? 'today';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$source = $_GET['source'] ?? 'all';

if (!in_array($filter, ['all', 'today', '7', '30', 'month', 'custom'], true)) {
    $filter = 'today';
}

if (!in_array($source, ['all', 'kedai', 'online'], true)) {
    $source = 'all';
}

$where = "1=1";

switch ($filter) {
    case 'all':
        break;

    case 'today':
        $where .= " AND DATE(created_at) = CURDATE()";
        break;

    case '7':
        $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;

    case '30':
        $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;

    case 'month':
        $where .= " AND MONTH(created_at) = MONTH(CURDATE())
                    AND YEAR(created_at) = YEAR(CURDATE())";
        break;

    case 'custom':
        if (!empty($start) && !empty($end)) {
            $start_safe = mysqli_real_escape_string($conn, $start);
            $end_safe = mysqli_real_escape_string($conn, $end);

            $where .= " AND DATE(created_at)
                        BETWEEN '$start_safe' AND '$end_safe'";
        }
        break;
}

$source_expression = "
    COALESCE(
        (
            SELECT pesanan.sumber_pesanan
            FROM pesanan
            WHERE pesanan.transaksi_id = transaksi.id
            ORDER BY pesanan.id DESC
            LIMIT 1
        ),
        CASE
            WHEN transaksi.kode_transaksi LIKE 'INV-KD-%' THEN 'Kedai'
            ELSE 'Online'
        END
    )
";

if ($source === 'kedai') {
    $where .= " AND ($source_expression) = 'Kedai'";
} elseif ($source === 'online') {
    $where .= " AND ($source_expression) <> 'Kedai'";
}

$summary_query = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_transaksi,
        COALESCE(SUM(bayar - kembalian), 0) AS total_pendapatan,
        COALESCE(SUM(total_profit), 0) AS total_profit,
        COALESCE(SUM(
            CASE WHEN metode_pembayaran = 'Cash'
            THEN (bayar - kembalian) ELSE 0 END
        ), 0) AS total_cash,
        COALESCE(SUM(
            CASE WHEN metode_pembayaran = 'QRIS'
            THEN (bayar - kembalian) ELSE 0 END
        ), 0) AS total_qris
    FROM transaksi
    WHERE $where
");

$summary = mysqli_fetch_assoc($summary_query);

$transaksi_query = mysqli_query($conn, "
    SELECT *
    FROM transaksi
    WHERE $where
    ORDER BY created_at DESC
");

$top_menu_query = mysqli_query($conn, "
    SELECT
        transaksi_detail.menu_id,
        COALESCE(MAX(menu.nama_menu), MAX(transaksi_detail.nama_menu)) AS nama_menu,
        SUM(transaksi_detail.qty) AS total_terjual,
        SUM(transaksi_detail.subtotal) AS total_pendapatan
    FROM transaksi_detail
    JOIN (
        SELECT id
        FROM transaksi
        WHERE $where
    ) AS transaksi_terfilter ON transaksi_terfilter.id = transaksi_detail.transaksi_id
    LEFT JOIN menu ON menu.id = transaksi_detail.menu_id
    GROUP BY transaksi_detail.menu_id
    ORDER BY total_terjual DESC, total_pendapatan DESC, nama_menu ASC
    LIMIT 5
");

$top_menus = [];
while ($top_menu = mysqli_fetch_assoc($top_menu_query)) {
    $top_menus[] = $top_menu;
}

// PDF export dibuat langsung agar tidak menambah dependency eksternal.
function pdf_escape($text) {
    $text = utf8_decode((string) $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function pdf_text($x, $y, $text, $size = 9, $color = '0.18 0.12 0.08') {
    $safe_text = pdf_escape($text);
    return "BT $color rg /F1 $size Tf $x $y Td ($safe_text) Tj ET\n";
}

function pdf_text_width($text, $size) {
    static $widths = [
        ' '=>278, '!'=>278, '"'=>355, '#'=>556, '$'=>556, '%'=>889, '&'=>667, "'"=>191,
        '('=>333, ')'=>333, '*'=>389, '+'=>584, ','=>278, '-'=>333, '.'=>278, '/'=>278,
        ':'=>278, ';'=>278, '<'=>584, '='=>584, '>'=>584, '?'=>556, '@'=>1015,
        'A'=>667, 'B'=>667, 'C'=>722, 'D'=>722, 'E'=>667, 'F'=>611, 'G'=>778, 'H'=>722,
        'I'=>278, 'J'=>500, 'K'=>667, 'L'=>556, 'M'=>833, 'N'=>722, 'O'=>778, 'P'=>667,
        'Q'=>778, 'R'=>722, 'S'=>667, 'T'=>611, 'U'=>722, 'V'=>667, 'W'=>944, 'X'=>667,
        'Y'=>667, 'Z'=>611,
        'a'=>556, 'b'=>556, 'c'=>500, 'd'=>556, 'e'=>556, 'f'=>278, 'g'=>556, 'h'=>556,
        'i'=>222, 'j'=>222, 'k'=>500, 'l'=>222, 'm'=>833, 'n'=>556, 'o'=>556, 'p'=>556,
        'q'=>556, 'r'=>333, 's'=>500, 't'=>278, 'u'=>556, 'v'=>500, 'w'=>722, 'x'=>500,
        'y'=>500, 'z'=>500,
    ];
    $total = 0;
    foreach (str_split((string) $text) as $character) {
        $total += ctype_digit($character) ? 556 : ($widths[$character] ?? 556);
    }
    return ($total / 1000) * $size;
}

function pdf_text_right($right_x, $y, $text, $size = 9, $color = '0.18 0.12 0.08') {
    return pdf_text($right_x - pdf_text_width($text, $size), $y, $text, $size, $color);
}

function pdf_text_center($left_x, $right_x, $y, $text, $size = 9, $color = '0.18 0.12 0.08') {
    $x = $left_x + (($right_x - $left_x - pdf_text_width($text, $size)) / 2);
    return pdf_text($x, $y, $text, $size, $color);
}

function pdf_fill_rect($x, $y, $width, $height, $color) {
    return "q $color rg $x $y $width $height re f Q\n";
}

function pdf_draw_logo($x, $y, $width, $height) {
    return "q $width 0 0 $height $x $y cm /Logo Do Q\n";
}

function pdf_logo_jpeg() {
    $logo_path = __DIR__ . '/../images/logo.png';
    if (!function_exists('imagecreatefrompng') || !is_file($logo_path)) {
        return '';
    }

    $source = @imagecreatefrompng($logo_path);
    if (!$source) {
        return '';
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $canvas = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $white);
    imagealphablending($canvas, true);
    imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

    ob_start();
    imagejpeg($canvas, null, 92);
    $jpeg = ob_get_clean();

    imagedestroy($source);
    imagedestroy($canvas);

    return $jpeg ?: '';
}

function pdf_line($x1, $y1, $x2, $y2, $color = '0.84 0.78 0.71') {
    return "q $color RG 0.5 w $x1 $y1 m $x2 $y2 l S Q\n";
}

function pdf_table_vertical_lines($top_y, $bottom_y, $color = '0 0 0') {
    $content = '';
    foreach ([30, 58, 190, 282, 342, 422, 502, 582] as $x) {
        $content .= pdf_line($x, $top_y, $x, $bottom_y, $color);
    }
    return $content;
}

function pdf_report_totals($top_y, $total_transaksi, $total_pendapatan, $total_profit) {
    $columns = [
        [48, 220, 'Total Transaksi', number_format($total_transaksi, 0, ',', '.')],
        [220, 392, 'Total Pendapatan', 'Rp' . number_format($total_pendapatan, 0, ',', '.')],
        [392, 564, 'Total Keuntungan', 'Rp' . number_format($total_profit, 0, ',', '.')],
    ];
    $content = pdf_fill_rect(48, $top_y - 20, 516, 20, '0.96 0.91 0.85');

    foreach ($columns as $column) {
        $content .= pdf_text_center($column[0], $column[1], $top_y - 14, $column[2], 8);
        $content .= pdf_text_center($column[0], $column[1], $top_y - 35, $column[3], 9);
    }

    foreach ([48, 220, 392, 564] as $x) {
        $content .= pdf_line($x, $top_y, $x, $top_y - 42, '0 0 0');
    }
    $content .= pdf_line(48, $top_y, 564, $top_y, '0 0 0');
    $content .= pdf_line(48, $top_y - 20, 564, $top_y - 20, '0 0 0');
    $content .= pdf_line(48, $top_y - 42, 564, $top_y - 42, '0 0 0');

    return $content;
}

function pdf_wrap_text($text, $limit) {
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ($text === '') { return ['-']; }
    return explode("\n", wordwrap($text, $limit, "\n", true));
}

function get_transaksi_rows($conn, $where) {
    $q = mysqli_query($conn, "
        SELECT
            id,
            kode_transaksi,
            created_at,
            subtotal,
            bayar,
            kembalian,
            total_profit,
            (
                SELECT sumber_pesanan
                FROM pesanan
                WHERE pesanan.transaksi_id = transaksi.id
                ORDER BY pesanan.id DESC
                LIMIT 1
            ) AS sumber_pesanan
        FROM transaksi
        WHERE $where
        ORDER BY created_at DESC
    ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($q)) { $rows[] = $r; }
    return $rows;
}

function pdf_table_header($y) {
    $headers = [
        [30, 58, 'No'],
        [58, 190, 'Invoice'],
        [190, 282, 'Tanggal'],
        [282, 342, 'Lokasi'],
        [342, 422, 'Subtotal'],
        [422, 502, 'Pendapatan'],
        [502, 582, 'Keuntungan'],
    ];

    $content = pdf_fill_rect(30, $y - 8, 552, 24, '0.37 0.23 0.16');
    $content .= pdf_table_vertical_lines($y + 16, $y - 8);
    $content .= pdf_line(30, $y + 16, 582, $y + 16, '0 0 0');
    $content .= pdf_line(30, $y - 8, 582, $y - 8, '0 0 0');
    foreach ($headers as $h) {
        $content .= pdf_text_center($h[0], $h[1], $y - 2, $h[2], 8, '1 1 1');
    }
    return $content;
}

function pdf_row_cells($row, $number) {
    $pendapatan = (floatval($row['bayar']) - floatval($row['kembalian']));
    $is_kedai = ($row['sumber_pesanan'] ?? '') === 'Kedai'
        || strpos((string) $row['kode_transaksi'], 'INV-KD-') === 0;
    $lokasi = $is_kedai ? 'Kedai' : 'Online';
    return [
        ['x' => 30, 'right' => 58, 'align' => 'center', 'lines' => [(string)$number]],
        ['x' => 58, 'right' => 190, 'align' => 'center', 'lines' => [$row['kode_transaksi']]],
        ['x' => 190, 'right' => 282, 'align' => 'center', 'lines' => [date('d/m/Y H:i', strtotime($row['created_at']))]],
        ['x' => 282, 'right' => 342, 'align' => 'center', 'lines' => [$lokasi]],
        ['x' => 342, 'right' => 422, 'align' => 'center', 'lines' => ['Rp' . number_format($row['subtotal'],0,',','.')] ],
        ['x' => 422, 'right' => 502, 'align' => 'center', 'lines' => ['Rp' . number_format($pendapatan,0,',','.')] ],
        ['x' => 502, 'right' => 582, 'align' => 'center', 'lines' => ['Rp' . number_format($row['total_profit'],0,',','.')] ],
    ];
}

function pdf_row_height($cells) {
    $max = 1; foreach ($cells as $c) { $max = max($max, count($c['lines'])); } return ($max * 10) + 6;
}

function pdf_table_row($cells, $y, $height, $is_even) {
    $content = '';
    foreach ($cells as $cell) {
        foreach ($cell['lines'] as $idx => $line) {
            $line_y = $y - 3 - ($idx * 10);
            if (($cell['align'] ?? '') === 'right') {
                $content .= pdf_text_right($cell['right'], $line_y, $line, 8);
            } elseif (($cell['align'] ?? '') === 'center') {
                $content .= pdf_text_center($cell['x'], $cell['right'], $line_y, $line, 8);
            } else {
                $content .= pdf_text($cell['x'], $line_y, $line, 8);
            }
        }
    }
    $row_bottom = $y - $height + 7;
    $content .= pdf_table_vertical_lines($y + 7, $row_bottom);
    return $content . pdf_line(30, $row_bottom, 582, $row_bottom, '0 0 0');
}

function pdf_best_sellers($top_menus) {
    $content = pdf_text_center(48, 564, 674, 'MENU TERLARIS', 10, '0.18 0.12 0.08');
    $columns = [
        [48, 90, 'No'],
        [90, 330, 'Nama Menu'],
        [330, 410, 'Terjual'],
        [410, 564, 'Pendapatan'],
    ];
    $content .= pdf_fill_rect(48, 641, 516, 20, '0.37 0.23 0.16');
    foreach ($columns as $column) {
        $content .= pdf_text_center($column[0], $column[1], 648, $column[2], 8, '1 1 1');
    }

    $row_top = 641;
    if (empty($top_menus)) {
        $content .= pdf_text_center(48, 564, 625, 'Belum ada data menu terjual.', 8, '0.42 0.37 0.33');
        $row_top -= 28;
    } else {
        foreach ($top_menus as $index => $menu) {
            $row_bottom = $row_top - 18;
            $text_y = $row_top - 12;
            $content .= pdf_text_center(48, 90, $text_y, (string) ($index + 1), 8);
            $content .= pdf_text_center(90, 330, $text_y, $menu['nama_menu'], 8);
            $content .= pdf_text_center(330, 410, $text_y, number_format($menu['total_terjual'], 0, ',', '.'), 8);
            $content .= pdf_text_center(410, 564, $text_y, 'Rp' . number_format($menu['total_pendapatan'], 0, ',', '.'), 8);
            $content .= pdf_line(48, $row_bottom, 564, $row_bottom, '0 0 0');
            $row_top = $row_bottom;
        }
    }

    foreach ([48, 90, 330, 410, 564] as $x) {
        $content .= pdf_line($x, 661, $x, $row_top, '0 0 0');
    }
    $content .= pdf_line(48, 661, 564, 661, '0 0 0');
    $content .= pdf_line(48, 641, 564, 641, '0 0 0');
    return $content;
}

function pdf_paginate_rows($rows, $with_best_sellers = false) {
    $pages = [];
    $current = [];
    $cur_h = 0;
    $avail = $with_best_sellers ? 340 : 460;
    foreach ($rows as $idx => $r) {
        $cells = pdf_row_cells($r, $idx + 1);
        $h = pdf_row_height($cells);
        if (!empty($current) && ($cur_h + $h) > $avail) {
            $pages[] = $current;
            $current = [];
            $cur_h = 0;
            $avail = 460;
        }
        $current[] = ['cells'=>$cells,'height'=>$h];
        $cur_h += $h;
    }
    if (!empty($current)) $pages[] = $current;
    return $pages;
}

function pdf_page_content($page_rows, $page_number, $page_count, $periode_label, $total_transaksi = 0, $total_pendapatan = 0, $total_profit = 0, $has_logo = false, $top_menus = []) {
    $content = '';
    if ($has_logo) {
        $content .= pdf_draw_logo(245, 754, 120, 33);
    } else {
        $content .= pdf_text(273, 763, 'POS OLBEEMI', 10, '0.54 0.35 0.23');
    }
    $content .= pdf_text(218.5, 734, 'LAPORAN PENJUALAN', 16, '0.18 0.12 0.08');
    $content .= pdf_text_right(564, 760, 'Halaman ' . $page_number . ' / ' . $page_count, 8, '0.42 0.37 0.33');
    $content .= pdf_line(48, 724, 564, 724);
    $content .= pdf_text(48, 705, 'Periode: ' . $periode_label, 8, '0.42 0.37 0.33');
    $content .= pdf_text_right(564, 705, 'Dicetak: ' . date('d/m/Y H:i'), 8, '0.42 0.37 0.33');

    $is_first_page = $page_number === 1;
    if ($is_first_page) {
        $content .= pdf_best_sellers($top_menus);
    }
    $transaction_title_y = $is_first_page ? 530 : 684;
    $table_header_y = $is_first_page ? 506 : 660;
    $content .= pdf_text_center(30, 582, $transaction_title_y, 'DAFTAR TRANSAKSI', 10, '0.18 0.12 0.08');
    $content .= pdf_table_header($table_header_y);

    if (empty($page_rows)) {
        $content .= pdf_text_center(30, 582, $table_header_y - 32, 'Tidak ada data transaksi.', 9, '0.42 0.37 0.33');
        $content .= pdf_report_totals($table_header_y - 70, $total_transaksi, $total_pendapatan, $total_profit);
        $content .= pdf_line(48, 45, 564, 45);
        return $content . pdf_text_center(48, 564, 28, 'Olbeemi Coffee Shop - Laporan Penjualan', 7, '0.42 0.37 0.33');
    }

    $y = $table_header_y - 15;
    foreach ($page_rows as $index => $row) {
        $content .= pdf_table_row($row['cells'], $y, $row['height'], $index % 2 === 1);
        $y -= $row['height'];
    }

    if ($page_number === $page_count) {
        $content .= pdf_report_totals($y - 8, $total_transaksi, $total_pendapatan, $total_profit);
    }

    $content .= pdf_line(48, 45, 564, 45);
    return $content . pdf_text_center(48, 564, 28, 'Olbeemi Coffee Shop - Laporan Penjualan', 7, '0.42 0.37 0.33');
}

function generate_pdf($rows, $periode_label, $total_pendapatan = 0, $total_profit = 0, $top_menus = []) {
    $filename = 'laporan_penjualan_' . date('Ymd_His') . '.pdf';
    $pages = pdf_paginate_rows($rows, true);
    if (empty($pages)) $pages = [[]];
    $page_count = count($pages);
    $total_transaksi = count($rows);
    $logo_data = pdf_logo_jpeg();
    $logo_object_number = $logo_data !== '' ? 4 + ($page_count * 2) : 0;
    $objects = ["<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>","<< /Type /Catalog /Pages 3 0 R >>"]; 
    $kids = [];
    foreach ($pages as $index => $page_rows) { $page_object_number = 4 + ($index * 2); $kids[] = $page_object_number . " 0 R"; }
    $objects[] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $page_count >>";
    foreach ($pages as $index => $page_rows) {
        $page_number = $index + 1;
        $page_object_number = 4 + ($index * 2);
        $content_object_number = $page_object_number + 1;
        $content = pdf_page_content($page_rows, $page_number, $page_count, $periode_label, $total_transaksi, $total_pendapatan, $total_profit, $logo_object_number > 0, $top_menus);
        $image_resource = $logo_object_number > 0 ? " /XObject << /Logo $logo_object_number 0 R >>" : '';
        $objects[] = "<< /Type /Page /Parent 3 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 1 0 R >>$image_resource >> /Contents $content_object_number 0 R >>";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
    }
    if ($logo_object_number > 0) {
        $objects[] = "<< /Type /XObject /Subtype /Image /Width 436 /Height 119 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logo_data) . " >>\nstream\n" . $logo_data . "\nendstream";
    }
    $pdf = "%PDF-1.3\n"; $offsets = [];
    foreach ($objects as $idx => $object_body) { $offsets[] = strlen($pdf); $pdf .= ($idx + 1) . " 0 obj\n" . $object_body . "\nendobj\n"; }
    $xref_start = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= sprintf("%010d 65535 f \n", 0);
    foreach ($offsets as $offset) { $pdf .= sprintf("%010d 00000 n \n", $offset); }
    $pdf .= "trailer<< /Size " . (count($objects) + 1) . " /Root 2 0 R >>\n";
    $pdf .= "startxref\n$xref_start\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf; exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $rows = get_transaksi_rows($conn, $where);
    // compute totals for pendapatan and profit
    $total_pendapatan = 0;
    $total_profit = 0;
    foreach ($rows as $r) {
        $total_pendapatan += (floatval($r['bayar']) - floatval($r['kembalian']));
        $total_profit += floatval($r['total_profit']);
    }
    $source_label = $source === 'kedai' ? 'Kedai' : ($source === 'online' ? 'Online' : 'Semua');
    $periode_labels = [
        'all' => 'Semua Periode',
        'today' => 'Hari Ini',
        '7' => '7 Hari Terakhir',
        '30' => '30 Hari Terakhir',
        'month' => 'Bulan Ini',
    ];
    $periode_label = $periode_labels[$filter] ?? 'Rentang Tanggal';
    if ($filter === 'custom' && $start !== '' && $end !== '') {
        $periode_label = date('d/m/Y', strtotime($start)) . ' - ' . date('d/m/Y', strtotime($end));
    }
    $periode_label .= ' | Lokasi: ' . $source_label;
    generate_pdf($rows, $periode_label, $total_pendapatan, $total_profit, $top_menus);
}
// -------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/pos.css?v=<?= filemtime(__DIR__ . '/../css/pos.css') ?>">
</head>
<body>

<?php
$current_module = 'laporan';
include '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="header">
        <div>
            <h1>Laporan Penjualan</h1>
            <p>Monitoring transaksi & keuntungan</p>
        </div>
    </div>

    <div class="filter-card sales-filter-card">
        <form method="GET" class="filter-form sales-filter-form">
            <select name="filter" onchange="toggleCustomRange(this.value)">
                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Semua Periode</option>
                <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                <option value="7" <?= $filter === '7' ? 'selected' : '' ?>>7 Hari Terakhir</option>
                <option value="30" <?= $filter === '30' ? 'selected' : '' ?>>30 Hari Terakhir</option>
                <option value="month" <?= $filter === 'month' ? 'selected' : '' ?>>Bulan Ini</option>
                <option value="custom" <?= $filter === 'custom' ? 'selected' : '' ?>>Rentang Tanggal</option>
            </select>

            <div id="customRange" style="<?= $filter === 'custom' ? 'display:flex;' : 'display:none;' ?>">
                <input type="date" name="start" value="<?= htmlspecialchars($start) ?>">
                <input type="date" name="end" value="<?= htmlspecialchars($end) ?>">
            </div>

            <button type="submit" class="btn-primary">
                Terapkan
            </button>

            <div class="report-source-filter" aria-label="Filter lokasi transaksi">
                <label>
                    <input type="radio" name="source" value="all" <?= $source === 'all' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span>Semua</span>
                </label>
                <label>
                    <input type="radio" name="source" value="kedai" <?= $source === 'kedai' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span>Kedai</span>
                </label>
                <label>
                    <input type="radio" name="source" value="online" <?= $source === 'online' ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span>Online</span>
                </label>
            </div>
            
            <button type="submit" name="export" value="pdf" class="btn-stock">
                Export PDF
            </button>
        </form>
    </div>

    <div class="summary-grid sales-summary-grid">
        <div class="summary-card">
            <h3>Total Transaksi</h3>
            <strong><?= $summary['total_transaksi'] ?></strong>
        </div>

        <div class="summary-card">
            <h3>Total Pendapatan</h3>
            <strong>Rp<?= number_format($summary['total_pendapatan'], 0, ',', '.') ?></strong>
        </div>

        <div class="summary-card">
            <h3>Total Keuntungan</h3>
            <strong>Rp<?= number_format($summary['total_profit'], 0, ',', '.') ?></strong>
        </div>

        <div class="summary-card payment-summary-card">
            <h3>Cash</h3>
            <strong>Rp<?= number_format($summary['total_cash'], 0, ',', '.') ?></strong>
        </div>

        <div class="summary-card payment-summary-card">
            <h3>QRIS</h3>
            <strong>Rp<?= number_format($summary['total_qris'], 0, ',', '.') ?></strong>
        </div>
    </div>

    <div class="report-table-tabs" role="tablist" aria-label="Tampilan laporan">
        <button type="button" class="report-table-tab active" role="tab" aria-selected="true" aria-controls="transactionReport" data-report-tab="transactionReport">
            Transaksi
        </button>
        <button type="button" class="report-table-tab" role="tab" aria-selected="false" aria-controls="bestSellerReport" data-report-tab="bestSellerReport">
            Menu Terlaris
        </button>
    </div>

    <div class="table-card best-seller-card" id="bestSellerReport" role="tabpanel" hidden>
        <div class="best-seller-header">
            <div>
                <h2>Menu Terlaris</h2>
                <p>Top 5 berdasarkan jumlah menu terjual</p>
            </div>
        </div>

        <table class="best-seller-table">
            <thead>
                <tr>
                    <th>Peringkat</th>
                    <th>Nama Menu</th>
                    <th>Terjual</th>
                    <th>Pendapatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($top_menus): ?>
                    <?php foreach ($top_menus as $index => $top_menu): ?>
                        <tr>
                            <td><span class="best-seller-rank"><?= $index + 1 ?></span></td>
                            <td><strong><?= htmlspecialchars($top_menu['nama_menu']) ?></strong></td>
                            <td><?= (int) $top_menu['total_terjual'] ?></td>
                            <td class="money-cell">Rp<?= number_format($top_menu['total_pendapatan'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Belum ada menu terjual pada filter ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-card transaction-report-card" id="transactionReport" role="tabpanel">
        <div class="transaction-table-header">
            <h2>Daftar Transaksi</h2>
        </div>
        <table>

            <thead>
                <tr>
                    <th>No</th>
                    <th>Invoice</th>
                    <th>Tanggal</th>
                    <th>Metode</th>
                    <th>Subtotal</th>
                    <th>Pendapatan</th>
                    <th>Keuntungan</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if (mysqli_num_rows($transaksi_query) > 0): ?>
                    <?php $no = 1; ?>
                    <?php while ($trx = mysqli_fetch_assoc($transaksi_query)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($trx['kode_transaksi']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?></td>
                            <td><?= htmlspecialchars($trx['metode_pembayaran']) ?></td>

                            <td class="money-cell">
                                Rp<?= number_format($trx['subtotal'], 0, ',', '.') ?>
                            </td>

                            <?php $pendapatan = floatval($trx['bayar']) - floatval($trx['kembalian']); ?>
                            <td class="money-cell">
                                Rp<?= number_format($pendapatan, 0, ',', '.') ?>
                            </td>

                            <td class="money-cell">
                                Rp<?= number_format($trx['total_profit'], 0, ',', '.') ?>
                            </td>

                            <td>
                                <a href="detail.php?id=<?= $trx['id'] ?>" class="btn-stock">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Belum ada transaksi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
function toggleCustomRange(value) {
    const custom = document.getElementById('customRange');

    if (value === 'custom') {
        custom.style.display = 'flex';
    } else {
        custom.style.display = 'none';
    }
}

document.querySelectorAll('[data-report-tab]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-report-tab]').forEach((tab) => {
            const isActive = tab === button;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            document.getElementById(tab.dataset.reportTab).hidden = !isActive;
        });
    });
});
</script>

</body>
</html>
