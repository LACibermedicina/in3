/* =====================================================================
   IN³ · site público — filtros e enriquecimento progressivo.
   Sem dependências. Nada aqui é obrigatório para ler o conteúdo.
   ===================================================================== */
(function () {
  'use strict';

  // filtro por área (progressivo: sem JS a lista completa continua visível)
  var grade = document.getElementById('grade-projetos');
  var filtros = document.querySelectorAll('.chip-f[data-filtro]');
  if (grade && filtros.length) {
    filtros.forEach(function (b) {
      b.addEventListener('click', function () {
        var alvo = b.getAttribute('data-filtro');
        filtros.forEach(function (o) { o.classList.toggle('on', o === b); });
        grade.querySelectorAll('.cartao-proj').forEach(function (c) {
          var bate = (alvo === 'todas') || (c.getAttribute('data-cat') === alvo);
          c.hidden = !bate;
        });
      });
    });
  }

  // revelação suave dos blocos (respeita movimento reduzido)
  var reduz = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduz && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (en) {
        if (en.isIntersecting) {
          en.target.classList.add('revela');
          io.unobserve(en.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px' });
    document.querySelectorAll('.cartao-proj, .bloco, .faixa-niveis').forEach(function (el) { io.observe(el); });
  }

  // contagem de repositórios/tecnologias para leitura por leitor de tela
  var cena = document.getElementById('cena-voxel');
  if (cena && window.IN3_CENA) {
    cena.setAttribute('aria-label', cena.getAttribute('aria-label') +
      ' A cena representa ' + window.IN3_CENA.total + ' projeto(s).');
  }
}());
