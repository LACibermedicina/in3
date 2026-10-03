<?php
declare(strict_types=1);
/**
 * =====================================================================
 * IN³ · biblioteca de semeadura
 * Constrói o acervo (repositórios + projetos + 4 camadas por projeto).
 *
 * Regra de honestidade de dados: nada é inventado. O que não existe no
 * documento de entrada ou no inventário do GitHub entra como "(pendente)".
 * =====================================================================
 */

/** Repositórios conhecidos da conta institucional (documento de entrada). */
function repos_de_entrada(): array
{
    return [
        'shop' => ['Comércio & Vitrine', 'Vitrine de produtos da incubadora.', ''],
        'shop.m3d' => ['Comércio & Vitrine', 'Loja institucional da m3d.pro.', ''],
        'catalogo' => ['Comércio & Vitrine', 'Catálogo de itens e serviços.', ''],
        'sersocial' => ['Comércio & Vitrine', 'Camada social do ecossistema.', ''],
        'show.m3d.pro' => ['Comércio & Vitrine', 'Apresentação pública do estúdio.', ''],
        'LAMD' => ['Educação Médica', 'Laudo médico digital / apoio à decisão.', ''],
        'revalida.m3d' => ['Educação Médica', 'Preparação para a prova de revalidação.', ''],
        'Emissor-de-Certificacao-de-Cursos' => ['Educação Médica', 'Emissão de certificados de curso.', ''],
        'M3D-contoscontas' => ['Fiscal & Finanças', 'Contabilidade e apuração fiscal.', ''],
        'contosecontasIRF' => ['Fiscal & Finanças', 'Rotina de imposto de renda.', ''],
        'Contos_Contas' => ['Fiscal & Finanças', 'Contabilidade de pequeno porte.', ''],
        'Contos-Contas' => ['Fiscal & Finanças', 'Variação do motor contábil.', ''],
        'ContoContas-Fiscal' => ['Fiscal & Finanças', 'Núcleo fiscal do motor contábil.', ''],
        'ContoContas' => ['Fiscal & Finanças', 'Motor contábil consolidado.', ''],
        'ContoConta' => ['Fiscal & Finanças', 'Protótipo do motor contábil.', ''],
        'Fiscal-Account' => ['Fiscal & Finanças', 'Módulo de contas fiscais.', ''],
        'tele.M3D.pro' => ['Telemedicina & Saúde Digital', 'Teleconsulta e prontuário.', ''],
        'tele.M3D.pro.final' => ['Telemedicina & Saúde Digital', 'Versão final da plataforma de telemedicina.', ''],
        'zipTELE' => ['Telemedicina & Saúde Digital', 'Empacotamento da solução de telemedicina.', ''],
        'CypherMED-Telemed' => ['Telemedicina & Saúde Digital', 'Camada de dados clínicos.', ''],
        'saudeconectada' => ['Telemedicina & Saúde Digital', 'Conectividade em saúde.', ''],
        'WifiSpaceMapper' => ['Instrumentação & Sensores', 'Mapeamento de espaços por sinal wi-fi.', ''],
        'surgeyTube' => ['Instrumentação & Sensores', 'Vídeo cirúrgico — mantido interno.', ''],
        'moondream-img-' => ['Pesquisa & IA', 'Experimentos de visão computacional.', ''],
        'janAI' => ['Pesquisa & IA', 'Assistente de pesquisa aberta.', ''],
        'amc' => ['Pesquisa & IA', 'Pesquisa aplicada em medicina.', ''],
        'LACibermedicina' => ['Governança & Infra', 'Perfil institucional do laboratório.', ''],
        'in3' => ['Governança & Infra', 'Este próprio sistema de portfólio.', ''],
    ];
}

/**
 * Fichas de curadoria dos projetos. Só o que consta no documento de
 * entrada é afirmado; o resto vem marcado como (pendente).
 */
