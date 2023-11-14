const colors = require('tailwindcss/colors')
module.exports = {
  content: ["./templates/**/*.html.twig"],
  theme: {
    colors: {
      transparent: 'transparent',
      current: 'currentColor',
      black: colors.black,
      white: colors.white,
    },
    extend: {
      colors: {
        background: {
          primary: '#ffedd5' // Orange:100
        },
        brand: {
          primary: '#f97316', // Orange:400
          secondary: '#4b5563', // Gray:600
        },
        placeholder: '#9ca3af', // Gray:400
        primary: '#111827', // Gray:900
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}