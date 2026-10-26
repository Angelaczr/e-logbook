<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\InvalidLogbookTransitionException;
use App\Http\Requests\BulkApproveRequest;
use App\Http\Requests\ReviewDecisionRequest;
use App\Http\Requests\ReviewIndexRequest;
use App\Http\Requests\ReviewReasonRequest;
use App\Http\Resources\LogbookDetailResource;
use App\Http\Resources\ReviewRowResource;
use App\Models\Department;
use App\Models\Logbook;
use App\Models\LogbookView;
use App\Models\User;
use App\Services\LogbookService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(private readonly LogbookService $logbooks) {}

    public function index(ReviewIndexRequest $request): Response
    {
        $reviewer = $request->user();
        $canFilterDepartment = $reviewer->hasRole(UserRole::Manager, UserRole::SuperAdmin);
        $departmentId = $canFilterDepartment ? $request->integer('department_id') ?: null : null;
        $status = $request->status();

        $logbooks = Logbook::query()
            ->reviewableBy($reviewer)
            ->status($status)
            ->when($request->integer('user_id') ?: null, fn ($query, int $id) => $query->forUser($id))
            ->dateBetween($request->validated('from'), $request->validated('to'))
            ->when($departmentId, fn ($query, int $id) => $query->whereHas(
                'user',
                fn ($users) => $users->whereIn('department_id', Department::query()
                    ->where('id', $id)->orWhere('parent_id', $id)->select('id')),
            ))
            ->with('user:id,name')
            ->withCount('items')
            ->when(
                $request->sort() === ReviewIndexRequest::SORT_VIEWED,
                fn ($query) => $query->orderByRaw(
                    '(select v.last_viewed_at from logbook_views v where v.logbook_id = logbooks.id and v.user_id = ?) desc nulls last',
                    [$reviewer->id],
                ),
            )
            ->orderBy('work_date')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $selected = null;
        if ($request->integer('review')) {
            $target = Logbook::query()->findOrFail($request->integer('review'));
            $this->authorize('view', $target);
            $this->recordView($target, $reviewer);
            $selected = $this->detail($target);
        }

        return Inertia::render('Reviews/Index', [
            'logbooks' => ReviewRowResource::collection($logbooks),
            'filters' => [
                'user_id' => $request->integer('user_id') ?: null,
                'department_id' => $departmentId,
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
                'status' => $status->value,
                'review' => $request->integer('review') ?: null,
                'sort' => $request->sort(),
            ],
            'selected' => $selected,
            'employees' => $this->employeeOptions($reviewer),
            'departments' => $canFilterDepartment ? $this->departmentOptions() : null,
            'statuses' => array_map(
                fn ($item) => ['value' => $item->value, 'label' => $item->label()],
                ReviewIndexRequest::filterableStatuses(),
            ),
        ]);
    }

    public function show(Logbook $logbook): Response
    {
        $this->authorize('view', $logbook);
        $this->recordView($logbook, request()->user());

        return Inertia::render('Reviews/Show', [
            'logbook' => $this->detail($logbook),
        ]);
    }

    public function approve(ReviewDecisionRequest $request, Logbook $logbook): RedirectResponse
    {
        $this->logbooks->approve($logbook, $request->user(), $request->validated('note'));

        return back()->with('success', 'Logbook disetujui.');
    }

    public function requestRevision(ReviewReasonRequest $request, Logbook $logbook): RedirectResponse
    {
        $this->logbooks->requestRevision($logbook, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Permintaan revisi dikirim ke karyawan.');
    }

    public function reject(ReviewReasonRequest $request, Logbook $logbook): RedirectResponse
    {
        $this->logbooks->reject($logbook, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Logbook ditolak.');
    }

    /**
     * Setiap logbook diproses sendiri-sendiri lewat service (policy dicek per logbook);
     * yang tidak berhak atau tidak valid dilewati, bukan menggagalkan seluruh permintaan.
     */
    public function bulkApprove(BulkApproveRequest $request): RedirectResponse
    {
        $ids = $request->validated('ids');
        $found = Logbook::query()->with('user')->whereKey($ids)->get();
        $approved = 0;

        foreach ($found as $logbook) {
            try {
                $this->logbooks->approve($logbook, $request->user());
                $approved++;
            } catch (AuthorizationException|InvalidLogbookTransitionException) {
                continue;
            }
        }

        $skipped = count($ids) - $approved;
        $redirect = back();

        if ($approved > 0) {
            $redirect->with('success', "{$approved} logbook disetujui.");
        }
        if ($skipped > 0) {
            $redirect->with('error', "{$skipped} logbook dilewati karena sudah diproses atau bukan wewenang Anda.");
        }

        return $redirect;
    }

    /** Hanya pembukaan oleh peninjau (bukan pemilik) yang dicatat. */
    private function recordView(Logbook $logbook, User $viewer): void
    {
        if ($logbook->user_id !== $viewer->id) {
            LogbookView::record($logbook, $viewer);
        }
    }

    private function detail(Logbook $logbook): LogbookDetailResource
    {
        return LogbookDetailResource::make($logbook->load([
            'user:id,name,supervisor_id', 'approver:id,name', 'items.project:id,code,name', 'histories.actor:id,name',
        ]));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function employeeOptions(User $reviewer): array
    {
        return User::query()
            ->select(['id', 'name'])
            ->whereKeyNot($reviewer->id)
            ->when($reviewer->role === UserRole::Supervisor, fn ($query) => $query->where('supervisor_id', $reviewer->id))
            ->when($reviewer->role === UserRole::Manager, fn ($query) => $query->whereIn('id', $reviewer->subordinateIds()))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function departmentOptions(): array
    {
        return Department::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name])
            ->all();
    }
}
