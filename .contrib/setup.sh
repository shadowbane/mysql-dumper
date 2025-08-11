#!/bin/sh

# Check if .git/hooks/pre-commit exists
if [ -f .git/hooks/pre-commit ]; then
    echo "pre-commit hook already exists"
    exit 0
fi

cp .contrib/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

echo "pre-commit hook installed"
