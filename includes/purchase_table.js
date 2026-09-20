// Apply one layout to order previews, receiving details, and expense details.
function wrapPurchaseTable(table) {
    table.classList.add('purchase-summary-table');
    const columns = Array.from(table.querySelectorAll('thead th'));
    const types = columns.map(header => {
        const label = header.textContent.trim();
        if (label === 'No') return 'number';
        if (label === 'Bahan Baku') return 'material';
        if (label === 'Konversi') return 'conversion';
        if (label.startsWith('Harga')) return 'price';
        if (label.startsWith('Total Harga')) return 'total-price';
        return 'quantity';
    });
    table.querySelectorAll('tr').forEach(row => {
        Array.from(row.cells).forEach((cell, index) => {
            cell.classList.add('purchase-col-' + types[index]);
            if (cell.tagName === 'TH') cell.scope = 'col';
        });
    });
    const wrapper = document.createElement('div');
    wrapper.className = 'purchase-table-scroll';
    wrapper.tabIndex = 0;
    wrapper.setAttribute('role', 'region');
    wrapper.setAttribute('aria-label', 'Rincian pembelian bahan baku, geser untuk melihat seluruh kolom');
    wrapper.appendChild(table);
    return wrapper;
}
