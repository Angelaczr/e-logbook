<?php

namespace App\Http\Controllers;

use App\Enums\LogbookStatus;
use App\Http\Requests\LogbookIndexRequest;
use App\Http\Requests\StoreLogbookRequest;
use App\Http\Requests\UpdateLogbookRequest;
use App\Http\Resources\LogbookDetailResource;
use App\Http\Resources\LogbookSummaryResource;
use App\Models\Logbook;
use App\Models\Project;
use App\Services\LogbookService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LogbookController extends Controller
{
    public function __construct(private readonly LogbookService $logbooks) {}

    public function index(LogbookIndexRequest $request): Response
    {
        $this->authorize('viewAny', Logbook::class);

        $month = $request->monthStart();

        $logbooks = Logbook::query()
            ->forUser($request->user())
            ->when($request->status(), fn ($query, LogbookStatus $status) => $query->status($status))
            ->when($month, fn ($query) => $query->dateBetween($month->toDateString(), $month->endOfMonth()->toDateString()))
            ->withCount('items')
            ->orderByDesc('work_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Logbooks/Index', [
            'logbooks' => LogbookSummaryResource::collection($logbooks),
            'filters' => [
                'month' => $month?->format('Y-m'),
                'status' => $request->status()?->value,
            ],
            'statuses' => collect(LogbookStatus::cases())
                ->map(fn (LogbookStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Logbook::class);

        return Inertia::render('Logbooks/Form', [
            'logbook' => null,
            'projects' => $this->projectOptions(),
        ]);
    }

    public function store(StoreLogbookRequest $request): RedirectResponse
    {
        $logbook = $this->logbooks->saveDraft($request->user(), $request->validated());

        return to_route('logbooks.show', $logbook)
            ->with('success', 'Draft logbook berhasil disimpan.');
    }

    public function show(Logbook $logbook): Response
    {
        $this->authorize('view', $logbook);

        return Inertia::render('Logbooks/Show', [
            'logbook' => LogbookDetailResource::make($this->withDetail($logbook)),
        ]);
    }

    public function edit(Logbook $logbook): Response
    {
        $this->authorize('update', $logbook);

        return Inertia::render('Logbooks/Form', [
            'logbook' => LogbookDetailResource::make($this->withDetail($logbook)),
            'projects' => $this->projectOptions(),
        ]);
    }

    public function update(UpdateLogbookRequest $request, Logbook $logbook): RedirectResponse
    {
        $this->logbooks->saveDraft($request->user(), $request->validated(), $logbook);

        return to_route('logbooks.show', $logbook)
            ->with('success', 'Draft logbook berhasil diperbarui.');
    }

    public function destroy(Logbook $logbook): RedirectResponse
    {
        $this->authorize('delete', $logbook);
        $logbook->delete();

        return to_route('logbooks.index')->with('success', 'Draft logbook dihapus.');
    }

    public function submit(Logbook $logbook): RedirectResponse
    {
        $this->authorize('submit', $logbook);

        $this->logbooks->submit($logbook, request()->user());

        return to_route('logbooks.show', $logbook)->with('success', 'Logbook berhasil dikirim untuk ditinjau.');
    }

    private function withDetail(Logbook $logbook): Logbook
    {
        return $logbook->load(['user:id,name,supervisor_id', 'approver:id,name', 'items.project:id,code,name', 'histories.actor:id,name']);
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    private function projectOptions(): array
    {
        return Project::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Project $project) => ['id' => $project->id, 'code' => $project->code, 'name' => $project->name])
            ->all();
    }
}
