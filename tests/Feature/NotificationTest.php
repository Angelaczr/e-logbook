<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\User;
use App\Notifications\LogbookReviewedNotification;
use App\Notifications\LogbookSubmittedNotification;
use App\Notifications\PendingReviewReminder;
use App\Notifications\UnsubmittedLogbookReminder;
use App\Services\LogbookService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->service = app(LogbookService::class);
    $this->manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'supervisor_id' => $this->manager->id]);
    $this->employee = User::factory()->create(['supervisor_id' => $this->supervisor->id]);
});

function notifLogbook(User $owner, LogbookStatus $status, ?string $date = null, mixed $submittedAt = null): Logbook
{
    $logbook = Logbook::factory()->create([
        'user_id' => $owner->id,
        'status' => $status,
        'work_date' => $date ?? fake()->unique()->dateTimeBetween('-300 days', '-3 days')->format('Y-m-d'),
        'submitted_at' => $submittedAt,
    ]);
    LogbookItem::factory()->create(['logbook_id' => $logbook->id]);

    return $logbook;
}

describe('listener', function () {
    it('memberi tahu atasan langsung saat bawahan submit', function () {
        Notification::fake();
        $logbook = notifLogbook($this->employee, LogbookStatus::Draft);

        $this->service->submit($logbook, $this->employee);

        Notification::assertSentTo($this->supervisor, LogbookSubmittedNotification::class, function ($notification, $channels) {
            return $channels === ['database', 'mail']
                && $notification->toArray($this->supervisor)['url'] === '/reviews?review='.Logbook::first()->id;
        });
        Notification::assertSentToTimes($this->supervisor, LogbookSubmittedNotification::class, 1);
        Notification::assertNotSentTo([$this->employee, $this->manager], LogbookSubmittedNotification::class);
    });

    it('tidak mengirim jika tidak ada atasan atau atasan nonaktif', function () {
        Notification::fake();
        $loner = User::factory()->create();
        $this->service->submit(notifLogbook($loner, LogbookStatus::Draft), $loner);

        $this->supervisor->update(['is_active' => false]);
        $this->service->submit(notifLogbook($this->employee, LogbookStatus::Draft), $this->employee);

        Notification::assertNothingSent();
    });

    it('memberi tahu karyawan untuk setiap keputusan review', function (string $method, array $args, string $title) {
        Notification::fake();
        $logbook = notifLogbook($this->employee, LogbookStatus::Submitted);

        $this->service->{$method}($logbook, $this->supervisor, ...$args);

        Notification::assertSentTo($this->employee, LogbookReviewedNotification::class, function ($notification) use ($title, $args) {
            $data = $notification->toArray($this->employee);

            return $data['title'] === $title
                && str_starts_with($data['url'], '/logbooks/')
                && ($args === [] || str_contains($data['message'], $args[0]));
        });
        Notification::assertNotSentTo($this->supervisor, LogbookReviewedNotification::class);
    })->with([
        'approve' => ['approve', ['Kerja bagus'], 'Logbook Anda disetujui'],
        'revision' => ['requestRevision', ['Lengkapi uraian pekerjaan'], 'Logbook Anda perlu direvisi'],
        'reject' => ['reject', ['Tidak sesuai lingkup'], 'Logbook Anda ditolak'],
    ]);

    it('tidak mengirim notifikasi jika transisi gagal', function () {
        Notification::fake();
        $logbook = notifLogbook($this->employee, LogbookStatus::Approved);

        expect(fn () => $this->service->approve($logbook, $this->supervisor))->toThrow(Exception::class);

        Notification::assertNothingSent();
    });
});

describe('notifikasi', function () {
    it('berjalan lewat antrean dan tersimpan di database serta email', function () {
        $notification = new UnsubmittedLogbookReminder;

        expect($notification)->toBeInstanceOf(ShouldQueue::class)
            ->and($notification->via($this->employee))->toBe(['database', 'mail']);
    });

    it('menyusun email dengan tautan absolut dan data database', function () {
        $logbook = notifLogbook($this->employee, LogbookStatus::Submitted);
        $notification = new LogbookReviewedNotification($logbook, $this->supervisor, LogbookStatus::Rejected, 'Alasan ditolak');

        $mail = $notification->toMail($this->employee);

        expect($mail->subject)->toBe('Logbook Anda ditolak')
            ->and($mail->actionUrl)->toBe(url("/logbooks/{$logbook->id}"))
            ->and($mail->render()->toHtml())->toContain('Alasan ditolak')
            ->and($notification->toArray($this->employee))->toMatchArray([
                'title' => 'Logbook Anda ditolak',
                'url' => "/logbooks/{$logbook->id}",
                'logbook_id' => $logbook->id,
            ]);
    });
});

