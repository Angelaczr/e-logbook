import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import hooks from 'eslint-plugin-react-hooks';
import tsParser from '@typescript-eslint/parser';
import globals from 'globals';

export default [
  { ignores: ['vendor/**', 'node_modules/**', 'public/build/**', 'storage/**', 'resources/js/components/ui/**'] },
  js.configs.recommended,
  ...vue.configs['flat/recommended'],
  {
    files: ['src/**/*.ts', 'src/**/*.vue', 'resources/js/**/*.ts', 'resources/js/**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tsParser,
        ecmaVersion: 'latest',
        sourceType: 'module',
        extraFileExtensions: ['.vue'],
      },
      globals: { ...globals.browser, ...globals.node, RequestInit: 'readonly' },
    },
    rules: {
      'no-restricted-syntax': [
        'error',
        { selector: 'Literal[value=/^(super_admin|department_head|direct_supervisor|employee|admin|pembimbing|peserta)$/]', message: 'Gunakan permission dari server, jangan hardcode nama role.' },
      ],
      'no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      'vue/multi-word-component-names': 'off',
      'vue/no-unused-vars': 'error',
    },
  },
  {
    files: ['src/**/*.ts', 'resources/js/**/*.ts'],
    languageOptions: { parser: tsParser },
    rules: { 'no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }] },
  },
  {
    files: ['src/**/*.d.ts'],
    rules: { 'no-unused-vars': 'off' },
  },
  {
    files: ['src/**/*.{tsx,jsx}'],
    plugins: { 'react-hooks': hooks },
    rules: {
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'error',
    },
  },
];
