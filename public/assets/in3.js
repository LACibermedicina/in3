/* =====================================================================
   IN³ · INcubator — interações do site público
   Nada aqui é essencial: sem JS o site continua legível e completo.
   1) ASCII art interativa  2) parallax/imersão  3) rótulos da cena voxel
   ===================================================================== */
(function () {
  'use strict';

  /* ------------------------------------------------ 1) ASCII interativo */
  var palco = document.getElementById('ascii-palco');
  var entrada = document.getElementById('ascii-entrada');
  var grade = [];

  var GLIFOS = ['#', '@', '%', '*', '+', '=', ':', '.', ' '];

  function altura(c, r) {
    // cubo projetado em isometria simples + ruído determinístico pelo texto
    var n = grade.letras || 0;
    var d = Math.abs(c - 7) + Math.abs(r - 4) * 2;
    var h = Math.max(0, 6 - d) + ((c * 3 + r * 5 + n) % 4 === 0 ? 1 : 0);
    return Math.min(9, h);
  }

  function desenhar() {
    var L = 22, A = 11, saida = [];
    for (var r = 0; r < A; r++) {
      var linha = '';
      for (var c = 0; c < L; c++) {
        var h = grade[r] && grade[r][c] ? grade[r][c] : 0;
        linha += h === 0 ? ' ' : GLIFOS[Math.min(GLIFOS.length - 1, 9 - h)];
      }
      saida.push(linha);
    }
    if (palco) { palco.textContent = saida.join('\n'); }
  }

  function montar(texto) {
    texto = (texto || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
    var L = 22, A = 11;
    grade = [];
    for (var r = 0; r < A; r++) { grade.push(new Array(L).fill(0)); grade.letras = texto.length; }
    // um cubo em bloco: base larga, topo mais estreito
    for (var rr = 0; rr < A; rr++) {
      for (var cc = 0; cc < L; cc++) {
        var dx = Math.abs(cc - (L - 1) / 2) / ((L - 1) / 2);
        var dy = Math.abs(rr - (A - 1) / 2) / ((A - 1) / 2);
        var dentro = (dx + dy * 1.15) <= 1.18;
        if (!dentro) { continue; }
        var alt = altura(cc, rr);
        grade[rr][cc] = Math.max(1, alt - Math.round((dx + dy) * 2));
      }
    }
    // assinatura do texto: marca colunas pelo código de cada letra
    for (var i = 0; i < texto.length; i++) {
      var code = texto.charCodeAt(i);
      var col = 2 + (i * 2) % (L - 4);
      var lin = 2 + (code % (A - 4));
      grade[lin][col] = 9;
      if (grade[lin][col + 1] !== undefined) { grade[lin][col + 1] = 6; }
    }
    desenhar();
  }

  if (palco) {
    montar('IN3');
    palco.addEventListener('click', function (ev) {
      var rect = palco.getBoundingClientRect();
      var ch = ev.clientX - rect.left, cv = ev.clientY - rect.top;
      var cw = rect.width / 22, chh = palco.scrollHeight / Math.max(1, palco.textContent.split('\n').length);
      var c = Math.floor(ch / (cw || 1));
      var r = Math.floor((cv + palco.scrollTop) / (chh || 1));
      if (grade[r] && grade[r][c] !== undefined) {
        grade[r][c] = grade[r][c] > 0 ? 0 : 9;
        desenhar();
      }
    });
    var ok = document.getElementById('ascii-ok');
    if (ok) { ok.addEventListener('click', function () { montar(entrada ? entrada.value : ''); }); }
    var limpar = document.getElementById('ascii-limpar');
    if (limpar) {
      limpar.addEventListener('click', function () {
        grade = []; for (var r = 0; r < 11; r++) { grade.push(new Array(22).fill(0)); }
        grade.letras = 0; desenhar();
      });
    }
    if (entrada) {
      entrada.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { montar(entrada.value); } });
    }
  }

  /* ------------------------------------------- 2) imersão por parallax */
  var cena = document.querySelector('.cena-mundo');
  var reduz = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (cena && !reduz) {
    var raf = null;
    window.addEventListener('scroll', function () {
      if (raf) { return; }
      raf = requestAnimationFrame(function () {
        var y = window.scrollY || 0;
        cena.style.setProperty('--par', Math.min(120, y * 0.08) + 'px');
        raf = null;
      });
    }, { passive: true });
  }

  /* ----------------------------------- 3) rótulos acessíveis da cena */
  var cenaEl = document.getElementById('cena-voxel');
  if (cenaEl) {
    var cubos = cenaEl.querySelectorAll('.voxel');
    cenaEl.setAttribute('aria-label', cenaEl.getAttribute('aria-label') || '');
    cubos.forEach(function (a, i) { a.setAttribute('tabindex', '0'); a.setAttribute('role', 'link'); });
    cenaEl.insertAdjacentHTML('afterend',
      '<p class="miudo centro">' + cubos.length + ' cubos = ' + cubos.length + ' projetos publicados. ' +
      'Cada cubo é um link: navegue também pelo teclado (Tab).</p>');
  }

  /* ------------------------- 4) link "ver detalhes institucionais" */
  document.querySelectorAll('a[href*="?camada="]').forEach(function (a) {
    a.addEventListener('click', function () {
      var alvo = document.querySelector(a.getAttribute('href').split('?')[0] ? '#camada-2' : null);
      if (alvo) { alvo.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    });
  });
}());