function fichas_de_curadoria(): array
{
    $pub = static function (int $nivel, string $status, string $resumo, array $repos, string $emoji, string $cat, string $cor): array {
        return compact('nivel', 'status', 'resumo', 'repos', 'emoji', 'cat', 'cor') + ['publico' => 1];
    };
    return [
        'tele-m3d' => $pub(3, 'ativo', 'Plataforma de teleconsulta que conecta atendimento remoto, prontuário e acompanhamento clínico.', ['tele.M3D.pro.final', 'tele.M3D.pro', 'zipTELE', 'CypherMED-Telemed', 'saudeconectada'], '🩺', 'Telemedicina & Saúde Digital', '#2A9581') + ['titulo' => 'Tele.M3D'],
        'contos-contas' => $pub(3, 'ativo', 'Motor contábil e fiscal: apuração, contas a pagar e receber e obrigações do dia a dia.', ['M3D-contoscontas', 'contosecontasIRF', 'Fiscal-Account', 'Contos_Contas', 'ContoContas-Fiscal'], '🧾', 'Fiscal & Finanças', '#3AA3BA') + ['titulo' => 'Contos & Contas'],
        'loja-catalogo' => $pub(3, 'ativo', 'Vitrine e catálogo de produtos com montagem de loja e exposição de acervo.', ['shop.m3d', 'catalogo', 'shop'], '🛍️', 'Comércio & Vitrine', '#59C1A5') + ['titulo' => 'Loja & Catálogo'],
        'revalida-m3d' => $pub(3, 'em incubação', 'Preparação guiada para provas médicas de revalidação, com trilhas de estudo e revisão.', ['revalida.m3d'], '🎓', 'Educação Médica', '#7B6CF6') + ['titulo' => 'Revalida.M3D'],
        'wifi-space-mapper' => $pub(3, 'protótipo', 'Mapeia espaços reais a partir do sinal de rede, gerando planta de cobertura utilizável.', ['WifiSpaceMapper'], '📶', 'Instrumentação & Sensores', '#F2994A') + ['titulo' => 'WifiSpaceMapper'],
        'lamd' => $pub(3, 'entregue', 'Apoio digital ao laudo médico: coleta estruturada e conferência do resultado.', ['LAMD'], '🏃', 'Educação Médica', '#EB5757') + ['titulo' => 'LAMD'],
        'show-m3d' => $pub(3, 'em incubação', 'Página de apresentação do estúdio: o que a incubadora faz, em linguagem direta.', ['show.m3d.pro'], '✨', 'Comércio & Vitrine', '#F2C94C') + ['titulo' => 'Show.M3D.pro'],
        'sersocial' => $pub(3, 'protótipo', 'Camada social do ecossistema: relacionamento, comunidade e engajamento.', ['sersocial'], '🌐', 'Comércio & Vitrine', '#56CCF2') + ['titulo' => 'SerSocial'],
        'pesquisa-ia' => $pub(3, 'pesquisa', 'Frente de pesquisa aberta em visão computacional e assistência por IA.', ['moondream-img-', 'janAI', 'amc'], '🧠', 'Pesquisa & IA', '#9B51E0') + ['titulo' => 'Pesquisa aberta'],
        'perfil-organizacao' => $pub(3, 'infra', 'Perfil institucional do laboratório: identidade, acervo e canais de contato.', ['LACibermedicina'], '🏛️', 'Governança & Infra', '#6FCF97') + ['titulo' => 'Perfil institucional'],
        'surgerytube' => ['titulo' => 'SurgeryTube', 'nivel' => 1, 'status' => 'backlog', 'publico' => 0,
            'resumo' => 'Acervo de vídeo cirúrgico. Mantido interno por conter imagem de paciente.',
            'repos' => ['surgeyTube'], 'emoji' => '🎥', 'cat' => 'Instrumentação & Sensores', 'cor' => '#828282'],
        'emissor-certificados' => ['titulo' => 'Emissor de Certificados', 'nivel' => 1, 'status' => 'arquivado', 'publico' => 0,
            'resumo' => 'Emissão e validação de certificados de curso. Mantido interno na curadoria.',
            'repos' => ['Emissor-de-Certificacao-de-Cursos'], 'emoji' => '📜', 'cat' => 'Educação Médica', 'cor' => '#BDBDBD'],
        'contos-contas-legado' => ['titulo' => 'Contos-Contas', 'nivel' => 1, 'status' => 'backlog', 'publico' => 0,
            'resumo' => 'Variação legacy do motor contábil, aguardando consolidação.',
            'repos' => ['Contos-Contas'], 'emoji' => '🧊', 'cat' => 'Fiscal & Finanças', 'cor' => '#A7A7A7'],
        'contocontas-legado' => ['titulo' => 'ContoContas', 'nivel' => 1, 'status' => 'backlog', 'publico' => 0,
            'resumo' => 'Núcleo contábil em consolidação, ainda sem curadoria final.',
            'repos' => ['ContoContas'], 'emoji' => '🧊', 'cat' => 'Fiscal & Finanças', 'cor' => '#A7A7A7'],
        'contoconta-legado' => ['titulo' => 'ContoConta', 'nivel' => 1, 'status' => 'backlog', 'publico' => 0,
            'resumo' => 'Protótipo do motor contábil, aguardando avaliação de curadoria.',
            'repos' => ['ContoConta'], 'emoji' => '🧊', 'cat' => 'Fiscal & Finanças', 'cor' => '#A7A7A7'],
        'in3' => ['titulo' => 'in3', 'nivel' => 1, 'status' => 'backlog', 'publico' => 0,
            'resumo' => 'O próprio sistema de portfólio da incubadora.',
            'repos' => ['in3'], 'emoji' => '🧊', 'cat' => 'Governança & Infra', 'cor' => '#A7A7A7'],
    ];
}

