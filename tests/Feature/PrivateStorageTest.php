<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateStorageTest extends TestCase
{
    public function test_local_disk_uses_private_storage_root(): void
    {
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    public function test_private_file_is_not_served_from_public_storage_url(): void
    {
        Storage::disk('local')->put('phase-one/private-check.txt', 'private');

        $this->get('/storage/phase-one/private-check.txt')
            ->assertNotFound();
    }
}
