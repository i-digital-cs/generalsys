<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemImage;
use App\Models\User;
use App\Services\Gross\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_multiple_images_on_create_and_view_show(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();

        $files = [
            UploadedFile::fake()->image('a.jpg', 100, 100),
            UploadedFile::fake()->image('b.png', 120, 120),
        ];

        $this->actingAs($manager)
            ->post(route('gross.items.store'), [
                'name' => 'Photo Item',
                'reorder_level' => 5,
                'status' => 'ACTIVE',
                'images' => $files,
            ])
            ->assertRedirect();

        $item = GrossItem::query()->where('name', 'Photo Item')->firstOrFail();
        $this->assertSame(2, $item->images()->count());

        $image = $item->images()->firstOrFail();
        Storage::disk('local')->assertExists($image->file_path);

        $this->actingAs($manager)
            ->get(route('gross.items.show', $item))
            ->assertOk()
            ->assertSee(__('gross.items.details'), false)
            ->assertSee(route('gross.items.images.show', [$item, $image]), false);

        $this->actingAs($manager)
            ->get(route('gross.items.images.show', [$item, $image]))
            ->assertOk();
    }

    public function test_can_remove_image_on_update(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();

        $item = app(ItemService::class)->create(
            [
                'name' => 'Removable',
                'reorder_level' => 5,
                'status' => GrossItem::STATUS_ACTIVE,
            ],
            [UploadedFile::fake()->image('keep.jpg'), UploadedFile::fake()->image('drop.jpg')]
        );

        $drop = $item->images()->skip(1)->firstOrFail();
        $path = $drop->file_path;

        $this->actingAs($manager)
            ->put(route('gross.items.update', $item), [
                'name' => 'Removable',
                'reorder_level' => 5,
                'status' => 'ACTIVE',
                'remove_image_ids' => [$drop->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('gross_item_images', ['id' => $drop->id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(1, GrossItemImage::query()->where('item_id', $item->id)->count());
    }
}
