/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.jsx",
    ],
    safelist: [
        // Safelist all possible column spans for dynamic usage
        'col-span-1',
        'col-span-2',
        'col-span-3',
        'col-span-4',
        'col-span-5',
        'col-span-6',
        'col-span-7',
        'grid-cols-4',
        'grid-cols-5',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#6D28D9',
                    foreground: '#FFFFFF',
                },
            },
        },
    },
    plugins: [require("tailwindcss-animate")],
}
