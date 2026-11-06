/**
 * BẮC DU (TRAVEL PLANNER) — CINEMATIC APP MODE ENGINE
 * Keyboard navigation (j/k, 1-6, /, Ctrl+K, ?), Command Palette, and Focus Preservation
 */
(function () {
  'use strict';

  function isTyping(el) {
    if (!el) return false;
    const tag = (el.tagName || '').toLowerCase();
    return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('cinematic-app')) return;

    // 1. Render [data-key] badges automatically so shortcuts are learned through the mouse
    document.querySelectorAll('[data-key]').forEach((btn) => {
      if (btn.querySelector('.app-kbd-badge')) return;
      const keyLabel = btn.getAttribute('data-key');
      if (!keyLabel) return;
      const badge = document.createElement('span');
      badge.className = 'app-kbd-badge';
      badge.setAttribute('aria-hidden', 'true');
      badge.textContent = keyLabel;
      btn.appendChild(badge);
    });

    // 2. Collect navigable rows / slips in the active view
    const getRows = () => Array.from(
      document.querySelectorAll('.app-table tbody tr, .cin-activity-slip, .field-slip[data-kb-row]')
    ).filter((el) => el.offsetParent !== null);

    let activeRowIdx = -1;

    function focusRow(idx) {
      const rows = getRows();
      if (!rows.length) return;
      rows.forEach((r) => r.classList.remove('kb-focused'));
      activeRowIdx = Math.max(0, Math.min(rows.length - 1, idx));
      const target = rows[activeRowIdx];
      target.classList.add('kb-focused');
      target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // 3. Command Palette & Shortcut Sheet injection
    const paletteCommands = [];
    document.querySelectorAll('[data-palette-label]').forEach((el) => {
      paletteCommands.push({
        label: el.getAttribute('data-palette-label'),
        key: el.getAttribute('data-key') || '',
        action: () => el.click(),
      });
    });

    document.querySelectorAll('.app-view-tab').forEach((tab, idx) => {
      paletteCommands.push({
        label: 'Chuyển khu vực: ' + tab.textContent.trim(),
        key: String(idx + 1),
        action: () => { window.location.href = tab.href; },
      });
    });

    // Add global navigation links
    [
      { label: 'Mở Trang chủ (Bắc Du Film)', url: 'index.php', key: 'g h' },
      { label: 'Mở Lịch trình 12 tháng', url: 'templates.php', key: 'g t' },
      { label: 'Mở Địa điểm & Bản đồ', url: 'locations.php', key: 'g l' },
      { label: 'Mở Chuyến đi của tôi', url: 'trips.php', key: 'g m' },
    ].forEach((nav) => {
      paletteCommands.push({
        label: nav.label,
        key: nav.key,
        action: () => { window.location.href = nav.url; },
      });
    });

    const paletteBackdrop = document.createElement('div');
    paletteBackdrop.className = 'cin-palette-backdrop';
    paletteBackdrop.id = 'cinAppPalette';
    paletteBackdrop.setAttribute('role', 'dialog');
    paletteBackdrop.setAttribute('aria-modal', 'true');
    paletteBackdrop.setAttribute('aria-label', 'Bảng lệnh điều phối nhanh');
    paletteBackdrop.innerHTML = `
      <div class="cin-palette-box">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-dark text-white small font-monospace">
          <span><i class="bi bi-terminal me-2 text-warning"></i>BẢNG LỆNH ĐIỀU PHỐI NHANH (APP MODE)</span>
          <span>ESC để đóng</span>
        </div>
        <input type="text" class="cin-palette-input" id="cinPaletteSearch" placeholder="Gõ lệnh, tên tab hoặc thao tác cần chạy...">
        <ul class="cin-palette-list" id="cinPaletteList"></ul>
        <div class="px-3 py-2 border-top bg-light d-flex flex-wrap justify-content-between small text-muted font-monospace" style="font-size:0.72rem;">
          <span>[j / k] Di chuyển dòng</span>
          <span>[Enter] Mở dòng đang chọn</span>
          <span>[1-6] Đổi Tab</span>
          <span>[?] Bảng phím tắt</span>
        </div>
      </div>
    `;
    document.body.appendChild(paletteBackdrop);

    const paletteInput = document.getElementById('cinPaletteSearch');
    const paletteList = document.getElementById('cinPaletteList');
    let filteredCmds = paletteCommands.slice();
    let paletteSel = 0;

    function renderPaletteList(q) {
      const kw = (q || '').trim().toLowerCase();
      filteredCmds = paletteCommands.filter((c) => !kw || c.label.toLowerCase().includes(kw));
      paletteSel = 0;
      paletteList.innerHTML = '';
      if (!filteredCmds.length) {
        paletteList.innerHTML = '<li class="px-3 py-3 text-muted small text-center">Không có lệnh khớp từ khóa.</li>';
        return;
      }
      filteredCmds.forEach((cmd, idx) => {
        const li = document.createElement('li');
        li.className = 'cin-palette-item' + (idx === paletteSel ? ' active' : '');
        li.innerHTML = `
          <span>${cmd.label}</span>
          ${cmd.key ? `<span class="app-kbd-badge bg-dark text-white">${cmd.key}</span>` : ''}
        `;
        li.addEventListener('click', () => {
          closePalette();
          cmd.action();
        });
        paletteList.appendChild(li);
      });
    }

    function openPalette() {
      paletteBackdrop.classList.add('open');
      paletteInput.value = '';
      renderPaletteList('');
      setTimeout(() => paletteInput.focus(), 20);
    }

    function closePalette() {
      paletteBackdrop.classList.remove('open');
    }

    paletteBackdrop.addEventListener('click', (e) => {
      if (e.target === paletteBackdrop) closePalette();
    });

    paletteInput.addEventListener('input', () => renderPaletteList(paletteInput.value));
    paletteInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        closePalette();
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (filteredCmds.length) {
          paletteSel = (paletteSel + 1) % filteredCmds.length;
          Array.from(paletteList.children).forEach((el, i) => el.classList.toggle('active', i === paletteSel));
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (filteredCmds.length) {
          paletteSel = (paletteSel - 1 + filteredCmds.length) % filteredCmds.length;
          Array.from(paletteList.children).forEach((el, i) => el.classList.toggle('active', i === paletteSel));
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (filteredCmds[paletteSel]) {
          const fn = filteredCmds[paletteSel].action;
          closePalette();
          fn();
        }
      }
    });

    // Expose opener for toolbar button
    window.BacDuApp = {
      openPalette,
    };

    // 4. Global Keyboard Handler
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (paletteBackdrop.classList.contains('open')) closePalette();
        else openPalette();
        return;
      }

      if (paletteBackdrop.classList.contains('open')) return;
      if (isTyping(e.target)) return;
      if (document.querySelector('.modal.show')) return;

      if (e.key === '/' || e.key === '?') {
        e.preventDefault();
        openPalette();
        return;
      }

      if (e.key === 'j' || e.key === 'ArrowDown') {
        const rows = getRows();
        if (rows.length) {
          e.preventDefault();
          focusRow(activeRowIdx + 1);
        }
      } else if (e.key === 'k' || e.key === 'ArrowUp') {
        const rows = getRows();
        if (rows.length) {
          e.preventDefault();
          focusRow(activeRowIdx <= 0 ? 0 : activeRowIdx - 1);
        }
      } else if (e.key === 'Enter' && activeRowIdx >= 0) {
        const rows = getRows();
        const row = rows[activeRowIdx];
        if (row) {
          const primaryBtn = row.querySelector('a[href], button[onclick], button[type="submit"]');
          if (primaryBtn) {
            e.preventDefault();
            primaryBtn.click();
          }
        }
      } else if (/^[1-6]$/.test(e.key) && !e.altKey && !e.ctrlKey && !e.metaKey) {
        const tabs = document.querySelectorAll('.app-view-tab');
        const idx = parseInt(e.key, 10) - 1;
        if (tabs[idx]) {
          e.preventDefault();
          window.location.href = tabs[idx].href;
        }
      } else {
        // Match any button with [data-key="<key>"]
        const keyChar = e.key.toLowerCase();
        const matchBtn = document.querySelector(`[data-key="${keyChar}"]`);
        if (matchBtn) {
          e.preventDefault();
          matchBtn.click();
        }
      }
    });
  });
})();
