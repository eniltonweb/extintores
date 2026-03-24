/**
 * Sorts a table based on a sort key.
 * Currently specific to the user management table.
 *
 * @param {string} sortKey - The key to sort by ('username' or 'nivel_acesso')
 */
function sortTable(sortKey) {
    const tableBody = document.querySelector('table tbody');
    if (!tableBody) return;

    const rows = Array.from(tableBody.querySelectorAll('tr'));
    if (rows.length === 0) return;

    const sortedRows = rows.sort((a, b) => {
        let aData, bData;
        if (sortKey === 'username') {
            const tdA = a.querySelector('td:nth-child(1)');
            const tdB = b.querySelector('td:nth-child(1)');
            aData = tdA ? tdA.textContent : '';
            bData = tdB ? tdB.textContent : '';
        } else if (sortKey === 'nivel_acesso') {
            const tdA = a.querySelector('td:nth-child(2)');
            const tdB = b.querySelector('td:nth-child(2)');
            aData = tdA ? tdA.textContent : '';
            bData = tdB ? tdB.textContent : '';
        } else {
            return 0;
        }
        return aData.localeCompare(bData);
    });

    // Use DocumentFragment for better performance
    const fragment = document.createDocumentFragment();
    sortedRows.forEach(row => fragment.appendChild(row));

    tableBody.innerHTML = '';
    tableBody.appendChild(fragment);
}
