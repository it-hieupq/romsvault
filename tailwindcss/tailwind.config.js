/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    '../*.php',
    '../template-parts/**/*.php',
    '../inc/**/*.php',
    '../auth/**/*.php',
    '../auth/**/*.js',
    '../js/**/*.js',
    '../assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        'romfun-pink': '#e91e63',
        'romfun-pink-dark': '#c2185b',
        'romfun-pink-light': '#f06292',
        'brand': '#e91e63',
        'shell': '#f6f7fb',
        'muted': '#6b7280',
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', "system-ui", "-apple-system", "BlinkMacSystemFont", "Segoe UI", "sans-serif"],
        jakarta: ['"Plus Jakarta Sans"', "system-ui", "sans-serif"],
      },
      maxWidth: {
        'container': '1400px',
      }
    },
  },
  plugins: [],
}