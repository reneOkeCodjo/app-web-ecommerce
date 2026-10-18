#!/usr/bin/env sh

cd "$(dirname "$0")"

php -S localhost:8000 

cleanup() {
  kill "$PHP_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

npx browser-sync start --proxy "http://localhost:8080" --files "**/*.php,**/*.css,**/*.js" --no-open