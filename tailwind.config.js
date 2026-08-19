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
                    50: '#f4f3ff',
                    100: '#ebe9fe',
                    200: '#d9d6fe',
                    300: '#bcb5fd',
                    400: '#9a8cfa',
                    500: '#7c65f5',
                    600: '#6842ea',
                    700: '#5a32d6',
                    800: '#4b2ab3',
                    900: '#3f2790',
                    950: '#25164f',
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
