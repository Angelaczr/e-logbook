<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsRequest;
use App\Services\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(AnalyticsRequest $request): Response
    {
        $filter = $request->filter();

        return Inertia::render('Analytics/Index', [
            'filters' => [
                'from' => $filter->from->toDateString(),
                'to' => $filter->to->toDateString(),
                'department_id' => $filter->departmentId,
            ],
            'departments' => $this->analytics->departmentOptions($filter),
            'report' => $this->analytics->dashboard($filter),
        ]);
    }
}
