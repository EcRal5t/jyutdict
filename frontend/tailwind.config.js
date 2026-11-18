// 全站字號倍率：只放大文字（及對應行高），不放大間距，保持信息密度。
// 同一倍率也用於 main.css 中 body 的默認字號（--jd-font-scale）。
const FONT_SCALE = 1.1;
const scaled = (size, lineHeight) => [
    `${+(size * FONT_SCALE).toFixed(4)}rem`,
    { lineHeight: typeof lineHeight === 'number' ? `${+(lineHeight * FONT_SCALE).toFixed(4)}rem` : lineHeight },
];

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        "./index.html",
        "./src/**/*.{vue,js,ts,jsx,tsx}",
    ],
    theme: {
        // Tailwind 默認字號表 × FONT_SCALE（行高為 rem 時同步放大，為倍數時不變）
        fontSize: {
            xs: scaled(0.75, 1),
            sm: scaled(0.875, 1.25),
            base: scaled(1, 1.5),
            lg: scaled(1.125, 1.75),
            xl: scaled(1.25, 1.75),
            '2xl': scaled(1.5, 2),
            '3xl': scaled(1.875, 2.25),
            '4xl': scaled(2.25, 2.5),
            '5xl': scaled(3, '1'),
            '6xl': scaled(3.75, '1'),
            '7xl': scaled(4.5, '1'),
            '8xl': scaled(6, '1'),
            '9xl': scaled(8, '1'),
        },
        extend: {
            colors: {
                background: '#F4F4EE',
                accent: '#D32913',
                wood: '#8b5a2b', // Auxiliary color for traditional feel?
            },
            fontFamily: {
                // 漢字優先傳承字形。襯線缺字時先落到一點明體，再到各系統的明體/宋體，避免生僻字頭掉到黑體
                serif: ['"Cactus Classical Serif"', '"I.Ming"', '"PMingLiU"', '"MingLiU_HKSCS"', '"PMingLiU-ExtB"', '"Songti TC"', '"Noto Serif CJK TC"', '"Source Han Serif TC"', 'serif'],
                sans: ['"Inter"', '"Chocolate Classical Sans"', '"Noto Sans HK"', 'sans-serif'],
                // 站名與各級標題：主字體只有一個字重，標題改用有真粗體的 Noto Serif HK，缺字再回到傳承字形明體
                heading: ['"Noto Serif HK"', '"Cactus Classical Serif"', '"I.Ming"', '"PMingLiU"', '"MingLiU_HKSCS"', '"PMingLiU-ExtB"', '"Songti TC"', '"Noto Serif CJK TC"', '"Source Han Serif TC"', 'serif'],
            }
        },
    },
    plugins: [],
}
