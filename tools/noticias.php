<?php
declare(strict_types=1);
/**
 * IN³ · semeia o feed de notícias.
 *
 *   php tools/noticias.php            # cria/atualiza notícias de exemplo
 *   php tools/noticias.php --limpar   # remove só as notícias semeadas
 *
 * Regra editorial: toda notícia nasce INTERNA (publicado=1, mostrar_ao_publico=0).
 * Nada vai ao ar sem decisão humana no painel. O detalhe técnico é sempre o
 * material original (mensagem de commit), preservado verbatim — nunca inventado.
 */
require_once dirname(__DIR__) . '/src/nucleo.php';

if (!banco_pronto()) {
    fwrite(STDERR, "Banco ausente. Rode: php tools/instalar.php\n");
    exit(1);
}
$limpar = in_array('--limpar', $argv, true);
if ($limpar) {
    $n = (int) escalar("SELECT count(*) FROM noticias WHERE fonte = 'github'");
    executa("DELETE FROM noticias WHERE fonte = 'github'");
    echo "Removidas {$n} notícias semeadas do GitHub.\n";
    exit(0);
}

$projetos = consulta('SELECT id, slug, titulo, emoji FROM projetos ORDER BY ordem');
$porRepo  = [];
foreach (consulta('SELECT projeto_id, repo_id FROM projeto_repos') as $l) {
    $porRepo[(int) $l['repo_id']] = (int) $l['projeto_id'];
}

$criadas = 0;
$linhas = consulta(
    "SELECT r.* FROM repos r
      WHERE r.gh_commit_msg != ''
      ORDER BY r.gh_commit_data DESC LIMIT 14"
);
foreach ($linhas as $r) {
    $h = humanizar_commit((string) $r['gh_commit_msg']);
    $projId = $porRepo[(int) $r['id']] ?? null;
    $emoji  = '🧊';
    $tituloProj = '';
    foreach ($projetos as $p) {
        if ((int) $p['id'] === (int) $projId) {
            $emoji = (string) $p['emoji'];
            $tituloProj = (string) $p['titulo'];
        }
    }
    $titulo = $h['frase'];
    $slug = slugificar('github-' . $r['nome'] . '-' . $h['tipo'] . '-' . substr((string) $r['gh_commit_data'], 0, 10));
    $existe = um('SELECT id FROM noticias WHERE slug = :s', [':s' => $slug]);
    $resumo = $tituloProj !== ''
        ? $h['legivel'] . ' — atualização no repositório ' . $r['nome'] . ', dentro do projeto ' . $tituloProj . '.'
        : $h['legivel'] . ' — atualização no repositório ' . $r['nome'] . '.';
    $corpo = 'A equipe publicou uma atualização no repositório ' . $r['nome']
        . (($r['linguagem'] ?? '') !== '' ? ' (' . $r['linguagem'] . ')' : '')
        . '. Traduzindo para linguagem humana: ' . mb_strtolower($h['frase'])
        . ' O histórico completo fica no repositório, com data e autoria preservadas.';
    $detalhe = "repositório: " . $r['nome_completo'] . "\nlinguagem: " . ($r['linguagem'] ?: '—')
        . "\núltimo commit: " . substr((string) $r['gh_commit_sha'], 0, 12)
        . "\ndata: " . ($r['gh_commit_data'] ?: '—')
        . "\nmensagem original: " . (string) $r['gh_commit_msg'];
    if ($existe) {
        noticia_salvar([
            'titulo' => $titulo, 'slug' => $slug, 'resumo' => $resumo, 'corpo' => $corpo,
            'detalhe_tecnico' => $detalhe, 'categoria' => 'Engenharia', 'projeto_id' => $projId,
            'repo' => (string) $r['nome'], 'commit_sha' => (string) $r['gh_commit_sha'],
            'fonte' => 'github', 'publicado' => 1, 'mostrar_ao_publico' => 0,
            'data' => substr((string) $r['gh_commit_data'], 0, 19) ?: agora(), 'ordem' => 100,
        ], (int) $existe['id']);
    } else {
        noticia_salvar([
            'titulo' => $titulo, 'slug' => $slug, 'resumo' => $resumo, 'corpo' => $corpo,
            'detalhe_tecnico' => $detalhe, 'categoria' => 'Engenharia', 'projeto_id' => $projId,
            'repo' => (string) $r['nome'], 'commit_sha' => (string) $r['gh_commit_sha'],
            'fonte' => 'github', 'publicado' => 1, 'mostrar_ao_publico' => 0,
            'data' => substr((string) $r['gh_commit_data'], 0, 19) ?: agora(), 'ordem' => 100,
        ]);
        $criadas++;
    }
}

/* notícias de curadoria — institucionais, estas sim publicadas e visíveis,
   porque não revelam nada além do que já está no site público. */
$curadoria = [
    ['A incubadora abre o portfólio vivo', 'Institucional',
     'O portfólio da IN³ passa a ser publicado com um nível de detalhamento por projeto.',
     "Cada projeto carrega um teto de profundidade definido na curadoria. O visitante vê sempre o menor valor entre esse teto e o seu próprio nível — e a busca aplica exatamente a mesma regra, no servidor.",
     "Regra aplicada no SQL: escopo <= menor(clareza do visitante, nível do projeto). Material acima disso é removido antes de sair do servidor, não escondido por CSS."],
    ['Ícones vetoriais para todo o acervo', 'Design',
     'Cada projeto ganhou um ícone SVG próprio, derivado do cubo da marca.',
     "O cubo isométrico da IN³ abre em glifos por área: saúde digital, fiscal, educação, sensores, comércio e pesquisa. São vetores, portanto mantêm a nitidez em qualquer tela.",
     "Ícones desenhados como SVG inline gerado por servidor (icone_svg), com viewBox por categoria e cor herdada da paleta da marca."],
    ['Cinco idiomas, com tradução sob controle', 'Produto',
     'Português, espanhol, inglês, chinês e Guarani, com tradução opcional por IA.',
     "A troca de idioma é instantânea. O vocabulário da interface vem de dicionários estáticos e o conteúdo é traduzido por um endpoint que mantém a chave de IA exclusivamente no servidor, com cache em banco.",
     "A chave de cache é o hash do texto de origem: só é possível encomendar a tradução de um texto que o visitante já recebeu, o que impede o cache de virar caminho de vazamento."],
];
foreach ($curadoria as $i => $c) {
    $slug = slugificar($c[0]);
    if (um('SELECT id FROM noticias WHERE slug = :s', [':s' => $slug])) {
        continue;
    }
    noticia_salvar([
        'titulo' => $c[0], 'slug' => $slug, 'resumo' => $c[2], 'corpo' => $c[3],
        'detalhe_tecnico' => $c[4], 'categoria' => $c[1], 'fonte' => 'curadoria',
        'publicado' => 1, 'mostrar_ao_publico' => 1, 'destaque' => $i === 0 ? 1 : 0,
        'data' => agora(), 'ordem' => $i,
    ]);
    $criadas++;
}
printf("Feed: %d notícia(s) nova(s) · %d no total · %d visíveis ao público\n",
    $criadas,
    (int) escalar('SELECT count(*) FROM noticias'),
    (int) escalar('SELECT count(*) FROM noticias WHERE publicado = 1 AND mostrar_ao_publico = 1'));
