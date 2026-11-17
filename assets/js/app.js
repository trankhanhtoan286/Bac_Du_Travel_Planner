/**
 * BẮC DU (TRAVEL PLANNER) - FRONTEND INTERACTIVE & LEAFLET MAP ENGINE
 */

window.BacDuMap = {
  mapInstance: null,
  markerLayer: null,
  allPoints: [],

  getPinConfig(type) {
    const map = {
      attraction: { color: '#0f766e', icon: 'bi-compass-fill', label: 'Điểm tham quan' },
      food:       { color: '#d97706', icon: 'bi-cup-hot-fill', label: 'Ăn uống' },
      stay:       { color: '#4f46e5', icon: 'bi-house-heart-fill', label: 'Lưu trú' },
      checkin_checkout: { color: '#4f46e5', icon: 'bi-key-fill', label: 'Lưu trú' },
      transport:  { color: '#0284c7', icon: 'bi-bus-front-fill', label: 'Di chuyển' },
      other:      { color: '#64748b', icon: 'bi-geo-alt-fill', label: 'Điểm dừng' }
    };
    return map[type] || map.attraction;
  },

  createCustomIcon(type) {
    const cfg = this.getPinConfig(type);
    return L.divIcon({
      className: 'custom-leaflet-div-icon',
      html: `<div class="leaflet-pin-marker" style="background:${cfg.color};"><i class="bi ${cfg.icon}"></i></div>`,
      iconSize: [34, 34],
      iconAnchor: [17, 34],
      popupAnchor: [0, -30]
    });
  },

  init(containerId, points, defaultCenter = [21.35, 105.15], defaultZoom = 8) {
    const el = document.getElementById(containerId);
    if (!el || typeof L === 'undefined') return;

    this.allPoints = Array.isArray(points) ? points : [];
    if (this.mapInstance) {
      this.mapInstance.remove();
    }

    this.mapInstance = L.map(containerId, { scrollWheelZoom: false }).setView(defaultCenter, defaultZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | Bắc Du'
    }).addTo(this.mapInstance);

    this.markerLayer = L.layerGroup().addTo(this.mapInstance);
    this.renderMarkers('all');

    // Khắc phục lỗi kích thước bản đồ khi nằm trong tab ẩn của Bootstrap
    document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach(tabBtn => {
      tabBtn.addEventListener('shown.bs.tab', () => {
        if (this.mapInstance) {
          setTimeout(() => this.mapInstance.invalidateSize(), 150);
        }
      });
    });
  },

  renderMarkers(dayFilter = 'all', typeFilter = 'all') {
    if (!this.mapInstance || !this.markerLayer) return;
    this.markerLayer.clearLayers();

    const filtered = this.allPoints.filter(pt => {
      const matchDay = (dayFilter === 'all' || String(pt.day_number) === String(dayFilter));
      const matchType = (typeFilter === 'all' || pt.type === typeFilter);
      return matchDay && matchType && pt.lat && pt.lng;
    });

    const bounds = [];
    const polylineCoords = [];

    filtered.forEach((pt, idx) => {
      const lat = parseFloat(pt.lat);
      const lng = parseFloat(pt.lng);
      if (isNaN(lat) || isNaN(lng)) return;

      bounds.push([lat, lng]);
      polylineCoords.push([lat, lng]);

      const cfg = this.getPinConfig(pt.type);
      const gmapView = pt.google_maps_url || `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
      const gmapDir = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
      const dayBadge = pt.day_number ? `<span class="badge bg-dark me-1">Ngày ${pt.day_number}</span>` : '';
      const timeBadge = pt.time ? `<span class="badge bg-light text-dark border">${pt.time}</span>` : '';
      const detailBtn = pt.location_id
        ? `<a href="location_detail.php?id=${pt.location_id}" class="btn btn-sm btn-outline-success py-1 px-2"><i class="bi bi-info-circle"></i> Chi tiết</a>`
        : '';

      const popupHtml = `
        <div style="min-width:230px;font-family:'Plus Jakarta Sans',sans-serif;">
          <div class="mb-1">${dayBadge}<span class="badge" style="background:${cfg.color};">${cfg.label}</span> ${timeBadge}</div>
          <h6 class="fw-bold mb-1 text-dark">${pt.title || pt.name}</h6>
          ${pt.subtitle ? `<div class="small text-muted mb-2">${pt.subtitle}</div>` : ''}
          <div class="d-flex flex-wrap gap-1 mt-2">
            ${detailBtn}
            <a href="${gmapView}" target="_blank" rel="noopener" class="btn btn-sm btn-light border py-1 px-2">
              <i class="bi bi-geo-alt-fill text-danger"></i> Google Maps
            </a>
            <a href="${gmapDir}" target="_blank" rel="noopener" class="btn btn-sm btn-primary py-1 px-2">
              <i class="bi bi-sign-turn-right-fill"></i> Chỉ đường
            </a>
          </div>
        </div>
      `;

      const marker = L.marker([lat, lng], { icon: this.createCustomIcon(pt.type) }).bindPopup(popupHtml);
      this.markerLayer.addLayer(marker);
      if (filtered.length === 1) {
        marker.openPopup();
      }
    });

    if (polylineCoords.length > 1) {
      L.polyline(polylineCoords, {
        color: '#0f5132',
        weight: 3,
        opacity: 0.65,
        dashArray: '6, 8'
      }).addTo(this.markerLayer);
    }

    if (bounds.length === 1) {
      this.mapInstance.setView(bounds[0], 14);
    } else if (bounds.length > 1) {
      this.mapInstance.fitBounds(bounds, { padding: [42, 42], maxZoom: 14 });
    }
  }
};

function copyToClipboard(inputId, btnEl) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.select();
  input.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(input.value).then(() => {
    if (btnEl) {
      const origHtml = btnEl.innerHTML;
      btnEl.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Đã sao chép!';
      btnEl.classList.replace('btn-bacdu', 'btn-success');
      setTimeout(() => {
        btnEl.innerHTML = origHtml;
      }, 2000);
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  // Tự động tính ngân sách gợi ý trong Modal "Dùng lịch trình này" khi thay đổi số người
  const paxInput = document.getElementById('clone_pax_count');
  const budgetInput = document.getElementById('clone_planned_budget');
  if (paxInput && budgetInput && paxInput.dataset.refPerPerson) {
    const refPerPerson = parseFloat(paxInput.dataset.refPerPerson) || 0;
    paxInput.addEventListener('input', () => {
      const pax = Math.max(1, parseInt(paxInput.value || '1', 10));
      budgetInput.value = Math.round(refPerPerson * pax);
    });
  }

  // Tự động ẩn các thông báo Floating Toast sau 4.6 giây (tạm dừng khi rê chuột vào)
  const scheduleToastDismiss = (toastEl) => {
    let timer = setTimeout(() => {
      if (window.bootstrap && bootstrap.Alert) {
        bootstrap.Alert.getOrCreateInstance(toastEl).close();
      } else {
        toastEl.remove();
      }
    }, 4600);
    toastEl.addEventListener('mouseenter', () => clearTimeout(timer));
    toastEl.addEventListener('mouseleave', () => {
      timer = setTimeout(() => {
        if (window.bootstrap && bootstrap.Alert) {
          bootstrap.Alert.getOrCreateInstance(toastEl).close();
        } else {
          toastEl.remove();
        }
      }, 2400);
    });
  };

  document.querySelectorAll('#cinToastStack .cin-flash-banner').forEach(scheduleToastDismiss);

  window.showBacDuToast = function(type, htmlMessage) {
    let stack = document.getElementById('cinToastStack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'cinToastStack';
      stack.className = 'cin-toast-stack';
      document.body.appendChild(stack);
    }
    const iconMap = {
      success: 'bi-check-circle-fill text-success',
      warning: 'bi-exclamation-triangle-fill text-warning',
      danger: 'bi-exclamation-octagon-fill text-danger',
      info: 'bi-info-circle-fill text-info'
    };
    const el = document.createElement('div');
    el.className = `alert alert-dismissible fade show cin-flash-banner cin-flash-${type || 'info'} d-flex align-items-start gap-2`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `
      <i class="bi ${iconMap[type] || iconMap.info} fs-6 mt-1 flex-shrink-0"></i>
      <div class="small lh-sm">${htmlMessage}</div>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Đóng"></button>
    `;
    stack.appendChild(el);
    scheduleToastDismiss(el);
  };

  // Xử lý thả tim Yêu thích bằng AJAX không tải lại trang (chống nhảy mất vị trí cuộn)
  const isFavoritesPage = window.location.pathname.endsWith('/favorites.php') || window.location.pathname === 'favorites.php';
  if (!isFavoritesPage) {
    document.querySelectorAll('form[action="favorites.php"]').forEach(form => {
      const actionInput = form.querySelector('input[name="action"][value="toggle"]');
      if (!actionInput) return;

      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        if (btn && btn.disabled) return;
        if (btn) btn.disabled = true;

        try {
          const fd = new FormData(form);
          fd.append('ajax', '1');
          const res = await fetch('favorites.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
          });
          const data = await res.json();

          if (data.require_login && data.login_url) {
            window.location.href = data.login_url;
            return;
          }

          if (data.ok) {
            const icon = form.querySelector('i.bi');
            if (icon) {
              if (data.favorited) {
                icon.className = 'bi bi-heart-fill text-danger';
                if (btn) btn.title = 'Bỏ yêu thích';
              } else {
                const isLightBtn = btn && btn.classList.contains('btn-light');
                icon.className = isLightBtn ? 'bi bi-heart text-secondary' : 'bi bi-heart';
                if (btn) btn.title = 'Lưu yêu thích';
              }
            }
            const navBadge = document.getElementById('navFavBadge');
            if (navBadge) {
              navBadge.textContent = data.fav_count;
              navBadge.classList.toggle('d-none', !(data.fav_count > 0));
            }
            const menuCount = document.getElementById('menuFavCount');
            if (menuCount) {
              menuCount.textContent = data.fav_count;
            }
            if (window.showBacDuToast && data.message) {
              window.showBacDuToast(data.favorited ? 'success' : 'info', data.message);
            }
          }
        } catch (err) {
          form.submit();
        } finally {
          if (btn) btn.disabled = false;
        }
      });
    });
  }
});

window.toggleAuthPw = function(btn, inputId) {
  const input = document.getElementById(inputId);
  const icon = btn ? btn.querySelector('i') : null;
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
};