/** Texto das quatro camadas. A camada 1 é escrita em linguagem humana. */
function camadas_modelo(array $f, string $titulo, array $tec): array
{
    $lista_tec = $tec ? implode(', ', array_unique($tec)) : '(pendente de inventário)';
    $repos = implode(', ', $f['repos']);
    $pend = '(pendente de curadoria)';
    return [
        1 => [
            'titulo' => 'O que este projeto é',
            'corpo' => $titulo . ' — ' . $f['resumo'] . "\n\n"
                . "Situação hoje: " . $f['status'] . ". Pertence à frente " . $f['cat'] . " da incubadora m3d.pro.\n\n"
                . "Este nível mostra o rumo e o propósito do projeto. Não há detalhe técnico aqui: para isso existe a camada 3, liberada sob pedido.",
        ],
        2 => [
            'titulo' => 'Contexto institucional',
            'corpo' => "Problema que o projeto ataca: " . $pend . "\n\n"
                . "Solução proposta: " . $f['resumo'] . "\n\n"
                . "Público-alvo: " . $pend . "\n\n"
                . "Onde vive no acervo: repositórios " . $repos . ".",
        ],
        3 => [
            'titulo' => 'Arquitetura e integrações',
            'corpo' => "Tecnologias detectadas no inventário: " . $lista_tec . ".\n\n"
                . "Arquitetura detalhada: " . $pend . "\n\n"
                . "Integrações e dados tratados: " . $pend . "\n\n"
                . "Critérios de validação: " . $pend . ".",
        ],
        4 => [
            'titulo' => 'Playbook completo',
            'corpo' => "Ficha integral do projeto, incluindo riscos conhecidos, tratamento de dados pessoais e LGPD, "
                . "critérios de validação e notas internas de curadoria.\n\n"
                . "Riscos: " . $pend . "\n\nPrivacidade e LGPD: " . $pend . "\n\nNotas internas: " . $pend . ".",
        ],
    ];
}

