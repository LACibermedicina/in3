// Verificacao de token mestre + tokens temporarios. Node runtime apenas.
import { randomBytes, scryptSync, timingSafeEqual } from 'node:crypto';
import { db, persist, uid, type AccessToken } from './store';

export function hashToken(plain: string): string {
  const salt = randomBytes(16);
  const h = scryptSync(plain, salt, 64);
  return `scrypt$${salt.toString('hex')}$${h.toString('hex')}`;
}

export function verifyHash(plain: string, stored: string | undefined): boolean {
  if (!stored) return false;
  const [scheme, saltHex, hashHex] = stored.split('$');
  if (scheme !== 'scrypt' || !saltHex || !hashHex) return false;
  try {
    const expected = Buffer.from(hashHex, 'hex');
    const actual = scryptSync(plain, Buffer.from(saltHex, 'hex'), expected.length);
    return expected.length === actual.length && timingSafeEqual(expected, actual);
  } catch {
    return false;
  }
}

/** Compara um valor apresentado com o hash do token mestre guardado em ambiente. */
export function isMasterToken(plain: string): boolean {
  return verifyHash(plain, process.env.MASTER_TOKEN_HASH);
}

/** Um token temporario esta valido se ativo, nao revogado e dentro da janela de prazo. */
export function tokenState(t: AccessToken): 'active' | 'inactive' | 'revoked' | 'expired' {
  if (t.revokedAt) return 'revoked';
  if (!t.active) return 'inactive';
  if (t.expiresAt && new Date(t.expiresAt).getTime() < Date.now()) return 'expired';
  return 'active';
}

export type Resolution = {
  ok: boolean;
  reason?: string;
  id: string;
  label: string;
  role: 'admin' | 'guest';
};

export function resolveToken(plain: string): Resolution {
  if (!plain) return { ok: false, reason: 'token ausente', id: '', label: '', role: 'guest' };

  if (isMasterToken(plain)) {
    return { ok: true, id: 'master', label: 'Administrador', role: 'admin' };
  }

  const store = db();
  const found = store.tokens.find((t) => verifyHash(plain, t.hash));
  if (!found) return { ok: false, reason: 'token nao reconhecido', id: '', label: '', role: 'guest' };

  const state = tokenState(found);
  if (state !== 'active') {
    return { ok: false, reason: `token ${state}`, id: found.id, label: found.label, role: found.role };
  }
  return { ok: true, id: found.id, label: found.label, role: found.role };
}
