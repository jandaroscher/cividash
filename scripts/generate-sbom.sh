#!/usr/bin/env bash
# Generate the release SBOM from composer.lock and package-lock.json.
#
# Usage:
#   bash scripts/generate-sbom.sh
#
# Writes SBOM.cdx.json (CycloneDX 1.6, runtime dependencies only) and SBOM.csv
# (name, version, license, ecosystem) to the repository root.
#
# Requires Docker, Node.js (npx) and python3. The CycloneDX tools run in a
# throwaway container or via npx, so no dependency is added to the project.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

cd "$ROOT"

# Composer: the plugin is installed into the container's global home, not the project.
cp composer.json composer.lock "$TMP/"
docker run --rm -v "$TMP":/app -w /app composer:2 sh -c '
    composer global config --no-plugins allow-plugins.cyclonedx/cyclonedx-php-composer true -q &&
    composer global require -q cyclonedx/cyclonedx-php-composer &&
    composer CycloneDX:make-sbom --omit=dev --output-format=JSON --spec-version=1.6 --output-file=php.cdx.json
'

# npm: reads package-lock.json directly, node_modules is not needed.
npx -y @cyclonedx/cyclonedx-npm --package-lock-only --omit dev \
    --output-format JSON --spec-version 1.6 --output-file "$TMP/npm.cdx.json"

# Merge both into one BOM with the Composer root as subject and the npm root as
# its dependency, then write the CSV.
TMP="$TMP" python3 - <<'PY'
import csv
import json
import os
import uuid

tmp = os.environ['TMP']
with open(f'{tmp}/php.cdx.json') as f:
    php = json.load(f)
with open(f'{tmp}/npm.cdx.json') as f:
    npm = json.load(f)


def is_dev(component):
    return any(p.get('name') == 'cdx:npm:package:development' and p.get('value') == 'true'
               for p in component.get('properties', []))


def flatten(components):
    # npm nests packages installed below a parent; flatten them and drop the
    # build-only ones that --omit dev still reaches through peer/optional edges.
    for c in components:
        if is_dev(c):
            continue
        children = c.pop('components', [])
        yield c
        yield from flatten(children)


php_root = php['metadata']['component']
npm_root = dict(npm['metadata']['component'])
npm_root.pop('components', None)

components, refs = [], set()
for c in php.get('components', []) + [npm_root] + list(flatten(npm.get('components', []))):
    if c['bom-ref'] not in refs and c['bom-ref'] != php_root['bom-ref']:
        refs.add(c['bom-ref'])
        components.append(c)

dependencies = []
for d in php.get('dependencies', []) + npm.get('dependencies', []):
    depends_on = [r for r in d.get('dependsOn', []) if r in refs]
    if d['ref'] == php_root['bom-ref']:
        depends_on.append(npm_root['bom-ref'])
    elif d['ref'] not in refs:
        continue
    dependencies.append({**d, 'dependsOn': depends_on})

bom = {**php, 'serialNumber': f'urn:uuid:{uuid.uuid4()}', 'components': components,
       'dependencies': dependencies}
with open('SBOM.cdx.json', 'w') as f:
    json.dump(bom, f, indent=2)
    f.write('\n')


def licenses(component):
    names = []
    for entry in component.get('licenses', []):
        if 'expression' in entry:
            names.append(entry['expression'])
        else:
            lic = entry.get('license', {})
            names.append(lic.get('id') or lic.get('name') or '')
    return ' OR '.join(n for n in names if n)


def ecosystem(component):
    purl = component.get('purl', '')
    return purl[4:].split('/', 1)[0] if purl.startswith('pkg:') else ''


rows = set()
for c in components:
    name = f"{c['group']}/{c['name']}" if c.get('group') else c['name']
    rows.add((name, c.get('version', ''), licenses(c), ecosystem(c)))

with open('SBOM.csv', 'w', newline='') as f:
    writer = csv.writer(f, lineterminator='\n')
    writer.writerow(['name', 'version', 'license', 'ecosystem'])
    writer.writerows(sorted(rows))
PY

echo "Wrote SBOM.cdx.json and SBOM.csv"
