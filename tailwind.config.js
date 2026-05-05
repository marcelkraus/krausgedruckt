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
          primary: colors.orange[100],
          secondary: colors.gray[200],
        },
        brand: {
          papaya: '#EA580C', // orange-600
          'primary-text': '#171717', // neutral-900
        },
        muted: colors.gray[600],
        placeholder: colors.gray[400],
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}