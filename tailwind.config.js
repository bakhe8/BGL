/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./www/**/*.{php,js,html}", "./app/Views/**/*.{php,html}"],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', 'sans-serif'],
                mono: ['Inter', 'monospace'],
            },
            colors: {
                primary: '#2563eb',
                'primary-soft': '#dbeafe',
            }
        },
    },
    plugins: [],
}
