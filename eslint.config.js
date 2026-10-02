import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import prettier from 'eslint-config-prettier';

export default tseslint.config(
    {
        // Build output and dependencies are not linted.
        ignores: ['public/build/**', 'node_modules/**', 'vendor/**', 'bootstrap/ssr/**'],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
            },
            parserOptions: {
                ecmaFeatures: { jsx: true },
            },
        },
        plugins: {
            react,
            'react-hooks': reactHooks,
        },
        settings: {
            react: { version: 'detect' },
        },
        rules: {
            ...react.configs.recommended.rules,
            ...react.configs['jsx-runtime'].rules,
            ...reactHooks.configs.recommended.rules,
            // The new JSX transform does not require React in scope.
            'react/react-in-jsx-scope': 'off',
        },
    },
    {
        // Node-based config files run in a Node environment.
        files: ['*.config.{js,ts}', 'vite.config.js'],
        languageOptions: {
            globals: {
                ...globals.node,
            },
        },
    },
    // Keep ESLint out of Prettier's way: must come last to turn off conflicting rules.
    prettier,
);
