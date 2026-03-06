<?php

namespace CoodexEs\LaravelEditingBy\Tests\Feature;

use CoodexEs\LaravelEditingBy\Events\EditingReleased;
use CoodexEs\LaravelEditingBy\Events\EditingStarted;
use CoodexEs\LaravelEditingBy\Events\EditingTakenOver;
use CoodexEs\LaravelEditingBy\Exceptions\ModelIsBeingEditedException;
use CoodexEs\LaravelEditingBy\Models\Editing;
use CoodexEs\LaravelEditingBy\Tests\TestCase;
use CoodexEs\LaravelEditingBy\Tests\TestSupport\TestItem;
use CoodexEs\LaravelEditingBy\Tests\TestSupport\TestUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

class HasEditingByTest extends TestCase
{
    public function test_mark_editing_creates_a_record_for_the_current_user(): void
    {
        Event::fake([EditingStarted::class]);

        $user = TestUser::query()->create(['name' => 'Alice']);
        $item = TestItem::query()->create(['name' => 'Draft']);
        Auth::login($user);

        $item->markEditing();

        $editing = Editing::query()->first();

        $this->assertNotNull($editing);
        $this->assertSame((string) $user->id, (string) $editing->user_id);
        Event::assertDispatched(EditingStarted::class);
    }

    public function test_mark_editing_throws_when_another_user_is_editing(): void
    {
        $alice = TestUser::query()->create(['name' => 'Alice']);
        $bob = TestUser::query()->create(['name' => 'Bob']);
        $item = TestItem::query()->create(['name' => 'Draft']);

        Auth::login($alice);
        $item->markEditing();

        Auth::login($bob);

        $this->expectException(ModelIsBeingEditedException::class);

        $item->markEditing();
    }

    public function test_mark_editing_extends_the_current_users_expiration(): void
    {
        $user = TestUser::query()->create(['name' => 'Alice']);
        $item = TestItem::query()->create(['name' => 'Draft']);
        Auth::login($user);

        $item->markEditing();
        $firstExpiration = $item->editing()->first()->expiration;

        Carbon::setTestNow(now()->addSeconds(5));
        $item->markEditing();
        $secondExpiration = $item->editing()->first()->expiration;
        Carbon::setTestNow();

        $this->assertTrue($secondExpiration->gt($firstExpiration));
    }

    public function test_is_being_edited_ignores_the_current_user(): void
    {
        $user = TestUser::query()->create(['name' => 'Alice']);
        $item = TestItem::query()->create(['name' => 'Draft']);
        Auth::login($user);
        $item->markEditing();

        $this->assertFalse($item->isBeingEdited());
    }

    public function test_take_over_editing_reassigns_the_record_and_dispatches_event(): void
    {
        Event::fake([EditingTakenOver::class]);

        $alice = TestUser::query()->create(['name' => 'Alice']);
        $bob = TestUser::query()->create(['name' => 'Bob']);
        $item = TestItem::query()->create(['name' => 'Draft']);

        Auth::login($alice);
        $item->markEditing();

        Auth::login($bob);
        $item->takeOverEditing();

        $this->assertSame((string) $bob->id, (string) $item->editing()->first()->user_id);
        Event::assertDispatched(EditingTakenOver::class);
    }

    public function test_release_editing_deletes_the_record(): void
    {
        Event::fake([EditingReleased::class]);

        $user = TestUser::query()->create(['name' => 'Alice']);
        $item = TestItem::query()->create(['name' => 'Draft']);
        Auth::login($user);
        $item->markEditing();

        $item->releaseEditing();

        $this->assertDatabaseCount('model_editings', 0);
        Event::assertDispatched(EditingReleased::class);
    }

    public function test_with_active_editor_adds_editor_columns_on_sqlite(): void
    {
        $alice = TestUser::query()->create([
            'name' => 'Alice',
            'surname' => 'Johnson',
            'email' => 'alice@example.com',
        ]);
        $bob = TestUser::query()->create(['name' => 'Bob']);
        $item = TestItem::query()->create(['name' => 'Draft']);

        Auth::login($alice);
        $item->markEditing();

        Auth::login($bob);

        $loadedItem = TestItem::query()->withActiveEditor()->first();

        $this->assertSame((string) $alice->id, (string) $loadedItem->editing_by_user_id);
        $this->assertSame('Alice', $loadedItem->editing_by_name);
        $this->assertSame('Johnson', $loadedItem->editing_by_surname);
        $this->assertSame('alice@example.com', $loadedItem->editing_by_email);
        $this->assertSame('Alice Johnson', $loadedItem->editing_by_fullname);
    }
}
