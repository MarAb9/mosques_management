import { copyFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'node_modules', '@mapbox', 'mapbox-gl-rtl-text', 'dist', 'mapbox-gl-rtl-text.js');
const destinationDirectory = path.join(root, 'public', 'assets', 'dist');
const destination = path.join(destinationDirectory, 'mapbox-gl-rtl-text.js');

await mkdir(destinationDirectory, { recursive: true });
await copyFile(source, destination);
