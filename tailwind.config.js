import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // No web font: `font-sans` resolves to the platform UI stack (Tailwind's
    // default). The bunny.net Figtree link was removed from the layouts, and
    // the public pages never loaded it — this makes the system font the
    // intended one everywhere instead of a silent fallback.
    theme: {
        extend: {},
    },

    plugins: [forms],
};
