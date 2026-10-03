/* =====================================================================
   IN³ · painel — ajuda de formulário (prévia de ícone, proteção contra
   envio duplo, contagem de campos). Nunca manipula credenciais.
   ===================================================================== */
(function () {
  'use strict';

  var sel = document.getElementById('icone');
  var previa = document.getElementById('previa-ico');
  if (sel && previa) {
    sel.addEventListener('change', function () {
      var svg = previa.querySelector('svg');
      if (!svg) { return; }
      var desenho = svg.innerHTML;
      var cl = svg.cloneNode(false);
      cl.innerHTML = desenho;
      // A prévia usa exatamente o mesmo SVG local servido pelo componente.
      svg.replaceWith(cl);
      previa.setAttribute('data-icone', sel.value);
    });
  }

  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('button[type=submit]:not([formaction])');
      if (b) {
        setTimeout(function () { b.disabled = true; b.style.opacity = '.6'; }, 30);
        setTimeout(function () { b.disabled = false; b.style.opacity = ''; }, 4000);
      }
    });
  });

  // confirmação para ações destrutivas
  document.querySelectorAll('button[value="desativar"], button[value="arquivado"], button[value="recusado"]').forEach(function (b) {
    b.addEventListener('click', function (ev) {
      if (!window.confirm('Confirmar a ação "' + b.textContent.trim() + '"? Ela altera o estado do registro.')) {
        ev.preventDefault();
      }
    });
  });

  // marca o menu conforme a rota
  var p = window.location.pathname.replace(/\/+$/, '');
  document.querySelectorAll('.painel-nav-a').forEach(function (a) {
    var href = (a.getAttribute('href') || '').replace(/\/+$/, '');
    if (href && p.indexOf(href) === 0) { a.classList.add('on'); }
  });
}());
