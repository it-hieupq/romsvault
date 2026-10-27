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
        // Brand colour, read from the CSS variables in assets/css/input.css.
        // 'primary' is the canonical name; 'rom-pink' and 'brand' are legacy
        // aliases still used across the templates and point at the same values.
        primary: {
          DEFAULT: 'rgb(var(--primary) / <alpha-value>)',
          hover: 'rgb(var(--primary-hover) / <alpha-value>)',
          light: 'rgb(var(--primary-light) / <alpha-value>)',
          accent: 'rgb(var(--primary-accent) / <alpha-value>)',
        },
        'rom-pink': 'rgb(var(--primary) / <alpha-value>)',
        'rom-pink-dark': 'rgb(var(--primary-hover) / <alpha-value>)',
        'rom-pink-light': 'rgb(var(--primary-accent) / <alpha-value>)',
        brand: {
          DEFAULT: 'rgb(var(--primary) / <alpha-value>)',
          dark: 'rgb(var(--primary-hover) / <alpha-value>)',
          light: 'rgb(var(--primary-accent) / <alpha-value>)',
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
        'card-hover': '0 14px 28px -6px rgb(var(--primary) / 0.12)',
        'glow-primary': '0 8px 24px -4px rgb(var(--primary) / 0.35)',
      },
    },
  },
  plugins: [],
}
