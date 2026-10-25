import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: ['bootstrap/ssr', 'node_modules', 'public', 'storage', 'vendor'],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...vue.configs['flat/recommended'],
    {
        languageOptions: {
            globals: globals.browser,
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
            },
        },
        rules: {
            // Page components are named after their route (Home, Show).
            'vue/multi-word-component-names': 'off',
        },
    },
    {
        files: ['*.config.{js,ts}', 'tests/js/**/*.ts'],
        languageOptions: {
            globals: globals.node,
        },
    },
    // Must come last: turns off rules that conflict with Prettier's formatting.
    prettier,
);
