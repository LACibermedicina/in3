<?php
declare(strict_types=1);
/**
 * IN³ · abas novas do painel: feed de notícias, pedidos de engajamento e idiomas.
 * Devolve o HTML do corpo quando a rota pertence a este módulo; null caso contrário.
 * Todas as ações de escrita passam por CSRF e auditoria.
 */
function painel_extra_rota(string $sub): ?string
{
    /* ------------------------------------------------------------- notícias */
    if ($sub === 'noticias') {
        if (metodo() === 'POST') {
            csrf_verifica();
            $acao = entrada('acao');
            $id = (int) entrada('id');
            if ($acao === 'salvar') {
                noticia_salvar($_POST, $id);
                flash('ok', 'Notícia gravada.');
            } elseif ($acao === 'alternar' && $id > 0) {
                executa('UPDATE noticias SET mostrar_ao_publico = 1 - mostrar_ao_publico WHERE id = :i', [':i' => $id]);
                auditar('noticia_visibilidade', 'noticias', $id);
                flash('ok', 'Visibilidade da notícia alternada.');
            } elseif ($acao === 'excluir' && $id > 0) {
                executa('DELETE FROM noticias WHERE id = :i', [':i' => $id]);
                auditar('noticia_excluida', 'noticias', $id);
                flash('ok', 'Notícia excluída.');
            }
            redirecionar(url_painel('/noticias'));
        }
        return view('painel_noticias.php', [
            'noticias' => noticias_admin(120),
            'stats'    => noticias_estatisticas(),
            'projetos' => consulta('SELECT id, titulo, emoji FROM projetos ORDER BY titulo'),
            'csrf'     => csrf_campo(),
        ]);
    }

    /* ---------------------------------------------------- pedidos/engajamento */
    if ($sub === 'engajamento' || $sub === 'pedidos') {
        if (metodo() === 'POST') {
            csrf_verifica();
            $acao = entrada('acao');
            $id = (int) entrada('id');
            if ($acao === 'liberar' && $id > 0) {
                $dias = max(1, min(365, (int) entrada('dias', '30')));
                $clareza = max(2, min(4, (int) entrada('clareza', '3')));
                $ped = um('SELECT * FROM pedidos_engajamento WHERE id = :i', [':i' => $id]);
                if ($ped) {
                    $proj = um('SELECT id FROM projetos WHERE titulo LIKE :t ORDER BY ordem LIMIT 1',
                        [':t' => '%' . (string) $ped['projeto'] . '%']);
                    $token = emitir_acesso((int) ($proj['id'] ?? 0), $clareza, (int) ($ped['origem_id'] ?? 0) ?: null, $dias);
                    executa("UPDATE pedidos_engajamento SET estado = 'liberado', acesso_token = :k, respondido_em = :q WHERE id = :i",
                        [':k' => $token, ':q' => agora(), ':i' => $id]);
                    if (!empty($ped['origem_id'])) {
                        executa("UPDATE pedidos SET estado = 'liberado', respondido_em = :q WHERE id = :i",
                            [':q' => agora(), ':i' => (int) $ped['origem_id']]);
                    }
                    auditar('engajamento_liberado', 'pedidos_engajamento', $id, 'clareza=' . $clareza);
                    flash('ok', 'Acesso liberado por ' . $dias . ' dia(s) no nível ' . nivel_rotulo($clareza)
                        . '. Link: ' . (string) cfg('IN3_URL_BASE', '') . '/acesso/' . $token);
                }
            } elseif ($acao === 'arquivar' && $id > 0) {
                executa("UPDATE pedidos_engajamento SET estado = 'arquivado', respondido_em = :q WHERE id = :i",
                    [':q' => agora(), ':i' => $id]);
                auditar('engajamento_arquivado', 'pedidos_engajamento', $id);
                flash('ok', 'Pedido arquivado.');
            } elseif ($acao === 'analise' && $id > 0) {
                executa("UPDATE pedidos_engajamento SET estado = 'em_analise' WHERE id = :i", [':i' => $id]);
                flash('ok', 'Pedido em análise.');
            }
            redirecionar(url_painel('/engajamento'));
        }
        $pedidos = consulta('SELECT * FROM pedidos_engajamento ORDER BY quando DESC LIMIT 200');
        $cnt = ['total' => count($pedidos)];
        foreach (['novo', 'liberado', 'em_analise', 'arquivado'] as $e) {
            $cnt[$e] = (int) escalar('SELECT count(*) FROM pedidos_engajamento WHERE estado = :e', [':e' => $e]);
        }
        return view('painel_engajamento.php', ['pedidos' => $pedidos, 'stats' => $cnt, 'csrf' => csrf_campo()]);
    }

    /* --------------------------------------------------------------- idiomas */
    if ($sub === 'idiomas') {
        if (metodo() === 'POST') {
            csrf_verifica();
            $acao = entrada('acao');
            if ($acao === 'padrao') {
                $c = entrada('idioma', 'pt-BR');
                if (idioma_valido($c)) {
                    configurar('idioma_padrao', $c);
                    flash('ok', 'Idioma padrão definido como ' . IN3_IDIOMAS[$c]['rotulo'] . '.');
                }
            } elseif ($acao === 'modelo') {
                configurar('traducao_modelo', texto_limpo(entrada('modelo', 'gpt-4o-mini'), 60));
                flash('ok', 'Modelo de tradução atualizado.');
            } elseif ($acao === 'limpar_cache') {
                $n = (int) escalar('SELECT count(*) FROM traducoes');
                executa('DELETE FROM traducoes');
                auditar('traducao_cache_limpo', 'traducoes', null, 'linhas=' . $n);
                flash('ok', 'Cache de traduções limpo (' . $n . ' linhas).');
            }
            redirecionar(url_painel('/idiomas'));
        }
        return view('painel_idiomas.php', [
            'csrf'      => csrf_campo(),
            'idiomas'   => IN3_IDIOMAS,
            'padrao'    => idioma_padrao(),
            'stats'     => traducao_estatisticas(),
            'dicionario'=> array_map(static fn($c) => [
                'codigo' => $c, 'chaves' => count((dicionario($c)['interface'] ?? [])),
                'arquivo' => basename(dicionario_arquivo($c)),
            ], array_keys(IN3_IDIOMAS)),
            'url_base'  => (string) cfg('IN3_TRADUCAO_URL', ''),
        ]);
    }

    return null;
}
