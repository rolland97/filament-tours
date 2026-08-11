<?php

// FR-023 / SC-008: the archive a consumer installs carries the runtime and
// nothing else.
//
// Verified manually in T079/T080, which caught nothing afterwards because
// nothing re-ran it. A directory added later — say docs/adr, or a fixtures
// folder — would ship silently, and a published tag cannot be retracted.

function archivedFiles(): array
{
    $root = dirname(__DIR__, 2);

    // Archive the current index, not HEAD.
    //
    // `git archive HEAD` reads the *committed* .gitattributes, so a broken
    // export-ignore rule would pass here and fail only one commit later —
    // confirmed by planting exactly that regression and watching this stay green.
    //
    // write-tree, not `git stash create`: stash also captures worktree files
    // that are staged for deletion but still sit on disk, which reported
    // `.claude/` as shipping after the index had already dropped it. A release
    // ships a commit, and write-tree is what a commit of this index would hold.
    //
    // `git -C` and an output file, rather than `cd … && git archive | tar -t`.
    // Chained POSIX shell commands and pipes are not valid under cmd.exe, which
    // is how the first version of this passed everywhere and failed only on the
    // Windows leg of CI. Letting git write a zip and reading it with PHP needs
    // no shell features at all.
    $tree = trim((string) shell_exec(sprintf('git -C %s write-tree', escapeshellarg($root)))) ?: 'HEAD';

    $zipPath = tempnam(sys_get_temp_dir(), 'ft-dist-') . '.zip';

    exec(
        sprintf(
            'git -C %s archive --format=zip --output=%s %s',
            escapeshellarg($root),
            escapeshellarg($zipPath),
            escapeshellarg($tree),
        ),
        $output,
        $status,
    );

    expect($status)->toBe(0, 'git archive failed; is this a git checkout?');

    $zip = new ZipArchive;

    expect($zip->open($zipPath))->toBeTrue("could not read the archive at {$zipPath}");

    $files = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);

        if (! str_ends_with($name, '/')) {
            $files[] = $name;
        }
    }

    $zip->close();
    @unlink($zipPath);

    return $files;
}

// CI installs ext-zip, but a contributor's machine might not. Skipping is
// honest; failing would blame them for something unrelated to their change.
beforeEach(function () {
    if (! class_exists(ZipArchive::class)) {
        test()->markTestSkipped('ext-zip is needed to inspect the distribution archive.');
    }
});

it('ships the runtime a consumer cannot work without', function () {
    $files = archivedFiles();

    expect($files)->toContain('composer.json')
        ->and($files)->toContain('config/filament-tours.php')
        ->and($files)->toContain('resources/views/tours.blade.php')
        // The built assets especially: the package is inert without them and no
        // consumer can rebuild them, because the sources are excluded below.
        ->and($files)->toContain('resources/dist/components/filament-tours.js')
        ->and($files)->toContain('resources/dist/components/filament-tours.css');

    expect(array_filter($files, fn (string $f): bool => str_starts_with($f, 'src/')))
        ->not->toBeEmpty();
});

it('ships no development tooling or build sources', function () {
    $files = archivedFiles();

    // .claude/ and .specify/ are gitignored rather than export-ignored, so they
    // cannot reach the archive at all. Still asserted: gitignore is one edit
    // away from being undone, and this is the assertion that would notice.
    $forbidden = [
        '.specify/',      // spec-kit installation (gitignored, bar two carve-outs)
        '.claude/',       // agent configuration (gitignored)
        'specs/',         // specification artifacts (tracked, export-ignored)
        'AGENTS.md',      // tracked, export-ignored
        'tests/',         // this suite
        'docs/',          // internal documentation
        'bin/',           // build scripts
        'resources/js/',  // unbuilt sources
        'resources/css/',
    ];

    foreach ($forbidden as $prefix) {
        expect(array_filter($files, fn (string $f): bool => str_starts_with($f, $prefix)))
            ->toBeEmpty("[{$prefix}] is in the distribution archive; add it to .gitattributes as export-ignore.");
    }

    foreach (['package.json', 'package-lock.json', 'composer.lock', 'phpunit.xml.dist'] as $file) {
        expect($files)->not->toContain($file);
    }
});
