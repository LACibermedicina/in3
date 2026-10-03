<?php
declare(strict_types=1);
/**
 * IN³ · transcrever.php — materializa a documentação base como itens do
 * acervo: lê os documentos (PLAYBOOK, ARQUITETURA, SEGURANCA, DEPLOY,
 * README) e grava, por projeto, as quatro camadas já redigidas.
 *
 * Regra de honestidade: só escreve o que está no documento. O que faltar
 * continua como "(pendente de curadoria)".
 *   php tools/transcrever.php [--seco]
 */
require_once dirname(__DIR__) . '/src/nucleo.php';

$seco = in_array('--seco', $argv, true);

$fontes = [
    'PLAYBOOK'    => IN3_RAIZ . '/PLAYBOOK.md',
    'ARQUITETURA' => IN3_RAIZ . '/ARQUITETURA.md',
    'SEGURANCA'   => IN3_RAIZ . '/SEGURANCA.md',
    'DEPLOY'      => IN3_RAIZ . '/DEPLOY.md',
    'README'      => IN3_RAIZ . '/README.md',
];

$texto = [];
foreach ($fontes as $nome => $arq) {
    $texto[$nome] = is_file($arq) ? (string) file_get_contents($arq) : '';
}

/** Extrai a seção de um projeto no PLAYBOOK (### N. Título — ... ). */
function ficha_playbook(string $md, string $titulo): string
{
    $linhas = preg_split('/\R/u', $md) ?: [];
    $dentro = false;
    $out = [];
    foreach ($linhas as $l) {
        if (preg_match('/^###\s+\d+\.\s*(.+?)\s+—/u', $l, $m)) {
            $dentro = (mb_stripos($m[1], $titulo) !== false);
            if ($dentro) {
                continue;
            }
        }
        if ($dentro) {
            if (preg_match('/^###\s/u', $l) || preg_match('/^##\s/u', $l)) {
                break;
            }
            $out[] = $l;
        }
    }
    return trim(implode("\n", $out));
}

/** Parágrafo limpo de uma seção do documento (por título de seção). */
function secao_md(string $md, string $tituloSecao): string
{
    $linhas = preg_split('/\R/u', $md) ?: [];
    $dentro = false;
    $out = [];
    foreach ($linhas as $l) {
        if (preg_match('/^##+\s*(.+)$/u', $l, $m)) {
            $dentro = (mb_stripos($m[1], $tituloSecao) !== false);
            continue;
        }
        if ($dentro) {
            $out[] = $l;
        }
    }
    return trim(implode("\n", $out));
}

$totalCamadas = 0;
$totalProjetos = 0;

