/* =====================================================================
   IN³ · cena voxel interativa (Three.js r128, servido localmente)
   Princípios: acessível por teclado, nunca essencial (há alternativa em
   texto e a lista de projetos ao lado), respeita prefers-reduced-motion,
   pausa quando fora da tela e não baixa nada de fora.
   ===================================================================== */
(function () {
  'use strict';

  var dados = {};
  try {
    var el = document.getElementById('in3-dados');
    if (el && el.textContent) { dados = JSON.parse(el.textContent) || {}; }
  } catch (err) { dados = {}; }

  var alvo = document.getElementById('cena-voxel');
  var canvas = document.getElementById('voxel-canvas');
  if (!alvo || !canvas) { return; }

  var reduz = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var semWebGL = false;
  try {
    var teste = document.createElement('canvas');
    semWebGL = !(teste.getContext('webgl') || teste.getContext('experimental-webgl'));
  } catch (e) { semWebGL = true; }

  if (reduz || semWebGL || typeof THREE === 'undefined') {
    // Modo estático: a cena some e o texto alternativo/lista assume o papel.
    alvo.setAttribute('data-estatico', '1');
    canvas.remove();
    var alt = document.getElementById('cena-alt');
    if (alt) {
      alt.style.position = 'static';
      alt.style.width = 'auto';
      alt.style.height = 'auto';
      alt.style.clip = 'auto';
      alt.textContent = 'Cena tridimensional desativada neste navegador. A lista de projetos abaixo traz o mesmo conteúdo.';
      alvo.appendChild(alt);
    }
    return;
  }

  var cena = new THREE.Scene();
  var camera = new THREE.PerspectiveCamera(45, 1, 0.1, 200);
  var renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

  var paleta = (dados.voxel && dados.voxel.paleta) || {};
  var cores = [
    new THREE.Color(paleta.teal || '#47B29F'),
    new THREE.Color(paleta.amarelo || '#FFD166'),
    new THREE.Color(paleta.azul || '#4CC9F0')
  ];

  // luz
  cena.add(new THREE.HemisphereLight('#DFFFF7', '#0F5C50', 0.85));
  var dir = new THREE.DirectionalLight('#FFFFFF', 0.85);
  dir.position.set(6, 10, 7);
  cena.add(dir);

  // piso em grade discreta
  var grade = new THREE.GridHelper(14, 14, 0x2C8D7C, 0x1C7A6B);
  grade.position.y = -1.6;
  cena.add(grade);

  var grupo = new THREE.Group();
  cena.add(grupo);

  // Um "cubo de projeto": três faces visíveis (público) e uma aresta técnica.
  var cubos = [];
  function caixa(x, y, z, s, cor, opac) {
    var geo = new THREE.BoxGeometry(s, s, s);
    var mat = new THREE.MeshLambertMaterial({ color: cor, transparent: opac < 1, opacity: opac });
    var m = new THREE.Mesh(geo, mat);
    m.position.set(x, y, z);
    m.userData.base = { x: x, y: y, z: z };
    return m;
  }

  var colunas = (dados.voxel && dados.voxel.colunas) || [];
  colunas.forEach(function (col, idx) {
    var g = new THREE.Group();
    var n = Math.max(1, col.altura || 1);
    for (var i = 0; i < n; i++) {
      var c = new THREE.Color(col.paleta[i % col.paleta.length]);
      var aresta = (i === n - 1);
      var b = caixa(0, (i - (n - 1) / 2) * 0.78, 0, 0.7, c, aresta ? 1 : 0.94);
      g.add(b);
      if (aresta) {
        var l = new THREE.LineSegments(
          new THREE.EdgesGeometry(b.geometry),
          new THREE.LineBasicMaterial({ color: cores[1].getHex() })
        );
        l.position.copy(b.position);
        g.add(l);
      }
    }
    g.position.set(col.pos ? col.pos.x * 2.4 : 0, 0, col.pos ? col.pos.z * 2.4 : 0);
    g.userData.slug = col.slug;
    g.userData.titulo = col.titulo;
    g.userData.emoji = col.emoji;
    grupo.add(g);
    cubos.push(g);
  });

  // animação de flutuação e estado
  var tempo = 0, girando = !reduz, explodir = 0, explodindo = false;
  var arrastando = false, ultimoX = 0, ultimoY = 0, alvoYaw = 0.5, yaw = 0.5, pitch = 0.42;
  var visivel = true, rodando = true;

  function ajusta() {
    var largura = alvo.clientWidth || 640;
    var altura = Math.max(320, Math.round(window.innerHeight * 0.46));
    canvas.style.height = altura + 'px';
    renderer.setSize(largura, altura, false);
    camera.aspect = largura / altura;
    camera.updateProjectionMatrix();
  }
  ajusta();
  window.addEventListener('resize', ajusta);

  function posicionaCamera() {
    var r = 11.5;
    camera.position.set(Math.sin(yaw) * r, 4.6 + Math.sin(pitch) * 5, Math.cos(yaw) * r);
    camera.lookAt(0, 0.2, 0);
  }

  function interage(alvoFn) {
    alvo.setAttribute('tabindex', '0');
    alvo.addEventListener('keydown', function (ev) {
      var passo = 0.12;
      if (ev.key === 'ArrowLeft') { alvoYaw -= passo; }
      else if (ev.key === 'ArrowRight') { alvoYaw += passo; }
      else if (ev.key === 'ArrowUp') { pitch = Math.max(-0.2, pitch - passo); }
      else if (ev.key === 'ArrowDown') { pitch = Math.min(1.1, pitch + passo); }
      else if (ev.key === 'Enter' || ev.key === ' ') { girando = !girando; }
      else { return; }
      ev.preventDefault();
      if (alvoFn) { alvoFn(); }
    });
  }
  interage(null);

  canvas.addEventListener('pointerdown', function (ev) {
    arrastando = true; ultimoX = ev.clientX; ultimoY = ev.clientY;
    if (canvas.setPointerCapture) { canvas.setPointerCapture(ev.pointerId); }
  });
  window.addEventListener('pointerup', function () { arrastando = false; });
  window.addEventListener('pointermove', function (ev) {
    if (!arrastando) { return; }
    alvoYaw += (ev.clientX - ultimoX) * 0.006;
    pitch = Math.max(-0.2, Math.min(1.1, pitch + (ev.clientY - ultimoY) * 0.004));
    ultimoX = ev.clientX; ultimoY = ev.clientY;
  });
  canvas.addEventListener('wheel', function (ev) {
    ev.preventDefault();
  }, { passive: false });
  canvas.addEventListener('click', function (ev) {
    var r = canvas.getBoundingClientRect();
    var p = new THREE.Vector2(((ev.clientX - r.left) / r.width) * 2 - 1, -((ev.clientY - r.top) / r.height) * 2 + 1);
    var rayon = new THREE.Raycaster();
    rayon.setFromCamera(p, camera);
    var achados = rayon.intersectObjects(grupo.children, true);
    if (achados.length) {
      var topo = achados[0].object;
      while (topo.parent && topo.parent !== grupo) { topo = topo.parent; }
      if (topo.userData && topo.userData.slug) {
        var alt = document.getElementById('cena-alt');
        if (alt) { alt.textContent = 'Projeto selecionado na cena: ' + topo.userData.titulo + '.'; }
        var cartao = document.querySelector('[data-slug="' + topo.userData.slug + '"]');
        if (!cartao) { cartao = document.querySelector('a[href$="/p/' + topo.userData.slug + '"]'); }
        if (cartao) { cartao.scrollIntoView({ block: 'center', behavior: reduz ? 'auto' : 'smooth' }); }
      }
    }
  });

  alvo.querySelectorAll('[data-cena]').forEach(function (b) {
    b.addEventListener('click', function () {
      var acao = b.getAttribute('data-cena');
      if (acao === 'girar') { girando = !girando; b.setAttribute('aria-pressed', girando ? 'true' : 'false'); }
      if (acao === 'explodir') { explodindo = !explodindo; b.setAttribute('aria-pressed', explodindo ? 'true' : 'false'); }
      if (acao === 'reset') { alvoYaw = 0.5; pitch = 0.42; girando = !reduz; explodindo = false; }
    });
  });

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (es) {
      visivel = es[0] ? es[0].isIntersecting : true;
      if (visivel && !rodando) { rodando = true; anima(); }
    }, { threshold: 0.05 }).observe(alvo);
  }
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !rodando) { rodando = true; anima(); }
  });

  var relogio = new THREE.Clock();
  function anima() {
    if (!rodando) { return; }
    requestAnimationFrame(anima);
    if (!visivel || document.hidden) { rodando = false; return; }

    var dt = Math.min(relogio.getDelta(), 0.05);
    tempo += dt;
    if (girando) { alvoYaw += dt * 0.16; }
    yaw += (alvoYaw - yaw) * 0.08;
    posicionaCamera();

    explodir += ((explodindo ? 1 : 0) - explodir) * 0.08;
    cubos.forEach(function (g, i) {
      g.rotation.y = Math.sin(tempo * 0.5 + i) * 0.06;
      g.position.y = Math.sin(tempo * 0.9 + i * 0.7) * 0.11 + explodir * 0.9 * (0.4 + (i % 3) * 0.3);
      g.children.forEach(function (ch) {
        if (!ch.userData || !ch.userData.base) { return; }
        ch.position.x = ch.userData.base.x + explodir * Math.sin(i + ch.userData.base.y) * 0.35;
        ch.position.z = ch.userData.base.z + explodir * Math.cos(i + ch.userData.base.y) * 0.35;
      });
    });
    grupo.rotation.y = Math.sin(tempo * 0.12) * 0.05;
    renderer.render(cena, camera);
  }
  anima();

  window.IN3_CENA = { total: cubos.length };
}());
