import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'IN3 - Estufa de Inovacao',
  description:
    'Acompanhamento gamificado de projetos incubados em uma floresta isometrica low-poly de casas na arvore, cachoeiras e nuvens.'
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="pt-BR">
      <body>{children}</body>
    </html>
  );
}
