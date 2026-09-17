<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlaceholderController extends Controller
{
    public function __invoke(Request $request): View
    {
        $routeName = $request->route()?->getName();
        $menu = Menu::findForRoute($routeName, $request->user());

        $title = $menu?->title ?? $this->titleFromRoute($routeName);
        $description = $menu?->description ?: 'Modul ini akan dikembangkan pada tahap berikutnya.';
        $icon = $menu?->icon ?: 'bx bx-cube';
        $breadcrumb = $menu?->breadcrumb() ?? [
            'Dashboard' => route('dashboard'),
            $title => null,
        ];

        return view('modules.placeholder', compact('title', 'description', 'icon', 'breadcrumb'));
    }

    protected function titleFromRoute(?string $routeName): string
    {
        if (! $routeName) {
            return 'Modul';
        }

        return str($routeName)
            ->replace(['.', '-', '_'], ' ')
            ->title()
            ->toString();
    }
}
