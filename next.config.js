/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // O scaffold prioriza "rodar de primeira". Em producao, troque para false e trate os tipos.
  typescript: { ignoreBuildErrors: true },
  eslint: { ignoreDuringBuilds: true }
};
module.exports = nextConfig;
