#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
sha256sum -c RELEASE-SHA256SUMS.txt
echo "JARINGANKU RELEASE SOURCE CHECKSUM PASSED"
