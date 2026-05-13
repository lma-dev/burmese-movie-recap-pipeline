/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', 'system-ui', 'sans-serif'],
                mono: ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
                my:   ['"Noto Sans Myanmar"', '"IBM Plex Sans"', 'sans-serif'],
            },
            colors: {
                ink:        '#111111',
                'ink-2':    '#44443F',
                'ink-3':    '#7A7A72',
                'ink-4':    '#A8A8A0',
                surface:    '#FFFFFF',
                'surface-2':'#FAFAF9',
                'surface-muted': '#F1F1EE',
                bg:         '#F6F6F4',
                'pip-green':    '#16A34A',
                'pip-green-bg': '#E8F6EC',
                'pip-blue':     '#2563EB',
                'pip-blue-bg':  '#E7EFFE',
                'pip-amber':    '#D97706',
                'pip-amber-bg': '#FCEFDD',
                'pip-red':      '#DC2626',
                'pip-red-bg':   '#FCE7E7',
            },
            borderRadius: {
                pipe: '4px',
            },
        },
    },
    plugins: [require('@tailwindcss/forms')],
};
