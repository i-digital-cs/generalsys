<?php

namespace Tests\Feature\Employee;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T-6.6 — optional multi photos on clock-in / clock-out + authorized view.
 */
class AttendancePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private User $leader;

    private User $manager;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->leader = User::factory()->teamLeader()->create();
        $this->manager = User::factory()->manager()->create();
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);
        $this->otherEmployee = User::factory()->employee()->create([
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->freezeClockToMorningShift();
    }

    public function test_clock_screen_shows_optional_photo_input(): void
    {
        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.photos.label'), false)
            ->assertSee('name="photos[]"', false)
            ->assertSee('data-clock-photos', false)
            ->assertSee(__('clock.photos.optional'), false)
            ->assertSee(__('clock.photos.description'), false);
    }

    public function test_clock_in_and_out_accept_optional_multi_photos(): void
    {
        $inA = UploadedFile::fake()->image('in-a.jpg', 200, 200);
        $inB = UploadedFile::fake()->image('in-b.png', 200, 200);

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [$inA, $inB],
                'photo_descriptions' => ['Front desk', 'Kitchen'],
            ]))
            ->assertRedirect(route('employee.clock'));

        $attendance = Attendance::where('user_id', $this->employee->id)->firstOrFail();
        $this->assertSame(2, $attendance->photos()->where('kind', AttendancePhoto::KIND_CLOCK_IN)->count());
        $this->assertSame(
            ['Front desk', 'Kitchen'],
            $attendance->photos()->where('kind', AttendancePhoto::KIND_CLOCK_IN)->orderBy('sort_order')->pluck('description')->all(),
        );

        $inPhotos = $attendance->photos()->where('kind', AttendancePhoto::KIND_CLOCK_IN)->get();
        $this->assertTrue($inPhotos->every(fn (AttendancePhoto $photo) => $photo->lat !== null && $photo->lng !== null));
        $this->assertEqualsWithDelta(30.0444, (float) $inPhotos->first()->lat, 0.0001);
        $this->assertEqualsWithDelta(31.2357, (float) $inPhotos->first()->lng, 0.0001);

        foreach ($attendance->photos as $photo) {
            Storage::disk('local')->assertExists($photo->file_path);
        }

        $this->actingAs($this->employee)
            ->get(route('employee.clock'))
            ->assertOk()
            ->assertSee(__('clock.photos.in_label'), false)
            ->assertSee('Front desk', false)
            ->assertSee('Kitchen', false)
            ->assertSee(route('attendance.photos.show', $attendance->photos->first()), false);

        $out = UploadedFile::fake()->image('out.webp', 200, 200);

        Attendance::where('user_id', $this->employee->id)->firstOrFail()
            ->update(['daily_report' => 'Done.']);

        $this->actingAs($this->employee)
            ->post(route('employee.clock.out'), $this->withClockGps([
                'photos' => [$out],
                'photo_descriptions' => ['Leaving site'],
            ]))
            ->assertRedirect(route('employee.clock'));

        $this->assertSame(1, $attendance->fresh()->photos()->where('kind', AttendancePhoto::KIND_CLOCK_OUT)->count());
        $this->assertSame('Leaving site', $attendance->fresh()->photos()->where('kind', AttendancePhoto::KIND_CLOCK_OUT)->value('description'));
        $outPhoto = $attendance->fresh()->photos()->where('kind', AttendancePhoto::KIND_CLOCK_OUT)->firstOrFail();
        $this->assertNotNull($outPhoto->lat);
        $this->assertNotNull($outPhoto->lng);
        $this->assertSame(3, $attendance->fresh()->photos()->count());
    }

    public function test_clock_in_without_photos_still_works(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
            ]))
            ->assertRedirect(route('employee.clock'));

        $this->assertSame(0, AttendancePhoto::count());
    }

    public function test_rejects_non_image_mime(): void
    {
        $pdf = UploadedFile::fake()->create('note.pdf', 100, 'application/pdf');

        $this->actingAs($this->employee)
            ->from(route('employee.clock'))
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [$pdf],
            ]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, Attendance::where('user_id', $this->employee->id)->count());
    }

    public function test_accepts_more_than_five_photos(): void
    {
        $files = [];
        for ($i = 0; $i < 8; $i++) {
            $files[] = UploadedFile::fake()->image("shot-{$i}.jpg");
        }

        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => $files,
            ]))
            ->assertRedirect(route('employee.clock'))
            ->assertSessionHasNoErrors();

        $attendance = Attendance::where('user_id', $this->employee->id)->firstOrFail();
        $this->assertSame(8, $attendance->photos()->count());
    }

    public function test_photo_view_authorization_matrix(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [UploadedFile::fake()->image('proof.jpg')],
            ]));

        $photo = AttendancePhoto::firstOrFail();

        $this->actingAs($this->employee)
            ->get(route('attendance.photos.show', $photo))
            ->assertOk();

        $this->actingAs($this->leader)
            ->get(route('attendance.photos.show', $photo))
            ->assertOk();

        $this->actingAs($this->manager)
            ->get(route('attendance.photos.show', $photo))
            ->assertOk();

        $this->actingAs($this->otherEmployee)
            ->get(route('attendance.photos.show', $photo))
            ->assertForbidden();
    }

    public function test_manager_can_view_team_leader_punch_photo(): void
    {
        $tl = User::factory()->teamLeader()->create([
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $this->actingAs($tl)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [UploadedFile::fake()->image('tl.jpg')],
            ]));

        $photo = AttendancePhoto::firstOrFail();

        $this->actingAs($this->manager)
            ->get(route('attendance.photos.show', $photo))
            ->assertOk();

        $this->actingAs($this->employee)
            ->get(route('attendance.photos.show', $photo))
            ->assertForbidden();
    }

    public function test_today_board_shows_photos_for_team_leader(): void
    {
        $this->actingAs($this->employee)
            ->post(route('employee.clock.in'), $this->withClockGps([
                'shift_type' => Attendance::SHIFT_FULL_TIME,
                'photos' => [UploadedFile::fake()->image('board.jpg')],
            ]));

        $photo = AttendancePhoto::firstOrFail();

        $this->actingAs($this->leader)
            ->get(route('team-leader.dashboard'))
            ->assertOk()
            ->assertSee(__('clock.photos.title'), false)
            ->assertSee(route('attendance.photos.show', $photo), false);
    }
}