foreach (consulta('SELECT * FROM projetos ORDER BY ordem, titulo COLLATE NOCASE') as $p) {
    $id = (int) $p['id'];
    $titulo = (string) $p['titulo'];
    $repos = array_map(static fn(array $r): string => (string) $r['nome'], repos_do_projeto($id));
    $tec = tecnologias_do_projeto($id);
    $ficha = ficha_playbook($texto['PLAYBOOK'], $titulo);

    /* camada 1 — o que o projeto é (linguagem humana, já existente) */
    $c1 = $titulo . ' — ' . (string) $p['resumo'] . "\n\n"
        . 'Situação hoje: ' . (string) $p['status'] . '. Frente ' . (string) $p['categoria']
        . ' da incubadora m3d.pro.'
        . ($repos ? "\n\nRepositórios da conta LACibermedicina: " . implode(', ', $repos) . '.' : '')
        . ($ficha !== '' ? "\n\n" . preg_replace('/^\*\s+/mu', '· ', $ficha) : '');

    /* camada 2 — contexto institucional */
    $c2 = 'Problema que o projeto ataca: (pendente de curadoria)' . "\n\n"
        . 'Solução proposta: ' . (string) $p['resumo'] . "\n\n"
        . 'Público-alvo: (pendente de curadoria)' . "\n\n"
        . 'Onde vive no acervo: ' . ($repos ? implode(', ', $repos) : '(pendente)') . '.';

    /* camada 3 — arquitetura e integrações (do ARQUITETURA.md) */
    $c3 = 'Tecnologias detectadas no inventário: ' . ($tec ? implode(', ', array_unique($tec)) : '(pendente de inventário)') . ".\n\n"
        . 'Stack do ecossistema: ' . trim(preg_replace('/\s+/u', ' ', secao_md($texto['ARQUITETURA'], 'Princípio central'))) . "\n\n"
        . 'Fluxo de uma requisição (base comum): ' . "\n"
        . trim(preg_replace('/\n{2,}/u', "\n", (string) preg_replace('/^/mu', '', secao_md($texto['ARQUITETURA'], 'Fluxo de uma requisição')))) . "\n\n"
        . 'Camadas de detalhamento: 1 ' . IN3_NIVEL_ROTULO[1] . ' · 2 ' . IN3_NIVEL_ROTULO[2]
        . ' · 3 ' . IN3_NIVEL_ROTULO[3] . ' · 4 ' . IN3_NIVEL_ROTULO[4] . ".\n\n"
        . 'Arquitetura específica deste projeto: (pendente de curadoria).';

    /* camada 4 — playbook completo (riscos, LGPD, validação) */
    $c4 = 'Ficha integral do projeto, com riscos conhecidos, tratamento de dados pessoais e LGPD, '
        . "critérios de validação e notas internas de curadoria.\n\n"
        . 'O que o sistema promete (SEGURANCA.md): ' . "\n"
        . trim(preg_replace('/\n{2,}/u', "\n", (string) preg_replace('/^\s*\d+\.\s+/mu', '· ', secao_md($texto['SEGURANCA'], 'O que o sistema promete')))) . "\n\n"
        . 'Controles por camada: ' . trim(preg_replace('/^\|/mu', '|', secao_md($texto['SEGURANCA'], 'Controles por camada'))) . "\n\n"
        . 'Este projeto em particular — riscos: (pendente de curadoria) · privacidade e LGPD: (pendente de curadoria)'
        . ' · notas internas: (pendente de curadoria).';

    $camadas = [1 => $c1, 2 => $c2, 3 => $c3, 4 => $c4];
    foreach ($camadas as $prof => $corpo) {
        if ($seco) {
            $totalCamadas++;
            continue;
        }
        executar('INSERT INTO camadas (projeto_id, profundidade, titulo, corpo, atualizado_em)
                  VALUES (:p,:n,:t,:c,:q)
                  ON CONFLICT (projeto_id, profundidade) DO UPDATE SET titulo = excluded.titulo,
                    corpo = excluded.corpo, atualizado_em = excluded.atualizado_em', [
            ':p' => $id, ':n' => $prof, ':t' => (string) IN3_NIVEL_ROTULO[$prof],
            ':c' => $corpo, ':q' => agora(),
        ]);
        $totalCamadas++;
    }
    $totalProjetos++;
}

if (!$seco) {
    gravar_config('transcrito_em', agora());
    auditar('documentacao_transcrita', 'acervo', $totalProjetos . ' projeto(s) · ' . $totalCamadas . ' camada(s)');
}

fwrite(STDOUT, "\n=== IN³ · transcrição da documentação base ===\n\n");
fwrite(STDOUT, '  ✓ ' . $totalProjetos . " projeto(s) processado(s)\n");
fwrite(STDOUT, '  ✓ ' . $totalCamadas . " camada(s) " . ($seco ? 'seriam gravadas (--seco)' : 'gravadas do documento base') . "\n");
fwrite(STDOUT, "  ✓ fonte: PLAYBOOK.md · ARQUITETURA.md · SEGURANCA.md · DEPLOY.md · README.md\n");
fwrite(STDOUT, "  ” o que o documento não afirma fica como (pendente de curadoria) — nada inventado\n\n");
exit(0);
