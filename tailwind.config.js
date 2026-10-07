/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./src/**/*.{js,ts,jsx,tsx,mdx}'],
  theme: {
    extend: {
      colors: {
        grass: '#6FCF97', sky: '#BFE3FF', gold: '#FFC53D',
        warm: '#FF8A5B', cool: '#4EA8DE', ink: '#0B1020'
      }
    }
  },
  plugins: []
};
