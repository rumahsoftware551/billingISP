<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports)
    {
        return Inertia::render('Dashboard', $reports->dashboard(6));
    }
}
