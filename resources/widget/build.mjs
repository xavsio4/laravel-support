import { build } from 'esbuild';
import { gzipSync } from 'node:zlib';
import { readFileSync, readdirSync, rmSync } from 'node:fs';

// Loaded on every page of the host app, so it stays small. There is nothing
// heavy to split off yet; ESM keeps the door open for lazy chunks later.
const BUDGET_GZIP_BYTES = 12 * 1024;

const outdir = '../../dist';

rmSync(outdir, { recursive: true, force: true });

await build({
    entryPoints: ['src/index.ts'],
    outdir,
    bundle: true,
    minify: true,
    format: 'esm',
    splitting: true,
    target: ['es2020'],
    entryNames: 'support',
    chunkNames: 'support-[name]-[hash]',
    legalComments: 'none',
});

let coreGzip = 0;

for (const file of readdirSync(outdir).filter((f) => f.endsWith('.js')).sort()) {
    const bytes = readFileSync(`${outdir}/${file}`);
    const gzipped = gzipSync(bytes).length;
    if (file === 'support.js') coreGzip = gzipped;

    console.log(`${file.padEnd(28)} ${(bytes.length / 1024).toFixed(1).padStart(6)}KB raw ${(gzipped / 1024).toFixed(2).padStart(6)}KB gzipped`);
}

if (coreGzip > BUDGET_GZIP_BYTES) {
    console.error(`\nsupport.js is ${(coreGzip / 1024).toFixed(2)}KB gzipped, over the ${BUDGET_GZIP_BYTES / 1024}KB budget.`);
    process.exit(1);
}

console.log(`\nWithin budget (${(coreGzip / 1024).toFixed(2)}KB / ${BUDGET_GZIP_BYTES / 1024}KB gzipped).`);
