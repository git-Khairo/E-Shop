import defaultTheme from 'tailwindcss/defaultTheme';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.jsx',
        './resources/**/*.ts',
        './resources/**/*.tsx',
        './resources/**/*.vue',
        'node_modules/preline/dist/*.js',
        './node_modules/flowbite/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['"Playfair Display"', 'Georgia', 'serif'],
            },
            colors: {
                brand: {
                    50:  '#f5f7fa',
                    100: '#e4e9f2',
                    200: '#c8d3e3',
                    300: '#9fb0c9',
                    400: '#6e84a5',
                    500: '#4a5d80',
                    600: '#354966',
                    700: '#283852',
                    800: '#1e2b3f',
                    900: '#151f2e',
                    950: '#0b1220',
                },
                accent: {
                    500: '#b38b5a',
                    600: '#9a7548',
                },
            },
            boxShadow: {
                'soft': '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 4px 16px -2px rgb(0 0 0 / 0.05)',
                'soft-lg': '0 4px 24px -4px rgb(0 0 0 / 0.08), 0 8px 40px -8px rgb(0 0 0 / 0.08)',
            },
        },
    },
    plugins: [
        require('preline/plugin'),
        require('flowbite/plugin'),
    ],
};
