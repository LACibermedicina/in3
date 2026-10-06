/* IN³ · carrossel interativo de projetos.
   Fluido (scroll-snap + inércia), acessível (teclado, pausa, aria) e leve.
   Nenhuma requisição de rede: trabalha só com o HTML já liberado pelo servidor. */
(function () {
  'use strict';
  var raiz = document.querySelector('[data-carrossel]');
  if (!raiz) return;

  var trilho = raiz.querySelector('[data-carrossel-trilho]');
  var itens  = Array.prototype.slice.call(raiz.querySelectorAll('[data-carrossel-item]'));
  var pontos = raiz.querySelector('[data-carrossel-pontos]');
  var barra  = raiz.querySelector('[data-carrossel-progresso]');
  var bAnt   = raiz.querySelector('[data-carrossel-ant]');
  var bProx  = raiz.querySelector('[data-carrossel-prox]');
  var bPausa = raiz.querySelector('[data-carrossel-pausa]');
  if (!trilho || !itens.length) return;

  var reduz = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var atual = 0, timer = null, rodando = !reduz;
  var passo = function () { return itens[0].getBoundingClientRect().width + 18; };

  /* pontos de navegação */
  itens.forEach(function (_, i) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'carrossel-ponto';
    b.setAttribute('role', 'tab');
    b.setAttribute('aria-label', 'Cartão ' + (i + 1));
    b.addEventListener('click', function () { ir(i, true); });
    pontos.appendChild(b);
  });
  var bolinhas = Array.prototype.slice.call(pontos.children);

  function pintar() {
    bolinhas.forEach(function (b, i) { b.classList.toggle('on', i === atual); b.setAttribute('aria-selected', i === atual ? 'true' : 'false'); });
    itens.forEach(function (c, i) { c.classList.toggle('ativo', i === atual); });
    if (barra) barra.style.width = ((atual + 1) / itens.length * 100) + '%';
    itens.forEach(function (c) { c.setAttribute('aria-hidden', 'false'); });
  }

  function ir(i, parar) {
    atual = (i + itens.length) % itens.length;
    trilho.scrollTo({ left: atual * passo(), behavior: reduz ? 'auto' : 'smooth' });
    pintar();
    if (parar) pausar(true);
  }

  function pausar(manual) {
    rodando = false;
    if (timer) { clearInterval(timer); timer = null; }
    if (bPausa) { bPausa.setAttribute('aria-pressed', 'true'); bPausa.classList.add('on'); }
    if (manual) raiz.dataset.pausado = '1';
  }
  function tocar() {
    if (reduz || raiz.dataset.pausado === '1') return;
    rodando = true;
    if (bPausa) { bPausa.setAttribute('aria-pressed', 'false'); bPausa.classList.remove('on'); }
    if (timer) clearInterval(timer);
    timer = setInterval(function () { ir(atual + 1); }, 5200);
  }

  if (bAnt)  bAnt.addEventListener('click', function () { ir(atual - 1, true); });
  if (bProx) bProx.addEventListener('click', function () { ir(atual + 1, true); });
  if (bPausa) bPausa.addEventListener('click', function () {
    if (timer) pausar(true); else { delete raiz.dataset.pausado; tocar(); }
  });

  /* teclado */
  trilho.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      e.preventDefault();
      ir(atual + (e.key === 'ArrowRight' ? 1 : -1), true);
    }
  });

  /* pausa ao interagir (leitura confortável) */
  ['mouseenter', 'focusin'].forEach(function (ev) { trilho.addEventListener(ev, function () { if (timer) pausar(false); }); });
  ['mouseleave', 'focusout'].forEach(function (ev) { trilho.addEventListener(ev, function () { if (rodando) tocar(); }); });
  trilho.addEventListener('touchstart', function () { if (timer) pausar(false); }, { passive: true });

  /* sincroniza os pontos com a rolagem real */
  var quadro = false;
  trilho.addEventListener('scroll', function () {
    if (quadro) return;
    quadro = true;
    requestAnimationFrame(function () {
      var i = Math.round(trilho.scrollLeft / passo());
      if (i !== atual && i >= 0 && i < itens.length) { atual = i; pintar(); }
      quadro = false;
    });
  }, { passive: true });

  pintar();
  tocar();
})();
