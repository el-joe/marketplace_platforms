import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/View/Components/**/*.php',
        './app/Http/Livewire/**/*.php',
        './storage/framework/views/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                figtree: ['Figtree', ...defaultTheme.fontFamily.sans],
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                yellow: {
                    300: '#e5d600',
                    400: '#feee00',
                },
                primary: {
                    50: '#f0f9ff',
                    100: '#e0f2fe',
                    200: '#bae6fd',
                    300: '#7dd3fc',
                    400: '#38bdf8',
                    500: '#0ea5e9',
                    600: '#0284c7',
                    700: '#0369a1',
                    800: '#075985',
                    900: '#0c4a6e',
                },
                success: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    200: '#bbf7d0',
                    300: '#86efac',
                    400: '#4ade80',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                    800: '#166534',
                    900: '#14532d',
                },
                warning: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
                danger: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    300: '#fca5a5',
                    400: '#f87171',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                    800: '#991b1b',
                    900: '#7f1d1d',
                },
            },
            transitionDuration: {
                DEFAULT: '200ms',
            },
            spacing: {
                'sidebar': '16rem',          // 256px expanded
                'sidebar-collapsed': '4rem', // 64px collapsed
                'topbar': '4rem',            // 64px
            },
            zIndex: {
                'sidebar': '40',
                'topbar': '30',
                'backdrop': '50',
                'modal': '60',
                'toast': '70',
            },
            keyframes: {
                'slide-in-right': {
                    '0%': { transform: 'translateX(100%)', opacity: '0' },
                    '100%': { transform: 'translateX(0)', opacity: '1' },
                },
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'fade-in-up': {
                    '0%': { transform: 'translateY(1rem)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                'bounce-x': {
                    '0%, 100%': {
                        transform: 'translateX(25%)',
                        animationTimingFunction: 'cubic-bezier(0.8, 0, 1, 1)'
                    },
                    '50%': {
                        transform: 'translateX(0)',
                        animationTimingFunction: 'cubic-bezier(0, 0, 0.2, 1)'
                    }
                }
            },
            animation: {
                'slide-in-right': 'slide-in-right 250ms ease-out',
                'fade-in': 'fade-in 200ms ease-out',
                'fade-in-up': 'fade-in-up 250ms ease-out',
                'bounce-x': 'bounce-x 1s infinite',
            },
        },
    },
    plugins: [forms, typography],
};
