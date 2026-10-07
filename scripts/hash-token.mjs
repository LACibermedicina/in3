// Gera o hash scrypt usado em MASTER_TOKEN_HASH.
// Uso: npm run hash-token -- "meu-token-mestre"
import { scryptSync, randomBytes } from 'node:crypto';

const token = process.argv[2];
if (!token) {
  console.error('Uso: npm run hash-token -- "seu-token-mestre"');
  process.exit(1);
}
const salt = randomBytes(16);
const hash = scryptSync(token, salt, 64);
console.log(`scrypt$${salt.toString('hex')}$${hash.toString('hex')}`);
console.error('\n-> Cole o valor acima em MASTER_TOKEN_HASH (.env.local)');
