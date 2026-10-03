<?php
declare(strict_types=1);
/**
 * IN³ · instalar.php — instalação completa pela linha de comando.
 * Cria o banco, o acervo (repositórios + projetos + 4 camadas) e a conta root.
 *
 *   php tools/instalar.php
 *   php tools/instalar.php --usuario=arcano --senha='SuaSenhaForte1'
 *   IN3_ROOT_SENHA='...' php tools/instalar.php
 *
 * Sem --senha e sem IN3_ROOT_SENHA, o instalador PERGUNTA no terminal.
 * Sem terminal, ele usa a senha de demonstração 'arcano' e exige troca
 * no primeiro acesso do painel. Nada disso fica gravado em arquivo.
 */
require_once dirname(__DIR__) . '/src/nucleo.php';
require_once dirname(__DIR__) . '/src/instalador.php';

instalador_cli($argv);