describe('pengingat harian', function () {
    it('mengingatkan karyawan yang belum submit hari ini', function () {
        Notification::fake();
        $draft = User::factory()->create();
        $submitted = User::factory()->create();
        $revision = User::factory()->create();
        $yesterdayOnly = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $none = User::factory()->create();

        notifLogbook($draft, LogbookStatus::Draft, today()->toDateString());
        notifLogbook($submitted, LogbookStatus::Submitted, today()->toDateString());
        notifLogbook($revision, LogbookStatus::RevisionRequested, today()->toDateString());
        notifLogbook($yesterdayOnly, LogbookStatus::Approved, today()->subDay()->toDateString());

        $this->artisan('logbook:remind-unsubmitted')->assertSuccessful();

        foreach ([$draft, $yesterdayOnly, $none, $this->employee] as $user) {
            Notification::assertSentTo($user, UnsubmittedLogbookReminder::class);
        }
        Notification::assertNotSentTo([$submitted, $revision, $inactive, $this->supervisor, $this->manager], UnsubmittedLogbookReminder::class);
    });

    it('mengingatkan atasan yang punya logbook pending lebih dari 2 hari', function () {
        Notification::fake();
        $other = User::factory()->create(['supervisor_id' => $this->supervisor->id]);
        $quietSupervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $quietEmployee = User::factory()->create(['supervisor_id' => $quietSupervisor->id]);

        notifLogbook($this->employee, LogbookStatus::Submitted, submittedAt: now()->subDays(3));
        notifLogbook($other, LogbookStatus::Submitted, submittedAt: now()->subDays(5));
        notifLogbook($this->employee, LogbookStatus::Submitted, submittedAt: now()->subHours(20));
        notifLogbook($this->employee, LogbookStatus::Approved, submittedAt: now()->subDays(9));
        notifLogbook($quietEmployee, LogbookStatus::Submitted, submittedAt: now()->subDay());

        $this->artisan('logbook:remind-pending-reviews')->assertSuccessful();

        Notification::assertSentTo($this->supervisor, PendingReviewReminder::class, fn ($n) => str_contains($n->toArray($this->supervisor)['message'], '2 logbook'));
        Notification::assertSentToTimes($this->supervisor, PendingReviewReminder::class, 1);
        Notification::assertNotSentTo([$quietSupervisor, $this->manager], PendingReviewReminder::class);
    });

    it('dijadwalkan pada hari kerja pukul 17:00 dan 09:00', function () {
        $events = collect(app(Schedule::class)->events())->mapWithKeys(
            fn ($event) => [Str::after($event->command, "'artisan' ") => $event->expression],
        );

        expect($events['logbook:remind-unsubmitted'])->toBe('0 17 * * 1-5')
            ->and($events['logbook:remind-pending-reviews'])->toBe('0 9 * * 1-5');
    });
});

describe('endpoint notifikasi', function () {
    beforeEach(function () {
        $this->employee->notify(new UnsubmittedLogbookReminder);
        $this->employee->notify(new PendingReviewReminder(1, 2));
    });

    it('menandai dibaca dan membuka tujuan notifikasi', function () {
        $notification = $this->employee->notifications->first(fn ($item) => $item->data['url'] === '/logbooks/create');

        $this->actingAs($this->employee)->post("/notifications/{$notification->id}/read")
            ->assertRedirect('/logbooks/create');

        expect($notification->fresh()->read_at)->not->toBeNull()
            ->and($this->employee->unreadNotifications()->count())->toBe(1);
    });

    it('tidak mengikuti URL eksternal', function () {
        $notification = $this->employee->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'uji',
            'data' => ['url' => '//evil.example.test'],
        ]);

        $this->actingAs($this->employee)->from('/logbooks')->post("/notifications/{$notification->id}/read")
            ->assertRedirect('/logbooks');
    });

    it('menolak notifikasi milik orang lain', function () {
        $notification = $this->employee->notifications()->first();

        $this->actingAs($this->supervisor)->post("/notifications/{$notification->id}/read")->assertNotFound();
        expect($notification->fresh()->read_at)->toBeNull();
    });

    it('menandai semua dibaca', function () {
        $this->actingAs($this->employee)->post('/notifications/read-all')->assertRedirect();

        expect($this->employee->unreadNotifications()->count())->toBe(0);
    });

    it('membagikan jumlah belum dibaca dan daftar terbaru ke Inertia', function () {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $this->employee);
        $request->setLaravelSession(app('session.store'));

        $shared = value((new HandleInertiaRequests)->share($request)['notifications']);

        expect($shared['unread_count'])->toBe(2)
            ->and($shared['items'])->toHaveCount(2)
            ->and($shared['items'][0])->toHaveKeys(['id', 'title', 'message', 'read', 'created_at']);
    });

    it('menolak tamu', function () {
        $this->post('/notifications/read-all')->assertRedirect('/login');
    });
});
