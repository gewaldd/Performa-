// accounts-script.js: only add table-specific behavior (sorting).
// Filtering/search is handled by Admin/script.js; avoid redeclaring globals.

// The create account form is posted server-side to create_user.php
// so no client-side demo handler is required. Leave form submission
// to the server (Admin/create_user.php handles creation and messages).

// ── Table sorting (click column headers) ────────────────────────────
const tableHead = document.querySelector('.table-head');
if (tableHead) {
  const headers = Array.from(tableHead.children);
  headers.forEach((h, idx) => {
    h.style.cursor = 'pointer';
    h.addEventListener('click', () => {
      const dir = h.dataset.order === 'asc' ? 'desc' : 'asc';
      headers.forEach(x => delete x.dataset.order);
      h.dataset.order = dir;

      const container = document.querySelector('.table-wrap');
      if (!container) return;

      // collect current visible rows
      const currentRows = Array.from(container.querySelectorAll('.table-row')).filter(r => !r.hidden);

      currentRows.sort((a, b) => {
        const aCell = (a.children[idx] && a.children[idx].innerText) ? a.children[idx].innerText.trim() : '';
        const bCell = (b.children[idx] && b.children[idx].innerText) ? b.children[idx].innerText.trim() : '';
        const aNum = parseFloat(aCell.replace(/[^0-9.-]+/g, ''));
        const bNum = parseFloat(bCell.replace(/[^0-9.-]+/g, ''));
        let cmp = 0;
        if (!isNaN(aNum) && !isNaN(bNum) && aCell !== '' && bCell !== '') {
          cmp = aNum - bNum;
        } else {
          cmp = aCell.localeCompare(bCell, undefined, { sensitivity: 'base' });
        }
        return dir === 'asc' ? cmp : -cmp;
      });

      // remove existing rows and append in sorted order
      Array.from(container.querySelectorAll('.table-row')).forEach(r => r.remove());
      currentRows.forEach(r => container.appendChild(r));
    });
  });
}
