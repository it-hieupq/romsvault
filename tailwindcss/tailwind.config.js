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
        // Ruby pink. 'primary' is the canonical name; 'romfun-pink' and 'brand'
        // are the legacy aliases still used across the templates and point at
        // the same values so the whole site shifts together.
        primary: {
          DEFAULT: '#d81b60',
          hover: '#ad1457',
          light: '#fdf2f8',
          accent: '#ec4899',
        },
        'romfun-pink': '#d81b60',
        'romfun-pink-dark': '#ad1457',
        'romfun-pink-light': '#f06292',
        brand: {
          DEFAULT: '#d81b60',
          dark: '#ad1457',
          light: '#f06292',
        },
        gold: {
          DEFAULT: '#f59e0b',
          hover: '#d97706',
          light: '#fef3c7',
        },
        slate: {
          850: '#151f33',
          900: '#0f172a',
          950: '#090d16',
        },
        'shell': '#f6f7fb',
        'muted': '#6b7280',
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', "system-ui", "-apple-system", "BlinkMacSystemFont", "Segoe UI", "sans-serif"],
        jakarta: ['"Plus Jakarta Sans"', "system-ui", "sans-serif"],
        display: ['Rajdhani', 'sans-serif'],
      },
      maxWidth: {
        'container': '1400px',
        'site': '1400px',    // header, footer and full-width grids
        'content': '1120px', // article body and the single-rom sections
      },
      fontSize: {
        'body': ['14px', '22px'],
        'meta': ['13px', '18px'],
        'caption': ['11px', '15px'],
      },
      boxShadow: {
        'subtle': '0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.03)',
        'card': '0 4px 16px -2px rgba(15, 23, 42, 0.04)',
        'card-hover': '0 14px 28px -6px rgba(233, 30, 99, 0.12)',
        'glow-primary': '0 8px 24px -4px rgba(216, 27, 96, 0.35)',
      },
    },
  },
  plugins: [],
}
