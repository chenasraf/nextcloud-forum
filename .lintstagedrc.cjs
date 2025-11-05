module.exports = {
  '*.{ts,vue}': ['eslint --fix'],
  'src/*.{scss,vue,ts,md,json}': ['prettier --write'],
  '*.php': [() => 'make php-cs-fixer'],
  '*Controller.php': [() => 'make openapi', () => 'git add openapi.json'],
}
