const fs = require('node:fs');
const path = require('node:path');
const { stageLibraries, publishOutputs } = require('./publish-assets.cjs');

async function compileStyles(root, stagingRoot) {
    const sass = require('sass');
    const rtlcss = require('rtlcss');
    const postcss = require('postcss');
    const css = sass.compile(path.join(root, 'resources/sass/bootstrap.scss'), {
        loadPaths: [path.join(root, 'node_modules')],
        style: 'expanded',
        sourceMap: false,
        quietDeps: true,
        silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'slash-div'],
    }).css;
    const rtl = rtlcss.process(css, require(path.join(root, 'rtlcss.config.json')).options);
    const { plugins } = require(path.join(root, 'postcss.config.cjs'));
    fs.mkdirSync(stagingRoot, { recursive: true });
    for (const [filename, source] of [['bootstrap.css', css], ['bootstrap.rtl.css', rtl]]) {
        const target = path.join(root, 'public/css/theme', filename);
        const result = await postcss(plugins).process(source, { from: target, to: target, map: false });
        fs.writeFileSync(path.join(stagingRoot, filename), result.css);
    }
}

async function runBuild(root, { mode = 'all', compile = compileStyles, fileSystem = fs, warn = console.warn } = {}) {
    if (!['all', 'css', 'libs'].includes(mode)) throw new Error(`Unknown build mode: ${mode}`);
    fs.mkdirSync(path.join(root, 'storage/app'), { recursive: true });
    const lock = path.join(root, 'storage/app/frontend-build.lock');
    let descriptor;
    try {
        descriptor = fs.openSync(lock, 'wx');
    } catch (error) {
        if (error.code === 'EEXIST') {
            throw new Error(`Another frontend build holds ${lock}. If interrupted, verify it has stopped before removing this lock.`);
        }
        throw error;
    }

    let stagingRoot;
    let retainStaging = false;
    try {
        fs.writeFileSync(descriptor, `pid=${process.pid}\n`);
        stagingRoot = fs.mkdtempSync(path.join(root, 'public/.frontend-build-'));
        const entries = [];
        if (mode !== 'libs') {
            const cssRoot = path.join(stagingRoot, 'css');
            await compile(root, cssRoot);
            for (const filename of ['bootstrap.css', 'bootstrap.rtl.css']) {
                entries.push({ source: path.join(cssRoot, filename), destination: path.join(root, 'public/css/theme', filename) });
            }
        }
        let packages = 0;
        if (mode !== 'css') {
            const libsRoot = path.join(stagingRoot, 'libs');
            packages = stageLibraries(root, libsRoot);
            entries.push({ source: libsRoot, destination: path.join(root, 'public/libs') });
        }
        publishOutputs(entries, { id: path.basename(stagingRoot), fileSystem, warn });
        return { packages };
    } catch (error) {
        retainStaging = Boolean(error.rollbackFailed);
        if (retainStaging) error.message += `\nStaging: ${stagingRoot}`;
        throw error;
    } finally {
        // Always release the lock, even if staging cleanup fails.
        try {
            if (stagingRoot && !retainStaging) fs.rmSync(stagingRoot, { recursive: true });
        } finally {
            fs.closeSync(descriptor);
            fs.unlinkSync(lock);
        }
    }
}

if (require.main === module) {
    const args = process.argv.slice(2);
    const mode = args.length === 0 ? 'all'
        : args.length === 1 && args[0] === '--css-only' ? 'css'
        : args.length === 1 && args[0] === '--libs-only' ? 'libs' : 'invalid';
    runBuild(path.resolve(__dirname, '../..'), { mode })
        .then(({ packages }) => console.log(mode === 'css' ? 'Published LTR and RTL stylesheets.' : `Published ${packages} frontend packages${mode === 'all' ? ' and LTR/RTL stylesheets' : ''}.`))
        .catch(error => {
            console.error(error.message);
            process.exitCode = 1;
        });
}

module.exports = { runBuild, compileStyles };
