(() => {
  'use strict';

  let ctx = null;
  let currentTemplate = 'classic';
  let summary = null;

  const $ = (s, root = document) => root.querySelector(s);
  const el = (tag, cls = '') => {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    return node;
  };

  function iconNode(app) {
    const box = el('div', 'adl-active-icon');
    if (app?.icon_url) {
      const img = document.createElement('img');
      img.src = app.icon_url;
      img.alt = '';
      box.appendChild(img);
    } else {
      const i = document.createElement('i');
      i.className = app?.icon || 'fas fa-mobile-alt';
      box.appendChild(i);
    }
    return box;
  }

  function buildSummary() {
    summary = el('section', 'adl-active-summary');
    summary.setAttribute('aria-live', 'polite');
    const body = el('div', 'adl-active-summary-body');
    const copy = el('div', 'adl-active-copy');
    const name = el('strong', 'adl-active-name');
    const meta = el('span', 'adl-active-meta');
    copy.append(name, meta);
    body.append(copy);
    summary.append(body);
    return summary;
  }

  function syncActive(slug) {
    if (!ctx || !summary) return;
    const app = (ctx.apps || []).find(a => a.slug === slug) || ctx.apps?.[0];
    if (!app) return;
    summary.style.setProperty('--adl-app-accent', app.theme_color || '#007aff');
    const body = $('.adl-active-summary-body', summary);
    const oldIcon = $('.adl-active-icon', summary);
    const newIcon = iconNode(app);
    if (oldIcon) oldIcon.replaceWith(newIcon); else body.prepend(newIcon);
    $('.adl-active-name', summary).textContent = app.name || '应用';
    const ways = Array.isArray(app.downloads) ? app.downloads.length : 0;
    const shots = Array.isArray(app.images) ? app.images.length : 0;
    $('.adl-active-meta', summary).textContent = `${ways} 种下载方式 · ${shots} 张截图`;
  }

  function moveInto(wrapper, nodes) {
    nodes.filter(Boolean).forEach(node => wrapper.appendChild(node));
  }

  function apply(template, context) {
    ctx = context || {};
    currentTemplate = template || 'classic';
    document.body.dataset.landingTemplate = currentTemplate;

    const container = $('.container');
    if (!container) return;
    const logo = $('#siteLogo');
    const heading = $('#siteHeading');
    const notice = $('#noticeBar');
    const tabs = $('.app-tabs-container');
    const content = $('#appContent');
    const stats = $('.download-stats');
    const features = $('.features');

    buildSummary();
    syncActive(ctx.apps?.[0]?.slug || '');

    if (currentTemplate === 'classic') return;

    container.classList.add('adl-layout-root');

    if (currentTemplate === 'glass' || currentTemplate === 'aurora') {
      const hero = el('section', 'adl-hero adl-hero-spotlight');
      const core = el('div', 'adl-hero-core');
      moveInto(core, [logo, heading, notice, summary]);
      hero.appendChild(core);
      container.insertBefore(hero, container.firstChild);
      return;
    }

    if (currentTemplate === 'minimal') {
      const hero = el('header', 'adl-editorial-head');
      const brand = el('div', 'adl-editorial-brand');
      moveInto(brand, [logo, heading]);
      moveInto(hero, [brand, notice, summary]);
      container.insertBefore(hero, container.firstChild);
      return;
    }

    if (currentTemplate === 'midnight') {
      const grid = el('div', 'adl-console-grid');
      const rail = el('aside', 'adl-console-rail');
      const stage = el('div', 'adl-console-stage');
      moveInto(rail, [logo, heading, notice, summary, tabs, stats]);
      moveInto(stage, [content, features]);
      grid.append(rail, stage);
      container.appendChild(grid);
      return;
    }

    if (currentTemplate === 'store') {
      const hero = el('header', 'adl-store-head');
      moveInto(hero, [logo, heading, notice, summary]);
      const grid = el('div', 'adl-store-grid');
      const rail = el('aside', 'adl-store-rail');
      const stage = el('div', 'adl-store-stage');
      moveInto(rail, [tabs]);
      moveInto(stage, [content, stats, features]);
      grid.append(rail, stage);
      container.append(hero, grid);
      return;
    }

    if (currentTemplate === 'bento') {
      const top = el('div', 'adl-bento-top');
      const hero = el('section', 'adl-bento-hero');
      moveInto(hero, [logo, heading, notice, summary]);
      moveInto(top, [hero, stats]);
      const body = el('div', 'adl-bento-body');
      moveInto(body, [tabs, content, features]);
      container.append(top, body);
      return;
    }

    if (currentTemplate === 'split') {
      const split = el('div', 'adl-split');
      const aside = el('aside', 'adl-split-aside');
      const stage = el('main', 'adl-split-stage');
      moveInto(aside, [logo, heading, notice, summary, tabs, stats]);
      moveInto(stage, [content, features]);
      split.append(aside, stage);
      container.appendChild(split);
      return;
    }

    if (currentTemplate === 'mobile') {
      const phone = el('div', 'adl-mobile-shell');
      const head = el('header', 'adl-mobile-head');
      moveInto(head, [logo, heading, notice, summary]);
      moveInto(phone, [head, tabs, content, stats, features]);
      container.appendChild(phone);
    }
  }

  window.AppDownLandingLayouts = {
    apply,
    syncActive,
    getTemplate: () => currentTemplate,
  };
})();
