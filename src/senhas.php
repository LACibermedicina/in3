<?php
declare(strict_types=1);
/**
 * IN³ · hashing de senha.
 *
 * A especificação pede SHA-256. SHA-256 "cru" (uma passada) é inadequado para
 * senha: é rápido demais e não tem sal. Aqui usamos o mesmo primitivo — SHA-256 —
 * na forma correta: PBKDF2-HMAC-SHA256 com sal aleatório por usuário e custo
 * configurável. A comparação é feita em tempo constante por hash_equals().
 *
 * Formato armazenado (uma coluna só):
 *     pbkdf2$sha256$<iterações>$<sal-base64>$<derivada-base64>
 *
 * Compatibilidade: hashes antigos em bcrypt/argon ($2y$, $argon2...) continuam
 * verificáveis e são migrados para o formato novo no primeiro login bem-sucedido.
 */

const IN3_SENHA_ALGO   = 'sha256';
const IN3_SENHA_ITER   = 210000;      // custo; subir aqui invalida nada (fica gravado no hash)
const IN3_SENHA_BYTES  = 32;
const IN3_SENHA_MINIMO = 10;

function senha_gerar(string $senha): string
{
    $sal = random_bytes(16);
    $der = hash_pbkdf2(IN3_SENHA_ALGO, $senha, $sal, IN3_SENHA_ITER, IN3_SENHA_BYTES, true);
    return 'pbkdf2$' . IN3_SENHA_ALGO . '$' . IN3_SENHA_ITER . '$'
        . base64_encode($sal) . '$' . base64_encode($der);
}

/** @return array{ok:bool,rehash:?string,algo:string} */
function senha_verificar(string $senha, string $armazenado): array
{
    $armazenado = trim($armazenado);
    if (str_starts_with($armazenado, 'pbkdf2$')) {
        $p = explode('$', $armazenado);
        if (count($p) !== 5 || $p[1] !== IN3_SENHA_ALGO) {
            return ['ok' => false, 'rehash' => null, 'algo' => 'pbkdf2-invalido'];
        }
        $iter = (int) $p[2];
        $sal  = base64_decode($p[3], true);
        $alvo = base64_decode($p[4], true);
        if ($sal === false || $alvo === false || $iter < 1) {
            return ['ok' => false, 'rehash' => null, 'algo' => 'pbkdf2-invalido'];
        }
        $der = hash_pbkdf2(IN3_SENHA_ALGO, $senha, $sal, $iter, strlen($alvo), true);
        $ok  = hash_equals($alvo, $der);
        return [
            'ok'     => $ok,
            'rehash' => ($ok && $iter < IN3_SENHA_ITER) ? senha_gerar($senha) : null,
            'algo'   => 'pbkdf2-sha256',
        ];
    }
    // legado (bcrypt/argon2) — verifica e devolve o rehash no formato novo
    if (preg_match('/^\$(2[aby]|argon2)/', $armazenado) === 1) {
        $ok = password_verify($senha, $armazenado);
        return ['ok' => $ok, 'rehash' => $ok ? senha_gerar($senha) : null, 'algo' => 'legado'];
    }
    // hash descartável: iguala o tempo de resposta para usuário inexistente
    senha_gerar($senha);
    return ['ok' => false, 'rehash' => null, 'algo' => 'nenhum'];
}

/** Só para conferência/diagnóstico: descreve o formato sem expor o hash. */
function senha_descrever(string $armazenado): string
{
    if (str_starts_with($armazenado, 'pbkdf2$sha256$')) {
        $p = explode('$', $armazenado);
        return 'pbkdf2-sha256/' . (int) ($p[2] ?? 0) . ' iterações';
    }
    if (preg_match('/^\$(argon2\w*|2[aby])\$/', $armazenado, $m) === 1) {
        return 'legado (' . $m[1] . ')';
    }
    return 'desconhecido';
}
