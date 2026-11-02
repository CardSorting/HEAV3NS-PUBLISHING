const fs = require('node:fs');
const path = require('node:path');

const preservedDirectories = ['livewire'];

function publishDirectory(source, destination) {
    fs.mkdirSync(destination, { recursive: true });
    for (const entry of fs.readdirSync(source)) {
        const sourcePath = path.join(source, entry);
        const destinationPath = path.join(destination, entry);
        if (fs.statSync(sourcePath).isDirectory()) {
            publishDirectory(sourcePath, destinationPath);
        } else {
            fs.writeFileSync(destinationPath, fs.readFileSync(sourcePath));
        }
    }
}

function stageLibraries(root, stagingRoot) {
    const packages = Object.keys(require(path.join(root, 'package.json')).dependencies);
    for (const name of packages) {
        if (!fs.existsSync(path.join(root, 'node_modules', name, 'package.json'))) {
            throw new Error(`Missing ${name}; run npm ci before building.`);
        }
    }
    fs.mkdirSync(stagingRoot, { recursive: true });
    for (const name of packages) {
        publishDirectory(path.join(root, 'node_modules', name), path.join(stagingRoot, name));
    }

    for (const directory of preservedDirectories) {
        const source = path.join(root, 'public/libs', directory);
        if (fs.existsSync(source)) {
            publishDirectory(source, path.join(stagingRoot, directory));
        }
    }
    return packages.length;
}

// All inputs are finished before this starts. Keep backups until every swap succeeds.
function publishOutputs(entries, { id, fileSystem = fs, warn = console.warn }) {
    const outputs = entries.map(entry => ({
        ...entry,
        // Same-parent backups also work when an existing library directory is owned
        // by another build user (moving it between parents needs extra permissions).
        backup: path.join(path.dirname(entry.destination), `.frontend-backup-${id}-${path.basename(entry.destination)}`),
        backedUp: false,
        installed: false,
    }));
    try {
        for (const output of outputs) {
            fileSystem.mkdirSync(path.dirname(output.destination), { recursive: true });
            if (fileSystem.existsSync(output.destination)) {
                fileSystem.renameSync(output.destination, output.backup);
                output.backedUp = true;
            }
            fileSystem.renameSync(output.source, output.destination);
            output.installed = true;
        }
    } catch (error) {
        const failures = [];
        for (const output of [...outputs].reverse()) {
            try {
                if (output.installed) fileSystem.renameSync(output.destination, output.source);
                if (output.backedUp) fileSystem.renameSync(output.backup, output.destination);
            } catch (rollbackError) {
                failures.push(`${output.destination}: ${rollbackError.message}`);
            }
        }
        if (failures.length) {
            error.rollbackFailed = true;
            error.message += `\nAsset rollback needs manual recovery; staging and backups retained.\n${failures.join('\n')}`;
        }
        throw error;
    }

    for (const output of outputs.filter(output => output.backedUp)) {
        try {
            fileSystem.rmSync(output.backup, { recursive: true });
        } catch (error) {
            // Publishing succeeded; a permissions problem during cleanup must not
            // trigger a rollback after other backups have already been removed.
            warn(`Assets published, but could not remove backup ${output.backup}: ${error.message}`);
        }
    }
}

module.exports = { stageLibraries, publishOutputs };
