module.exports = {
  content: [
    "./**/*.{html,php,js}",
  ],
  theme: {
    extend: {
      colors: {
        primary: '#4361ee',
        secondary: '#6c757d',
        success: '#4aa96c',
        danger: '#dc3545',
        warning: '#ff9f43',
        info: '#3db2ff',
        dark: '#2b2d42',
      },
      fontFamily: {
        sans: ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'],
      },
      boxShadow: {
        card: '0 4px 6px rgba(0, 0, 0, 0.1)',
        'card-hover': '0 5px 15px rgba(0, 0, 0, 0.1)',
      },
    },
  },
  plugins: [],
} 