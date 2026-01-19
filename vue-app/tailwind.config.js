/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
    ],
    theme: {
        extend: {
            colors: {
                background: '#F4F4EE',
                accent: '#D32913',
                wood: '#8b5a2b', // Auxiliary color for traditional feel?
            },
            fontFamily: {
                serif: ['"Cactus Classical Serif"', 'serif'],
                sans: ['"Inter"', '"Noto Sans TC"', 'sans-serif'],
            }
        },
    },
    plugins: [],
}
