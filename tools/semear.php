<?php
declare(strict_types=1);
/**
 * IN³ · semear.php — (re)constrói o acervo: repositórios, projetos,
 * vínculos e as quatro camadas de cada projeto.
 *   php tools/semear.php
 */
require_once dirname(__DIR__) . '/src/nucleo.php';
require_once __DIR__ . '/semear_lib.php';

if (!banco_existe()) {
    fwrite(STDERR, "  ! banco ausente — rode primeiro: php tools/instalar.php\n");
    exit(1);
}

$r = semear_acervo();
$cob = cobertura_repos();

fwrite(STDOUT, "\n=== IN³ · semeadura ===\n\n");
fwrite(STDOUT, '  ✓ ' . (int) $r['projetos'] . " projeto(s) no acervo\n");
fwrite(STDOUT, '  ✓ ' . (int) $r['publicos'] . ' publicado(s) · ' . (int) $r['internos'] . " interno(s)\n");
fwrite(STDOUT, '  ✓ cobertura de repositórios: ' . (int) $cob['mapeados'] . '/' . (int) $cob['total']
    . ($cob['orfaos'] ? ' — ' . count($cob['orfaos']) . ' sem projeto: ' . implode(', ', $cob['orfaos']) : ' (completa)') . "\n");
fwrite(STDOUT, '  ✓ camadas gravadas: ' . (int) escalar('SELECT count(*) FROM camadas') . " (4 por projeto)\n");
fwrite(STDOUT, "  → agora rode: php tools/verificar.php\n");
