#!/bin/bash

set -e

# Bail early if the requested directory does not exist.
if [[ ! -d $1s/$2 ]]; then
	exit 0
fi

echo "Running the PHP linter on $1s/$2 ..."
find ./$1s/$2 -type "f" -iname "*.php" -not -path "./vendor/*" | xargs -L "1" php -l
