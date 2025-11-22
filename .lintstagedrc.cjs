module.exports = {
  '*.{ts,vue}': ['eslint --fix'],
  '*.{scss,vue,ts,md}': ['prettier --write'],
  '*.json': (files) => {
    const filtered = files.filter(file => !file.includes('openapi.json'));
    return filtered.length > 0 ? `prettier --write ${filtered.join(' ')}` : [];
  },
  '*.php': [() => 'make php-cs-fixer', () => 'make test'],
  '*Controller.php': [() => 'make openapi', () => 'git add openapi.json'],
}
