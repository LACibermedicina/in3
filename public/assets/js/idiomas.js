/* IN³ · seletor de idiomas por bandeiras + tradução instantânea.
   Estratégia em duas velocidades:
     1) troca imediata do vocabulário de interface (dicionário embutido);
     2) POST /api/traduzir para os textos de conteúdo — chave de IA só no servidor,
        resposta em cache no SQLite. Se não houver chave, o texto original é mantido. */
(function () {
  'use strict';
  var caixa = document.querySelector('[data-idiomas]');
  if (!caixa) return;
  var abrir = caixa.querySelector('[data-idiomas-abrir]');
  var lista = caixa.querySelector('[data-idiomas-lista]');
  var atual = caixa.getAttribute('data-atual') || document.documentElement.lang || 'pt-BR';

  function fechar() { caixa.classList.remove('aberto'); if (abrir) abrir.setAttribute('aria-expanded', 'false'); }
  if (abrir) abrir.addEventListener('click', function (e) {
    e.stopPropagation();
    caixa.classList.toggle('aberto');
    abrir.setAttribute('aria-expanded', caixa.classList.contains('aberto') ? 'true' : 'false');
  });
  document.addEventListener('click', fechar);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });

  /* --- tradução de conteúdo sob demanda (sem recarregar a página) ------- */
  var cache = {};
  function chaveDoNo(el) {
    var p = el.getAttribute('data-i18n');
    return p || (el.tagName + '#' + (el.getAttribute('href') || '') + ':' + (el.textContent || '').slice(0, 40));
  }
  function alvos() {
    return Array.prototype.slice.call(document.querySelectorAll(
      '[data-i18n], .cc-resumo, .cc-titulo, .feed-corpo > p, .feed-corpo h3, .ini-slogan, .ini-hero h1, .noticia-resumo'
    )).slice(0, 40);
  }
  function aplicar(mapa) {
    alvos().forEach(function (el) {
      var k = chaveDoNo(el);
      if (mapa[k] && typeof mapa[k] === 'string') {
        if (el.dataset._orig === undefined) el.dataset._orig = el.textContent;
        el.textContent = mapa[k];
      }
    });
  }
  function traduzir(codigo) {
    if (codigo === 'pt-BR') {
      alvos().forEach(function (el) { if (el.dataset._orig !== undefined) el.textContent = el.dataset._orig; });
      return;
    }
    var textos = {};
    alvos().forEach(function (el) { textos[chaveDoNo(el)] = (el.dataset._orig !== undefined ? el.dataset._orig : el.textContent).trim(); });
    if (cache[codigo]) { aplicar(cache[codigo]); return; }
    fetch('/api/traduzir', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ idioma: codigo, textos: textos }),
      credentials: 'same-origin'
    }).then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.textos) return;
        cache[codigo] = j.textos;
        aplicar(j.textos);
        caixa.dataset.motor = j.motor || '';
      })
      .catch(function () { /* sem rede: o idioma da interface já foi trocado pelo servidor */ });
  }

  /* clique nas bandeiras: navega (o servidor troca o idioma e regrava a página) */
  Array.prototype.slice.call(caixa.querySelectorAll('[data-idioma]')).forEach(function (a) {
    a.addEventListener('mouseenter', function () { caixa.dataset.previa = a.getAttribute('data-idioma'); });
    a.addEventListener('focus',      function () { caixa.dataset.previa = a.getAttribute('data-idioma'); });
  });

  if (atual !== 'pt-BR') { setTimeout(function () { traduzir(atual); }, 60); }
  window.in3Traduzir = traduzir;
})();
