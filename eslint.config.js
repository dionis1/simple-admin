import js from '@eslint/js';

export default [
    { ignores: ['vendor/**', 'node_modules/**', 'public/**'] },
    js.configs.recommended,
    { files: ['resources/js/**/*.js', '*.config.js'], languageOptions: { ecmaVersion: 'latest', sourceType: 'module' } },
];
