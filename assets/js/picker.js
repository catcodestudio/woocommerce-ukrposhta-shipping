(function () {
  const cfg = window.UPWC;
  if (!cfg || window.__upwcMounted) return;

  const accent = /^#[0-9a-fA-F]{6}$/.test(cfg.accent || '') ? cfg.accent : '#374151';

  const STYLE = `
  .upwc{--a:${accent};border:1px solid var(--upwc-line,#e4e4e7);border-radius:14px;margin:18px 0;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04);}
  .upwc__head{display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;padding:13px 16px;background:color-mix(in srgb,var(--a) 16%,transparent);border-bottom:1px solid #e4e4e7;border-radius:14px 14px 0 0;}
  .upwc__logo{width:26px;height:26px;border-radius:7px;background:var(--a);color:#0f1b3d;display:inline-flex;align-items:center;justify-content:center;font-weight:800;flex:none;}
  .upwc__body{padding:14px 16px;}
  .upwc__grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
  .upwc__field--region{grid-column:1 / -1;}
  @media(max-width:600px){.upwc__grid{grid-template-columns:1fr;}}
  .upwc__field{position:relative;}
  .upwc__field label{display:block;font-size:12px;color:#71717a;margin-bottom:5px;}
  .upwc__input{width:100%;height:42px;padding:0 12px;border:1px solid #e4e4e7;border-radius:10px;font-size:14px;background:#fff;color:#18181b;}
  .upwc__input:focus{outline:none;border-color:var(--a);box-shadow:0 0 0 3px color-mix(in srgb,var(--a) 30%,transparent);}
  .upwc__input:disabled{background:#f4f4f5;color:#a1a1aa;}
  .upwc__menu{position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:60;background:#fff;border:1px solid #e4e4e7;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.15);max-height:250px;overflow-y:auto;display:none;}
  .upwc__menu.open{display:block;}
  .upwc__opt{padding:9px 12px;font-size:14px;cursor:pointer;border-bottom:1px solid #f0f0f2;}
  .upwc__opt:hover,.upwc__opt.active{background:color-mix(in srgb,var(--a) 16%,transparent);}
  .upwc__opt small{display:block;color:#a1a1aa;font-size:12px;}
  .upwc__opt--muted{color:#a1a1aa;cursor:default;text-align:center;}
  .upwc__pin{display:inline-block;font-weight:700;color:#0f1b3d;margin-right:6px;}
  .upwc__sum{display:inline-flex;gap:8px;margin-top:12px;padding:8px 12px;font-size:13px;font-weight:600;background:color-mix(in srgb,var(--a) 16%,transparent);border-radius:9px;}
  .upwc__sum[hidden]{display:none;}`;

  const t = {
    title: 'Доставка Укрпоштою', region: 'Область', regionPh: 'Оберіть область…',
    city: 'Місто', cityPh: 'Спочатку оберіть область', cityReady: 'Почніть вводити назву…',
    office: 'Відділення', officePh: 'Спочатку оберіть місто', officeReady: 'Оберіть відділення або індекс…',
    searching: 'Пошук…', loading: 'Завантаження…', loadingRegions: 'Завантаження областей…', regionsFail: 'Області недоступні. Перевірте підключення до Укрпошти.', noRegion: 'Не знайдено', noCity: 'Нічого не знайдено', noOffice: 'Відділень немає',
  };

  const el = (h) => { const d = document.createElement('div'); d.innerHTML = h.trim(); return d.firstElementChild; };
  const esc = (s) => (s || '').replace(/"/g, '&quot;');
  const debounce = (fn, ms) => { let h; return (...a) => { clearTimeout(h); h = setTimeout(() => fn(...a), ms); }; };

  const api = (action, data) => {
    const body = new URLSearchParams(Object.assign({ action, nonce: cfg.nonce }, data || {}));
    return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body }).then((r) => r.json());
  };

  const wrap = el(`
    <div class="upwc">
      <div class="upwc__head"><span>${t.title}</span></div>
      <div class="upwc__body">
        <div class="upwc__grid">
          <div class="upwc__field upwc__field--region">
            <label>${t.region}</label>
            <input class="upwc__input" id="upwc-region" type="text" autocomplete="off" placeholder="${t.regionPh}" readonly onfocus="this.removeAttribute('readonly')">
            <div class="upwc__menu" id="upwc-region-m"></div>
          </div>
          <div class="upwc__field">
            <label>${t.city}</label>
            <input class="upwc__input" id="upwc-city" type="text" autocomplete="off" placeholder="${t.cityPh}" disabled>
            <div class="upwc__menu" id="upwc-city-m"></div>
          </div>
          <div class="upwc__field">
            <label>${t.office}</label>
            <input class="upwc__input" id="upwc-office" type="text" autocomplete="off" placeholder="${t.officePh}" disabled readonly onfocus="this.removeAttribute('readonly')">
            <div class="upwc__menu" id="upwc-office-m"></div>
            <input type="hidden" id="upwc-pi">
          </div>
        </div>
        <div class="upwc__sum" id="upwc-sum" hidden></div>
      </div>
    </div>`);

  const style = document.createElement('style');
  style.textContent = STYLE;

  let regions = [], regionsReady = false, regionId = '', regionName = '', cityId = '', cityDistrict = '', cityName = '', offices = [], officesLoaded = false;

  const $ = (id) => wrap.querySelector(id);
  const open = (m) => m.classList.add('open');
  const close = (m) => m.classList.remove('open');
  const muted = (x) => `<div class="upwc__opt upwc__opt--muted">${x}</div>`;

  const setNative = (sel, v) => {
    const n = document.querySelector(sel);
    if (!n) return;
    n.value = v;
    n.dispatchEvent(new Event('change', { bubbles: true }));
  };
  const fillNative = () => {
    setNative('#shipping_postcode', $('#upwc-pi').value || '');
    setNative('#shipping_city', cityName || '');
    setNative('#shipping_address_1', $('#upwc-pi').dataset.name || 'Укрпошта');
  };
  const recalc = () => { if (window.jQuery) window.jQuery(document.body).trigger('update_checkout'); };

  const renderSum = () => {
    const s = $('#upwc-sum'), name = $('#upwc-pi').dataset.name || '', pi = $('#upwc-pi').value || '';
    if (cityName && name) { s.innerHTML = `✓ ${cityName} — ${name} (${pi})`; s.hidden = false; }
    else s.hidden = true;
  };

  const renderRegions = (f) => {
    const m = $('#upwc-region-m'); const q = (f || '').trim().toLowerCase();
    const list = q ? regions.filter((r) => r.name.toLowerCase().includes(q)) : regions;
    if (!regions.length) { m.innerHTML = muted(regionsReady ? t.regionsFail : t.loadingRegions); open(m); return; }
    if (!list.length) { m.innerHTML = muted(t.noRegion); open(m); return; }
    m.innerHTML = list.map((r) => `<div class="upwc__opt" data-id="${esc(r.id)}" data-name="${esc(r.name)}">${r.name}</div>`).join('');
    open(m);
  };
  const pickRegion = (id, name) => {
    regionId = id; regionName = name; $('#upwc-region').value = name; close($('#upwc-region-m'));
    cityId = ''; cityName = ''; cityDistrict = '';
    const ci = $('#upwc-city'); ci.disabled = false; ci.value = ''; ci.placeholder = t.cityReady;
    const oi = $('#upwc-office'); oi.disabled = true; oi.value = ''; oi.placeholder = t.officePh;
    $('#upwc-pi').value = ''; $('#upwc-pi').dataset.name = ''; offices = []; renderSum();
  };

  const searchCities = debounce((q) => {
    const m = $('#upwc-city-m');
    api('upwc_cities', { region_id: regionId, q }).then((d) => {
      const list = (d && d.cities) || [];
      if (!list.length) { m.innerHTML = muted(t.noCity); open(m); return; }
      m.innerHTML = list.map((c) => `<div class="upwc__opt" data-id="${esc(c.id)}" data-district="${esc(c.district_id)}" data-name="${esc(c.name)}">${c.name}</div>`).join('');
      open(m);
    }).catch(() => { m.innerHTML = muted(t.noCity); open(m); });
  }, 280);
  const pickCity = (id, district, name) => {
    cityId = id; cityDistrict = district || ''; cityName = name; $('#upwc-city').value = name; close($('#upwc-city-m'));
    const oi = $('#upwc-office'); oi.disabled = false; oi.value = ''; oi.placeholder = t.loading;
    $('#upwc-pi').value = ''; $('#upwc-pi').dataset.name = ''; offices = []; officesLoaded = false; renderSum(); fillNative();
    api('upwc_offices', { city_id: id, district_id: cityDistrict, region_id: regionId }).then((d) => {
      offices = (d && d.offices) || [];
    }).catch(() => { offices = []; }).finally(() => {
      officesLoaded = true; oi.placeholder = t.officeReady;
      if (oi === document.activeElement) renderOffices(oi.value);
    });
  };

  const renderOffices = (f) => {
    const m = $('#upwc-office-m'); const q = (f || '').trim().toLowerCase();
    const list = q ? offices.filter((o) => (o.name + ' ' + o.postindex + ' ' + o.address).toLowerCase().includes(q)) : offices;
    if (!offices.length) { m.innerHTML = muted(officesLoaded ? t.noOffice : t.loading); open(m); return; }
    if (!list.length) { m.innerHTML = muted(t.noOffice); open(m); return; }
    m.innerHTML = list.slice(0, 80).map((o) => `<div class="upwc__opt" data-pi="${esc(o.postindex)}" data-name="${esc(o.name)}"><span class="upwc__pin">${o.postindex}</span>${o.name}${o.address ? `<small>${o.address}</small>` : ''}</div>`).join('');
    open(m);
  };
  const pickOffice = (pi, name) => {
    $('#upwc-office').value = `${name} (${pi})`; $('#upwc-pi').value = pi; $('#upwc-pi').dataset.name = name;
    close($('#upwc-office-m')); renderSum(); fillNative();
    api('upwc_set', { region_id: regionId, city_id: cityId, city_name: cityName, office_postindex: pi, office_name: name }).then(recalc);
  };

  const bind = () => {
    const ri = $('#upwc-region'), rm = $('#upwc-region-m');
    const ci = $('#upwc-city'), cm = $('#upwc-city-m');
    const oi = $('#upwc-office'), om = $('#upwc-office-m');
    ri.addEventListener('focus', () => renderRegions(ri.value));
    ri.addEventListener('input', () => { regionId = ''; renderRegions(ri.value); });
    rm.addEventListener('click', (e) => { const o = e.target.closest('[data-id]'); if (o) pickRegion(o.dataset.id, o.dataset.name); });
    ci.addEventListener('input', () => { const v = ci.value.trim(); cityId = ''; renderSum(); if (v.length < 2) { close(cm); return; } cm.innerHTML = muted(t.searching); open(cm); searchCities(v); });
    cm.addEventListener('click', (e) => { const o = e.target.closest('[data-id]'); if (o) pickCity(o.dataset.id, o.dataset.district, o.dataset.name); });
    oi.addEventListener('focus', () => { if (!oi.disabled) renderOffices(oi.value); });
    oi.addEventListener('input', () => renderOffices(oi.value));
    om.addEventListener('click', (e) => { const o = e.target.closest('[data-pi]'); if (o) pickOffice(o.dataset.pi, o.dataset.name); });
    document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) { close(rm); close(cm); close(om); } });
  };

  const mount = () => {
    const root = document.querySelector('#upwc-picker-root');
    if (!root || root.__done) return false;
    root.__done = true;
    root.appendChild(wrap);
    document.head.appendChild(style);
    return true;
  };

  const init = () => {
    if (window.__upwcMounted) return;
    if (!mount()) return;
    window.__upwcMounted = true;
    bind();
    api('upwc_regions').then((d) => { regions = (d && d.regions) || []; }).catch(() => {}).finally(() => { regionsReady = true; if ($('#upwc-region') === document.activeElement) renderRegions($('#upwc-region').value); });
  };

  // The checkout fragment can re-render; (re)mount whenever WC updates it.
  const boot = () => { window.__upwcMounted = false; init(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  if (window.jQuery) window.jQuery(document.body).on('updated_checkout', () => { if (!document.querySelector('#upwc-picker-root .upwc')) { window.__upwcMounted = false; init(); } });
})();
