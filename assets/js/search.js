(function () {
  'use strict';

  var config = window.RahaSearchData || {};
  var categories = Array.isArray(config.cats) ? config.cats : [];
  var $ = function (selector, root) { return (root || document).querySelector(selector); };
  var $$ = function (selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); };
  var digits = '۰۱۲۳۴۵۶۷۸۹';
  var fa = function (value) { return String(value).replace(/\d/g, function (digit) { return digits[+digit]; }); };
  var esc = function (value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]; }); };
  function highlight(value, query) {
    var words = String(query || '').trim().split(/\s+/).filter(Boolean);
    if (!words.length) return esc(value);
    var pattern = new RegExp('(' + words.map(function (word) { return word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + ')', 'gi');
    return String(value == null ? '' : value).split(pattern).map(function (part, index) {
      return index % 2 ? '<mark>' + esc(part) + '</mark>' : esc(part);
    }).join('');
  }
  var form = $('#searchForm');
  if (!form || !config.endpoint) return;

  var input = $('#q');
  var field = $('#searchField');
  var clearBtn = $('#clearBtn');
  var panel = $('#spPanel');
  var suggGroup = $('#suggGroup');
  var suggList = $('#suggList');
  var viewAllBtn = $('#viewAllBtn');
  var viewAllLabel = $('#viewAllLabel');
  var recentGroup = $('#recentGroup');
  var recentChips = $('#recentChips');
  var popularChips = $('#popularChips');
  var countEl = $('#count');
  var resetBtn = $('#resetBtn');
  var sortSelect = $('#sortSelect');
  var results = $('#results');
  var empty = $('#empty');
  var emptyText = $('#emptyText');
  var emptyChips = $('#emptyChips');
  var retryBtn = $('#retrySearch');
  var pager = $('#pager');
  var catGroup = $('#catGroup');
  var state = { q: '', cat: '', sort: 'newest', page: 1 };
  var suggestions = [];
  var activeIndex = -1;
  var controller = null;
  var timer = null;
  var recentKey = 'raha.recentSearches';
  var recent = [];
  var popular = ['CSS', 'Core Web Vitals', 'دیزاین سیستم', 'Next.js', 'سئو', 'موبایل‌فرست', 'وردپرس', 'تایپوگرافی'];
  try { var saved = JSON.parse(localStorage.getItem(recentKey) || '[]'); if (Array.isArray(saved)) recent = saved.slice(0, 6); } catch (error) {}

  function categoryName() {
    var match = categories.filter(function (cat) { return cat.slug === state.cat; })[0];
    return match ? match.name : '';
  }

  function renderCategories() {
    var all = [{slug: '', name: 'همه', count: Number(config.total) || 0}].concat(categories);
    catGroup.innerHTML = all.map(function (cat) {
      return '<button type="button" class="cat" data-cat="' + esc(cat.slug) + '" aria-pressed="' + (cat.slug === state.cat) + '"><span class="cat__label">' + esc(cat.name) + '</span><span class="cat__count">' + fa(cat.count) + '</span></button>';
    }).join('');
  }

  function updateCategoryUI(total) {
    $$('.cat', catGroup).forEach(function (button) {
      var selected = button.dataset.cat === state.cat;
      button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      var badge = $('.cat__count', button);
      badge.hidden = !!state.q.trim() && !selected;
      var cat = categories.filter(function (item) { return item.slug === button.dataset.cat; })[0];
      badge.textContent = fa(selected ? total : cat ? cat.count : (Number(config.total) || 0));
    });
  }

  function card(post) {
    var media = post.thumb ? '<img src="' + esc(post.thumb) + '" alt="" class="post__thumb">' :
      '<div class="post__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>';
    return '<article class="post" data-id="' + Number(post.id) + '"><div class="post__media"><span class="post__tag">' + esc(post.tag) + '</span>' + media +
      '</div><div class="post__body"><h3 class="post__title"><a class="post__title-link" href="' + esc(post.url) + '">' + highlight(post.title, state.q) +
      '</a></h3><p class="post__desc">' + highlight(post.desc, state.q) + '</p><div class="post__foot"><span class="post__date"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>' + esc(post.date) +
      '</span><span class="post__arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg></span></div></div></article>';
  }

  function pageNumbers(current, total) {
    if (total <= 7) return Array.from({length: total}, function (_, i) { return i + 1; });
    var pages = [1, total, current, current - 1, current + 1];
    if (current <= 3) pages.push(2, 3, 4);
    if (current >= total - 2) pages.push(total - 1, total - 2, total - 3);
    return pages.filter(function (n) { return n > 0 && n <= total; }).filter(function (n, i, list) { return list.indexOf(n) === i; }).sort(function (a, b) { return a - b; });
  }

  function renderPager(pages) {
    pager.hidden = pages <= 1;
    if (pager.hidden) { pager.innerHTML = ''; return; }
    var html = '<button type="button" data-page="' + (state.page - 1) + '"' + (state.page === 1 ? ' disabled' : '') + '>قبل</button>';
    var nums = pageNumbers(state.page, pages);
    nums.forEach(function (n, i) {
      if (i && n - nums[i - 1] > 1) html += '<span class="pager-gap" aria-hidden="true">…</span>';
      html += '<button type="button" data-page="' + n + '"' + (n === state.page ? ' aria-current="page"' : '') + ' aria-label="صفحه ' + fa(n) + '">' + fa(n) + '</button>';
    });
    html += '<button type="button" data-page="' + (state.page + 1) + '"' + (state.page === pages ? ' disabled' : '') + '>بعد</button>';
    pager.innerHTML = html;
  }

  function showSuggestions(items, total) {
    suggestions = state.q.trim() && state.page === 1 ? items.slice(0, 5) : [];
    activeIndex = -1;
    input.removeAttribute('aria-activedescendant');
    suggGroup.hidden = !suggestions.length;
    suggList.innerHTML = suggestions.map(function (post, index) {
      return '<button type="button" class="panel__option" id="sug-' + index + '" data-index="' + index + '" role="option" aria-selected="false"><span class="panel__option-icon" aria-hidden="true">⌕</span><span class="panel__option-title">' + highlight(post.title, state.q) + '</span><span class="panel__option-meta"><span class="panel__option-meta-tag">' + esc(post.tag) + '</span></span></button>';
    }).join('');
    viewAllBtn.hidden = !total;
    viewAllLabel.textContent = 'مشاهده همه نتایج (' + fa(total) + ')';
  }

  function renderResponse(data) {
    var total = Number(data.total) || 0;
    var items = Array.isArray(data.items) ? data.items : [];
    results.innerHTML = items.map(card).join('');
    results.hidden = !total;
    empty.hidden = !!total;
    retryBtn.hidden = true;
    $('#emptyReset').hidden = false;
    if (!total) emptyText.textContent = state.q.trim() ? 'برای «' + state.q.trim() + '» نوشته‌ای پیدا نشد.' : 'در این دسته نوشته‌ای نیست.';
    var suffix = state.q.trim() ? 'نتیجه برای «' + state.q.trim() + '»' : 'نوشته در آرشیو';
    if (state.cat) suffix += ' در دسته «' + categoryName() + '»';
    countEl.textContent = fa(total) + ' ' + suffix;
    resetBtn.hidden = !state.q.trim() && !state.cat && state.sort === 'newest';
    sortSelect.querySelector('option[value="relevant"]').disabled = !state.q.trim();
    updateCategoryUI(total);
    renderPager(Number(data.pages) || 0);
    showSuggestions(items, total);
  }

  function updateURL(replace) {
    var params = new URLSearchParams();
    params.set('s', state.q.trim());
    if (state.cat) params.set('category', state.cat);
    if (state.sort !== 'newest') params.set('sort', state.sort);
    if (state.page > 1) params.set('paged', state.page);
    history[replace ? 'replaceState' : 'pushState'](null, '', window.location.pathname + '?' + params.toString());
  }

  function readURL() {
    var params = new URLSearchParams(window.location.search);
    state.q = (params.get('s') || params.get('q') || '').slice(0, 80);
    var requestedCat = params.get('category') || params.get('cat');
    state.cat = categories.some(function (cat) { return cat.slug === requestedCat; }) ? requestedCat : '';
    state.sort = ['newest', 'oldest', 'relevant'].indexOf(params.get('sort')) >= 0 ? params.get('sort') : 'newest';
    state.page = Math.min(10000, Math.max(1, parseInt(params.get('paged') || params.get('page'), 10) || 1));
    if (!state.q.trim() && state.sort === 'relevant') state.sort = 'newest';
    input.value = state.q;
    clearBtn.hidden = !state.q;
    sortSelect.value = state.sort;
  }

  async function loadResults() {
    if (controller) controller.abort();
    controller = new AbortController();
    var current = controller;
    var params = new URLSearchParams({q: state.q.trim(), category: state.cat, sort: state.sort, page: String(state.page)});
    countEl.textContent = 'در حال بارگذاری…';
    results.setAttribute('aria-busy', 'true');
    try {
      var response = await fetch(config.endpoint + '?' + params.toString(), {signal: current.signal, credentials: 'same-origin'});
      if (!response.ok) throw new Error('search failed');
      var data = await response.json();
      if (current !== controller) return;
      if (data.total && data.pages && state.page > data.pages) {
        state.page = data.pages;
        updateURL(true);
        loadResults();
        return;
      }
      renderResponse(data);
    } catch (error) {
      if (error.name === 'AbortError' || current !== controller) return;
      results.innerHTML = '';
      results.hidden = true;
      empty.hidden = false;
      emptyText.textContent = 'بارگذاری نتایج انجام نشد. دوباره تلاش کنید.';
      retryBtn.hidden = false;
      $('#emptyReset').hidden = true;
      countEl.textContent = 'خطا در جست‌وجو';
      pager.hidden = true;
      suggestions = [];
      suggGroup.hidden = true;
    } finally {
      if (current === controller) results.removeAttribute('aria-busy');
    }
  }

  function change(replace) {
    if (timer) clearTimeout(timer);
    updateURL(replace);
    loadResults();
  }

  function setQuery(term) {
    input.value = term;
    state.q = term;
    state.page = 1;
    clearBtn.hidden = !term;
    change(false);
  }

  function saveRecent(term) {
    term = String(term || '').trim();
    if (!term) return;
    recent = [term].concat(recent.filter(function (old) { return old !== term; })).slice(0, 6);
    try { localStorage.setItem(recentKey, JSON.stringify(recent)); } catch (error) {}
    renderRecent();
  }

  function chips(target, terms) {
    target.innerHTML = '';
    terms.forEach(function (term) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'chip';
      button.textContent = term;
      button.addEventListener('click', function () { saveRecent(term); closePanel(); setQuery(term); results.scrollIntoView({behavior: 'smooth', block: 'start'}); });
      target.appendChild(button);
    });
  }

  function renderRecent() { recentGroup.hidden = !recent.length; chips(recentChips, recent); }
  function openPanel() { panel.hidden = false; field.classList.add('is-open'); input.setAttribute('aria-expanded', 'true'); }
  function closePanel() { panel.hidden = true; field.classList.remove('is-open'); input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); activeIndex = -1; }
  function activate(index) {
    var buttons = $$('.panel__option', suggList);
    if (!buttons.length) return;
    activeIndex = (index + buttons.length) % buttons.length;
    buttons.forEach(function (button, i) { button.classList.toggle('is-active', i === activeIndex); button.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false'); });
    input.setAttribute('aria-activedescendant', buttons[activeIndex].id);
  }

  input.addEventListener('input', function () {
    state.q = input.value;
    state.page = 1;
    if (!state.q.trim() && state.sort === 'relevant') { state.sort = 'newest'; sortSelect.value = 'newest'; }
    clearBtn.hidden = !state.q;
    suggestions = [];
    suggGroup.hidden = true;
    openPanel();
    if (controller) controller.abort();
    if (timer) clearTimeout(timer);
    updateURL(true);
    timer = setTimeout(loadResults, 300);
  });
  input.addEventListener('focus', openPanel);
  input.addEventListener('keydown', function (event) {
    if (event.key === 'ArrowDown' && suggestions.length) { event.preventDefault(); activate(activeIndex + 1); }
    else if (event.key === 'ArrowUp' && suggestions.length) { event.preventDefault(); activate(activeIndex - 1); }
    else if (event.key === 'Enter' && activeIndex >= 0 && suggestions[activeIndex]) { event.preventDefault(); window.location.href = suggestions[activeIndex].url; }
    else if (event.key === 'Escape') { closePanel(); }
  });
  form.addEventListener('submit', function (event) { event.preventDefault(); state.q = input.value; state.page = 1; saveRecent(state.q); closePanel(); change(false); results.scrollIntoView({behavior: 'smooth', block: 'start'}); });
  clearBtn.addEventListener('click', function () { setQuery(''); input.focus(); });
  $('#recentClear').addEventListener('click', function () { recent = []; try { localStorage.removeItem(recentKey); } catch (error) {} renderRecent(); });
  viewAllBtn.addEventListener('click', function () { saveRecent(state.q); closePanel(); state.page = 1; change(false); results.scrollIntoView({behavior: 'smooth', block: 'start'}); });
  suggList.addEventListener('click', function (event) { var button = event.target.closest('.panel__option'); if (button && suggestions[+button.dataset.index]) window.location.href = suggestions[+button.dataset.index].url; });
  catGroup.addEventListener('click', function (event) { var button = event.target.closest('.cat'); if (!button) return; state.cat = button.dataset.cat; state.page = 1; closePanel(); change(false); });
  sortSelect.addEventListener('change', function () { state.sort = sortSelect.value; state.page = 1; change(false); });
  pager.addEventListener('click', function (event) { var button = event.target.closest('button[data-page]'); if (!button || button.disabled) return; state.page = +button.dataset.page; change(false); results.scrollIntoView({behavior: 'smooth', block: 'start'}); });
  function reset() { state.cat = ''; state.sort = 'newest'; state.page = 1; sortSelect.value = 'newest'; closePanel(); setQuery(''); }
  resetBtn.addEventListener('click', reset);
  $('#emptyReset').addEventListener('click', reset);
  retryBtn.addEventListener('click', loadResults);
  document.addEventListener('click', function (event) { if (!panel.contains(event.target) && !field.contains(event.target)) closePanel(); });
  document.addEventListener('keydown', function (event) {
    var tag = (event.target.tagName || '').toLowerCase();
    var typing = ['input', 'textarea', 'select'].indexOf(tag) >= 0 || event.target.isContentEditable;
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); input.focus(); input.select(); openPanel(); }
    else if (event.key === '/' && !typing) { event.preventDefault(); input.focus(); openPanel(); }
    else if (event.key === 'Escape') closePanel();
  });
  window.addEventListener('popstate', function () { readURL(); renderCategories(); loadResults(); });

  readURL();
  renderCategories();
  renderRecent();
  chips(popularChips, popular);
  chips(emptyChips, popular);
  loadResults();
  if (window.location.search === '?s=') setTimeout(function () { input.focus(); }, 100);
})();
