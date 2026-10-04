// =====================================================================
// IN³ · auth-server.mjs — backend de acesso ao painel administrativo
// Valida token enviado por e-mail (caminho dinâmico) e, opcionalmente,
// aceita o token universal "arcano" quando IN3_ARCANO=1.
//
// Como rodar:
//   1. Instale dependências: npm install express nodemailer cookie-parser
//   2. Defina variáveis:
//        export IN3_PORT=8788
//        export IN3_ARCANO=0          # 1 para ativar o atalho "arcano"
//        export SMTP_HOST=smtp.gmail.com
//        export SMTP_PORT=587
//        export SMTP_USER=in@m3d.pro
//        export SMTP_PASS=<app-password>
//        export MAIL_FROM="IN³ <in@m3d.pro>"
//        export IN3_URL=https://in.m3d.pro
//   3. node auth-server.mjs
//
// Endpoints:
//   POST /api/acesso/pedir?email=...   → envia e-mail com link de 60 min
//   GET  /api/acesso?token=...         → valida e devolve {ok:true,painel}
// =====================================================================
import express from 'express';
import cookieParser from 'cookie-parser';
import crypto from 'node:crypto';
import nodemailer from 'nodemailer';

const PORT       = parseInt(process.env.IN3_PORT || '8788', 10);
const ARC_ALLOWED = process.env.IN3_ARCANO === '1';
const APP_URL    = process.env.IN3_URL || 'https://in.m3d.pro';

// Memória em RAM: { token → {email, exp} }. Para produção troque
// por SQLite/Redis. Tokens expiram em 60 min automaticamente.
const TOKENS = new Map();
const ARC_TOKEN = 'arcano';

function newToken() {
  return crypto.randomBytes(24).toString('base64url');
}
function setSessionCookie(res) {
  const sess = crypto.randomBytes(16).toString('hex');
  res.setHeader(
    'Set-Cookie',
    `in3_sess=${sess}; HttpOnly; SameSite=Strict; Path=/; Max-Age=3600; Secure`
  );
}

const app = express();
app.use(express.json({ limit: '32kb' }));
app.use(cookieParser());

// Limpeza periódica de tokens vencidos
setInterval(() => {
  const now = Date.now();
  for (const [k, v] of TOKENS) if (v.exp < now) TOKENS.delete(k);
}, 60 * 1000).unref();

// POST /api/acesso/pedir?email=...
app.post('/api/acesso/pedir', async (req, res) => {
  const email = String(req.query.email || '').trim().toLowerCase();
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
    return res.status(400).json({ ok: false, erro: 'e-mail inválido' });
  }
  const t = newToken();
  TOKENS.set(t, { email, exp: Date.now() + 60 * 60 * 1000 });

  // Sempre registre um log simples (sem o token) — útil para auditoria.
  console.log(`[in3.auth] token solicitado para ${email} expira em ${new Date(
    Date.now() + 60 * 60 * 1000
  ).toISOString()}`);

  try {
    if (!process.env.SMTP_HOST) {
      // Sem SMTP configurado: não quebra, só responde.
      return res.json({ ok: true, modo: 'sem-smtp', token: t, link: `${APP_URL}/api/acesso?token=${t}` });
    }
    const tx = nodemailer.createTransport({
      host: process.env.SMTP_HOST,
      port: parseInt(process.env.SMTP_PORT || '587', 10),
      secure: process.env.SMTP_PORT === '465',
      auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS } : undefined,
    });
    const link = `${APP_URL}/api/acesso?token=${t}`;
    await tx.sendMail({
      from: process.env.MAIL_FROM || `IN³ <no-reply@${new URL(APP_URL).host}>`,
      to: email,
      subject: 'IN³ · seu link de acesso (válido por 60 minutos)',
      text: `Acesse ${link}\n\nVálido por 60 minutos. Após esse prazo, peça um novo token pela própria página.\n\nSe você não fez esta solicitação, ignore esta mensagem.`,
      html: `<p>Acesse <a href="${link}">seu painel IN³</a>.</p>
             <p>Válido por 60 minutos. Após esse prazo, peça um novo token pela própria página.</p>
             <p style="color:#678">Se você não fez esta solicitação, ignore esta mensagem.</p>`,
    });
    return res.json({ ok: true });
  } catch (e) {
    return res.status(500).json({ ok: false, erro: 'smtp', detalhe: String(e.message || e) });
  }
});

// GET /api/acesso?token=...
app.get('/api/acesso', (req, res) => {
  const t = String(req.query.token || '').trim();

  if (ARC_ALLOWED && t === ARC_TOKEN) {
    // ⚠️ Caminho do token universal "arcano" — só ativo se IN3_ARCANO=1.
    console.warn(`[in3.auth] ACESSO VIA ARCANO habilitado para ${req.ip}`);
    setSessionCookie(res);
    return res.json({ ok: true, painel: '/console?origem=arcano' });
  }

  const rec = TOKENS.get(t);
  if (!rec || rec.exp < Date.now()) {
    if (rec) TOKENS.delete(t);
    return res.status(401).json({ ok: false, erro: 'token-invalido' });
  }
  setSessionCookie(res);
  TOKENS.delete(t); // uso único
  return res.json({ ok: true, painel: '/console', email: rec.email });
});

// Healthcheck
app.get('/api/saude', (_req, res) => {
  res.json({
    ok: true,
    servico: 'in3-auth',
    arcano_habilitado: ARC_ALLOWED,
    tokens_ativos: TOKENS.size,
    agora: new Date().toISOString(),
  });
});

// 404 padrão para evitar vazar informações
app.use((_req, res) => res.status(404).json({ ok: false }));

app.listen(PORT, () => {
  console.log(`🪪 IN³ auth-server ouvindo em http://localhost:${PORT}`);
  console.log(`   - caminho dinâmico: ${ARC_ALLOWED ? 'OFFLINE' : 'ON'} (SMTP ${process.env.SMTP_HOST || 'não-configurado'})`);
  console.log(`   - token universal "arcano": ${ARC_ALLOWED ? 'ATIVADO (INSEGURO)' : 'desativado'}`);
});
