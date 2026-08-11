<?php

use App\Jobs\CleanOrphanMediaJob;
use App\Jobs\OptimizeImageJob;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();

    // Create an admin user matching our Sanctum credentials
    $this->admin = User::factory()->create([
        'email' => 'admin@larashop.test',
    ]);
});

test('unauthenticated users cannot access media endpoints', function () {
    $this->getJson('/api/admin/media')->assertStatus(401);
    $this->postJson('/api/admin/media')->assertStatus(401);
    $this->getJson('/api/admin/media-folders')->assertStatus(401);
});

test('admin can manage media folders', function () {
    // 1. Create Folder
    $response = $this->actingAs($this->admin)
        ->postJson('/api/admin/media-folders', [
            'name' => 'Banner Folder',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Banner Folder');

    $folderId = $response->json('data.id');

    // 2. Create Child Folder
    $this->actingAs($this->admin)
        ->postJson('/api/admin/media-folders', [
            'name' => 'Spring Campaign',
            'parent_id' => $folderId,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.parent_id', $folderId);

    // 3. Show Folder
    $this->actingAs($this->admin)
        ->getJson("/api/admin/media-folders/{$folderId}")
        ->assertStatus(200)
        ->assertJsonPath('data.children.0.name', 'Spring Campaign');

    // 4. Update Folder
    $this->actingAs($this->admin)
        ->putJson("/api/admin/media-folders/{$folderId}", [
            'name' => 'Banners Main',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Banners Main');
});

test('cannot delete a folder if it contains subfolders or media files', function () {
    $parent = MediaFolder::create(['name' => 'Parent']);
    $child = MediaFolder::create(['name' => 'Child', 'parent_id' => $parent->id]);

    // Deleting parent should fail because it has child folder
    $this->actingAs($this->admin)
        ->deleteJson("/api/admin/media-folders/{$parent->id}")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Folder cannot be deleted because it contains files or subfolders.']);

    // Delete child first
    $this->actingAs($this->admin)
        ->deleteJson("/api/admin/media-folders/{$child->id}")
        ->assertStatus(204);

    // Deleting parent should now succeed
    $this->actingAs($this->admin)
        ->deleteJson("/api/admin/media-folders/{$parent->id}")
        ->assertStatus(204);
});

test('admin can upload files and deduplication works', function () {
    $imageFile = UploadedFile::fake()->image('product.jpg', 800, 600);

    // First upload
    $response1 = $this->actingAs($this->admin)
        ->postJson('/api/admin/media', [
            'file' => $imageFile,
            'tags' => ['sale', 'summer'],
        ]);

    $response1->assertStatus(201);
    $mediaId1 = $response1->json('data.id');
    expect($mediaId1)->not->toBeNull();

    // Verify optimization job was dispatched
    Queue::assertPushed(OptimizeImageJob::class);

    // Simulate second upload of the exact same file
    $response2 = $this->actingAs($this->admin)
        ->postJson('/api/admin/media', [
            'file' => $imageFile,
        ]);

    $response2->assertStatus(201);
    $mediaId2 = $response2->json('data.id');

    // Deduplication should return the EXACT same Media row ID
    expect($mediaId2)->toBe($mediaId1);
    expect(Media::count())->toBe(1); // Only 1 row in media table!
});

test('admin can bulk upload multiple files', function () {
    // Use different dimensions so they have different content/hashes
    $file1 = UploadedFile::fake()->image('image1.jpg', 100, 100);
    $file2 = UploadedFile::fake()->image('image2.jpg', 200, 200);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/admin/media', [
            'files' => [$file1, $file2],
            'tags' => ['bulk'],
        ]);

    $response->assertStatus(201)
        ->assertJsonCount(2, 'data');

    expect(Media::count())->toBe(2);
});

test('admin can list, search, and filter media assets', function () {
    $folder = MediaFolder::create(['name' => 'Uploads']);

    // Create seed files
    $media1 = Media::create([
        'folder_id' => $folder->id,
        'uploaded_by' => $this->admin->id,
        'name' => 'special-laptop.jpg',
        'filename' => 'special-laptop.webp',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/webp',
        'size' => 1024,
        'hash' => 'hash1',
    ]);

    $media2 = Media::create([
        'uploaded_by' => $this->admin->id,
        'name' => 'review-video.mp4',
        'filename' => 'review-video.mp4',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'video/mp4',
        'size' => 4096,
        'hash' => 'hash2',
    ]);

    // List all
    $this->actingAs($this->admin)
        ->getJson('/api/admin/media')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data');

    // Filter by search
    $this->actingAs($this->admin)
        ->getJson('/api/admin/media?search=laptop')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'special-laptop.jpg');

    // Filter by mime type
    $this->actingAs($this->admin)
        ->getJson('/api/admin/media?mime_type=video')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.mime_type', 'video/mp4');

    // Filter by folder
    $this->actingAs($this->admin)
        ->getJson("/api/admin/media?folder_id={$folder->id}")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');
});

test('admin can edit image properties using GD (crop, rotate)', function () {
    // Save fake image content to fake disk so service can read it
    $disk = Storage::disk('public');

    // Create a real small JPEG image using GD in-memory and get its content
    $im = imagecreatetruecolor(100, 100);
    ob_start();
    imagejpeg($im);
    $jpegData = ob_get_clean();
    imagedestroy($im);

    $filename = 'to-edit.jpg';
    $disk->put("media/{$filename}", $jpegData);

    $media = Media::create([
        'uploaded_by' => $this->admin->id,
        'name' => 'to-edit.jpg',
        'filename' => $filename,
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/jpeg',
        'size' => strlen($jpegData),
        'width' => 100,
        'height' => 100,
        'hash' => hash('sha256', $jpegData),
        'conversions' => [
            'thumbnail' => ['path' => 'media/to-edit_thumb.webp'],
        ],
    ]);

    // Perform crop and rotate 90
    $response = $this->actingAs($this->admin)
        ->patchJson("/api/admin/media/{$media->id}/crop", [
            'crop_x' => 0,
            'crop_y' => 0,
            'crop_width' => 50,
            'crop_height' => 50,
            'rotate' => 90,
        ]);

    $response->assertStatus(200);

    // Check that original dimensions in database were updated to cropped sizes
    $media->refresh();
    expect($media->width)->toBe(50);
    expect($media->height)->toBe(50);
    expect($media->conversions)->toBeEmpty(); // Old conversions should be cleared

    // Check that re-optimization was queued
    Queue::assertPushed(OptimizeImageJob::class);
});

test('admin can bulk delete media assets', function () {
    $disk = Storage::disk('public');
    $disk->put('media/file1.png', 'content');

    $media = Media::create([
        'uploaded_by' => $this->admin->id,
        'name' => 'file1.png',
        'filename' => 'file1.png',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/png',
        'size' => 7,
        'hash' => 'hash123',
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/admin/media', [
            'ids' => [$media->id],
        ])
        ->assertStatus(204);

    expect(Media::find($media->id))->toBeNull();
    // Physical file must be deleted from storage
    expect($disk->exists('media/file1.png'))->toBeFalse();
});

test('orphan media cleanup job purges older unattached files', function () {
    // 1. Create a media file that has no polymorphic links
    $oldOrphan = Media::create([
        'name' => 'orphan.jpg',
        'filename' => 'orphan.jpg',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/jpeg',
        'size' => 100,
        'hash' => 'hash-old',
    ]);

    // Force date change using DB query to bypass Eloquent timestamp updates
    DB::table('media')->where('id', $oldOrphan->id)->update([
        'created_at' => now()->subHours(30),
    ]);

    // 2. Create a media file that is new (created 2 hours ago) and has no links
    $newOrphan = Media::create([
        'name' => 'new-orphan.jpg',
        'filename' => 'new-orphan.jpg',
        'path' => 'media',
        'disk' => 'public',
        'mime_type' => 'image/jpeg',
        'size' => 100,
        'hash' => 'hash-new',
    ]);

    DB::table('media')->where('id', $newOrphan->id)->update([
        'created_at' => now()->subHours(2),
    ]);

    // Run job handler directly to bypass QueueFake
    (new CleanOrphanMediaJob)->handle();

    // Old orphan should be deleted
    expect(Media::find($oldOrphan->id))->toBeNull();
    // New orphan should be kept
    expect(Media::find($newOrphan->id))->not->toBeNull();
});

test('admin can retrieve storage statistics', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/media/storage-stats')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total_size',
                'original_size',
                'conversions_size',
                'file_count',
                'image_count',
                'video_count',
                'doc_count',
                'storage_limit',
                'usage_percentage',
            ],
        ]);
});
