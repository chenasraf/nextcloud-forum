#!/usr/bin/env bash
# Shared helpers for the dev tools: find the target Nextcloud instance and run PHP scripts against it.
#
# The target is picked from environment variables, the same ones `make test` reads:
#   NEXTCLOUD_CONTAINER       Run inside this Docker container (e.g. nextcloud-nextcloud-1).
#                             Use this when the database is only reachable from inside Docker.
#   NEXTCLOUD_CONTAINER_ROOT  Nextcloud root inside the container (default: /var/www/html)
#   NEXTCLOUD_CONTAINER_USER  User that runs PHP inside the container (default: www-data)
#   NEXTCLOUD_ROOT            Nextcloud root on this machine, resolved the same way as `make test`.
#                             Falls back to ../../.. when the app lives in a Nextcloud apps directory.
#   NEXTCLOUD_PHP             PHP binary to use with NEXTCLOUD_ROOT (default: php)

set -euo pipefail

TOOLS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(dirname "$TOOLS_DIR")"

info() { printf '\033[32m%s\033[0m\n' "$*"; }
warn() { printf '\033[33m%s\033[0m\n' "$*"; }
fail() {
	printf '\033[31m%s\033[0m\n' "$*" >&2
	exit 1
}

# Print the local Nextcloud root, or nothing when it cannot be found
resolve_nextcloud_root() {
	local root="${NEXTCLOUD_ROOT:-}"
	if [ -n "$root" ]; then
		root="${root/#\~/$HOME}"
	elif [ -f "$APP_DIR/../../../lib/base.php" ]; then
		root="$(cd "$APP_DIR/../../.." && pwd)"
	fi
	printf '%s' "$root"
}

describe_target() {
	if [ -n "${NEXTCLOUD_CONTAINER:-}" ]; then
		printf 'container %s (%s)' "$NEXTCLOUD_CONTAINER" "${NEXTCLOUD_CONTAINER_ROOT:-/var/www/html}"
	else
		resolve_nextcloud_root
	fi
}

# Print the given tools/ PHP files as one script. Every file starts with an opening `<?php` line,
# which is kept for the first file only.
combine_php() {
	local first=1 file
	for file in "$@"; do
		if [ "$first" = 1 ]; then
			cat "$TOOLS_DIR/$file"
			first=0
		else
			tail -n +2 "$TOOLS_DIR/$file"
		fi
		echo
	done
}

# Run PHP files from tools/ against the target instance, after bootstrap.php has booted Nextcloud.
# Usage: run_php <file.php>... [-- VAR=value ...] — the variables are passed to the script's environment.
run_php() {
	local files=(bootstrap.php) vars=()
	while [ $# -gt 0 ] && [ "$1" != "--" ]; do
		files+=("$1")
		shift
	done
	[ $# -gt 0 ] && shift
	vars=("$@")

	if [ -n "${NEXTCLOUD_CONTAINER:-}" ]; then
		local container_root="${NEXTCLOUD_CONTAINER_ROOT:-/var/www/html}"
		local env_args=(-e "NEXTCLOUD_ROOT=$container_root")
		local var
		for var in ${vars[@]+"${vars[@]}"}; do
			env_args+=(-e "$var")
		done
		docker ps --format '{{.Names}}' | grep -qx "$NEXTCLOUD_CONTAINER" \
			|| fail "Container $NEXTCLOUD_CONTAINER is not running."
		# The script is piped in, so it does not matter where the app is mounted inside the container
		combine_php "${files[@]}" | docker exec -i --user "${NEXTCLOUD_CONTAINER_USER:-www-data}" \
			-w "$container_root" "${env_args[@]}" "$NEXTCLOUD_CONTAINER" php
		return
	fi

	local root
	root="$(resolve_nextcloud_root)"
	if [ -z "$root" ]; then
		warn "Could not find Nextcloud installation."
		echo ""
		echo "Options:"
		echo "  1. Run inside the Nextcloud Docker container:"
		printf '     \033[32mNEXTCLOUD_CONTAINER=nextcloud-nextcloud-1 %s\033[0m\n' "$0"
		echo "  2. Set NEXTCLOUD_ROOT to a Nextcloud installation whose database is reachable from here:"
		printf '     \033[32mNEXTCLOUD_ROOT=/path/to/nextcloud %s\033[0m\n' "$0"
		echo ""
		exit 1
	fi
	[ -f "$root/lib/base.php" ] || fail "No Nextcloud installation at $root (lib/base.php is missing)."

	combine_php "${files[@]}" | (cd "$root" && env NEXTCLOUD_ROOT="$root" ${vars[@]+"${vars[@]}"} "${NEXTCLOUD_PHP:-php}")
}

# Ask before touching the target; skipped with -y / --yes
confirm() {
	local prompt="$1"
	shift
	local arg
	for arg in "$@"; do
		if [ "$arg" = "-y" ] || [ "$arg" = "--yes" ]; then
			return 0
		fi
	done
	read -r -p "$prompt [y/N] " answer
	[[ "$answer" =~ ^[Yy]$ ]] || fail "Aborted."
}
