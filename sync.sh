#!/bin/sh
set -e

UPSTREAM_API="https://api.uupdump.net"
LOCAL_API="http://127.0.0.1:80"
FILEINFO_DIR="/var/www/html/fileinfo"
METADATA_DIR="$FILEINFO_DIR/metadata"
FULL_DIR="$FILEINFO_DIR/full"

mkdir -p "$METADATA_DIR" "$FULL_DIR"

# --- 1. Sync build list from public API ---
echo "[sync] Fetching build list from upstream..."
LISTDATA=$(wget -qO- "$UPSTREAM_API/listid.php?sortByDate=1" 2>/dev/null) || {
    echo "[sync] Failed to fetch upstream listid"
    exit 1
}

COUNT=$(echo "$LISTDATA" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
$builds = $d["response"]["builds"] ?? [];
echo count($builds);
')
echo "[sync] Upstream has $COUNT builds"

# Write metadata files for each build (listid now scans metadata/)
echo "$LISTDATA" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
$builds = $d["response"]["builds"] ?? [];
$metaDir = $argv[1];
$wrote = 0;

foreach ($builds as $b) {
    $uuid = $b["uuid"];
    $metaFile = "$metaDir/$uuid.json";

    // Skip if already exists
    if (file_exists($metaFile)) continue;

    $meta = [
        "title" => $b["title"],
        "build" => $b["build"],
        "arch" => $b["arch"],
        "created" => $b["created"],
    ];

    file_put_contents($metaFile, json_encode($meta) . "\n");
    $wrote++;
}

echo "$wrote new metadata entries written\n";
' "$METADATA_DIR"

# Invalidate listid cache so new data is picked up
rm -f "$FILEINFO_DIR/cache.json"
rm -f /var/www/html/cache/listid-*.json

# --- 2. Run fetchupd for key configurations to populate full fileinfo ---
echo "[sync] Running fetchupd for key configurations..."

fetch() {
    wget -qO /dev/null "$LOCAL_API/fetchupd.php?arch=$1&ring=$2&flight=Active&build=$3&sku=$4" 2>/dev/null || true
    sleep 11
}

# Retail channel - common builds
for BUILD in 26100 26200; do
    for ARCH in amd64 arm64; do
        fetch "$ARCH" "Retail" "$BUILD" "48"
    done
done

# Insider channels
for ARCH in amd64 arm64; do
    fetch "$ARCH" "WIF" "26100" "48"
    fetch "$ARCH" "WIS" "26100" "48"
    fetch "$ARCH" "RP" "26100" "48"
done

# --- 3. Generate packs for builds that don't have them ---
echo "[sync] Generating packs for new builds..."
cd /var/www/html && php packsgen.php

echo "[sync] Done"
