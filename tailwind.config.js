import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brix: {
                    50: '#f5f5f5',
                    100: '#e5e5e5',
                    200: '#d4d4d4',
                    300: '#a3a3a3',
                    400: '#737373',
                    500: '#404040',
                    600: '#171717',
                    700: '#000000',
                    800: '#000000',
                    900: '#000000',
                    950: '#000000',
                },
                ink: {
                    50: '#f7f7f8',
                    100: '#eeeef0',
                    200: '#d9d9de',
                    300: '#b6b6bf',
                    400: '#8d8d9a',
                    500: '#6f6f7d',
                    600: '#585866',
                    700: '#484852',
                    800: '#2f2f37',
                    900: '#1b1b21',
                    950: '#101014',
                },
            },
            boxShadow: {
                subtle: '0 1px 2px 0 rgb(16 16 20 / 0.04), 0 1px 1px 0 rgb(16 16 20 / 0.03)',
                panel: '0 12px 32px -8px rgb(16 16 20 / 0.14), 0 4px 12px -4px rgb(16 16 20 / 0.08)',
            },
            borderRadius: {
                xl: '12px',
                '2xl': '16px',
            },
        },
    },

    plugins: [forms],
};
