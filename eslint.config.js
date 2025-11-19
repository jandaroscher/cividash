import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
  js.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
      },
      ecmaVersion: 2022,
      sourceType: 'module',
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/no-unused-vars': 'warn',
      'no-unused-vars': 'warn',
      'no-console': 'warn',
    },
  },
  {
    ignores: [
      'node_modules/**',
      'vendor/**',
      'storage/**',
      'bootstrap/cache/**',
      'public/build/**',
      '.github/**',
    ],
  },
];