/** Popula repositórios, projetos, vínculos e camadas. Idempotente. */
function semear_acervo(): array
{
    $repos = repos_de_entrada();

    // inventário real (cache do GitHub), quando existir
    $cache = IN3_RAIZ . '/data/semear/github.repos.cache.json';
    if (is_file($cache)) {
        $d = json_decode((string) file_get_contents($cache), true) ?: [];
        foreach ($d as $r) {
            $nome = (string) ($r['name'] ?? '');
            if ($nome === '') {
                continue;
            }
            $entrada = $repos[$nome] ?? ['Governança & Infra', '', ''];
            executar('INSERT INTO repos (nome, visibilidade, privado, linguagem, descricao, gh_atualizado_em, commits)
                      VALUES (:n,:v,:p,:l,:d,:q,:c)
                      ON CONFLICT (nome) DO UPDATE SET visibilidade = excluded.visibilidade,
                        privado = excluded.privado, linguagem = excluded.linguagem, descricao = excluded.descricao,
                        gh_atualizado_em = excluded.gh_atualizado_em, commits = excluded.commits', [
                ':n' => $nome,
                ':v' => !empty($r['private']) ? 'privado' : 'publico',
                ':p' => !empty($r['private']) ? 1 : 0,
                ':l' => (string) ($r['language'] ?? ''),
                ':d' => (string) ($r['description'] ?? ($entrada[1] ?? '')),
                ':q' => (string) ($r['updated_at'] ?? ''),
                ':c' => 0,
            ]);
        }
    }

    // qualquer repositório do documento de entrada que ainda não exista
    foreach ($repos as $nome => $meta) {
        if (!um('SELECT id FROM repos WHERE nome = :n', [':n' => $nome])) {
            executar('INSERT INTO repos (nome, visibilidade, privado, linguagem, descricao) VALUES (:n,:v,0,\'\',:d)', [
                ':n' => $nome, ':v' => 'pendente', ':d' => (string) ($meta[1] ?? ''),
            ]);
        }
    }

    $fichas = fichas_de_curadoria();
    $nProj = 0;
    $ordem = 10;
    foreach ($fichas as $slug => $f) {
        $id = um('SELECT id FROM projetos WHERE slug = :s', [':s' => $slug])['id'] ?? null;
        $par = [
            ':s' => $slug, ':t' => (string) $f['titulo'], ':e' => (string) $f['emoji'],
            ':i' => $slug, ':c' => (string) $f['cat'], ':st' => (string) $f['status'],
            ':r' => (string) $f['resumo'], ':co' => (string) $f['cor'], ':o' => $ordem,
            ':p' => (int) $f['publico'], ':n' => array_search((int) $f['nivel'], IN3_NIVEIS, true) ?: 'roadmap',
            ':q' => agora(), ':l' => 0,
        ];
        if ($id) {
            executar('UPDATE projetos SET titulo=:t, emoji=:e, categoria=:c, status=:st, resumo=:r,
                      cor=:co, ordem=:o, mostrar_ao_publico=:p, nivel_divulgacao=:n, atualizado_em=:q
                      WHERE id=' . (int) $id, $par);
        } else {
            executar('INSERT INTO projetos (slug,titulo,emoji,icone,categoria,status,resumo,cor,ordem,
                      mostrar_ao_publico,mostrar_link_repo,nivel_divulgacao,criado_em,atualizado_em)
                      VALUES (:s,:t,:e,:i,:c,:st,:r,:co,:o,:p,:l,:n,:q,:q)', $par);
            $id = (int) banco()->lastInsertId();
        }
        $id = (int) $id;
        $ordem += 10;
        $nProj++;

        // vínculos com repositórios
        $tec = [];
        foreach ((array) $f['repos'] as $nomeRepo) {
            $r = um('SELECT * FROM repos WHERE nome = :n', [':n' => $nomeRepo]);
            if (!$r) {
                continue;
            }
            executar('INSERT OR IGNORE INTO projeto_repos (projeto_id, repo_id) VALUES (:p,:r)',
                [':p' => $id, ':r' => (int) $r['id']]);
            if (!empty($r['linguagem'])) {
                $tec[] = (string) $r['linguagem'];
            }
        }

        // camadas
        foreach (camadas_modelo($f, (string) $f['titulo'], $tec) as $prof => $c) {
            executar('INSERT INTO camadas (projeto_id, profundidade, titulo, corpo, atualizado_em)
                      VALUES (:p,:n,:t,:c,:q)
                      ON CONFLICT (projeto_id, profundidade) DO UPDATE SET titulo = excluded.titulo,
                        corpo = excluded.corpo, atualizado_em = excluded.atualizado_em', [
                ':p' => $id, ':n' => $prof, ':t' => $c['titulo'], ':c' => $c['corpo'], ':q' => agora(),
            ]);
        }
    }

    $pub = (int) escalar('SELECT count(*) FROM projetos WHERE mostrar_ao_publico = 1');
    $tot = (int) escalar('SELECT count(*) FROM projetos');
    gravar_config('semeado_em', agora());

    return ['projetos' => $nProj, 'repos' => (int) escalar('SELECT count(*) FROM repos'),
            'publicos' => $pub, 'internos' => $tot - $pub];
}
