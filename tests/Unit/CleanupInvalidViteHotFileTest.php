<?php

use App\Support\CleanupInvalidViteHotFile;

beforeEach(function () {
    $this->tempHot = sys_get_temp_dir().'/vgc-draft-hot-test-'.uniqid('', true);
});

afterEach(function () {
    if (is_file($this->tempHot)) {
        unlink($this->tempHot);
    }
});

it('deletes empty hot file when local', function () {
    file_put_contents($this->tempHot, '');

    CleanupInvalidViteHotFile::deleteIfInvalid(true, $this->tempHot);

    expect(is_file($this->tempHot))->toBeFalse();
});

it('deletes hot file with only whitespace when local', function () {
    file_put_contents($this->tempHot, "  \n  ");

    CleanupInvalidViteHotFile::deleteIfInvalid(true, $this->tempHot);

    expect(is_file($this->tempHot))->toBeFalse();
});

it('deletes hot file without http scheme when local', function () {
    file_put_contents($this->tempHot, 'not-a-url');

    CleanupInvalidViteHotFile::deleteIfInvalid(true, $this->tempHot);

    expect(is_file($this->tempHot))->toBeFalse();
});

it('keeps valid hot file when local', function () {
    file_put_contents($this->tempHot, 'http://127.0.0.1:5173');

    CleanupInvalidViteHotFile::deleteIfInvalid(true, $this->tempHot);

    expect(is_file($this->tempHot))->toBeTrue();
    expect(trim((string) file_get_contents($this->tempHot)))->toBe('http://127.0.0.1:5173');
});

it('does nothing when not local', function () {
    file_put_contents($this->tempHot, '');

    CleanupInvalidViteHotFile::deleteIfInvalid(false, $this->tempHot);

    expect(is_file($this->tempHot))->toBeTrue();
});

it('does nothing when path missing', function () {
    CleanupInvalidViteHotFile::deleteIfInvalid(true, $this->tempHot.'/does-not-exist');

    expect(is_file($this->tempHot.'/does-not-exist'))->toBeFalse();
});
