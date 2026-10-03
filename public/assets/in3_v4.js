/* =====================================================================
   IN³ · INcubator v4 — camada nova de interação
   1) barra de idiomas + tradução por IA em tempo real (todo o conteúdo)
   2) seletor imersivo de projetos (sem <select>)
   3) dropdown imersivo do prompt de sugestão
   4) Kanban com arrastar-e-soltar
   Nada aqui é essencial: sem JS o site continua legível e completo.
   ===================================================================== */
(function () {
  'use strict';

  var IDIOMAS = [
    { cod: 'pt-BR', emoji: '🇧🇷', nome: 'Português' },
    { cod: 'en',    emoji: '🇺🇸', nome: 'English' },
    { cod: 'es',    emoji: '🇪🇸', nome: 'Español' },
    { cod: 'gn',    emoji: '🇵🇾', nome: "Avañe'ẽ" },
    { cod: 'zh',    emoji: '🇨🇳', nome: '中文' }
  ];
  var LANG_ATUAL = document.documentElement.getAttribute('lang') || 'pt-BR';
  var ORIGINAIS = new WeakMap();   // nó → texto original (pt-BR)
  var META_CSRF = document.querySelector('meta[name="in3-csrf"]');
  var CSRF = META_CSRF ? META_CSRF.getAttribute('content') : '';

  /* --------------------------------------------------------------- util */
  function post(url, dados) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
      credentials: 'same-origin',
      body: JSON.stringify(dados || {})
    }).then(function (r) { return r.json(); });
  }

  /* --------------------------------------- 1) barra de idiomas + tradução */
  function montarBarra() {
    if (document.querySelector('.idiomas')) { return; }
    var alvo = document.querySelector('.topo') || document.querySelector('.pe') || document.body;
    var barra = document.createElement('div');
    barra.className = 'idiomas';
    barra.setAttribute('role', 'group');
    barra.setAttribute('aria-label', 'Idioma / Language');
    IDIOMAS.forEach(function (l) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bandeira' + (l.cod === LANG_ATUAL ? ' on' : '');
      b.setAttribute('data-lang', l.cod);
      b.setAttribute('aria-pressed', l.cod === LANG_ATUAL ? 'true' : 'false');
      b.title = l.nome;
      b.innerHTML = '<span class="bz" aria-hidden="true">' + l.emoji + '</span><span class="bt">' + l.nome + '</span>';
      b.addEventListener('click', function () { trocarIdioma(l.cod, barra); });
      barra.appendChild(b);
    });
    alvo.appendChild(barra);
  }

  function veu(ligar) {
    var v = document.getElementById('in3-veu');
    if (!v) {
      v = document.createElement('div');
      v.id = 'in3-veu';
      v.className = 'traducao-veu';
      v.innerHTML = '<div>' + (LANG_ATUAL === 'zh' ? '正在翻译…' : 'Traduzindo…') + '</div>';
      document.body.appendChild(v);
    }
    v.classList.toggle('on', !!ligar);
  }

  function nosDeTexto() {
    var fora = { SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, CODE: 1, PRE: 1, TEXTAREA: 1, INPUT: 1, CANVAS: 1, SVG: 1, OPTION: 1 };
    var out = [];
    var w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (n) {
        if (!n.nodeValue || !n.nodeValue.trim()) { return NodeFilter.FILTER_REJECT; }
        var p = n.parentNode;
        if (!p || fora[p.nodeName]) { return NodeFilter.FILTER_REJECT; }
        if (p.closest && p.closest('.idiomas')) { return NodeFilter.FILTER_REJECT; }
        return NodeFilter.FILTER_ACCEPT;
      }
    });
    while (w.nextNode()) { out.push(w.currentNode); }
    return out;
  }

  /** Texto voltado ao leitor: o conteúdo do portfólio é texto puro em <p>, <h*>, <li>. */
  function trocarIdioma(cod, barra) {
    if (cod === LANG_ATUAL) { return; }
    var antigo = LANG_ATUAL;
    LANG_ATUAL = cod;
    document.documentElement.setAttribute('lang', cod);
    if (barra) {
      barra.querySelectorAll('.bandeira').forEach(function (b) {
        var on = b.getAttribute('data-lang') === cod;
        b.classList.toggle('on', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }
    veu(true);

    var nos = nosDeTexto();
    var pedido = [];
    var vistos = {};
    nos.forEach(function (n) {
      if (!ORIGINAIS.has(n)) { ORIGINAIS.set(n, n.nodeValue); }
      var t = ORIGINAIS.get(n).trim();
      if (!t || t.length > 2000) { return; }
      if (!vistos[t]) { vistos[t] = 1; pedido.push(t); }
    });
    if (!pedido.length) { veu(false); return; }

    post('/api/v1/i18n/traducao', { destino: cod, textos: pedido }).then(function (r) {
      var mapa = (r && r.mapa) || {};
      nos.forEach(function (n) {
        var orig = ORIGINAIS.get(n);
        if (orig === undefined) { return; }
        var chave = orig.trim();
        var trad = mapa[chave];
        if (typeof trad !== 'string' || trad === '') { trad = chave; }
        var esp = orig.match(/^\s*/)[0] + trad + orig.match(/\s*$/)[0];
        n.nodeValue = esp;
      });
      document.dispatchEvent(new CustomEvent('in3:traduzido', { detail: { idioma: cod, fonte: r.fonte, n: pedido.length } }));
    }).catch(function () {
      document.documentElement.setAttribute('lang', antigo);
      LANG_ATUAL = antigo;
    }).then(function () { veu(false); });
  }

  /* --------------------------------------- 2) seletor imersivo de projetos */
  function montarSeletor() {
    var grade = document.getElementById('seletor-grade');
    if (!grade) { return; }
    var filtro = document.getElementById('seletor-filtro');
    var ops = Array.prototype.slice.call(grade.querySelectorAll('.cubo-op'));

    function aplicar() {
      var q = filtro ? filtro.value.trim().toLowerCase() : '';
      var n = 0;
      ops.forEach(function (a) {
        var ok = !q || (a.getAttribute('data-nome') || '').indexOf(q) !== -1;
        a.style.display = ok ? '' : 'none';
        if (ok) { n++; }
      });
      var vazio = grade.querySelector('.seletor-vazio');
      if (!n && !vazio) {
        vazio = document.createElement('p');
        vazio.className = 'seletor-vazio';
        vazio.textContent = 'nenhum projeto corresponde ao filtro';
        grade.appendChild(vazio);
      } else if (n && vazio) { vazio.remove(); }
    }
    if (filtro) { filtro.addEventListener('input', aplicar); }

    var atual = -1;
    function focar(i) {
      var vis = ops.filter(function (a) { return a.style.display !== 'none'; });
      if (!vis.length) { return; }
      atual = (i + vis.length) % vis.length;
      ops.forEach(function (a) { a.setAttribute('aria-selected', 'false'); });
      vis[atual].setAttribute('aria-selected', 'true');
      vis[atual].focus();
    }
    grade.addEventListener('keydown', function (ev) {
      var passo = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[ev.key];
      if (passo) { ev.preventDefault(); focar(atual + passo); }
    });
    grade.querySelectorAll('.cubo-op').forEach(function (a) {
      a.addEventListener('mouseenter', function () {
        a.setAttribute('aria-selected', 'true');
      });
      a.addEventListener('mouseleave', function () {
        a.setAttribute('aria-selected', 'false');
      });
    });
    aplicar();
  }

  /* ------------------------------- 3) dropdown imersivo da sugestão */
  function montarDropdown() {
    var dd = document.getElementById('sug-dropdown');
    if (!dd) { return; }
    var botao = document.getElementById('sug-botao');
    var painel = document.getElementById('sug-painel');
    var lista = document.getElementById('sug-lista');
    var busca = document.getElementById('sug-busca');
    var nome = document.getElementById('sug-nome');
    var escudo = document.getElementById('sug-escudo');
    var campo = document.getElementById('sug-projeto');
    if (!botao || !painel || !lista) { return; }

    function abrir(on) {
      painel.hidden = !on;
      botao.setAttribute('aria-expanded', on ? 'true' : 'false');
      if (on) { if (busca) { busca.value = ''; filtrar(''); busca.focus(); } }
    }
    function filtrar(q) {
      q = (q || '').toLowerCase();
      lista.querySelectorAll('.dd-item').forEach(function (it) {
        var ok = !q || (it.getAttribute('data-busca') || '').indexOf(q) !== -1;
        it.style.display = ok ? '' : 'none';
      });
    }
    function escolher(it) {
      var id = it.getAttribute('data-id');
      if (campo) { campo.value = id; }
      if (nome) { nome.textContent = it.getAttribute('data-nome'); }
      if (escudo) { escudo.innerHTML = it.getAttribute('data-icone') || ''; }
      lista.querySelectorAll('.dd-item').forEach(function (o) { o.setAttribute('aria-selected', 'false'); });
      it.setAttribute('aria-selected', 'true');
      abrir(false);
    }

    botao.addEventListener('click', function () { abrir(painel.hidden); });
    document.addEventListener('click', function (ev) {
      if (!dd.contains(ev.target)) { abrir(false); }
    });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { abrir(false); } });
    if (busca) { busca.addEventListener('input', function () { filtrar(busca.value); }); }
    lista.addEventListener('click', function (ev) {
      var it = ev.target.closest ? ev.target.closest('.dd-item') : null;
      if (it) { escolher(it); }
    });
    lista.addEventListener('keydown', function (ev) {
      var itens = Array.prototype.slice.call(lista.querySelectorAll('.dd-item'))
        .filter(function (i) { return i.style.display !== 'none'; });
      var i = itens.indexOf(document.activeElement);
      if (ev.key === 'ArrowDown') { ev.preventDefault(); (itens[i + 1] || itens[0]).focus(); }
      if (ev.key === 'ArrowUp') { ev.preventDefault(); (itens[i - 1] || itens[itens.length - 1]).focus(); }
      if (ev.key === 'Enter') { ev.preventDefault(); if (document.activeElement.classList.contains('dd-item')) { escolher(document.activeElement); } }
    });
    var jaSel = lista.querySelector('.dd-item[aria-selected="true"]');
    if (jaSel) {
      if (nome) { nome.textContent = jaSel.getAttribute('data-nome'); }
      if (escudo) { escudo.innerHTML = jaSel.getAttribute('data-icone') || ''; }
    }
  }

  /* ------------------------------------------- 4) Kanban arrastar-e-soltar */
  function montarKanban() {
    var quadro = document.getElementById('in3-kanban');
    if (!quadro) { return; }
    var arrastado = null;
    quadro.querySelectorAll('.kb-cartao').forEach(function (c) {
      c.addEventListener('dragstart', function () {
        arrastado = c;
        c.classList.add('arrastando');
      });
      c.addEventListener('dragend', function () {
        c.classList.remove('arrastando');
        quadro.querySelectorAll('.kb-col').forEach(function (col) { col.classList.remove('por-cima'); });
        arrastado = null;
      });
    });
    quadro.querySelectorAll('.kb-col').forEach(function (col) {
      var corpo = col.querySelector('.kb-corpo');
      col.addEventListener('dragover', function (ev) {
        if (!arrastado) { return; }
        ev.preventDefault();
        col.classList.add('por-cima');
        if (corpo && corpo !== arrastado.parentNode) { corpo.appendChild(arrastado); }
      });
      col.addEventListener('dragleave', function () { col.classList.remove('por-cima'); });
      col.addEventListener('drop', function (ev) {
        ev.preventDefault();
        col.classList.remove('por-cima');
        if (!arrastado) { return; }
        var id = arrastado.getAttribute('data-id');
        var destino = col.getAttribute('data-col');
        post('/api/v1/painel/kanban', { id: id, status: destino, csrf: CSRF })
          .then(function (r) { if (!r || !r.ok) { location.reload(); } })
          .catch(function () { location.reload(); });
      });
    });
  }

  /* ------------------------------------------------------------- arranque */
  function iniciar() {
    montarBarra();
    montarSeletor();
    montarDropdown();
    montarKanban();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else { iniciar(); }

  window.IN3 = { trocarIdioma: trocarIdioma, idioma: function () { return LANG_ATUAL; } };
}());
